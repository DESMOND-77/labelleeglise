<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CMSRepository;
use App\Repositories\PublicSiteRepository;

/**
 * Logique du site public : assemble ce qui est publiable, valide ce que
 * l'administrateur saisit. Aucune valeur par défaut inventée : un champ vide
 * reste vide et la section correspondante est masquée.
 */
class PublicSiteService
{
    private PublicSiteRepository $repo;
    private CMSRepository $cms;

    public function __construct(?PublicSiteRepository $repo = null, ?CMSRepository $cms = null)
    {
        $this->repo = $repo ?? new PublicSiteRepository();
        $this->cms = $cms ?? new CMSRepository();
    }

    /* ================= LECTURE PUBLIQUE ================= */

    /** @return array<string,mixed> */
    public function landing(): array
    {
        $settings = $this->repo->settings();
        $presentation = $this->cms->presentation();

        $contact = $this->contactLinks($settings);

        $rows = $this->repo->publishedCentres();
        $horaires = $this->repo->horairesByCentre(array_map(static fn($c) => (int) $c['id'], $rows));

        $centres = [];
        foreach ($rows as $c) {
            $id = (int) $c['id'];
            $slots = array_map([$this, 'formatSlot'], $horaires[$id] ?? []);
            $own = $this->contactLinks([
                'telephone' => (string) ($c['telephone'] ?? ''),
                'whatsapp'  => (string) ($c['whatsapp'] ?? ''),
                'email'     => (string) ($c['email'] ?? ''),
            ]);
            $centre = [
                'id'          => $id,
                'nom'         => (string) $c['nom'],
                'adresse'     => $this->clean($c['adresse'] ?? null),
                'quartier'    => $this->clean($c['quartier'] ?? null),
                'ville'       => $this->clean($c['ville'] ?? null),
                'itineraire'  => $this->safeUrl((string) ($c['itineraire_url'] ?? '')),
                'intro'       => $this->clean($c['intro'] ?? null),
                'photo'       => $this->safePhoto($c['photo'] ?? null),
                'horaires'    => $slots,
                'contact'     => $own,
            ];
            // Seuil minimal : adresse + au moins un horaire + un moyen de contact.
            if ($this->meetsThreshold($centre, $contact)) {
                $centres[] = $centre;
            }
        }

        $accroche = trim((string) ($presentation['accroche'] ?? ''));
        $histoire = trim((string) ($presentation['histoire'] ?? ''));

        return [
            'equipe'     => $this->equipeGroups(),
            'evenements' => $this->events(),
            'nom'      => ($settings['nom_affichage'] ?? '') !== '' ? $settings['nom_affichage'] : (defined('APP_NAME') ? APP_NAME : ''),
            'accroche' => $accroche,
            'histoire' => $histoire,
            'centres'  => $centres,
            'contact'  => $contact,
            'socials'  => $this->socials($settings),
            'direct'   => $this->safeUrl($settings['lien_direct'] ?? ''),
        ];
    }

    /**
     * Données structurées schema.org : une entrée `Church` par centre publié
     * (donc à adresse confirmée). Rien n'est émis sans centre publié. Les
     * horaires de culte ne sont volontairement pas mappés sur openingHours.
     *
     * @param array<string,mixed> $landing @return array<string,mixed>|null
     */
    public function jsonLd(array $landing, string $pageUrl): ?array
    {
        $graph = [];
        foreach ($landing['evenements'] as $e) {
            if ($e['lieu'] === '') {
                continue; // Event exige un lieu : on n'invente rien.
            }
            $graph[] = [
                '@type'     => 'Event',
                'name'      => $e['nom'],
                'startDate' => $e['iso'],
                'location'  => ['@type' => 'Place', 'name' => $e['lieu']],
            ];
        }

        foreach ($landing['centres'] as $c) {
            $node = [
                '@type'   => 'Church',
                'name'    => $landing['nom'] . ' - ' . $c['nom'],
                'address' => array_filter([
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => $c['adresse'],
                    'addressLocality' => $c['ville'],
                ], static fn($v) => $v !== ''),
            ];
            if ($pageUrl !== '') {
                $node['url'] = $pageUrl;
            }
            $tel = $c['contact']['telephone']['label'] ?? $landing['contact']['telephone']['label'] ?? '';
            if ($tel !== '') {
                $node['telephone'] = $tel;
            }
            $graph[] = $node;
        }
        return $graph === [] ? null : ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /** @param array<string,mixed> $centre @param array<string,string> $globalContact */
    private function meetsThreshold(array $centre, array $globalContact): bool
    {
        return $centre['adresse'] !== ''
            && $centre['horaires'] !== []
            && ($centre['contact'] !== [] || $globalContact !== []);
    }

    private const TEAM_LABELS = ['Révérend' => 'Révérends', 'Pasteur' => 'Pasteurs', 'Berger' => 'Bergers', 'Leader' => 'Leaders', 'Autre' => 'Autres'];
    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /** @return list<array{label:string,members:list<array<string,string>>}> */
    private function equipeGroups(): array
    {
        $by = [];
        foreach ($this->repo->publishedEquipe() as $m) {
            $cat = isset(self::TEAM_LABELS[$m['categorie'] ?? '']) ? $m['categorie'] : 'Autre';
            $by[$cat][] = [
                'nom'   => (string) $m['nom_affichage'],
                'role'  => (string) $m['role_affichage'],
                'bio'   => $this->clean($m['bio'] ?? null),
                'photo' => $this->safePhoto($m['photo'] ?? null),
            ];
        }
        $groups = [];
        foreach (self::TEAM_LABELS as $cat => $label) {
            if (!empty($by[$cat])) {
                $groups[] = ['label' => $label, 'members' => $by[$cat]];
            }
        }
        return $groups;
    }

    /** @return list<array<string,string>> */
    private function events(): array
    {
        $out = [];
        foreach ($this->repo->upcomingEvents(date('Y-m-d H:i:s')) as $e) {
            $start = new \DateTimeImmutable((string) $e['date_debut']);
            $out[] = [
                'nom'         => (string) $e['nom'],
                'quand'       => $this->formatEventWhen($start, !empty($e['date_fin']) ? new \DateTimeImmutable((string) $e['date_fin']) : null),
                'iso'         => $start->format('c'),
                'lieu'        => $this->clean($e['lieu'] ?? null),
                'description' => $this->clean($e['description'] ?? null),
            ];
        }
        return $out;
    }

    private function formatEventWhen(\DateTimeImmutable $s, ?\DateTimeImmutable $e): string
    {
        $date = static fn(\DateTimeImmutable $d): string => (int) $d->format('j') . ' ' . self::MONTHS[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
        $time = fn(\DateTimeImmutable $d): string => $this->formatTime($d->format('H:i'));
        $out = $date($s) . ', ' . $time($s);
        if ($e !== null && $e > $s) {
            $out .= $e->format('Y-m-d') === $s->format('Y-m-d') ? ' – ' . $time($e) : ' → ' . $date($e) . ', ' . $time($e);
        }
        return $out;
    }

    /** Chemin d'image local sous uploads/ ou assets/ (jamais d'URL arbitraire). */
    private function safePhoto(mixed $p): string
    {
        $p = trim((string) ($p ?? ''));
        return preg_match('#^/?(uploads|assets)/[\w./-]+$#', $p) === 1 && !str_contains($p, '..') ? $p : '';
    }

    /** Liens tel:/mailto:/WhatsApp, uniquement pour les valeurs valides. @return array<string,array{label:string,href:string}> */
    private function contactLinks(array $s): array
    {
        $out = [];
        $tel = trim((string) ($s['telephone'] ?? ''));
        if ($this->validPhone($tel)) {
            $out['telephone'] = ['label' => $tel, 'href' => 'tel:' . preg_replace('/[^0-9+]/', '', $tel)];
        }
        $wa = trim((string) ($s['whatsapp'] ?? ''));
        if ($this->validPhone($wa)) {
            $out['whatsapp'] = ['label' => $wa, 'href' => 'https://wa.me/' . preg_replace('/\D/', '', $wa)];
        }
        $mail = trim((string) ($s['email'] ?? ''));
        if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $out['email'] = ['label' => $mail, 'href' => 'mailto:' . $mail];
        }
        return $out;
    }

    /** @return array<string,string> réseau => URL https valide */
    private function socials(array $s): array
    {
        $out = [];
        foreach (['facebook', 'youtube', 'instagram'] as $k) {
            $u = $this->safeUrl($s[$k] ?? '');
            if ($u !== '') {
                $out[$k] = $u;
            }
        }
        return $out;
    }

    /** @return array{libelle:string,jours:string,debut:string,fin:string} */
    private function formatSlot(array $h): array
    {
        return [
            'libelle' => (string) $h['libelle'],
            'jours'   => (string) $h['jours'],
            'debut'   => $this->formatTime((string) $h['heure_debut']),
            'fin'     => $h['heure_fin'] !== null && $h['heure_fin'] !== '' ? $this->formatTime((string) $h['heure_fin']) : '',
        ];
    }

    private function formatTime(string $t): string
    {
        return preg_match('/^(\d{1,2}):(\d{2})/', $t, $m) ? ((int) $m[1]) . 'h' . $m[2] : $t;
    }

    private function clean(mixed $v): string
    {
        return trim((string) ($v ?? ''));
    }

    /** URL https valide, sinon chaîne vide (jamais de javascript:, data:, http clair). */
    private function safeUrl(string $u): string
    {
        $u = trim($u);
        if ($u === '' || !filter_var($u, FILTER_VALIDATE_URL)) {
            return '';
        }
        return strtolower((string) parse_url($u, PHP_URL_SCHEME)) === 'https' ? $u : '';
    }

    private function validPhone(string $p): bool
    {
        return $p !== ''
            && preg_match('/^\+?[0-9 ().-]{6,30}$/', $p) === 1
            && strlen((string) preg_replace('/\D/', '', $p)) >= 8;
    }

    /* ================= ADMINISTRATION ================= */

    /** @return array{settings:array<string,string>,centres:array<int,array<string,mixed>>} */
    public function adminData(): array
    {
        $centres = $this->repo->allCentresForAdmin();
        $horaires = $this->repo->horairesByCentre(array_map(static fn($c) => (int) $c['id'], $centres));
        foreach ($centres as &$c) {
            $lines = [];
            foreach ($horaires[(int) $c['id']] ?? [] as $h) {
                $lines[] = $h['libelle'] . ' | ' . $h['jours'] . ' | ' . substr((string) $h['heure_debut'], 0, 5)
                    . ($h['heure_fin'] ? ' | ' . substr((string) $h['heure_fin'], 0, 5) : '');
            }
            $c['horaires_text'] = implode("\n", $lines);
        }
        unset($c);

        return [
            'settings'   => $this->repo->settings(),
            'centres'    => $centres,
            'equipe'     => $this->repo->equipeForAdmin(),
            'evenements' => $this->repo->eventsForAdmin(date('Y-m-d H:i:s')),
        ];
    }

    /** @param array<string,mixed> $post @return list<string> erreurs (vide = enregistré) */
    public function saveSettings(array $post): array
    {
        $errors = [];
        $values = [];
        $values['nom_affichage'] = mb_substr(trim((string) ($post['nom_affichage'] ?? '')), 0, 150);

        $tel = trim((string) ($post['telephone'] ?? ''));
        $wa = trim((string) ($post['whatsapp'] ?? ''));
        $mail = trim((string) ($post['email'] ?? ''));
        if ($tel !== '' && !$this->validPhone($tel)) {
            $errors[] = 'Téléphone invalide.';
            $tel = '';
        }
        if ($wa !== '' && !$this->validPhone($wa)) {
            $errors[] = 'Numéro WhatsApp invalide (indicatif international requis).';
            $wa = '';
        }
        if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail invalide.';
            $mail = '';
        }
        $values['telephone'] = $tel;
        $values['whatsapp'] = $wa;
        $values['email'] = $mail;

        foreach (['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'lien_direct' => 'Lien du direct'] as $k => $label) {
            $raw = trim((string) ($post[$k] ?? ''));
            if ($raw !== '' && $this->safeUrl($raw) === '') {
                $errors[] = $label . ' : URL https valide requise.';
                $raw = '';
            }
            $values[$k] = $raw;
        }

        $this->repo->saveSettings($values);
        return $errors;
    }

    /** @param array<string,mixed> $post */
    public function saveEquipe(array $post): void
    {
        $this->repo->setEquipePublie((array) ($post['publie'] ?? []));
    }

    /** @param array<string,mixed> $post @return list<string> */
    public function saveEvents(array $post): array
    {
        $errors = [];
        $listed = array_map('intval', (array) ($post['ids'] ?? []));
        $publie = array_map('intval', (array) ($post['publie'] ?? []));
        $desc = (array) ($post['description'] ?? []);
        foreach ($this->repo->eventsForAdmin(date('Y-m-d H:i:s')) as $e) {
            $id = (int) $e['id'];
            if (!in_array($id, $listed, true)) {
                continue;
            }
            $d = mb_substr(trim((string) ($desc[$id] ?? '')), 0, 1000);
            $want = in_array($id, $publie, true);
            if ($want && $d === '' && trim((string) ($e['lieu'] ?? '')) === '') {
                $errors[] = '« ' . $e['nom'] . ' » non publié : lieu ou description requis.';
                $want = false;
            }
            $this->repo->saveEventPublic($id, $want, $d !== '' ? $d : null);
        }
        return $errors;
    }

    /** @param array<string,mixed> $post @return list<string> */
    public function saveCentre(int $id, array $post): array
    {
        if (!$this->repo->centreExists($id)) {
            return ['Centre introuvable.'];
        }
        $errors = [];

        $tel = trim((string) ($post['telephone'] ?? ''));
        $wa = trim((string) ($post['whatsapp'] ?? ''));
        $mail = trim((string) ($post['email'] ?? ''));
        if ($tel !== '' && !$this->validPhone($tel)) {
            $errors[] = 'Téléphone invalide.';
            $tel = '';
        }
        if ($wa !== '' && !$this->validPhone($wa)) {
            $errors[] = 'WhatsApp invalide.';
            $wa = '';
        }
        if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail invalide.';
            $mail = '';
        }
        $itin = trim((string) ($post['itineraire_url'] ?? ''));
        if ($itin !== '' && $this->safeUrl($itin) === '') {
            $errors[] = "Lien d'itinéraire : URL https valide requise.";
            $itin = '';
        }

        [$rows, $lineErrors] = $this->parseHoraires((string) ($post['horaires'] ?? ''));
        $errors = array_merge($errors, $lineErrors);

        $fields = [
            'adresse'        => mb_substr(trim((string) ($post['adresse'] ?? '')), 0, 255) ?: null,
            'quartier'       => mb_substr(trim((string) ($post['quartier'] ?? '')), 0, 100) ?: null,
            'ville'          => mb_substr(trim((string) ($post['ville'] ?? '')), 0, 100) ?: null,
            'itineraire_url' => $itin ?: null,
            'telephone'      => $tel ?: null,
            'whatsapp'       => $wa ?: null,
            'email'          => $mail ?: null,
            'publie'         => !empty($post['publie']),
        ];

        // Un centre ne se publie que s'il atteint le seuil minimal.
        if ($fields['publie']) {
            $global = $this->contactLinks($this->repo->settings());
            $hasContact = $tel !== '' || $wa !== '' || $mail !== '' || $global !== [];
            if ($fields['adresse'] === null || $rows === [] || !$hasContact) {
                $fields['publie'] = false;
                $errors[] = 'Centre non publié : adresse, au moins un horaire valide et un moyen de contact sont requis.';
            }
        }

        $this->repo->saveCentrePublic($id, $fields);
        $this->repo->replaceHoraires($id, $rows);
        return $errors;
    }

    /**
     * Une ligne = « Libellé | Jours | HH:MM | HH:MM (facultatif) ».
     * @return array{0:array<int,array<string,mixed>>,1:list<string>}
     */
    public function parseHoraires(string $text): array
    {
        $rows = [];
        $errors = [];
        foreach (preg_split('/\R/', $text) ?: [] as $n => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $p = array_map('trim', explode('|', $line));
            $ok = count($p) >= 3 && count($p) <= 4 && $p[0] !== '' && $p[1] !== ''
                && preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $p[2]) === 1
                && (!isset($p[3]) || $p[3] === '' || preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $p[3]) === 1);
            if (!$ok) {
                $errors[] = 'Horaire ligne ' . ($n + 1) . ' ignoré (format : Libellé | Jours | HH:MM | HH:MM).';
                continue;
            }
            $rows[] = [
                'libelle'     => mb_substr($p[0], 0, 100),
                'jours'       => mb_substr($p[1], 0, 100),
                'heure_debut' => $p[2] . ':00',
                'heure_fin'   => (isset($p[3]) && $p[3] !== '') ? $p[3] . ':00' : null,
            ];
        }
        return [$rows, $errors];
    }
}
