<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Query;
use App\Repositories\ClasseRepository;

/**
 * Classes / écoles post-culte : validation + progression automatique.
 * Quand un inscrit valide les deux examens (oral + écrit à 'reussi'), il
 * est marqué 'termine' et inscrit dans la classe d'ordre supérieur.
 */
class ClasseService
{
    private ClasseRepository $repo;

    public function __construct(?ClasseRepository $repo = null)
    {
        $this->repo = $repo ?? new ClasseRepository();
    }

    public function all(): array { return $this->repo->all(); }
    public function find(int $id): ?array { return $this->repo->find($id); }
    public function findInscrit(int $id): ?array { return $this->repo->findInscrit($id); }
    public function inscrits(int $classeId): array { return $this->repo->inscritsOf($classeId); }
    public function candidates(int $classeId): array { return $this->repo->candidates($classeId); }
    public function formateurCandidates(): array { return $this->repo->formateurCandidates(); }
    public function deleteInscrit(int $id): void { $this->repo->deleteInscrit($id); }
    public function deleteClasse(int $id): void { $this->repo->delete($id); }

    /** @return array{ok:bool,errors:array<string,string>,id:?int} */
    public function saveClasse(array $in): array
    {
        $errors = [];
        $nom = trim((string) ($in['nom'] ?? ''));
        if ($nom === '') {
            $errors['nom'] = 'Le nom est obligatoire.';
        }
        $ordre = (int) ($in['ordre'] ?? 0);
        if ($ordre < 0) {
            $errors['ordre'] = "L'ordre doit être positif.";
        }
        $nbModules = (int) ($in['nb_modules'] ?? 1);
        if ($nbModules < 1) {
            $errors['nb_modules'] = 'Au moins un module.';
        }
        $session = trim((string) ($in['prochaine_session'] ?? ''));
        if ($session !== '') {
            $ts = strtotime($session);
            if ($ts === false || date('Y-m-d', $ts) !== $session) {
                $errors['prochaine_session'] = 'Date invalide (AAAA-MM-JJ).';
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $formateurId = (int) ($in['formateur_id'] ?? 0) ?: null;
        $actif = !empty($in['actif']) ? 1 : 0;
        $sessionVal = $session !== '' ? $session : null;
        $id = (int) ($in['id'] ?? 0);
        if ($id) {
            $this->repo->update($id, $nom, $formateurId, $ordre, $nbModules, $sessionVal, $actif);
        } else {
            $id = $this->repo->create($nom, $formateurId, $ordre, $nbModules, $sessionVal, $actif);
        }
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    /** @return array{ok:bool,errors:array<string,string>,id:?int,promoted_to:?int} */
    public function saveInscrit(array $in): array
    {
        $errors = [];
        $classeId = (int) ($in['classe_id'] ?? 0);
        $classe = $classeId > 0 ? $this->repo->find($classeId) : null;
        if (!$classe) {
            $errors['classe_id'] = 'Classe introuvable.';
        }
        $userId = (int) ($in['user_id'] ?? 0);
        if ($userId <= 0 || !Query::value('SELECT id FROM users WHERE id = ?', [$userId])) {
            $errors['user_id'] = 'Membre introuvable.';
        }

        $valid = array_keys(EXAM_STATUTS);
        $oral = in_array($in['exam_oral'] ?? '', $valid, true) ? $in['exam_oral'] : 'non_passe';
        $ecrit = in_array($in['exam_ecrit'] ?? '', $valid, true) ? $in['exam_ecrit'] : 'non_passe';

        $noteRaw = trim((string) ($in['exam_note'] ?? ''));
        $note = $noteRaw === '' ? null : (float) str_replace(',', '.', $noteRaw);
        $dateRaw = trim((string) ($in['exam_date'] ?? ''));
        $date = null;
        if ($dateRaw !== '') {
            $ts = strtotime($dateRaw);
            if ($ts === false || date('Y-m-d', $ts) !== $dateRaw) {
                $errors['exam_date'] = 'Date invalide.';
            } else {
                $date = $dateRaw;
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null, 'promoted_to' => null];
        }

        $modules = max(0, min((int) ($in['modules_valides'] ?? 0), (int) $classe['nb_modules']));
        $ordreCourant = (int) $classe['ordre'];

        $result = Query::transaction(function () use ($classeId, $userId, $modules, $oral, $ecrit, $note, $date, $ordreCourant) {
            $inscritId = $this->repo->insertInscrit($classeId, $userId);
            $this->repo->updateInscrit($inscritId, $modules, (string) $oral, (string) $ecrit, $note, $date);

            $promotedTo = null;
            if ($oral === 'reussi' && $ecrit === 'reussi') {
                $this->repo->setInscritStatut($inscritId, 'termine');
                $nextId = $this->repo->nextActiveClassId($ordreCourant);
                if ($nextId !== null) {
                    $this->repo->insertInscrit($nextId, $userId);
                    $promotedTo = $nextId;
                }
            }
            return ['id' => $inscritId, 'promoted_to' => $promotedTo];
        });

        return ['ok' => true, 'errors' => [], 'id' => $result['id'], 'promoted_to' => $result['promoted_to']];
    }
}
