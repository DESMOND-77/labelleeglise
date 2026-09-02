<?php

/**
 * Wrappers de compatibilité pour la couche de données.
 * -------------------------------------------------------------
 * Expose les anciennes fonctions globales (get_centres, get_bacenta,
 * compute_summary_stats, save_offrandes_month…) en déléguant aux
 * repositories / services. Les vues continuent ainsi de fonctionner
 * sans modification.
 */

declare(strict_types=1);

use App\Core\Query;
use App\Repositories\CentreRepository;
use App\Repositories\BacentaRepository;
use App\Repositories\BasontaRepository;
use App\Repositories\CulteRepository;
use App\Repositories\UserRepository;
use App\Repositories\MemberRepository;
use App\Repositories\AttendanceRepository;
use App\Repositories\ContributionRepository;
use App\Repositories\BergerRepository;
use App\Repositories\CMSRepository;
use App\Services\StatisticsService;
use App\Services\MemberService;
use App\Services\ReportService;
use App\Services\ContributionService;

function _repo(string $class): object
{
    static $instances = [];
    if (!isset($instances[$class])) {
        $instances[$class] = new $class();
    }
    return $instances[$class];
}

/* ---------- Structure ---------- */

function get_centres(): array { return _repo(CentreRepository::class)->all(); }
function get_centre(int $id): ?array { return _repo(CentreRepository::class)->find($id); }
function get_bacentas(?int $centreId = null): array { return _repo(BacentaRepository::class)->all($centreId); }
function get_bacenta(int $id): ?array { return _repo(BacentaRepository::class)->find($id); }
function bacenta_name_map(): array { return _repo(BacentaRepository::class)->nameMap(); }
function get_basontas(): array { return _repo(BasontaRepository::class)->all(); }
function get_basonta(int $id): ?array { return _repo(BasontaRepository::class)->find($id); }
function get_cultes(): array { return _repo(CulteRepository::class)->all(); }
function get_culte(int $id): ?array { return _repo(CulteRepository::class)->find($id); }

/* ---------- Membres ---------- */

function get_user(int $id): ?array { return _repo(UserRepository::class)->find($id); }
function full_name(array $u): string { return trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')); }
function get_members_of_bacenta(int $bacentaId): array { return _repo(MemberRepository::class)->ofBacenta($bacentaId); }
function get_members_of_centre(int $centreId): array { return _repo(MemberRepository::class)->ofCentre($centreId); }
function get_members_of_basonta(int $basontaId): array { return _repo(MemberRepository::class)->ofBasonta($basontaId); }
function get_members_of_culte(int $culteId): array { return _repo(MemberRepository::class)->ofCulte($culteId); }
function get_nouveaux_members(?string $filter = null): array { return _repo(MemberRepository::class)->nouveaux($filter); }
function get_generale_members(?string $filter = null): array { return _repo(MemberRepository::class)->generale($filter); }
function get_berger_members(?string $filter = null): array { return _repo(MemberRepository::class)->bergers($filter); }
function get_users(): array { return _repo(UserRepository::class)->all(); }
function search_people(string $q): array { return _repo(MemberRepository::class)->search($q); }

function get_members_context(string $section, ?int $id = null): array
{
    switch ($section) {
        case 'bacentas':
            $b = $id ? get_bacenta($id) : null;
            return $b ? ['members' => get_members_of_bacenta($id), 'label' => $b['nom'], 'entity' => $b, 'entityId' => $id]
                      : ['members' => [], 'label' => '', 'entity' => null, 'entityId' => null];
        case 'centres':
            $c = $id ? get_centre($id) : null;
            return $c ? ['members' => get_members_of_centre($id), 'label' => $c['nom'], 'entity' => $c, 'entityId' => $id]
                      : ['members' => [], 'label' => '', 'entity' => null, 'entityId' => null];
        case 'cultes':
            $c = $id ? get_culte($id) : null;
            return $c ? ['members' => get_members_of_culte($id), 'label' => $c['nom'], 'entity' => $c, 'entityId' => $id]
                      : ['members' => [], 'label' => '', 'entity' => null, 'entityId' => null];
        case 'basontas':
            $b = $id ? get_basonta($id) : null;
            return $b ? ['members' => get_members_of_basonta($id), 'label' => $b['nom'], 'entity' => $b, 'entityId' => $id]
                      : ['members' => [], 'label' => '', 'entity' => null, 'entityId' => null];
        case 'nouveaux':
            return ['members' => get_nouveaux_members(), 'label' => 'Nouveaux membres', 'entity' => null, 'entityId' => null];
        case 'bergers':
            return ['members' => get_berger_members(), 'label' => 'Liste des bergers', 'entity' => null, 'entityId' => null];
        default:
            return ['members' => get_generale_members(), 'label' => 'Liste générale des membres', 'entity' => null, 'entityId' => null];
    }
}

/* ---------- Statistiques ---------- */

function count_members(string $section): int { return _repo(StatisticsService::class)->countMembers($section); }
function compute_evolution_history(): array { return _repo(StatisticsService::class)->evolutionHistory(); }
function compute_summary_stats(): array { return _repo(StatisticsService::class)->summaryStats(); }
function build_narrative(array $stats): array { return _repo(StatisticsService::class)->narrative($stats); }

/* ---------- Présences ---------- */

function latest_culte(): ?array { return _repo(CulteRepository::class)->latest(); }
function latest_basonta_of_user(int $userId): ?int { return _repo(BasontaRepository::class)->latestOfUser($userId); }
function presence_status(array $user, string $type): string { return _repo(MemberService::class)->presenceStatus($user, $type); }
function save_quick_presence(int $userId, string $type, string $value): void { _repo(MemberService::class)->saveQuickPresence($userId, $type, $value); }
function point_culte_presence(int $culteId, string $date, array $userIds): void { _repo(\App\Services\AttendanceService::class)->pointCulte($culteId, $date, $userIds); }

function save_unit_presence(string $unitType, int $unitId, string $date, array $rawStatuts, array $allowedUserIds): void
{
    _repo(\App\Services\AttendanceService::class)->pointOccurrence($unitType, $unitId, $date, $rawStatuts, $allowedUserIds);
}
function unit_presence_grid(string $unitType, int $unitId, string $date, array $members): array
{
    return _repo(\App\Services\AttendanceService::class)->occurrenceGrid($unitType, $unitId, $date, $members);
}
function unit_annual_matrix(string $unitType, int $unitId, int $year, array $members): array
{
    return _repo(\App\Services\AttendanceService::class)->annualMatrix($unitType, $unitId, $year, $members);
}
function member_presence_counts(array $user): array { return _repo(MemberService::class)->presenceCounts($user); }

/* ---------- Visites & offrandes ---------- */

function get_bacenta_visites(int $bacentaId, string $monthKey): array { return _repo(BergerRepository::class)->bacentaVisites($bacentaId, $monthKey); }
function save_bacenta_visites(int $bacentaId, string $monthKey, array $visites, int $visiteurId): void { _repo(BergerRepository::class)->saveBacentaVisites($bacentaId, $monthKey, $visites, $visiteurId); }
function get_offrandes_month(string $type, int $entityId, string $monthKey): array { return _repo(ContributionRepository::class)->offrandesMonth($type, $entityId, $monthKey); }
function save_offrandes_month(string $type, int $entityId, string $monthKey, array $vals): void { _repo(ContributionRepository::class)->saveOffrandesMonth($type, $entityId, $monthKey, $vals); }
function sum_offrandes_month_total(string $type, int $entityId, string $monthKey): int { return _repo(ContributionRepository::class)->sumOffrandesMonth($type, $entityId, $monthKey); }
function sum_offrandes_year_total(string $type, int $entityId, int $year): int { return _repo(ContributionRepository::class)->sumOffrandesYear($type, $entityId, $year); }
function sum_offrandes_section_year(string $type, int $year): int { return _repo(ContributionRepository::class)->sumOffrandesSectionYear($type, $year); }
function global_offrandes_year(int $year): int { return _repo(ContributionService::class)->globalYear($year); }

/* ---------- Fiche berger ---------- */

function get_dimes(int $userId, int $year): array { return _repo(ContributionRepository::class)->dimes($userId, $year); }
function save_dimes(int $userId, int $year, array $vals): void { _repo(ContributionRepository::class)->saveDimes($userId, $year, $vals); }
function get_examens(int $userId): array { return _repo(BergerRepository::class)->examens($userId); }
function add_examen(int $userId, string $nom, ?string $date): void { _repo(BergerRepository::class)->addExamen($userId, $nom, $date); }
function delete_examen(int $userId, int $examenId): void { _repo(BergerRepository::class)->deleteExamen($userId, $examenId); }
function get_veillees(int $userId): array { return _repo(BergerRepository::class)->veillees($userId); }
function add_veillee(int $userId, string $date, bool $present): void { _repo(BergerRepository::class)->addVeillee($userId, $date, $present); }
function delete_veillee(int $userId, int $veilleeId): void { _repo(BergerRepository::class)->deleteVeillee($userId, $veilleeId); }
function get_suivi_week(int $userId, string $weekKey): array { return _repo(BergerRepository::class)->suiviWeek($userId, $weekKey); }
function save_suivi_week(int $userId, string $weekKey, array $data): void { _repo(BergerRepository::class)->saveSuiviWeek($userId, $weekKey, $data); }
function is_field_filled(array $f, $v): bool { return _repo(ReportService::class)->isFieldFilled($f, $v); }
function compute_week_completion(array $week): int { return _repo(ReportService::class)->weekCompletion($week); }
function compute_year_completion(int $userId, int $year): int { return _repo(ReportService::class)->yearCompletion($userId, $year); }

/* ---------- CMS ---------- */

function get_presentation(): array { return _repo(CMSRepository::class)->presentation(); }
function save_presentation(string $accroche, string $histoire): void { _repo(CMSRepository::class)->savePresentation($accroche, $histoire); }
function get_equipe(): array { return _repo(CMSRepository::class)->equipe(); }
function get_centres_articles(): array { return _repo(CMSRepository::class)->centresArticles(); }
function get_centre_article(int $id): ?array { return _repo(CMSRepository::class)->centreArticle($id); }

/* ---------- CRUD users ---------- */

function insert_user(array $data): int { return _repo(UserRepository::class)->insert($data); }
function user_data_from_post(?int $existingId = null): array { return _repo(MemberService::class)->dataFromPost($existingId); }
function email_taken(string $email, ?int $exceptId = null): bool { return _repo(UserRepository::class)->emailTaken($email, $exceptId); }
function insert_user_from_post(array $data, ?string $photo): int
{
    $data['password'] = password_hash((string) ($_POST['password'] ?? ''), PASSWORD_DEFAULT);
    $data['photo_de_profil'] = $photo;
    return insert_user($data);
}
function update_user_from_post(int $id, array $data, ?string $photo, ?string $newPassword): void
{
    $sets = 'nom = ?, prenom = ?, telephone = ?, email = ?, quartier = ?, date_naissance = ?, role = ?, bacenta_id = ?, compte_actif = ?, invite_par = ?, recu_par = ?, date_recu = ?';
    $params = [$data['nom'], $data['prenom'], $data['telephone'], $data['email'], $data['quartier'],
               $data['date_naissance'], $data['role'], $data['bacenta_id'], $data['compte_actif'],
               $data['invite_par'], $data['recu_par'], $data['date_recu']];
    if ($photo) {
        $sets .= ', photo_de_profil = ?';
        $params[] = $photo;
    }
    if ($newPassword !== null && $newPassword !== '') {
        $sets .= ', password = ?';
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    $params[] = $id;
    Query::run("UPDATE users SET $sets WHERE id = ?", $params);
}
function delete_user(int $id): void { _repo(UserRepository::class)->delete($id); }
function delete_user_photo(?string $path): void { delete_photo_file($path); }
