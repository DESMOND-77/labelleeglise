<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Query;
use App\Repositories\RapportJourRepository;
use App\Repositories\ResponsibilityRepository;

/**
 * Rapport du Jour : validation, instantané des responsables, upsert.
 */
class RapportJourService
{
    private RapportJourRepository $repo;
    private ResponsibilityRepository $resp;

    public function __construct(?RapportJourRepository $repo = null, ?ResponsibilityRepository $resp = null)
    {
        $this->repo = $repo ?? new RapportJourRepository();
        $this->resp = $resp ?? new ResponsibilityRepository();
    }

    public function report(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function reportForCentreDate(int $centreId, string $date): ?array
    {
        return $this->repo->findByCentreDate($centreId, $date);
    }

    public function list(?int $centreId, ?string $monthKey): array
    {
        return $this->repo->list($centreId, $monthKey);
    }

    /** @return list<array{id:int,nom:string}> */
    public function reportableBacentas(int $userId, int $centreId, bool $isAdmin = false): array
    {
        if ($isAdmin) {
            return array_map(
                static fn($r) => ['id' => (int) $r['id'], 'nom' => (string) $r['nom']],
                Query::all('SELECT id, nom FROM bacentas WHERE centre_id = ? ORDER BY nom', [$centreId])
            );
        }
        return array_map(
            static fn($r) => ['id' => (int) $r['id'], 'nom' => (string) $r['nom']],
            Query::all(
                "SELECT id, nom FROM bacentas
                  WHERE centre_id = ?
                    AND (
                      id IN (SELECT target_id FROM responsibilities WHERE user_id = ? AND target_type = 'bacenta')
                      OR id = (SELECT bacenta_id FROM users WHERE id = ?)
                    )
                  ORDER BY nom",
                [$centreId, $userId, $userId]
            )
        );
    }

    /** @return array{resp_centre_nom:string,resp_bacenta_nom:string} */
    public function derivedNames(int $centreId, ?int $bacentaId, int $authorId): array
    {
        $centreResp = $this->resp->listForTarget('center', $centreId);
        $respCentreNom = $centreResp ? trim(($centreResp[0]['prenom'] ?? '') . ' ' . ($centreResp[0]['nom'] ?? '')) : '';

        $respBacentaNom = '';
        if ($bacentaId) {
            $bacResp = $this->resp->listForTarget('bacenta', $bacentaId);
            if ($bacResp) {
                $respBacentaNom = trim(($bacResp[0]['prenom'] ?? '') . ' ' . ($bacResp[0]['nom'] ?? ''));
            }
        }
        if ($respBacentaNom === '') {
            $a = Query::one('SELECT prenom, nom FROM users WHERE id = ?', [$authorId]);
            $respBacentaNom = $a ? trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')) : '';
        }

        return ['resp_centre_nom' => $respCentreNom, 'resp_bacenta_nom' => $respBacentaNom];
    }

    /**
     * @param array<string,mixed> $in
     * @return array{ok:bool,errors:array<string,string>,id:?int}
     */
    public function save(array $in, int $userId, bool $isAdmin): array
    {
        $errors = [];

        $centreId = (int) ($in['centre_id'] ?? 0);
        if ($centreId <= 0) {
            $errors['centre_id'] = 'Choisissez un centre.';
        }

        $date = trim((string) ($in['date_rapport'] ?? ''));
        $ts = $date !== '' ? strtotime($date) : false;
        if ($ts === false || date('Y-m-d', $ts) !== $date) {
            $errors['date_rapport'] = 'Date invalide (format attendu AAAA-MM-JJ).';
        }

        $bacentaIdRaw = (int) ($in['bacenta_id'] ?? 0) ?: null;
        if ($bacentaIdRaw !== null && $centreId > 0) {
            $allowed = array_column($this->reportableBacentas($userId, $centreId, $isAdmin), 'id');
            if (!in_array($bacentaIdRaw, $allowed, true)) {
                $errors['bacenta_id'] = 'Ce bacenta ne fait pas partie de ceux que vous pouvez rapporter pour ce centre.';
            }
        }

        $clean = [];
        foreach (RAPPORT_JOUR_FIELDS as $f) {
            $raw = $in[$f['key']] ?? null;
            switch ($f['type']) {
                case 'int':
                    $v = (int) $raw;
                    if ($v < 0) {
                        $errors[$f['key']] = 'Valeur négative interdite.';
                    }
                    $clean[$f['key']] = max(0, $v);
                    break;
                case 'decimal':
                    $v = (float) str_replace([' ', ','], ['', '.'], (string) $raw);
                    if ($v < 0) {
                        $errors[$f['key']] = 'Montant négatif interdit.';
                    }
                    $clean[$f['key']] = max(0, $v);
                    break;
                case 'text':
                    $s = trim((string) $raw);
                    $clean[$f['key']] = $s === '' ? null : mb_substr($s, 0, 150);
                    break;
                case 'textarea':
                default:
                    $s = trim((string) $raw);
                    $clean[$f['key']] = $s === '' ? null : $s;
                    break;
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        // Contrôle d'édition : un rapport existant appartient à son auteur (ou admin).
        $existing = $this->repo->findByCentreDate($centreId, $date);
        if ($existing && !$isAdmin && (int) $existing['auteur_id'] !== $userId) {
            return [
                'ok' => false,
                'errors' => ['_form' => "Ce rapport a été créé par une autre personne ; seul son auteur ou un administrateur peut le modifier."],
                'id' => null,
            ];
        }

        $names = $this->derivedNames($centreId, $bacentaIdRaw, $userId);

        $data = array_merge($clean, [
            'centre_id'        => $centreId,
            'date_rapport'     => $date,
            'auteur_id'        => $existing ? (int) $existing['auteur_id'] : $userId,
            'bacenta_id'       => $bacentaIdRaw,
            'resp_centre_nom'  => $names['resp_centre_nom'],
            'resp_bacenta_nom' => $names['resp_bacenta_nom'],
        ]);

        $id = $this->repo->upsert($data);
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }
}
