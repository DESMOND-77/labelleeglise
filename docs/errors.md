# Spécification de correction et plan d'implémentation

## Objectif

Corriger les six anomalies signalées dans les modules Sections, Rapport du
jour, Classes, Budget Bus, Accès et Profil, en conservant les URLs existantes,
les contrôles RBAC et le modèle sans dépendances externes du projet.

## Principes transverses

- Toute règle affichée dans l'interface doit être revalidée dans le service ou
	le repository côté serveur.
- Les opérations qui modifient un cumul financier et le rapport source doivent
	être atomiques, idempotentes lors d'une modification, et ne doivent pas
	compter deux fois une même saisie.
- Les changements d'onglets doivent préserver les paramètres utiles (`id`,
	`tab`, date, mois et filtre) et ne pas supprimer les formulaires existants.
- Chaque correction doit être vérifiée par `php -l` sur les fichiers modifiés,
	puis par un script d'assertions ou une marche manuelle du parcours concerné.

## 1. Bacentas / Basontas : conserver le tab-row

### Constat et cause probable

Les branches `presences` et `suivi` de `render_bacenta_detail()` retournent
directement leur contenu avant de construire le `tab_row`. La même structure
est à vérifier et à factoriser pour les basontas. Le problème est donc un
problème de composition du rendu, pas de navigation.

### Correction attendue

- Construire un seul jeu d'onglets pour chaque détail d'unité : `Membres`,
	`Présences` et `Suivi & Offrandes` pour un bacenta ; les onglets pertinents
	pour un basonta.
- Rendre ce `tab_row` dans toutes les branches, y compris `presences`,
	`presences_annuel` et `suivi`.
- Conserver l'onglet actif et les paramètres de contexte dans les URLs.
- Ne pas afficher un onglet d'offrandes pour une unité qui ne possède pas ce
	suivi métier.

### Fichiers probables

- `app/Compat/sections.php`
- `Views/pages/presence_occurrence.php`
- `Views/pages/presence_matrix.php`
- tests smoke existants dans `tmp/` et un nouveau cas ciblé si nécessaire.

### Critères d'acceptation

- Depuis un bacenta, le tab-row reste visible après ouverture de `Présences`,
	de la matrice annuelle et de `Suivi & Offrandes`.
- Depuis un basonta, le tab-row reste visible après ouverture de `Présences`.
- Le retour à `Membres` conserve le bon `id` et le contenu existant.

## 2. Rapport du jour : parcours progressif et offrandes

### Constat et cause probable

`Views/pages/rapport_form.php` affiche le formulaire complet dès qu'un centre
est sélectionné. `RapportJourService::save()` valide les nombres mais ne
refuse pas une date future. `derivedNames()` prévoit déjà un responsable de
bacenta, et `ContributionRepository` possède un registre mensuel d'offrandes,
mais aucun lien transactionnel avec l'enregistrement du rapport n'est défini.

### Parcours fonctionnel

1. État initial : afficher uniquement les sélecteurs Centre et Date.
2. Après sélection d'un centre et d'une date comprise entre une date minimale
	 métier et aujourd'hui : afficher le responsable du centre et le sélecteur de
	 bacenta autorisé pour l'utilisateur.
3. Tant qu'aucun bacenta n'est choisi : ne pas afficher le responsable du
	 bacenta ni les champs détaillés du rapport.
4. Après sélection d'un bacenta : afficher son responsable et le reste du
	 formulaire, dont le montant d'offrande.
5. Autoriser la soumission uniquement lorsque le centre, la date, le bacenta
	 requis par le parcours et les champs obligatoires sont valides.

La date future doit être refusée côté serveur et côté interface (`max` égal à
la date du jour). Un rapport existant doit permettre de recharger son état,
sans contourner les autorisations ni afficher de données d'un autre centre.

### Règle financière à préciser puis implémenter

Le montant d'offrande saisi dans le rapport doit alimenter la caisse du
bacenta sélectionné. La correction doit choisir explicitement l'une des deux
stratégies suivantes avant codage :

- faire du rapport la source unique et remplacer la ligne d'offrande de la
	même date / période lors d'un upsert ; ou
- créer une écriture financière liée au rapport par un identifiant stable,
	avec compensation de l'ancien montant lors d'une modification.

Dans les deux cas, une modification de rapport ne doit pas ajouter l'ancien
montant une seconde fois, un rapport sans bacenta ne doit pas alimenter une
caisse de bacenta, et la suppression éventuelle doit définir sa règle de
contrepassation. Cette décision est un prérequis fonctionnel, car le registre
actuel `offrandes` est mensuel et indexé par `jour_index`.

### Fichiers probables

- `Views/pages/rapport_form.php`
- `app/Services/RapportJourService.php`
- `app/Repositories/RapportJourRepository.php`
- `app/Repositories/ContributionRepository.php` et/ou un repository de caisse
- migration si une clé de liaison ou une nouvelle écriture est nécessaire.

### Critères d'acceptation

- Une date future est refusée avec une erreur explicite, même via POST direct.
- Le responsable du bacenta est vide et invisible sans bacenta sélectionné.
- Un utilisateur ne peut pas soumettre le bacenta d'un autre périmètre.
- La création puis la modification d'un rapport donnent exactement le cumul
	attendu dans la caisse, sans double comptage.

## 3. Classe : élèves actifs, anciens élèves et évaluations

### Constat

`classe_detail.php` affiche actuellement tous les inscrits dans un tableau
unique. Le modèle possède déjà `statut`, `exam_oral`, `exam_ecrit`,
`exam_note` et `exam_date`, mais une seule note et une seule date sont
partagées par les deux examens.

### Correction attendue

- Ajouter un `tab_row` avec `Élèves` et `Anciens élèves`.
- L'onglet `Élèves` affiche les inscrits dont le statut est actif/en cours.
- L'onglet `Anciens élèves` affiche les inscrits terminés, sans les mélanger
	au formulaire d'inscription courant.
- Remplacer la saisie ambiguë de la note/date par un formulaire d'évaluation
	par élève contenant, séparément pour l'examen écrit et l'examen oral :
	statut, note et date.
- Valider les notes dans une plage définie par le métier, les dates au format
	ISO, et empêcher une date future si les examens ne peuvent pas être futurs.
- Conserver la promotion automatique uniquement lorsque les deux examens sont
	réussis, dans la même transaction que la sauvegarde.

### Fichiers probables

- `Views/pages/classe_detail.php`
- `app/Services/ClasseService.php`
- `app/Repositories/ClasseRepository.php`
- migration de `classe_inscrits` pour les colonnes séparées, si le schéma ne
	les contient pas déjà.

### Critères d'acceptation

- Chaque onglet ne montre que sa population et conserve son état après POST.
- Une note et une date d'écrit peuvent différer de celles de l'oral.
- Une promotion ne se produit pas avec un seul examen réussi.
- Un élève terminé apparaît dans `Anciens élèves` et plus dans `Élèves`.

## 4. Budget Bus : solde disponible par centre

### Constat et cause probable

Le formulaire `budget_bus.php` ne montre aucun solde au changement de centre.
`BusBudgetService::save()` refuse les montants négatifs mais ne compare pas le
montant au solde disponible. Le solde doit être calculé à partir d'une source
de caisse explicitement choisie, et non seulement à partir du total des
mouvements bus.

### Correction attendue

- À la sélection d'un centre, afficher son solde courant et le montant
	maximal retirable.
- Recalculer ce solde côté serveur au moment de créer ou modifier un mouvement.
- Refuser zéro négatif et tout montant strictement supérieur au solde
	disponible ; lors d'une modification, réintégrer d'abord le montant de la
	ligne éditée dans le solde disponible.
- Revalider que le centre est dans le périmètre autorisé de l'utilisateur.
- Prévoir un état explicite si la caisse du centre est inconnue ou indisponible.

### Fichiers probables

- `Views/pages/budget_bus.php`
- `app/Services/BusBudgetService.php`
- `app/Repositories/BusBudgetRepository.php` et repository de caisse
- `app/Controllers/ActionsController.php`
- éventuellement un endpoint GET interne pour le solde, protégé par RBAC.

### Critères d'acceptation

- Le solde s'actualise lorsqu'un centre est choisi.
- Un POST direct avec un montant supérieur au solde est rejeté.
- Une modification ne pénalise pas deux fois le solde de son ancienne ligne.
- Une suppression ou une annulation restitue le montant selon la règle de
	caisse retenue.

## 5. Attribution des accès : responsables de classes

### Constat

`ResponsibilityService` supporte les cibles `center`, `bacenta`, `cult` et
`basonta`. La page des accès rend ces quatre sections, mais aucune cible
`classe` n'est déclarée.

### Correction attendue

- Ajouter `classe` aux types de cibles connus et à la table autorisée.
- Définir les rôles éligibles aux responsabilités de classe et les constantes
	d'autorisation associées.
- Ajouter la section `Responsables de classes` dans `parametres_acces()`.
- Réutiliser la validation serveur existante : utilisateur existant, rôle
	éligible, classe existante, absence de doublon et révocation possible.
- Ajouter la responsabilité de classe au périmètre effectivement consommé par
	les pages et actions qui doivent être limitées aux responsables.

### Critères d'acceptation

- Un administrateur peut affecter et retirer un responsable pour chaque classe.
- Un rôle non éligible ne peut pas être affecté, même en POST forgé.
- La responsabilité apparaît dans la fiche du membre et ne modifie pas son
	rôle applicatif.

## 6. Retour Profil depuis Paramètres

### Constat et cause probable

La liste des comptes de `parametres_comptes()` pointe vers
`personProfile`, mais `Views/pages/profile.php` utilise toujours un bouton
retour vers la recherche globale. Le contexte d'origine n'est pas transporté.

### Correction attendue

- Ajouter un paramètre d'origine explicite et whitelisté, par exemple
	`return=parametres` et `param_tab=comptes`.
- Générer depuis Paramètres un lien vers le profil avec ce contexte.
- Dans le profil, retourner vers Paramètres si le contexte est valide ; sinon
	conserver le retour actuel vers la recherche.
- Ne jamais accepter une URL de redirection arbitraire fournie par le client.

### Critères d'acceptation

- Paramètres > Comptes > fiche membre > Retour revient à Paramètres, onglet
	Comptes.
- Un accès direct au profil sans contexte conserve un retour fonctionnel.
- Un paramètre de retour externe ou inconnu est ignoré.

## Plan d'exécution proposé

### Phase 0 - Décisions et garde-fous

1. Confirmer la source de vérité de la caisse d'offrandes et la règle de
	 contrepassation lors d'une modification ou suppression.
2. Confirmer la plage des notes et la date autorisée pour les examens.
3. Confirmer les rôles autorisés à être responsables d'une classe.
4. Écrire les scénarios de non-régression dans `tmp/` avant les changements.

### Phase 1 - Corrections sans migration

1. Corriger le rendu commun des tab-rows Bacentas/Basontas.
2. Ajouter la validation de date future au rapport et son affichage progressif.
3. Ajouter le contexte de retour Paramètres > Profil.
4. Ajouter le solde affiché et la validation Bus si la source de caisse existe
	 déjà et est interrogeable sans changement de schéma.

### Phase 2 - Modèle métier et migrations

1. Étendre `classe_inscrits` pour stocker note/date par examen si nécessaire.
2. Ajouter la cible `classe` aux responsabilités et à ses règles RBAC.
3. Ajouter la liaison idempotente entre rapport du jour et écriture
 d'offrande si le modèle existant ne permet pas de garantir l'absence de
 double comptage.

### Phase 3 - Finalisation et vérification

1. Implémenter les services et repositories avec transactions et contrôles
	 serveur.
2. Mettre à jour les vues et conserver les paramètres de navigation.
3. Exécuter `php -l` sur chaque fichier PHP modifié.
4. Exécuter les scripts smoke ciblés : onglets, rapport, classes, budget,
	 responsabilités et retour profil.
5. Effectuer une marche manuelle des six parcours avec un compte admin et un
	 compte à périmètre restreint.
6. Vérifier la migration sur une base existante et sur une base vierge.

## Risques et questions bloquantes

- Le mot « caisse » peut désigner une table de caisse absente du modèle actuel
	ou le registre `offrandes`; il faut trancher avant toute écriture financière.
- Les colonnes actuelles `exam_note` et `exam_date` ne permettent pas deux
	évaluations indépendantes sans migration ou table dédiée.
- Ajouter une responsabilité `classe` ne suffit pas à restreindre une page :
	chaque action métier concernée doit consommer ce nouveau périmètre.
- Le solde Bus doit être défini comme recettes moins retraits, avec une règle
	claire pour les montants historiques et les modifications.
