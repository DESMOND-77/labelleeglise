<?php
/**
 * Données de démonstration — adaptées au nouveau modèle de données
 * (centres = structure / users = membres / presences par événement…).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/data.php';

function months_ago_date(int $monthsAgo): string
{
    return date('Y-m-d', strtotime("-{$monthsAgo} months", strtotime(date('Y-m-12'))));
}

function clear_all(): void
{
    $tables = ['users_basontas', 'presences', 'offrandes', 'visites', 'suivi_hebdo', 'dimes',
               'examens', 'veillees', 'cultes', 'basontas', 'bacentas', 'users',
               'centres_presentation', 'equipe', 'presentation', 'centres'];
    db()->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        db()->exec("TRUNCATE TABLE `$t`");
    }
    db()->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function seed(): void
{
    clear_all();

    /* ================= 1. STRUCTURE ================= */

    // Centres = ancienne structure "quartier"
    $centreIds = [];
    foreach ([ 'Mingara', 'Mbaya', 'Quartier Sable', 'Franceville 2', 'Lewai' ] as $nom) {
        $centreIds[$nom] = (int) qexec('INSERT INTO centres (nom) VALUES (?)', [$nom]);
    }

    /* ================= 2. COMPTES + MEMBRES ================= */

    // Comptes de connexion
    $adminId = insert_user([
        'email' => 'admin@labelleeglise.ga',
        'password' => password_hash('LBEGF', PASSWORD_DEFAULT),
        'role' => 'admin',
        'nom' => 'Administrateur', 'prenom' => 'Général',
        'compte_actif' => 1,
    ]);
    insert_user([
        'email' => 'user@labelleeglise.ga',
        'password' => password_hash('user1111', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Utilisateur', 'prenom' => 'Standard',
        'compte_actif' => 1,
    ]);

    // Membres du Bacenta Sion (centre Mingara)
    $sionMembers = [
        ['nom' => 'Kwamou', 'prenom' => 'Estelle', 'tel' => '061 20 30 40', 'mois' => 5, 'role' => 'membre'],
        ['nom' => 'Boussamba', 'prenom' => 'Yannick', 'tel' => '062 21 31 41', 'mois' => 3, 'role' => 'membre'],
        ['nom' => 'Nkoulou', 'prenom' => 'Sion', 'tel' => '074 12 34 56', 'mois' => 1, 'role' => 'membre'],
        ['nom' => 'Axel', 'prenom' => 'Jean', 'tel' => '066 98 76 54', 'mois' => 0, 'role' => 'membre'],
        ['nom' => 'Ntsame', 'prenom' => 'Aurel', 'tel' => '077 40 41 42', 'mois' => 4, 'role' => 'membre'],
        ['nom' => 'Boukinda', 'prenom' => 'Grace', 'tel' => '077 45 12 90', 'mois' => 1, 'role' => 'membre'],
    ];
    $sionUserIds = [];
    foreach ($sionMembers as $i => $m) {
        $sionUserIds[] = insert_user([
            'email' => 'membre.sion.' . ($i + 1) . '@labelleeglise.ga',
            'password' => password_hash('membre' . ($i + 1) . '123', PASSWORD_DEFAULT),
            'role' => $m['role'],
            'nom' => $m['nom'], 'prenom' => $m['prenom'],
            'telephone' => $m['tel'],
            'quartier' => 'Mingara',
            'compte_actif' => 1,
            'created_at' => months_ago_date($m['mois']),
        ]);
    }

    // Berger Eric Bongo (rôle leader) — membre du bacenta Sion
    $ericId = insert_user([
        'email' => 'berger.eric.bongo@labelleeglise.ga',
        'password' => password_hash('BergerEB1', PASSWORD_DEFAULT),
        'role' => 'leader',
        'nom' => 'Bongo', 'prenom' => 'Eric',
        'telephone' => '076 90 91 92',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => months_ago_date(3),
    ]);
    $josueId = insert_user([
        'email' => 'berger.josue@labelleeglise.ga',
        'password' => password_hash('bergerjosue123', PASSWORD_DEFAULT),
        'role' => 'leader',
        'nom' => 'Mabiala', 'prenom' => 'Josué',
        'telephone' => '076 55 66 77',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => months_ago_date(0),
    ]);

    // Responsable du Bacenta Sion
    $respSionId = insert_user([
        'email' => 'resp.bacenta.sion@labelleeglise.ga',
        'password' => password_hash('ESKLna', PASSWORD_DEFAULT),
        'role' => 'responsable',
        'nom' => 'Sion', 'prenom' => 'Responsable Bacenta',
        'telephone' => '060 00 00 01',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => months_ago_date(6),
    ]);

    // Membres "générale" / "nouveaux" (invités / reçus)
    $steveId = insert_user([
        'email' => 'membre.generale.1@labelleeglise.ga',
        'password' => password_hash('membreg123', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Obame', 'prenom' => 'Rachel',
        'telephone' => '074 80 81 82',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'invite_par' => $sionUserIds[0], // Estelle
        'recu_par' => $ericId,           // Akwaba Eric
        'date_recu' => months_ago_date(2),
        'created_at' => months_ago_date(2),
    ]);
    insert_user([
        'email' => 'nouveau.1@labelleeglise.ga',
        'password' => password_hash('nouveau123', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Koumba', 'prenom' => 'Steve',
        'telephone' => '060 70 71 72',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'invite_par' => $sionUserIds[0],
        'recu_par' => $ericId,
        'date_recu' => months_ago_date(3),
        'created_at' => months_ago_date(3),
    ]);
    insert_user([
        'email' => 'nouveau.2@labelleeglise.ga',
        'password' => password_hash('nouveau223', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Ibinga', 'prenom' => 'Merveille',
        'telephone' => '060 11 22 33',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'invite_par' => $sionUserIds[3], // Jean Axel
        'recu_par' => $ericId,
        'date_recu' => months_ago_date(1),
        'created_at' => months_ago_date(1),
    ]);

    /* ================= 3. BACENTAS ================= */

    $sionBacentaId = (int) qexec(
        "INSERT INTO bacentas (nom, responsable_id, centre_id) VALUES ('Sion', ?, ?)",
        [$respSionId, $centreIds['Mingara']]
    );
    $bethelBacentaId = (int) qexec(
        "INSERT INTO bacentas (nom, responsable_id, centre_id) VALUES ('Bethel', NULL, ?)",
        [$centreIds['Mbaya']]
    );

    // Rattache les membres au bacenta Sion
    foreach ($sionUserIds as $uid) {
        qexec('UPDATE users SET bacenta_id = ? WHERE id = ?', [$sionBacentaId, $uid]);
    }
    qexec('UPDATE users SET bacenta_id = ? WHERE id IN (?, ?, ?)', [$sionBacentaId, $ericId, $josueId, $respSionId]);

    /* ================= 4. BASONTAS ================= */

    $basontaIds = [];
    foreach (BASONTAS_DEFAULT as $nom) {
        $basontaIds[$nom] = (int) qexec('INSERT INTO basontas (nom) VALUES (?)', [$nom]);
    }
    // Chorale : Larissa Mihindou + Chriscie Mavoungou (créées ci-dessous)
    $larissaId = insert_user([
        'email' => 'basonta.chorale.1@labelleeglise.ga',
        'password' => password_hash('chorale123', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Mihindou', 'prenom' => 'Larissa',
        'telephone' => '062 60 61 62',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => months_ago_date(4),
    ]);
    $chriscieId = insert_user([
        'email' => 'basonta.chorale.2@labelleeglise.ga',
        'password' => password_hash('chorale223', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Mavoungou', 'prenom' => 'Chriscie',
        'telephone' => '062 33 44 55',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => months_ago_date(0),
    ]);
    qexec('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)', [$larissaId, $basontaIds['Chorale']]);
    qexec('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)', [$chriscieId, $basontaIds['Chorale']]);
    qexec('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)', [$ericId, $basontaIds['Akwaba']]);

    /* ================= 5. CULTES ================= */

    $culteIds = [];
    $sundays = [];
    $d = new DateTimeImmutable('last sunday');
    for ($i = 0; $i < 4; $i++) {
        $sundays[] = $d->modify("-$i weeks")->format('Y-m-d');
    }
    foreach (CULTES_DEFAULT as $i => $nom) {
        $culteIds[$nom] = (int) qexec(
            'INSERT INTO cultes (nom, date_culte, heure_debut, heure_fin, responsable_id) VALUES (?, ?, ?, ?, ?)',
            [$nom, $sundays[$i], '09:00:00', '11:30:00', $ericId]
        );
    }

    /* ================= 6. PRÉSENCES ================= */

    // Présence au Culte d'Impact (dernier dimanche) : Divine Moussavou + Patrick Ondo
    $divineId = insert_user([
        'email' => 'culte.impact.1@labelleeglise.ga',
        'password' => password_hash('cultimpact1', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Moussavou', 'prenom' => 'Divine',
        'telephone' => '065 50 51 52',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => months_ago_date(5),
    ]);
    $patrickId = insert_user([
        'email' => 'culte.impact.2@labelleeglise.ga',
        'password' => password_hash('cultimpact2', PASSWORD_DEFAULT),
        'role' => 'membre',
        'nom' => 'Ondo', 'prenom' => 'Patrick',
        'telephone' => '065 22 11 33',
        'quartier' => 'Mingara',
        'compte_actif' => 1,
        'created_at' => months_ago_date(2),
    ]);
    qexec('UPDATE users SET bacenta_id = ? WHERE id IN (?, ?)', [$sionBacentaId, $divineId, $patrickId]);

    $culteImpactId = $culteIds[CULTES_DEFAULT[0]];
    foreach ([$divineId, $patrickId, $ericId, $steveId] as $uid) {
        qexec('INSERT INTO presences (user_id, date_presence, culte_id, bacenta_id, centre_id) VALUES (?, ?, ?, ?, ?)',
              [$uid, $sundays[0], $culteImpactId, $sionBacentaId, $centreIds['Mingara']]);
    }
    // Présence bacenta (réunion hebdo) pour les membres de Sion
    $lastFriday = (new DateTimeImmutable('last friday'))->format('Y-m-d');
    foreach (array_slice($sionUserIds, 0, 4) as $uid) {
        qexec('INSERT INTO presences (user_id, date_presence, bacenta_id) VALUES (?, ?, ?)',
              [$uid, $lastFriday, $sionBacentaId]);
    }
    // Présence basonta (chorale)
    foreach ([$larissaId, $chriscieId] as $uid) {
        qexec('INSERT INTO presences (user_id, date_presence, basonta_id) VALUES (?, ?, ?)',
              [$uid, $lastFriday, $basontaIds['Chorale']]);
    }
    // Présence centre
    foreach ([$sionUserIds[0], $sionUserIds[1]] as $uid) {
        qexec('INSERT INTO presences (user_id, date_presence, centre_id) VALUES (?, ?, ?)',
              [$uid, $lastFriday, $centreIds['Mingara']]);
    }

    /* ================= 7. OFFRANDES ================= */

    $month = current_month_key();
    foreach ([[15000, 18000, 12000, 20000], [22000, 19000, 25000, 21000]] as $weekOffrandes) {
        // Bacenta Sion (vendredis)
        foreach ($weekOffrandes as $i => $m) {
            qexec('INSERT INTO offrandes (bacenta_id, montant, date_offrande, mois, jour_index) VALUES (?, ?, ?, ?, ?)',
                  [$sionBacentaId, $m, date('Y-m-d', strtotime("this week friday -" . (3 - $i) . " week")), $month, $i]);
        }
        // Centre Mbaya (mercredis)
        foreach ($weekOffrandes as $i => $m) {
            qexec('INSERT INTO offrandes (centre_id, montant, date_offrande, mois, jour_index) VALUES (?, ?, ?, ?, ?)',
                  [$centreIds['Mbaya'], $m, date('Y-m-d', strtotime("this week wednesday -" . (3 - $i) . " week")), $month, $i]);
        }
    }

    /* ================= 8. FICHE BERGER (Eric) ================= */

    $year = current_year();
    foreach ([[20000, 20000, 25000, 0, 0, 0, 0, 0, 0, 0, 0, 0]] as $dimesArr) {
        foreach ($dimesArr as $mi => $montant) {
            if ($montant > 0) {
                qexec('INSERT INTO dimes (user_id, annee, mois, montant) VALUES (?, ?, ?, ?)', [$ericId, $year, $mi + 1, $montant]);
            }
        }
    }
    qexec('INSERT INTO examens (user_id, nom, date_exam) VALUES (?, ?, ?)', [$ericId, 'École des Leaders — Niveau 1', months_ago_date(4)]);
    qexec('INSERT INTO veillees (user_id, date_veillee, present) VALUES (?, ?, 1)', [$ericId, months_ago_date(1)]);
    qexec('INSERT INTO veillees (user_id, date_veillee, present) VALUES (?, ?, 0)', [$ericId, months_ago_date(0)]);

    $weekKey = current_week_key();
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
                qexec('INSERT INTO suivi_hebdo (user_id, semaine, jour, champ, valeur) VALUES (?, ?, ?, ?, ?)',
                      [$ericId, $weekKey, $day, $champ, $valeur]);
            }
        }
    }

    // Visites du mois (bacenta Sion)
    $visites = [
        ['prenom' => 'Estelle', 'date' => date('Y-m-d', strtotime('-12 days')), 'obs' => 'Bien accueillie, à revisiter'],
        ['prenom' => 'Yannick', 'date' => date('Y-m-d', strtotime('-9 days')), 'obs' => ''],
        ['prenom' => '', 'date' => null, 'obs' => ''],
        ['prenom' => '', 'date' => null, 'obs' => ''],
    ];
    foreach ($visites as $i => $v) {
        qexec('INSERT INTO visites (visiteur_id, bacenta_id, nom_visite, date_visite, mois, semaine, observations) VALUES (?, ?, ?, ?, ?, ?, ?)',
              [$ericId, $sionBacentaId, $v['prenom'] !== '' ? $v['prenom'] : null, $v['date'] ?? date('Y-m-d'), $month, $i, $v['obs'] !== '' ? $v['obs'] : null]);
    }

    /* ================= 9. PRÉSENTATION ================= */

    qexec('INSERT INTO presentation (accroche, histoire) VALUES (?, ?)', [
        'Une famille de foi au cœur de Franceville, tournée vers l\'excellence et le service.',
        'Fondée avec la vision de bâtir une église vivante et accueillante, La Belle Église rassemble aujourd\'hui plusieurs centres, bacentas, basontas et cultes autour d\'un même objectif : faire grandir chacun dans la foi, la communion fraternelle et le service.',
    ]);

    $equipe = [
        ['nom' => 'Rév. Jean-Pierre Moussavou', 'role' => 'Révérend Principal', 'categorie' => 'Révérend', 'bio' => 'À la tête de l\'église depuis sa fondation, il porte la vision spirituelle de La Belle Église.', 'emoji' => '🙏'],
        ['nom' => 'Pasteure Grace Ondo', 'role' => 'Pasteure', 'categorie' => 'Pasteur', 'bio' => 'En charge de l\'accompagnement pastoral et du suivi des bacentas.', 'emoji' => '✝️'],
        ['nom' => 'Steve Koumba', 'role' => 'Leader Akwaba', 'categorie' => 'Leader', 'bio' => 'Responsable de l\'accueil et de l\'intégration des nouveaux membres.', 'emoji' => '🌟'],
    ];
    foreach ($equipe as $e) {
        qexec('INSERT INTO equipe (nom_affichage, role_affichage, bio, emoji, categorie) VALUES (?, ?, ?, ?, ?)',
              [$e['nom'], $e['role'], $e['bio'], $e['emoji'], $e['categorie']]);
    }

    qexec('INSERT INTO centres_presentation (centre_id, intro, vision, direction, origine, objectifs,
           situation_annees, situation_pasteurs, situation_bergers, situation_leaders, situation_bacentas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $centreIds['Mbaya'],
        'Rassemblement de chrétiens communiant chaque mercredi dans la prière, l\'adoration et la parole de Dieu au campus universitaire. Jeunesse talentueuse, zélée et passionnée.',
        'Sauver les âmes et former les saints en vue de l\'œuvre du Seigneur (Éphésiens 4:11-12).',
        'Dirigé par un Pasteur étudiant, le Pasteur Joas NZIENGUI, accompagné de plusieurs bergers et leaders actifs.',
        'Extension de La Belle Église Internationale Franceville, lancée par le Révérend Crowl MILAGUE.',
        json_encode(['Devenir une église First Love', 'Rassembler 150 personnes cette année',
                     'Implanter 10 bacentas sur le campus et 10 en extérieur', 'Développer de méga basontas'], JSON_UNESCAPED_UNICODE),
        4, 1, 3, 7, 10,
    ]);
}
