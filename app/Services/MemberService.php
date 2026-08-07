<?php

namespace App\Services;

use App\Core\Query;
use App\Repositories\UserRepository;
use App\Repositories\BacentaRepository;
use App\Repositories\BasontaRepository;
use App\Repositories\CulteRepository;
use App\Repositories\AttendanceRepository;

/**
 * Logique métier des membres (CRUD, rattachement, présences rapides).
 */
class MemberService
{
    private UserRepository $users;
    private BacentaRepository $bacentas;
    private BasontaRepository $basontas;
    private CulteRepository $cultes;
    private AttendanceRepository $attendance;

    public function __construct(
        ?UserRepository $users = null,
        ?BacentaRepository $bacentas = null,
        ?BasontaRepository $basontas = null,
        ?CulteRepository $cultes = null,
        ?AttendanceRepository $attendance = null
    ) {
        $this->users = $users ?? new UserRepository();
        $this->bacentas = $bacentas ?? new BacentaRepository();
        $this->basontas = $basontas ?? new BasontaRepository();
        $this->cultes = $cultes ?? new CulteRepository();
        $this->attendance = $attendance ?? new AttendanceRepository();
    }

    /** Champs d'un membre depuis $_POST (limités à la liste autorisée). */
    public function dataFromPost(?int $existingId = null): array
    {
        $data = [];
        foreach (['nom', 'prenom', 'telephone', 'email', 'quartier'] as $f) {
            $data[$f] = trim((string) ($_POST[$f] ?? ''));
        }
        $data['date_naissance'] = trim((string) ($_POST['date_naissance'] ?? '')) ?: null;
        $data['role'] = in_array($_POST['role'] ?? '', ['admin', 'leader', 'assistant', 'pasteur', 'reverant', 'membre', 'responsable'], true)
            ? $_POST['role'] : 'membre';
        $data['bacenta_id'] = (int) ($_POST['bacenta_id'] ?? 0) ?: null;
        $data['compte_actif'] = isset($_POST['compte_actif']) ? 1 : 0;
        $data['invite_par'] = (int) ($_POST['invite_par'] ?? 0) ?: null;
        $data['recu_par'] = (int) ($_POST['recu_par'] ?? 0) ?: null;
        $data['date_recu'] = trim((string) ($_POST['date_recu'] ?? '')) ?: null;
        return $data;
    }

    /** Statut de présence "rapide" d'un membre pour un type donné. */
    public function presenceStatus(array $user, string $type): string
    {
        $uid = (int) $user['id'];
        switch ($type) {
            case 'presenceCulte':
                $culte = $this->cultes->latest();
                if (!$culte) {
                    return '';
                }
                return $this->attendance->hasPresence('culte_id', $uid, (int) $culte['id']) ? 'Présent' : '';
            case 'presenceBacenta':
                if (empty($user['bacenta_id'])) {
                    return '';
                }
                return $this->attendance->hasPresence('bacenta_id', $uid, (int) $user['bacenta_id']) ? 'Présent' : '';
            case 'presenceBasonta':
                $basontaId = $this->basontas->latestOfUser($uid);
                if (!$basontaId) {
                    return '';
                }
                return $this->attendance->hasPresence('basonta_id', $uid, $basontaId) ? 'Présent' : '';
            case 'presenceCentre': {
                if (empty($user['bacenta_id'])) {
                    return '';
                }
                $b = $this->bacentas->find((int) $user['bacenta_id']);
                if (!$b || empty($b['centre_id'])) {
                    return '';
                }
                return $this->attendance->hasPresence('centre_id', $uid, (int) $b['centre_id']) ? 'Présent' : '';
            }
        }
        return '';
    }

    /** Enregistre (upsert) une présence rapide : 'Présent' → ligne, sinon suppression. */
    public function saveQuickPresence(int $userId, string $type, string $value): void
    {
        $user = $this->users->find($userId);
        if (!$user) {
            return;
        }
        $present = ($value === 'Présent');

        switch ($type) {
            case 'presenceCulte':
                $culte = $this->cultes->latest();
                if (!$culte) {
                    return;
                }
                if ($present) {
                    if (!$this->attendance->hasPresence('culte_id', $userId, (int) $culte['id'])) {
                        $this->attendance->insert($userId, date('Y-m-d'), ['culte_id' => $culte['id']]);
                    }
                } else {
                    $this->attendance->deleteByColumn('culte_id', $userId, (int) $culte['id']);
                }
                break;
            case 'presenceBacenta':
                if (empty($user['bacenta_id'])) {
                    return;
                }
                if ($present) {
                    if (!$this->attendance->hasPresence('bacenta_id', $userId, (int) $user['bacenta_id'])) {
                        $this->attendance->insert($userId, date('Y-m-d'), ['bacenta_id' => $user['bacenta_id']]);
                    }
                } else {
                    $this->attendance->deleteByColumn('bacenta_id', $userId, (int) $user['bacenta_id']);
                }
                break;
            case 'presenceBasonta':
                $basontaId = $this->basontas->latestOfUser($userId);
                if (!$basontaId) {
                    return;
                }
                if ($present) {
                    if (!$this->attendance->hasPresence('basonta_id', $userId, $basontaId)) {
                        $this->attendance->insert($userId, date('Y-m-d'), ['basonta_id' => $basontaId]);
                    }
                } else {
                    $this->attendance->deleteByColumn('basonta_id', $userId, $basontaId);
                }
                break;
            case 'presenceCentre': {
                if (empty($user['bacenta_id'])) {
                    return;
                }
                $b = $this->bacentas->find((int) $user['bacenta_id']);
                if (!$b || empty($b['centre_id'])) {
                    return;
                }
                if ($present) {
                    if (!$this->attendance->hasPresence('centre_id', $userId, (int) $b['centre_id'])) {
                        $this->attendance->insert($userId, date('Y-m-d'), ['centre_id' => $b['centre_id']]);
                    }
                } else {
                    $this->attendance->deleteByColumn('centre_id', $userId, (int) $b['centre_id']);
                }
                break;
            }
        }
    }

    /** Comptes de présence d'un membre (donut profil). */
    public function presenceCounts(array $user): array
    {
        $present = 0;
        $absent = 0;
        $none = 0;
        foreach (PRESENCE_FIELDS as $f) {
            $s = $this->presenceStatus($user, $f);
            if ($s === 'Présent') {
                $present++;
            } elseif ($s === 'Absent') {
                $absent++;
            } else {
                $none++;
            }
        }
        return ['present' => $present, 'absent' => $absent, 'none' => $none];
    }

    /** Rattache automatiquement un nouveau membre selon sa section d'origine. */
    public function attachNewMember(int $userId, string $section, ?int $entityId): void
    {
        if ($section === 'bacentas' && $entityId) {
            Query::run('UPDATE users SET bacenta_id = ? WHERE id = ?', [$entityId, $userId]);
        } elseif ($section === 'centres' && $entityId) {
            $first = $this->bacentas->firstIdOfCentre($entityId);
            if ($first) {
                Query::run('UPDATE users SET bacenta_id = ? WHERE id = ?', [$first, $userId]);
            }
        } elseif ($section === 'basontas' && $entityId) {
            Query::run('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)', [$userId, $entityId]);
        } elseif ($section === 'cultes' && $entityId) {
            Query::run('INSERT INTO presences (user_id, date_presence, culte_id) VALUES (?, CURDATE(), ?)', [$userId, $entityId]);
        }
    }
}
