# SP-5 - Statistiques de présence honnêtes - Design

- **Date** : 2026-09-03
- **Source** : `prompts/AUDIT…AGENDA & PRÉSENCES.md` (Objectif B, §10, §31-32, §66, §74) + Q/R
- **Statut** : à valider
- **Dépend de** : SP-2 (les statuts `absent`/`excuse` doivent être réellement saisis pour que la formule ait du sens). Consomme l'historique enrichi de SP-4 (`statut`).

## 1. Objectif

Remplacer le taux de présence individuel actuel - `présences ÷ dates de culte distinctes enregistrées` (dénominateur biaisé : ignore les statuts `absent`/`excuse`, ne compte que les cultes, compte toutes les lignes comme des « présences ») - par une **formule documentée et honnête** :

> **Taux = présences ÷ (présents + absents + excusés)**

Le dénominateur = nombre d'occurrences où le membre a été **explicitement pointé**, quel que soit le statut. Les occurrences « non renseignées » (aucune ligne) sont **exclues** : un oubli de pointage du responsable ne pénalise pas le membre. « Total de présences » redevient le nombre de lignes `statut='present'` (et non le nombre total de lignes).

## 2. État actuel (vérifié)

| Élément | Détail |
|---|---|
| `AttendanceService::statsForUser(int $userId, ?string $from = null, ?string $to = null): array` | renvoie `['total' => countForUser (COUNT(*) - toutes lignes), 'last_date' => mostRecentDateForUser, 'rate' => round(min(100, total/denominator*100)), 'rate_denominator_note' => 'présences ÷ dates de culte distinctes…']` où `denominator = distinctCulteDatesInRange($from, $to)` |
| `AttendanceRepository` | `countForUser` (`SELECT COUNT(*) FROM presences WHERE user_id=?`), `mostRecentDateForUser` (`MAX(date_presence)`), `distinctCulteDatesInRange` (`COUNT(DISTINCT date_presence) WHERE culte_id IS NOT NULL …`) |
| Appelant unique | `app/Compat/profile.php:113` - `$stats = attendance_service()->statsForUser($membreId)` (fiche membre) |
| `distinctCulteDatesInRange` | appelé **uniquement** par `statsForUser` |
| `countForUser` | appelé **uniquement** par `statsForUser` |
| `countDistinctForCultes` | appelé par `StatisticsService::countMembers` - **non concerné** |
| SP-4 | ajoute un bloc « Présences récentes » sur la fiche consommant `total` / `last_date` / `rate` / `rate_denominator_note` |

## 3. Décisions de cadrage

1. **Formule retenue** : `rate = present / (present + absent + excuse)` (arrondi entier). `null` si `(present + absent + excuse) === 0`. Documentée dans la clé `formula` (chaîne lisible) **et** dans un docbloc de `statsForUser`.
2. **`total`** = nombre de lignes `statut='present'` (présences réelles), **pas** `COUNT(*)`. Rétro-compatible : la clé `total` existe toujours, sa valeur change de sens (désormais correcte pour un libellé « Total de présences »).
3. **Nouvelles clés** dans le retour : `present`, `absent`, `excuse`, `pointed` (= somme des trois). `rate_denominator_note` **conservée** (alias rétro-compat, texte mis à jour).
4. **Période** : `$from` / `$to` restent des filtres optionnels sur `date_presence` (déjà dans la signature). Pas de notion de « période d'appartenance » : décision assumée - on ne calcule pas un dénominateur d'« occurrences éligibles », trop coûteux et fragile sans date d'entrée fiable (`date_recu` souvent vide). Documenté comme **limite connue**.
5. **1 requête** : `SELECT statut, COUNT(*) c FROM presences WHERE user_id = ? [AND date_presence >= ?] [AND date_presence <= ?] GROUP BY statut` via un nouveau `AttendanceRepository::statusCountsForUser()`. `mostRecentDateForUser` reste une 2ᵉ requête légère.
6. **Nettoyage** : `distinctCulteDatesInRange` et `countForUser` deviennent inutilisés → `/** @deprecated */` (conservés, pas supprimés).
7. **Fiche** : SP-5 ajoute une ligne de ventilation « N présents · M absents · K excusés » au bloc « Présences récentes » de SP-4 (si SP-4 est livré ; sinon la ligne est ajoutée avec le bloc). Le taux affiche `formula` en info-bulle / légende.
8. **Aucune migration.**

## 4. Architecture

- **`AttendanceRepository::statusCountsForUser(int $userId, ?string $from = null, ?string $to = null): array`** *(nouveau)* → `['present'=>int, 'absent'=>int, 'excuse'=>int]` (clés absentes → 0). Bornes `date_presence >= ? / <= ?` optionnelles.
- **`AttendanceService::statsForUser(int $userId, ?string $from = null, ?string $to = null): array`** *(réécrit)* :
  ```php
  $c = $this->attendance->statusCountsForUser($userId, $from, $to);
  $present = (int) ($c['present'] ?? 0);
  $absent  = (int) ($c['absent'] ?? 0);
  $excuse  = (int) ($c['excuse'] ?? 0);
  $pointed = $present + $absent + $excuse;
  $rate    = $pointed > 0 ? (int) round($present / $pointed * 100) : null;
  return [
      'total'    => $present,          // présences réelles (clé conservée)
      'present'  => $present,
      'absent'   => $absent,
      'excuse'   => $excuse,
      'pointed'  => $pointed,
      'last_date'=> $this->attendance->mostRecentDateForUser($userId),
      'rate'     => $rate,             // null si aucune occurrence pointée
      'formula'  => 'Taux = présences ÷ (présents + absents + excusés). Occurrences non renseignées exclues du dénominateur.',
      'rate_denominator_note' => 'présents ÷ (présents + absents + excusés)',
  ];
  ```
  Docbloc au-dessus de la méthode : rappel de la formule + limite (« ne tient pas compte des occurrences auxquelles le membre était éligible mais non pointé »).
- **`AttendanceRepository::distinctCulteDatesInRange` / `countForUser`** : `/** @deprecated Plus utilisé par statsForUser (SP-5). */`.
- **Fiche** (`app/Compat/sections.php` `member_recent_presence_html` de SP-4, ou `render_profile_page`) : ajouter `'<div class="stat-label">' . (int)$stats['present'] . ' présents · ' . (int)$stats['absent'] . ' absents · ' . (int)$stats['excuse'] . ' excusés</div>'` et afficher `formula` sous le taux.

## 5. Tests

- `statusCountsForUser` : membre avec 6 lignes (4 present, 1 absent, 1 excuse) → `['present'=>4,'absent'=>1,'excuse'=>1]` ; sans ligne → `['present'=>0,'absent'=>0,'excuse'=>0]` ; bornes `from/to` filtrent.
- `statsForUser` : mêmes données → `total=4`, `present=4`, `absent=1`, `excuse=1`, `pointed=6`, `rate=67` (`round(4/6*100)`), `formula` présente, `rate_denominator_note` présente.
- `statsForUser` sans aucune présence → `rate=null`, `total=0`, `pointed=0` (pas de division par zéro).
- Rétro-compat : `profile.php` (`$stats['total']`, `$stats['rate']`, `$stats['rate_denominator_note']`, `$stats['last_date']`) ne casse pas ; smoke render de la fiche.
- `grep` : `distinctCulteDatesInRange` / `countForUser` marqués `@deprecated`, aucun appel restant dans `statsForUser`.
- Non-régression : `StatisticsService::countMembers` (`countDistinctForCultes`) intact ; SP-4 bloc « Présences récentes » affiche les bonnes valeurs.

## 6. Hors périmètre

- Dénominateur « occurrences éligibles / période d'appartenance » (limite documentée, évolution possible).
- Statistiques agrégées par unité / par mois (audit §31 « occurrences prévues / pointées ») - reste à traiter dans un SP ultérieur si besoin.
- Graphiques / dashboards de présence.

## 7. Livrables

- Spec : ce document. Plan : `docs/superpowers/plans/2026-09-03-sp5-statistiques-honnetes.md`.
