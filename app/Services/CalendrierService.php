<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Query;
use App\Repositories\AnniversaireRepository;
use App\Repositories\EvenementRepository;

/**
 * Calendrier événementiel + calendrier d'anniversaires.
 * Les anniversaires des membres sont dérivés de users.date_naissance ;
 * les personnes sans compte sont saisies dans la table `anniversaires`.
 */
class CalendrierService
{
    private EvenementRepository $evt;
    private AnniversaireRepository $anniv;

    public function __construct(?EvenementRepository $evt = null, ?AnniversaireRepository $anniv = null)
    {
        $this->evt = $evt ?? new EvenementRepository();
        $this->anniv = $anniv ?? new AnniversaireRepository();
    }

    /* ---------------- Événements ---------------- */

    public function allEvents(): array
    {
        return $this->evt->all();
    }

    public function upcomingEvents(): array
    {
        return $this->evt->all(date('Y-m-d 00:00:00'));
    }

    public function event(int $id): ?array
    {
        return $this->evt->find($id);
    }

    /**
     * @param array<string,mixed> $in champs bruts (nom, date_debut, date_fin, lieu, responsable_id, id?)
     * @return array{ok:bool,errors:array<string,string>,id:?int}
     */
    public function saveEvent(array $in, int $userId): array
    {
        $errors = [];
        $nom = trim((string) ($in['nom'] ?? ''));
        if ($nom === '') {
            $errors['nom'] = 'Le nom est obligatoire.';
        }
        $debut = $this->normalizeDateTime((string) ($in['date_debut'] ?? ''));
        if ($debut === null) {
            $errors['date_debut'] = 'La date de début est obligatoire et doit être valide.';
        }
        $finRaw = trim((string) ($in['date_fin'] ?? ''));
        $fin = $finRaw !== '' ? $this->normalizeDateTime($finRaw) : null;
        if ($finRaw !== '' && $fin === null) {
            $errors['date_fin'] = 'La date de fin est invalide.';
        } elseif ($debut !== null && $fin !== null && $fin < $debut) {
            $errors['date_fin'] = 'La date de fin doit être postérieure à la date de début.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $lieu = trim((string) ($in['lieu'] ?? '')) ?: null;
        $respId = (int) ($in['responsable_id'] ?? 0) ?: null;
        $id = (int) ($in['id'] ?? 0);
        if ($id) {
            $this->evt->update($id, $nom, $debut, $fin, $lieu, $respId);
        } else {
            $id = $this->evt->create($nom, $debut, $fin, $lieu, $respId, $userId);
        }
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    public function deleteEvent(int $id): void
    {
        $this->evt->delete($id);
    }

    /* ---------------- Anniversaires ---------------- */

    /**
     * @return list<array{nom:string,jour:int,mois:int,annee:?int,source:string,id:int,age:?int,is_current_month:bool}>
     */
    public function birthdays(): array
    {
        $out = [];
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');
        $currentDay = (int) date('j');

        foreach (Query::all("SELECT id, prenom, nom, date_naissance FROM users WHERE date_naissance IS NOT NULL") as $u) {
            $ts = strtotime((string) $u['date_naissance']);
            if ($ts === false) {
                continue;
            }
            $y = (int) date('Y', $ts);
            $out[] = [
                'nom'    => trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')),
                'jour'   => (int) date('j', $ts),
                'mois'   => (int) date('n', $ts),
                'annee'  => $y > 1900 ? $y : null,
                'source' => 'membre',
                'id'     => (int) $u['id'],
            ];
        }
        foreach ($this->anniv->all() as $a) {
            $out[] = [
                'nom'    => (string) $a['nom'],
                'jour'   => (int) $a['jour'],
                'mois'   => (int) $a['mois'],
                'annee'  => $a['annee'] !== null ? (int) $a['annee'] : null,
                'source' => 'manuel',
                'id'     => (int) $a['id'],
            ];
        }

        usort($out, static fn($x, $y) => ($x['mois'] <=> $y['mois']) ?: ($x['jour'] <=> $y['jour']) ?: strcmp($x['nom'], $y['nom']));

        foreach ($out as &$b) {
            if ($b['annee'] === null) {
                $b['age'] = null;
            } else {
                $age = $currentYear - $b['annee'];
                if ($b['mois'] > $currentMonth || ($b['mois'] === $currentMonth && $b['jour'] > $currentDay)) {
                    $age--;
                }
                $b['age'] = max(0, $age);
            }
            $b['is_current_month'] = $b['mois'] === $currentMonth;
        }
        unset($b);
        return $out;
    }

    /**
     * @return array{ok:bool,errors:array<string,string>,id:?int}
     */
    public function saveBirthday(array $in, int $userId): array
    {
        $errors = [];
        $nom = trim((string) ($in['nom'] ?? ''));
        if ($nom === '') {
            $errors['nom'] = 'Le nom est obligatoire.';
        }
        $jour = (int) ($in['jour'] ?? 0);
        if ($jour < 1 || $jour > 31) {
            $errors['jour'] = 'Jour invalide (1–31).';
        }
        $mois = (int) ($in['mois'] ?? 0);
        if ($mois < 1 || $mois > 12) {
            $errors['mois'] = 'Mois invalide (1–12).';
        }
        $anneeRaw = trim((string) ($in['annee'] ?? ''));
        $annee = null;
        if ($anneeRaw !== '') {
            $annee = (int) $anneeRaw;
            if ($annee < 1900 || $annee > (int) date('Y')) {
                $errors['annee'] = 'Année invalide.';
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }
        $id = $this->anniv->create($nom, $jour, $mois, $annee, $userId);
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    public function deleteBirthday(int $id): void
    {
        $this->anniv->delete($id);
    }

    /* ---------------- Helpers ---------------- */

    /** Accepte "Y-m-d\TH:i" (input datetime-local) ou "Y-m-d H:i(:s)". Renvoie "Y-m-d H:i:s" ou null. */
    private function normalizeDateTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace('T', ' ', $raw);
        $ts = strtotime($raw);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }
}
