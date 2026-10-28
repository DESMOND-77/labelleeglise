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
                'article'     => $this->centreArticle($c),
                'responsable' => $this->clean($c['responsable_public'] ?? null),
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

        $predication = [
            'titre' => $this->clean($settings['predication_titre'] ?? null),
            'date'  => $this->clean($settings['predication_date'] ?? null),
            'url'   => $this->safeUrl((string) ($settings['predication_url'] ?? '')),
        ];
        // Une prédication n'est publiée que si son lien officiel est valide.
        if ($predication['url'] === '') {
            $predication = ['titre' => '', 'date' => '', 'url' => ''];
        }

        $ministries = $this->ministries();
        $events = $this->events();
        $heroImage = $this->safePhoto($settings['hero_image'] ?? null);
        $heroImageAlt = $this->clean($settings['hero_image_alt'] ?? null);
        $nom = ($settings['nom_affichage'] ?? '') !== '' ? $settings['nom_affichage'] : (defined('APP_NAME') ? APP_NAME : '');
        $about = $this->aboutImage($heroImage, $heroImageAlt, $nom, $centres);
        $accueilTexte = $this->clean($settings['accueil_texte'] ?? null);

        return [
            'equipe'     => $this->equipeGroups(),
            'evenements' => $events,
            'next_event' => $this->nextEvent($events),
            'impact'     => $this->impact($centres, $ministries),
            'about_image'     => $about['src'],
            'about_image_alt' => $about['alt'],
            'about_title'     => $histoire !== '' ? 'Notre histoire' : 'Notre église',
            'meta_description' => $this->metaDescription($accroche, $accueilTexte),
            'nom'      => $nom,
            'accroche' => $accroche,
            'histoire' => $histoire,
            'slogan'   => $this->clean($settings['slogan'] ?? null),
            'accueil_texte' => $accueilTexte,
            'hero_image'    => $heroImage,
            'hero_image_alt' => $heroImageAlt,
            'hero_slides'   => $this->heroSlides(),
            'adresse'  => $this->clean($settings['adresse'] ?? null),
            'centres'  => $centres,
            'contact'  => $contact,
            'socials'  => $this->socials($settings),
            'direct'   => $this->safeUrl($settings['lien_direct'] ?? ''),
            'predication' => $predication,
            'lien_don' => $this->safeUrl((string) ($settings['lien_don'] ?? '')),
            'don_usage' => $this->clean($settings['don_usage'] ?? null),
            'faq'      => $this->faq(),
            'ministries' => $ministries,
            'hasMinistries' => $ministries['bacentas'] !== [] || $ministries['basontas'] !== [],
            'fuseau'   => $this->timezoneLabel(),
            'has_mentions_legales' => $this->clean($settings['mentions_legales'] ?? null) !== '',
            'has_confidentialite'  => $this->clean($settings['confidentialite'] ?? null) !== '',
        ];
    }

    /**
     * Chiffres de la section « À propos » : uniquement des valeurs publiées.
     * L'ordre des candidats est l'ordre de priorité ; les 4 premiers sont gardés.
     * @param list<array<string,mixed>> $centres
     * @param array{bacentas:list<array<string,string>>,basontas:list<array<string,string>>} $ministries
     * @return list<array{value:int,label:string}>
     */
    private function impact(array $centres, array $ministries): array
    {
        $plural = static fn(int $n, string $one, string $many): string => $n > 1 ? $many : $one;

        $annees = 0;
        $encadrement = 0;
        foreach ($centres as $c) {
            $stats = $c['article']['stats'] ?? [];
            $annees = max($annees, (int) ($stats['ans d\'existence'] ?? 0));
            $encadrement += (int) ($stats['pasteur(s)'] ?? 0) + (int) ($stats['bergers'] ?? 0);
        }
        $nbCentres = count($centres);
        $nbPriere = count($ministries['bacentas'] ?? []);
        $nbServices = count($ministries['basontas'] ?? []);

        $candidates = [
            [$nbCentres, $plural($nbCentres, 'Centre de culte', 'Centres de culte')],
            [$annees, $plural($annees, 'Année de ministère', 'Années de ministère')],
            [$encadrement, $plural($encadrement, 'Pasteur ou berger', 'Pasteurs et bergers')],
            [$nbPriere, $plural($nbPriere, 'Groupe de prière', 'Groupes de prière')],
            [$nbServices, $plural($nbServices, 'Groupe et service', 'Groupes et services')],
        ];

        $impact = [];
        foreach ($candidates as [$value, $label]) {
            if ($value > 0) {
                $impact[] = ['value' => $value, 'label' => $label];
            }
        }
        return array_slice($impact, 0, 4);
    }

    /**
     * Image « à propos » : image d'accueil, sinon photo d'un centre publié.
     * @param list<array<string,mixed>> $centres
     * @return array{src:string,alt:string}
     */
    private function aboutImage(string $heroImage, string $heroAlt, string $nom, array $centres): array
    {
        if ($heroImage !== '') {
            return ['src' => $heroImage, 'alt' => $heroAlt !== '' ? $heroAlt : $nom];
        }
        foreach ($centres as $c) {
            if ($c['photo'] !== '') {
                return ['src' => $c['photo'], 'alt' => $c['nom']];
            }
        }
        return ['src' => '', 'alt' => $heroAlt !== '' ? $heroAlt : $nom];
    }

    /**
     * Prochain événement publié (barre d'annonce + carte du hero), avec jour
     * et mois courts. Null s'il n'y en a aucun.
     * @param list<array<string,mixed>> $events
     * @return array<string,mixed>|null
     */
    private function nextEvent(array $events): ?array
    {
        if ($events === []) {
            return null;
        }
        $next = $events[0];
        $ts = strtotime((string) $next['iso']);
        $next['jour'] = $ts ? date('j', $ts) : '';
        $next['mois'] = $ts ? self::MONTHS_SHORT[(int) date('n', $ts) - 1] : '';
        return $next;
    }

    /** Description SEO : accroche, sinon texte d'accueil ; vide si rien n'est publié. */
    private function metaDescription(string $accroche, string $accueil): string
    {
        $text = $accroche !== '' ? $accroche : $accueil;
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($text) > 160 ? rtrim(mb_substr($text, 0, 157)) . '…' : $text;
    }

    /** @return list<array{question:string,reponse:string}> */
    private function faq(): array
    {
        $out = [];
        foreach ($this->repo->publishedFaq() as $f) {
            $q = $this->clean($f['question'] ?? null);
            $r = $this->clean($f['reponse'] ?? null);
            if ($q === '' || $r === '') {
                continue; // jamais de demi-entrée publiée
            }
            $out[] = ['question' => $q, 'reponse' => $r];
        }
        return $out;
    }

    /**
     * Ministères publiés : bacentas (rassemblements de prière) et
     * basontas (groupes/services). Jamais de liste de membres.
     * @return array{bacentas:list<array<string,string>>,basontas:list<array<string,string>>}
     */
    private function ministries(): array
    {
        $out = ['bacentas' => [], 'basontas' => []];
        foreach ($this->repo->publishedMinistries() as $type => $rows) {
            foreach ($rows as $m) {
                $description = $this->clean($m['description_publique'] ?? null);
                if ($description === '') {
                    continue; // un ministère sans description publique n'est pas publié
                }
                $out[$type][] = [
                    'nom'         => (string) $m['nom'],
                    'description' => $description,
                    'horaires'    => $this->formatMinistrySchedule($m),
                ];
            }
        }
        if ($out['bacentas'] === [] && $out['basontas'] === []) {
            return ['bacentas' => [], 'basontas' => []];
        }
        return $out;
    }

    /** Horaires internes d'un groupe, formatés pour le public ('' si absents). */
    private function formatMinistrySchedule(array $m): string
    {
        $jours = trim((string) ($m['jours_semaine'] ?? ''));
        if ($jours === '') {
            return '';
        }
        $out = $jours;
        $debut = (string) ($m['heure_debut'] ?? '');
        $fin = (string) ($m['heure_fin'] ?? '');
        if (preg_match('/^(\d{1,2}):(\d{2})/', $debut, $m1) === 1) {
            $out .= ' · ' . (int) $m1[1] . 'h' . $m1[2];
            if (preg_match('/^(\d{1,2}):(\d{2})/', $fin, $m2) === 1) {
                $out .= ' – ' . (int) $m2[1] . 'h' . $m2[2];
            }
        }
        return $out;
    }

    /** Fuseau horaire de l'application, dérivé de la configuration (jamais codé en dur). */
    private function timezoneLabel(): string
    {
        $tzName = (string) date_default_timezone_get();
        if ($tzName === '') {
            return '';
        }
        try {
            $tz = new \DateTimeZone($tzName);
            $offset = (new \DateTimeImmutable('now', $tz))->getOffset();
            $sign = $offset < 0 ? '-' : '+';
            $abs = abs($offset);
            $label = 'UTC' . $sign . (int) ($abs / 3600);
            $minutes = (int) (($abs % 3600) / 60);
            if ($minutes > 0) {
                $label .= ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
            }
            return $label . ' (' . $tzName . ')';
        } catch (\Exception $e) {
            return '';
        }
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

        // Nœud racine : l'église elle-même (organisation).
        $root = ['@type' => 'Church', 'name' => $landing['nom']];
        if ($pageUrl !== '') {
            $root['url'] = $pageUrl;
        }
        if (($landing['contact']['telephone']['label'] ?? '') !== '') {
            $root['telephone'] = $landing['contact']['telephone']['label'];
        }
        if (($landing['contact']['email']['label'] ?? '') !== '') {
            $root['email'] = $landing['contact']['email']['label'];
        }
        if (($landing['adresse'] ?? '') !== '') {
            $root['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $landing['adresse']];
        }
        $graph[] = $root;

        foreach ($landing['evenements'] as $e) {
            if ($e['lieu'] === '') {
                continue; // Event exige un lieu : on n'invente rien.
            }
            $node = [
                '@type'     => 'Event',
                'name'      => $e['nom'],
                'startDate' => $e['iso'],
                'location'  => ['@type' => 'Place', 'name' => $e['lieu']],
            ];
            if (($e['affiche'] ?? '') !== '') {
                $node['image'] = $e['affiche'];
            }
            $graph[] = $node;
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
    /**
     * Article de présentation d'un centre (modal de la vitrine), ou null si
     * aucun contenu n'est renseigné - la carte n'est alors pas cliquable.
     * @return array<string,mixed>|null
     */
    private function centreArticle(array $c): ?array
    {
        $objectifs = json_decode((string) ($c['objectifs'] ?? ''), true);
        $objectifs = is_array($objectifs)
            ? array_values(array_filter(array_map(fn($o) => $this->clean(is_scalar($o) ? $o : ''), $objectifs), static fn($o) => $o !== ''))
            : [];
        $article = [
            'intro'     => $this->clean($c['intro'] ?? null),
            'vision'    => $this->clean($c['vision'] ?? null),
            'direction' => $this->clean($c['direction'] ?? null),
            'origine'   => $this->clean($c['origine'] ?? null),
            'objectifs' => $objectifs,
            'stats'     => [
                'ans d\'existence' => (int) ($c['situation_annees'] ?? 0),
                'pasteur(s)'       => (int) ($c['situation_pasteurs'] ?? 0),
                'bergers'          => (int) ($c['situation_bergers'] ?? 0),
                'leaders'          => (int) ($c['situation_leaders'] ?? 0),
                'bacentas'         => (int) ($c['situation_bacentas'] ?? 0),
            ],
        ];
        $article['stats'] = array_filter($article['stats'], static fn($n) => $n > 0);
        $hasContent = $article['intro'] !== '' || $article['vision'] !== '' || $article['direction'] !== ''
            || $article['origine'] !== '' || $objectifs !== [];
        return $hasContent ? $article : null;
    }

    private function meetsThreshold(array $centre, array $globalContact): bool
    {
        return $centre['adresse'] !== ''
            && $centre['horaires'] !== []
            && ($centre['contact'] !== [] || $globalContact !== []);
    }

    private const TEAM_LABELS = ['Révérend' => 'Révérends', 'Pasteur' => 'Pasteurs', 'Berger' => 'Bergers', 'Leader' => 'Leaders', 'Autre' => 'Autres'];
    private const MONTHS_SHORT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
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
                'affiche'     => $this->safePhoto($e['affiche'] ?? null),
                'lien_inscription' => $this->safeUrl((string) ($e['lien_inscription'] ?? '')),
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

    /**
     * Médias du carrousel d'accueil : fichiers assets/carousel/<N>.<ext>,
     * triés par numéro croissant (le nom définit l'ordre de passage).
     * @return list<array{src:string,type:'image'|'video'}>
     */
    private function heroSlides(): array
    {
        $dir = BASE_PATH . '/assets/carousel';
        $files = is_dir($dir) ? scandir($dir) : false;
        if ($files === false) {
            return [];
        }
        $found = [];
        foreach ($files as $file) {
            if (preg_match('/^(\d+)\.(jpe?g|png|webp|avif|gif|mp4|webm)$/i', $file, $m) !== 1) {
                continue;
            }
            $found[] = [
                'n'    => (int) $m[1],
                'src'  => 'assets/carousel/' . $file,
                'type' => in_array(strtolower($m[2]), ['mp4', 'webm'], true) ? 'video' : 'image',
            ];
        }
        usort($found, static fn(array $a, array $b): int => $a['n'] <=> $b['n']);
        return array_map(static fn(array $f): array => ['src' => $f['src'], 'type' => $f['type']], $found);
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
        $global = $this->contactLinks($centres ? $this->repo->settings() : []);
        foreach ($centres as &$c) {
            $lines = [];
            foreach ($horaires[(int) $c['id']] ?? [] as $h) {
                $lines[] = $h['libelle'] . ' | ' . $h['jours'] . ' | ' . substr((string) $h['heure_debut'], 0, 5)
                    . ($h['heure_fin'] ? ' | ' . substr((string) $h['heure_fin'], 0, 5) : '');
            }
            $c['horaires_text'] = implode("\n", $lines);
            $c['horaires_rows'] = array_values($horaires[(int) $c['id']] ?? []);
            $c['completion'] = $this->centreCompletion($c, $global);
        }
        unset($c);

        return [
            'settings'   => $this->repo->settings(),
            'centres'    => $centres,
            'equipe'     => $this->repo->equipeForAdmin(),
            'evenements' => $this->repo->eventsForAdmin(date('Y-m-d H:i:s')),
            'faq'        => $this->repo->faqForAdmin(),
            'ministries' => $this->repo->ministriesForAdmin(),
        ];
    }

    /** Un centre avec ses horaires structurés (fiche de modification). */
    public function centreForAdmin(int $id): ?array
    {
        $row = $this->repo->centreForAdmin($id);
        if ($row === null) {
            return null;
        }
        $row['horaires_text'] = '';
        foreach ($row['horaires_rows'] as $h) {
            $row['horaires_text'] .= ($row['horaires_text'] === '' ? '' : "\n")
                . $h['libelle'] . ' | ' . $h['jours'] . ' | ' . substr((string) $h['heure_debut'], 0, 5)
                . ($h['heure_fin'] ? ' | ' . substr((string) $h['heure_fin'], 0, 5) : '');
        }
        $row['completion'] = $this->centreCompletion(
            $row,
            $this->contactLinks($this->repo->settings())
        );
        return $row;
    }

    /**
     * Complétude d'un centre selon la règle métier de publication
     * (seuil : adresse + au moins un horaire + un moyen de contact).
     * @param array<string,mixed> $centre (avec horaires_rows)
     * @return array{complete:bool,missing:list<string>}
     */
    public function centreCompletion(array $centre, array $globalContact): array
    {
        $missing = [];
        if (trim((string) ($centre['adresse'] ?? '')) === '') {
            $missing[] = 'adresse';
        }
        if (($centre['horaires_rows'] ?? []) === []) {
            $missing[] = 'horaires';
        }
        $own = ((string) ($centre['telephone'] ?? '')) !== ''
            || ((string) ($centre['whatsapp'] ?? '')) !== ''
            || ((string) ($centre['email'] ?? '')) !== '';
        if (!$own && $globalContact === []) {
            $missing[] = 'contact';
        }
        return ['complete' => $missing === [], 'missing' => $missing];
    }

    /** Statistiques réelles du tableau de bord (aucune valeur fictive). */
    public function adminOverview(): array
    {
        $settings = $this->repo->settings();
        $global = $this->contactLinks($settings);

        $centres = $this->repo->allCentresForAdmin();
        $horaires = $this->repo->horairesByCentre(array_map(static fn($c) => (int) $c['id'], $centres));
        $centresList = [];
        $publies = 0;
        $incomplets = [];
        foreach ($centres as $c) {
            $rows = array_values($horaires[(int) $c['id']] ?? []);
            $c['horaires_rows'] = $rows;
            $completion = $this->centreCompletion($c, $global);
            $centresList[] = [
                'id' => (int) $c['id'], 'nom' => (string) $c['nom'],
                'ville' => (string) ($c['ville'] ?? ''), 'publie' => (int) $c['publie'] === 1,
                'complete' => $completion['complete'], 'missing' => $completion['missing'],
            ];
            if ((int) $c['publie'] === 1) {
                $publies++;
            } elseif (!$completion['complete']) {
                $incomplets[] = (string) $c['nom'];
            }
        }

        $equipe = $this->repo->equipeForAdmin();
        $equipePublies = count(array_filter($equipe, static fn($m) => (int) $m['publie'] === 1));

        $events = $this->repo->eventsForAdmin(date('Y-m-d H:i:s'));
        $eventsPublies = count(array_filter($events, static fn($e) => (int) $e['publie'] === 1));

        $faq = $this->repo->faqForAdmin();
        $faqPublies = count(array_filter($faq, static fn($f) => (int) $f['publie'] === 1));

        $ministries = $this->repo->ministriesForAdmin();
        $ministryCounts = [];
        foreach ($ministries as $type => $rows) {
            $ministryCounts[$type] = [
                'total' => count($rows),
                'publies' => count(array_filter($rows, static fn($m) => (int) $m['publie'] === 1)),
            ];
        }

        $identity = [
            'nom_affichage' => (string) ($settings['nom_affichage'] ?? '') !== '',
            'slogan' => (string) ($settings['slogan'] ?? '') !== '',
            'accueil_texte' => (string) ($settings['accueil_texte'] ?? '') !== '',
            'adresse' => (string) ($settings['adresse'] ?? '') !== '',
            'contact' => $global !== [],
            'hero_image' => (string) ($settings['hero_image'] ?? '') !== '',
        ];

        return [
            'centres' => ['total' => count($centres), 'publies' => $publies, 'list' => $centresList, 'noms_incomplets' => $incomplets],
            'equipe' => ['total' => count($equipe), 'publies' => $equipePublies],
            'evenements' => ['total' => count($events), 'publies' => $eventsPublies],
            'faq' => ['total' => count($faq), 'publies' => $faqPublies],
            'ministries' => $ministryCounts,
            'identity' => $identity,
            'identity_complete' => !in_array(false, $identity, true),
        ];
    }

    /** @param array<string,mixed> $post @return list<string> erreurs (vide = enregistré) */
    public function saveSettings(array $post): array
    {
        $errors = [];
        $values = [];
        $values['nom_affichage'] = mb_substr(trim((string) ($post['nom_affichage'] ?? '')), 0, 150);
        $values['slogan'] = mb_substr(trim((string) ($post['slogan'] ?? '')), 0, 200);
        $values['accueil_texte'] = mb_substr(trim((string) ($post['accueil_texte'] ?? '')), 0, 500);
        $values['adresse'] = mb_substr(trim((string) ($post['adresse'] ?? '')), 0, 255);
        $values['hero_image'] = $this->safePhoto((string) ($post['hero_image'] ?? ''));
        $values['hero_image_alt'] = mb_substr(trim((string) ($post['hero_image_alt'] ?? '')), 0, 200);

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

        foreach (['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'lien_direct' => 'Lien du direct', 'predication_url' => 'Lien de la prédication', 'lien_don' => 'Lien de don'] as $k => $label) {
            $raw = trim((string) ($post[$k] ?? ''));
            if ($raw !== '' && $this->safeUrl($raw) === '') {
                $errors[] = $label . ' : URL https valide requise.';
                $raw = '';
            }
            $values[$k] = $raw;
        }

        $values['predication_titre'] = mb_substr(trim((string) ($post['predication_titre'] ?? '')), 0, 200);
        $predicationDate = trim((string) ($post['predication_date'] ?? ''));
        $values['predication_date'] = $this->validDate($predicationDate) ? $predicationDate : '';

        $values['mentions_legales'] = trim((string) ($post['mentions_legales'] ?? ''));
        $values['confidentialite'] = trim((string) ($post['confidentialite'] ?? ''));
        $values['don_usage'] = mb_substr(trim((string) ($post['don_usage'] ?? '')), 0, 500);

        $this->repo->saveSettings($values);
        return $errors;
    }

    private function validDate(string $s): bool
    {
        if ($s === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) !== 1) {
            return false;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $s);
        return $d !== false && $d->format('Y-m-d') === $s;
    }

    /** @param array<string,mixed> $post */
    public function saveEquipe(array $post): void
    {
        $this->repo->setEquipePublie((array) ($post['publie'] ?? []));
    }

    /**
     * @param array<string,mixed> $post
     * @param array<int,string|null> $affichePaths chemins résolus par le contrôleur [id => chemin|null]
     * @return list<string>
     */
    public function saveEvents(array $post, array $affichePaths = []): array
    {
        $errors = [];
        $listed = array_map('intval', (array) ($post['ids'] ?? []));
        $publie = array_map('intval', (array) ($post['publie'] ?? []));
        $desc = (array) ($post['description'] ?? []);
        $liens = (array) ($post['lien_inscription'] ?? []);
        foreach ($this->repo->eventsForAdmin(date('Y-m-d H:i:s')) as $e) {
            $id = (int) $e['id'];
            if (!in_array($id, $listed, true)) {
                continue;
            }
            $d = mb_substr(trim((string) ($desc[$id] ?? '')), 0, 1000);
            $lien = $this->safeUrl(trim((string) ($liens[$id] ?? '')));
            if (trim((string) ($liens[$id] ?? '')) !== '' && $lien === '') {
                $errors[] = '« ' . $e['nom'] . ' » : lien d\'inscription https invalide (ignoré).';
            }
            $want = in_array($id, $publie, true);
            if ($want && $d === '' && trim((string) ($e['lieu'] ?? '')) === '') {
                $errors[] = '« ' . $e['nom'] . ' » non publié : lieu ou description requis.';
                $want = false;
            }
            $this->repo->saveEventPublic($id, $want, $d !== '' ? $d : null, $affichePaths[$id] ?? null, $lien);
        }
        return $errors;
    }

    /** Supprime une entrée de FAQ (appelé par ActionsController, admin vérifié). */
    public function deleteFaq(int $id): void
    {
        $this->repo->deleteFaq($id);
    }

    /** @param array<string,mixed> $post @return list<string> */
    public function saveFaq(array $post): array
    {
        $errors = [];
        $rows = [];
        $questions = (array) ($post['question'] ?? []);
        $reponses = (array) ($post['reponse'] ?? []);
        $publies = array_map('strval', (array) ($post['publie'] ?? []));
        foreach ($questions as $key => $q) {
            $key = (string) $key;
            $question = mb_substr(trim((string) $q), 0, 255);
            $reponse = mb_substr(trim((string) ($reponses[$key] ?? '')), 0, 5000);
            if ($question === '' && $reponse === '') {
                continue; // ligne vide ignorée
            }
            if ($question === '' || $reponse === '') {
                $errors[] = 'FAQ : chaque entrée nécessite une question et une réponse.';
                continue;
            }
            $publie = in_array($key, $publies, true);
            if (str_starts_with($key, 'new')) {
                $rows[] = ['question' => $question, 'reponse' => $reponse, 'publie' => $publie];
                continue;
            }
            $id = (int) $key;
            if ($id <= 0 || !$this->repo->faqExists($id)) {
                $errors[] = 'FAQ introuvable.';
                continue;
            }
            $rows[] = ['id' => $id, 'question' => $question, 'reponse' => $reponse, 'publie' => $publie];
        }
        if ($rows !== []) {
            $this->repo->saveFaq($rows);
        }
        return $errors;
    }

    /** @param array<string,mixed> $post */
    public function saveMinistries(array $post): void
    {
        foreach (['bacentas', 'basontas'] as $type) {
            $ids = array_map('intval', (array) ($post[$type . '_publie'] ?? []));
            $this->repo->setMinistryPublie($type, $ids);
            $descriptions = [];
            foreach ((array) ($post[$type . '_description'] ?? []) as $id => $desc) {
                $descriptions[(int) $id] = mb_substr(trim((string) $desc), 0, 2000);
            }
            $this->repo->saveMinistryDescriptions($type, $descriptions);
        }
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

        [$rows, $lineErrors] = $this->parseHorairesInput($post);
        $errors = array_merge($errors, $lineErrors);

        $fields = [
            'adresse'        => mb_substr(trim((string) ($post['adresse'] ?? '')), 0, 255) ?: null,
            'quartier'       => mb_substr(trim((string) ($post['quartier'] ?? '')), 0, 100) ?: null,
            'ville'          => mb_substr(trim((string) ($post['ville'] ?? '')), 0, 100) ?: null,
            'itineraire_url' => $itin ?: null,
            'telephone'      => $tel ?: null,
            'whatsapp'       => $wa ?: null,
            'email'          => $mail ?: null,
            'responsable_public' => mb_substr(trim((string) ($post['responsable_public'] ?? '')), 0, 150) ?: null,
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
     * Horaires de culte : saisie structurée (lignes de champs
     * distincts) ou format texte hérité « Libellé | Jours | HH:MM
     * | HH:MM ». Même validation, même stockage - le contrat de
     * données du backend est préservé.
     * @param array<string,mixed> $post
     * @return array{0:array<int,array<string,mixed>>,1:list<string>}
     */
    public function parseHorairesInput(array $post): array
    {
        $libelles = (array) ($post['horaire_libelle'] ?? []);
        $jours = (array) ($post['horaire_jours'] ?? []);
        $debuts = (array) ($post['horaire_debut'] ?? []);
        $fins = (array) ($post['horaire_fin'] ?? []);
        if ($libelles === [] && $jours === [] && $debuts === [] && $fins === []) {
            return $this->parseHoraires((string) ($post['horaires'] ?? ''));
        }
        $rows = [];
        $errors = [];
        foreach ($libelles as $i => $lib) {
            $i = (int) $i;
            $lib = mb_substr(trim((string) $lib), 0, 100);
            $jr = $jours[$i] ?? [];
            $jr = is_array($jr) ? implode(',', array_values(array_filter(array_map('trim', array_map('strval', $jr)), static fn($d) => $d !== ''))) : trim((string) $jr);
            $jr = mb_substr($jr, 0, 100);
            $d = trim((string) ($debuts[$i] ?? ''));
            $f = trim((string) ($fins[$i] ?? ''));
            if ($lib === '' && $jr === '' && $d === '' && $f === '') {
                continue; // ligne vide ignorée
            }
            $ok = $lib !== '' && $jr !== '' && preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $d) === 1
                && ($f === '' || preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $f) === 1);
            if (!$ok) {
                $errors[] = 'Horaire ligne ' . ($i + 1) . ' ignoré (libellé, jours et heure de début requis ; HH:MM).';
                continue;
            }
            $rows[] = [
                'libelle'     => $lib,
                'jours'       => $jr,
                'heure_debut' => $d . ':00',
                'heure_fin'   => $f !== '' ? $f . ':00' : null,
            ];
        }
        return [$rows, $errors];
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
