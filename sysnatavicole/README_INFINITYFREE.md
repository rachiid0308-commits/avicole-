# Déploiement sur InfinityFree

Le paquet `cartographie-avicole-modernise.zip` est prêt pour le webroot `htdocs`.

L’interface reprend les tons vert forêt, sauge et or de `national-2.html`, avec navigation latérale sur ordinateur et barre mobile. Le registre et ses filtres utilisent toujours les données de l’application et ses contrôles d’accès par profil; aucun jeu de démonstration du fichier de référence n’est repris.

## Installation en 4 étapes

1. Créez votre site InfinityFree et une base depuis **MySQL Databases**. Notez le hostname, le nom de la base, l’utilisateur et le mot de passe. Le hostname doit être celui du panneau, **pas `localhost`**.
2. Décompressez le ZIP et téléversez **le contenu** du dossier `htdocs/` dans le dossier `htdocs` de votre site. Vérifiez que le fichier caché `.htaccess` est présent.
3. Activez le SSL InfinityFree, puis ouvrez `https://votre-site/install.php`. Entrez les informations MySQL; l’adresse du premier administrateur est préremplie à `basta@exemple.com`, et vous définissez puis confirmez vous-même son mot de passe.
4. Connectez-vous : l’assistant aura créé les tables, les 12 régions, les 75 provinces/préfectures et le compte administrateur. Le mot de passe est haché et l’assistant se verrouille dès qu’un compte existe.

L’assistant crée `config/local.php` et le protège par `.htaccess`. Le port MySQL vaut `3306` par défaut ; s’il diffère, renseignez-le dans le champ **Port** (ou ajoutez `'port' => …` dans `config/local.php`). Si le serveur ne peut pas écrire ce fichier, copiez `config/local.example.php` sous `config/local.php`, remplissez les identifiants, puis rechargez la page. Si la création automatique du schéma échoue, importez `database/schema.sql` via phpMyAdmin puis réessayez. Activez le SSL et vérifiez que le site s’ouvre en HTTPS.

## Créer les utilisateurs

Après la première connexion, ouvrez **Gérer les comptes**. Créez-y les comptes centraux, régionaux et provinciaux, avec leur affectation. Saisissez un titre professionnel facultatif, une adresse professionnelle se terminant par `@onssa.gov.ma` et un numéro mobile de 10 chiffres. Le formulaire empêche les doublons de région/province et respecte les plafonds de 4, 12 et 75 comptes. Choisissez un mot de passe initial d’au moins 12 caractères et transmettez-le à la personne par un canal séparé.

Le tableau **Comptes enregistrés** permet, pour chaque compte, de le **désactiver** (connexion bloquée immédiatement) ou de le réactiver, de **réinitialiser son mot de passe** et de **modifier** son titre professionnel, son nom, son e-mail, son numéro mobile, son profil et son affectation. Le dernier directeur central actif ne peut pas être désactivé et chacun ne peut pas désactiver son propre compte.

Seul l’**administrateur** importe et exporte les données (CSV ou Excel). Les profils central et régional consultent l’ensemble du territoire en lecture seule ; le profil **provincial** (chef de service) ajoute les unités une par une. La session expire après cinq minutes sans activité.

## Importer les fermes de l’ancienne page

1. Dans l’ancien HTML, exporter le CSV depuis le navigateur où se trouvent les données.
2. Contrôler le CSV avant l’import : la page d’origine génère des exemples aléatoires par défaut. L’importeur ignore les noms commençant par « Ferme démo » ou « Unité » et les CNIE `DEMO-`, mais il faut confirmer que le reste correspond bien à des données réelles.
3. Se connecter avec le compte **administrateur**, ouvrir **Importer/Exporter**, choisir le jeu **Unités avicoles**, éventuellement la région ou la province cible, sélectionner le fichier puis lancer l’import. Formats acceptés : CSV (séparateur `;` ou `,`, 10 Mo et 5 000 lignes maximum) ou classeur Excel `.xlsx`. Téléchargez le **modèle unités** depuis cette page si nécessaire ; un fichier identique (même empreinte SHA-256) ne peut pas être réimporté.
4. L’import est transactionnel : une ligne invalide annule tout le lot. Il accepte le modèle à 14 colonnes (avec en-tête) ainsi que l’ancien export sans en-tête. Les encodages UTF-8 et Windows-1252 ainsi que les dates `AAAA-MM-JJ` et `JJ/MM/AAAA` sont pris en charge. Les lignes de démonstration et les doublons sont ignorés.

## Carte, GPS et distances

Les pages **Carte**, **GPS**, **Statistiques** et **Distances** sont accessibles depuis le tableau de bord. Les cartes proposent les modes **Plan**, **Hybride** et **Satellite** de Google Maps lorsqu’une clé Google Maps JavaScript API est configurée. Sans clé, le site conserve les fonds de secours OpenStreetMap (plan) et Esri (satellite); le mode hybride est alors indisponible. Pour Google Maps, copiez `htdocs/config/maps.example.php` sous `maps.local.php` dans le dossier `config`, activez la facturation et l’API Google Maps JavaScript, renseignez une clé navigateur et limitez-la au domaine du site (HTTP referrer) ainsi qu’à Maps JavaScript API. Ne publiez jamais une clé non restreinte. La page GPS requiert HTTPS pour la géolocalisation.

La page **Statistiques** respecte la portée du profil et affiche en tableaux les répartitions géographiques propres à cette portée (régions et provinces pour les profils à portée nationale), par type et par statut; chaque tableau est accompagné à droite de son graphique à secteurs. Les listes des unités, des comptes et des rapports (unités et véhicules) affichent 20 lignes par page avec navigation précédente/suivante.

Pour un jeu de démonstration dans une base de test initialisée, depuis la racine du projet, exécutez `php bin/seed_demo_6000.php --confirm-demo`. Le script ajoute exactement 6 000 fermes fictives, distribuées au hasard dans les 75 provinces/préfectures et les 12 régions, en garantissant au moins une ferme par province; il crée aussi les profils DEMO libres : un central, un régional par région et un provincial par province lorsque ces affectations sont disponibles. Les coordonnées sont tirées à l’intérieur des polygones ADM2 fournis sous `data/`; les fermes, coordonnées, propriétaires et identifiants sont fictifs et ne correspondent pas à des exploitations réelles. Le script n’efface aucune donnée et refuse un second chargement dès qu’il trouve des fermes `DEMO TEST`. Les mots de passe uniques sont hachés avec PHP et enregistrés dans un CSV privé hors de `htdocs` (par défaut `bin/demo-credentials-<date>.csv`); protégez ou supprimez ce fichier après usage. Un chemin de sortie privé peut être ajouté à la commande. N’exécutez jamais ce script sur la base de production. Voir `data/README-demo-boundaries.md` pour la source et la licence des limites administratives. Pour une base de démonstration neuve, lancez d’abord `install.php` (les 12 régions et 75 provinces sont créées), puis exécutez le script; connectez-vous ensuite par `login.php` avec `demo.central@example.test` et le mot de passe du CSV privé. N’exécutez jamais ce script sur une base de production.

Les distances calculées à vol d’oiseau servent d’aide au contrôle; vérifiez les coordonnées et la réglementation avant toute décision. La page des distances compare aussi les unités proches des limites provinciales/préfectorales afin de détecter les écarts transfrontaliers; pour un profil provincial, seules les unités de son périmètre sont nommées, et le cercle rouge animé est réservé au Chef de service.

## Formulaires de contrôle et rapports Word

Le menu **Contrôles** propose les fiches Chair, Reproducteurs, Pondeuses et Transport. Seul le profil **Chef de service** crée, renseigne, modifie ou supprime les fiches. Les directions **centrale** et **régionale** consultent et impriment uniquement les rapports de leur périmètre.

Pour un contrôle d’unité, la région, la province, le type et l’unité autorisée sont sélectionnés dans la base, puis les réponses Oui/Non et les observations sont conservées critère par critère. Le formulaire **Transport** est indépendant des unités avicoles et de leurs autorisations : il accepte un nouveau propriétaire saisi librement et un N° d’autorisation facultatif. Il demande les renseignements du véhicule, une immatriculation au format `12345-A-01` (5 chiffres, lettre majuscule parmi A, B, D, H, E, T, Y, K, L, M ou N, puis code provincial), et le produit transporté : poussins, œufs à couver ou volailles vivantes. La fiche affiche toutes les sections de la checklist, à l’écran comme sur le rapport imprimé; seules les sections du produit sélectionné sont actives, les autres sont grisées et leurs réponses ne sont pas enregistrées. À l’impression, la checklist complète est isolée sur une page A4 paysage compacte. Le rapport propose l’impression (ou l’enregistrement PDF depuis le navigateur) et un téléchargement Word. À la fermeture de la fenêtre d’impression, l’application revient automatiquement à la page **Accueil**. Les cellules de la checklist s’ajustent au texte.

Pour une installation neuve, les champs des comptes et tables de contrôle sont inclus dans `htdocs/database/schema.sql`. Pour les tables de contrôle sur un site existant, importez une fois `htdocs/database/controls.sql` via phpMyAdmin. Pour les nouveaux champs des comptes, importez aussi `htdocs/database/account_contact_fields.sql` (idempotent) après avoir sélectionné la base concernée; cette migration ajoute le téléphone et le titre professionnel sans effacer les comptes existants. Les rapports sont filtrés côté serveur par la région/province autorisée au profil connecté. Ouvrez un rapport puis choisissez **Télécharger le rapport Word** pour obtenir un document `.doc` éditable/ouvrable dans Microsoft Word, ou **Imprimer le rapport** depuis le navigateur.

## Données sensibles et exploitation

Les champs propriétaire et CNIE sont des données personnelles. Avant d’héberger le registre réel sur une offre gratuite tierce, obtenir l’accord de l’organisme responsable et valider les exigences juridiques, de confidentialité, de sauvegarde et de contrôle d’accès applicables. Ne pas tester avec des CNIE réels. Conserver des sauvegardes SQL régulières hors du compte d’hébergement et vérifier périodiquement les accès.

## Sources InfinityFree

- [Connexion MySQL sur InfinityFree](https://forum.infinityfree.com/t/how-to-connect-with-mysql/49340) — hostname exact, PDO/MySQLi, absence de connexion distante.
- [Installation Laravel et webroot `htdocs`](https://forum.infinityfree.com/t/how-to-install-a-laravel-site-on-infinityfree/118578) — webroot fixe et contraintes d’accès aux fichiers hors `htdocs` (le projet évite de dépendre d’un accès hors webroot).
