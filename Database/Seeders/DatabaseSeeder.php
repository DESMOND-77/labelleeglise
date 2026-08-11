<?php

/**
 * Seeder — Données de démonstration du modèle complet.
 * (adapté de l'ancien seed.php : comptes, membres, bacentas, basontas,
 * cultes, présences, offrandes, fiche berger, présentation CMS)
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

/**
 * Purge puis insère les données de démonstration.
 */
function seed(): void
{
    $pdo = Database::connection();

    /* ---------- 0. Purge ---------- */
    $tables = ['users_basontas', 'presences', 'offrandes', 'visites', 'suivi_hebdo', 'dimes',
               'examens', 'veillees', 'cultes', 'basontas', 'bacentas', 'users',
               'centres_presentation', 'equipe', 'presentation', 'centres'];
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $pdo->exec("TRUNCATE TABLE `$t`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    /* ---------- Helpers internes ---------- */
    $insertUser = static function (array $data): int {
        $cols = array_keys($data);
        $sql = 'INSERT INTO users (' . implode(', ', $cols) . ') VALUES ('
             . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $pdo = Database::connection();
        $st = $pdo->prepare($sql);
        $st->execute(array_values($data));
        return (int) $pdo->lastInsertId();
    };

    $monthsAgo = static function (int $monthsAgo): string {
        return date('Y-m-d', strtotime("-{$monthsAgo} months", strtotime(date('Y-m-12'))));
    };

    /* ---------- 1. Structure ---------- */
    $centreIds = [];
    foreach (['Mingara', 'Mbaya', 'Quartier Sable', 'Franceville 2', 'Lewai'] as $nom) {
        $pdo->prepare('INSERT INTO centres (nom) VALUES (?)')->execute([$nom]);
        $centreIds[$nom] = (int) $pdo->lastInsertId();
    }

    /* ---------- 2. Comptes + membres ---------- */
    $adminId = $insertUser([
        'email' => 'nob692888@gmail.com',
        'password' => password_hash('LBEGF', PASSWORD_DEFAULT),
        'role' => 'admin',
        'nom' => 'Administrateur', 'prenom' => 'Général',
        'compte_actif' => 1,
    ]);

    $sionMembers = [
        ['nom' => 'Kwamou', 'prenom' => 'Estelle', 'tel' => '061 20 30 40', 'mois' => 5],
        ['nom' => 'Boussamba', 'prenom' => 'Yannick', 'tel' => '062 21 31 41', 'mois' => 3],
        ['nom' => 'Nkoulou', 'prenom' => 'Sion', 'tel' => '074 12 34 56', 'mois' => 1],
        ['nom' => 'Axel', 'prenom' => 'Jean', 'tel' => '066 98 76 54', 'mois' => 0],
        ['nom' => 'Ntsame', 'prenom' => 'Aurel', 'tel' => '077 40 41 42', 'mois' => 4],
        ['nom' => 'Boukinda', 'prenom' => 'Grace', 'tel' => '077 45 12 90', 'mois' => 1],
    ];
    $sionUserIds = [];
    foreach ($sionMembers as $i => $m) {
        $sionUserIds[] = $insertUser([
            'email' => 'membre.sion.' . ($i + 1) . '@labelleeglise.ga',
            'password' => password_hash('membre' . ($i + 1) . '123', PASSWORD_DEFAULT),
            'role' => 'membre',
            'nom' => $m['nom'], 'prenom' => $m['prenom'],
            'telephone' => $m['tel'],
            'quartier' => 'Mingara',
            'compte_actif' => 1,
            'created_at' => $monthsAgo($m['mois']),
        ]);
    }

    $ericId = $insertUser([
        'email' => 'berger.eric.bongo@labelleeglise.ga',
        'password' => password_hash('BergerEB1', PASSWORD_DEFAULT),
        'role' => 'leader',
        'nom' => 'Bongo', 'prenom' => 'Eric',
        'telephone' => '076 90 91 92',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => $monthsAgo(3),
    ]);
    $josueId = $insertUser([
        'email' => 'berger.josue@labelleeglise.ga',
        'password' => password_hash('bergerjosue123', PASSWORD_DEFAULT),
        'role' => 'leader',
        'nom' => 'Mabiala', 'prenom' => 'Josué',
        'telephone' => '076 55 66 77',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => $monthsAgo(0),
    ]);
    $respSionId = $insertUser([
        'email' => 'resp.bacenta.sion@labelleeglise.ga',
        'password' => password_hash('ESKLna', PASSWORD_DEFAULT),
        'role' => 'responsable',
        'nom' => 'Sion', 'prenom' => 'Responsable Bacenta',
        'telephone' => '060 00 00 01',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => $monthsAgo(6),
    ]);

    $steveId = $insertUser([
        'email' => 'membre.generale.1@labelleeglise.ga',
        'password' => password_hash('membreg123', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Obame', 'prenom' => 'Rachel',
        'telephone' => '074 80 81 82',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'invite_par' => $sionUserIds[0],
        'recu_par' => $ericId,
        'date_recu' => $monthsAgo(2),
        'created_at' => $monthsAgo(2),
    ]);
    $insertUser([
        'email' => 'nouveau.1@labelleeglise.ga',
        'password' => password_hash('nouveau123', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Koumba', 'prenom' => 'Steve',
        'telephone' => '060 70 71 72',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'invite_par' => $sionUserIds[0],
        'recu_par' => $ericId,
        'date_recu' => $monthsAgo(3),
        'created_at' => $monthsAgo(3),
    ]);
    $insertUser([
        'email' => 'nouveau.2@labelleeglise.ga',
        'password' => password_hash('nouveau223', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Ibinga', 'prenom' => 'Merveille',
        'telephone' => '060 11 22 33',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'invite_par' => $sionUserIds[3],
        'recu_par' => $ericId,
        'date_recu' => $monthsAgo(1),
        'created_at' => $monthsAgo(1),
    ]);

    /* ---------- 3. Bacentas ---------- */
    $pdo->prepare('INSERT INTO bacentas (nom, responsable_id, centre_id) VALUES (?, ?, ?)')
        ->execute(['Sion', $respSionId, $centreIds['Mingara']]);
    $sionBacentaId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO bacentas (nom, responsable_id, centre_id) VALUES (?, NULL, ?)')
        ->execute(['Bethel', $centreIds['Mbaya']]);

    foreach ($sionUserIds as $uid) {
        $pdo->prepare('UPDATE users SET bacenta_id = ? WHERE id = ?')->execute([$sionBacentaId, $uid]);
    }
    $pdo->prepare('UPDATE users SET bacenta_id = ? WHERE id IN (?, ?, ?)')
        ->execute([$sionBacentaId, $ericId, $josueId, $respSionId]);

    /* ---------- 4. Basontas ---------- */
    $basontaIds = [];
    foreach (BASONTAS_DEFAULT as $nom) {
        $pdo->prepare('INSERT INTO basontas (nom) VALUES (?)')->execute([$nom]);
        $basontaIds[$nom] = (int) $pdo->lastInsertId();
    }
    $larissaId = $insertUser([
        'email' => 'basonta.chorale.1@labelleeglise.ga',
        'password' => password_hash('chorale123', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Mihindou', 'prenom' => 'Larissa',
        'telephone' => '062 60 61 62',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => $monthsAgo(4),
    ]);
    $chriscieId = $insertUser([
        'email' => 'basonta.chorale.2@labelleeglise.ga',
        'password' => password_hash('chorale223', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Mavoungou', 'prenom' => 'Chriscie',
        'telephone' => '062 33 44 55',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => $monthsAgo(0),
    ]);
    $pdo->prepare('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)')
        ->execute([$larissaId, $basontaIds['Chorale']]);
    $pdo->prepare('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)')
        ->execute([$chriscieId, $basontaIds['Chorale']]);
    $pdo->prepare('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)')
        ->execute([$ericId, $basontaIds['Akwaba']]);

    /* ---------- 5. Cultes ---------- */
    $culteIds = [];
    $sundays = [];
    $d = new \DateTimeImmutable('last sunday');
    for ($i = 0; $i < 4; $i++) {
        $sundays[] = $d->modify("-$i weeks")->format('Y-m-d');
    }
    foreach (CULTES_DEFAULT as $i => $nom) {
        $pdo->prepare('INSERT INTO cultes (nom, date_culte, heure_debut, heure_fin, responsable_id) VALUES (?, ?, ?, ?, ?)')
            ->execute([$nom, $sundays[$i], '09:00:00', '11:30:00', $ericId]);
        $culteIds[$nom] = (int) $pdo->lastInsertId();
    }

    /* ---------- 6. Présences ---------- */
    $divineId = $insertUser([
        'email' => 'culte.impact.1@labelleeglise.ga',
        'password' => password_hash('cultimpact1', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Moussavou', 'prenom' => 'Divine',
        'telephone' => '065 50 51 52',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => $monthsAgo(5),
    ]);
    $patrickId = $insertUser([
        'email' => 'culte.impact.2@labelleeglise.ga',
        'password' => password_hash('cultimpact2', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Ondo', 'prenom' => 'Patrick',
        'telephone' => '065 22 11 33',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => $monthsAgo(2),
    ]);
    $pdo->prepare('UPDATE users SET bacenta_id = ? WHERE id IN (?, ?)')
        ->execute([$sionBacentaId, $divineId, $patrickId]);

    $culteImpactId = $culteIds[CULTES_DEFAULT[0]];
    foreach ([$divineId, $patrickId, $ericId, $steveId] as $uid) {
        $pdo->prepare('INSERT INTO presences (user_id, date_presence, culte_id, bacenta_id, centre_id) VALUES (?, ?, ?, ?, ?)')
            ->execute([$uid, $sundays[0], $culteImpactId, $sionBacentaId, $centreIds['Mingara']]);
    }
    $lastFriday = (new \DateTimeImmutable('last friday'))->format('Y-m-d');
    foreach (array_slice($sionUserIds, 0, 4) as $uid) {
        $pdo->prepare('INSERT INTO presences (user_id, date_presence, bacenta_id) VALUES (?, ?, ?)')
            ->execute([$uid, $lastFriday, $sionBacentaId]);
    }
    foreach ([$larissaId, $chriscieId] as $uid) {
        $pdo->prepare('INSERT INTO presences (user_id, date_presence, basonta_id) VALUES (?, ?, ?)')
            ->execute([$uid, $lastFriday, $basontaIds['Chorale']]);
    }
    foreach ([$sionUserIds[0], $sionUserIds[1]] as $uid) {
        $pdo->prepare('INSERT INTO presences (user_id, date_presence, centre_id) VALUES (?, ?, ?)')
            ->execute([$uid, $lastFriday, $centreIds['Mingara']]);
    }

    /* ---------- 7. Offrandes ---------- */
    $month = date('Y-m');
    foreach ([[15000, 18000, 12000, 20000], [22000, 19000, 25000, 21000]] as $weekOffrandes) {
        foreach ($weekOffrandes as $i => $m) {
            $pdo->prepare('INSERT INTO offrandes (bacenta_id, montant, date_offrande, mois, jour_index) VALUES (?, ?, ?, ?, ?)')
                ->execute([$sionBacentaId, $m, date('Y-m-d', strtotime("this week friday -" . (3 - $i) . " week")), $month, $i]);
        }
        foreach ($weekOffrandes as $i => $m) {
            $pdo->prepare('INSERT INTO offrandes (centre_id, montant, date_offrande, mois, jour_index) VALUES (?, ?, ?, ?, ?)')
                ->execute([$centreIds['Mbaya'], $m, date('Y-m-d', strtotime("this week wednesday -" . (3 - $i) . " week")), $month, $i]);
        }
    }

    /* ---------- 8. Fiche berger (Eric) ---------- */
    $year = (int) date('Y');
    foreach ([20000, 20000, 25000] as $mi => $montant) {
        $pdo->prepare('INSERT INTO dimes (user_id, annee, mois, montant) VALUES (?, ?, ?, ?)')
            ->execute([$ericId, $year, $mi + 1, $montant]);
    }
    $pdo->prepare('INSERT INTO examens (user_id, nom, date_exam) VALUES (?, ?, ?)')
        ->execute([$ericId, 'École des Leaders — Niveau 1', $monthsAgo(4)]);
    $pdo->prepare('INSERT INTO veillees (user_id, date_veillee, present) VALUES (?, ?, 1)')
        ->execute([$ericId, $monthsAgo(1)]);
    $pdo->prepare('INSERT INTO veillees (user_id, date_veillee, present) VALUES (?, ?, 0)')
        ->execute([$ericId, $monthsAgo(0)]);

    $weekKey = date('o-\WW');
    foreach (WEEK_DAYS as $i => $day) {
        $fields = [
            'priere' => '30 min', 'meditation' => '15 min',
            'jourFlow' => $i === 2 ? 'Oui' : 'Non',
            'livre' => 'Les Fondements',
            'themeEveque' => 'La foi qui agit',
            'themeReverend' => 'Marcher dans la vision',
            'visites' => 'Estelle K.',
            'invitesDimanche' => 'Merveille I.',
            'invitesApres' => $day === 'Dimanche' ? 'Steve K., Rachel O.' : '',
        ];
        foreach ($fields as $champ => $valeur) {
            if ($valeur !== '') {
                $pdo->prepare('INSERT INTO suivi_hebdo (user_id, semaine, jour, champ, valeur) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$ericId, $weekKey, $day, $champ, $valeur]);
            }
        }
    }

    $visites = [
        ['prenom' => 'Estelle', 'date' => date('Y-m-d', strtotime('-12 days')), 'obs' => 'Bien accueillie, à revisiter'],
        ['prenom' => 'Yannick', 'date' => date('Y-m-d', strtotime('-9 days')), 'obs' => ''],
        ['prenom' => '', 'date' => null, 'obs' => ''],
        ['prenom' => '', 'date' => null, 'obs' => ''],
    ];
    foreach ($visites as $i => $v) {
        $pdo->prepare('INSERT INTO visites (visiteur_id, bacenta_id, nom_visite, date_visite, mois, semaine, observations) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$ericId, $sionBacentaId, $v['prenom'] !== '' ? $v['prenom'] : null, $v['date'] ?? date('Y-m-d'), $month, $i, $v['obs'] !== '' ? $v['obs'] : null]);
    }

    /* ---------- 9. Présentation CMS ---------- */
    $pdo->prepare('INSERT INTO presentation (accroche, histoire) VALUES (?, ?)')->execute([
        'Une famille de foi au cœur de Franceville, tournée vers l\'excellence et le service.',
        'Fondée avec la vision de bâtir une église vivante et accueillante, La Belle Église rassemble aujourd\'hui plusieurs centres, bacentas, basontas et cultes autour d\'un même objectif : faire grandir chacun dans la foi, la communion fraternelle et le service.',
    ]);

    $equipe = [
        ['nom' => 'Rév. Jean-Pierre Moussavou', 'role' => 'Révérend Principal', 'categorie' => 'Révérend', 'bio' => 'À la tête de l\'église depuis sa fondation, il porte la vision spirituelle de La Belle Église.', 'emoji' => '🙏'],
        ['nom' => 'Pasteure Grace Ondo', 'role' => 'Pasteure', 'categorie' => 'Pasteur', 'bio' => 'En charge de l\'accompagnement pastoral et du suivi des bacentas.', 'emoji' => '✝️'],
        ['nom' => 'Steve Koumba', 'role' => 'Leader Akwaba', 'categorie' => 'Leader', 'bio' => 'Responsable de l\'accueil et de l\'intégration des nouveaux membres.', 'emoji' => '🌟'],
    ];
    foreach ($equipe as $e) {
        $pdo->prepare('INSERT INTO equipe (nom_affichage, role_affichage, bio, emoji, categorie) VALUES (?, ?, ?, ?, ?)')
            ->execute([$e['nom'], $e['role'], $e['bio'], $e['emoji'], $e['categorie']]);
    }

    $pdo->prepare('INSERT INTO centres_presentation (centre_id, intro, vision, direction, origine, objectifs,
           situation_annees, situation_pasteurs, situation_bergers, situation_leaders, situation_bacentas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
        $centreIds['Mbaya'],
        'Rassemblement de chrétiens communiant chaque mercredi dans la prière, l\'adoration et la parole de Dieu au campus universitaire. Jeunesse talentueuse, zélée et passionnée.',
        'Sauver les âmes et former les saints en vue de l\'œuvre du Seigneur (Éphésiens 4:11-12).',
        'Dirigé par un Pasteur étudiant, le Pasteur Joas NZIENGUI, accompagné de plusieurs bergers et leaders actifs.',
        'Extension de La Belle Église Internationale Franceville, lancée par le Révérend Crowl MILAGUE.',
        json_encode(['Devenir une église First Love', 'Rassembler 150 personnes cette année',
                     'Implanter 10 bacentas sur le campus et 10 en extérieur', 'Développer de méga basontas'], JSON_UNESCAPED_UNICODE),
        4, 1, 3, 7, 10,
    ]);

    // Les comptes de démonstration sont déjà "actifs" (compte_actif = 1) :
    // on les considère comme vérifiés + validés pour que les identifiants
    // documentés (README) continuent de fonctionner immédiatement après
    // une (ré)installation, sans passer par le workflow d'inscription publique.
    $pdo->exec(
        "UPDATE users SET account_status = 'active', email_verified = 1, email_verified_at = NOW()
          WHERE compte_actif = 1"
    );

    // Référence l'admin pour ne pas l'oublier (déjà inséré).
    unset($adminId);
}
