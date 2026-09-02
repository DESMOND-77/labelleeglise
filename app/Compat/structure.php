<?php

/**
 * Wrappers de compatibilité pour les opérations de structure (CRUD)
 * et le CMS (équipe, articles). Déleguent aux repositories.
 */

declare(strict_types=1);

use App\Repositories\CentreRepository;
use App\Repositories\BacentaRepository;
use App\Repositories\BasontaRepository;
use App\Repositories\CulteRepository;
use App\Repositories\CMSRepository;
use App\Core\Query;

/* ---------- Centres ---------- */

function delete_centre(int $id): void { _repo(CentreRepository::class)->delete($id); }
function save_centre(?int $id, string $nom): void
{
    if ($id) {
        _repo(CentreRepository::class)->update($id, $nom);
    } else {
        _repo(CentreRepository::class)->create($nom);
    }
}

/* ---------- Bacentas ---------- */

function delete_bacenta(int $id): void { _repo(BacentaRepository::class)->delete($id); }
function save_bacenta(?int $id, string $nom, ?int $centreId, ?int $respId, ?string $jours = null, ?string $debut = null, ?string $fin = null): void
{
    if ($id) {
        _repo(BacentaRepository::class)->update($id, $nom, $centreId, $respId, $jours, $debut, $fin);
    } else {
        _repo(BacentaRepository::class)->create($nom, $centreId, $respId, $jours, $debut, $fin);
    }
}

/* ---------- Cultes ---------- */

function delete_culte(int $id): void { _repo(CulteRepository::class)->delete($id); }
function save_culte(?int $id, string $nom, ?string $date, ?string $debut, ?string $fin, ?int $resp, ?string $jours = null): void
{
    if ($id) {
        _repo(CulteRepository::class)->update($id, $nom, $date, $debut, $fin, $resp, $jours);
    } else {
        _repo(CulteRepository::class)->create($nom, $date, $debut, $fin, $resp, $jours);
    }
}

/* ---------- Basontas ---------- */

function delete_basonta(int $id): void { _repo(BasontaRepository::class)->delete($id); }
function save_basonta(?int $id, string $nom, ?int $resp, ?string $jours = null, ?string $debut = null, ?string $fin = null): void
{
    if ($id) {
        _repo(BasontaRepository::class)->update($id, $nom, $resp, $jours, $debut, $fin);
    } else {
        _repo(BasontaRepository::class)->create($nom, $resp, $jours, $debut, $fin);
    }
}
function basonta_add_member(int $basonta, int $membre): void { _repo(BasontaRepository::class)->addMember($basonta, $membre); }
function basonta_remove_member(int $basonta, int $membre): void { _repo(BasontaRepository::class)->removeMember($basonta, $membre); }

/* ---------- CMS : équipe & articles ---------- */

function save_equipe_record(?int $id, array $data): void { _repo(CMSRepository::class)->saveEquipe($id, $data); }
function delete_equipe_record(int $id): ?array { return _repo(CMSRepository::class)->deleteEquipe($id); }
function save_article_record(?int $id, array $fields): void { _repo(CMSRepository::class)->saveCentreArticle($id, $fields); }
function delete_article_record(int $id): ?array { return _repo(CMSRepository::class)->deleteCentreArticle($id); }

/* ---------- Responsables ----------
 * Délègue à ResponsibilityService (table `responsibilities` — source de
 * vérité) : la colonne responsable_id historique n'est plus qu'un reflet
 * synchronisé automatiquement (spec §41), jamais écrite directement ici.
 * Types acceptés : 'center' | 'bacenta' | 'cult' | 'basonta'
 * (alias FR historiques 'centre'/'culte' tolérés pour compatibilité).
 */
function save_responsable(string $type, int $id, ?int $userId): array
{
    $type = match ($type) {
        'centre' => 'center',
        'culte'  => 'cult',
        default  => $type,
    };
    if ($userId === null) {
        // "Aucun" sélectionné : retire toutes les responsabilités actuelles de cette cible.
        foreach (responsibility_service()->listForTarget($type, $id) as $row) {
            responsibility_service()->revokeById((int) $row['id']);
        }
        return ['ok' => true, 'error' => null];
    }
    return responsibility_service()->assign($userId, $type, $id);
}
