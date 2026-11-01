# Tutoriel — Photos des véhicules dans les rapports de transport

## Ce que contient cette version

- Quatre vues facultatives : **face avant**, **face arrière**, **côté droit** et **côté gauche**.
- Sur smartphone, le champ propose la prise de photo avec l’appareil photo arrière; sur ordinateur, il permet de choisir une image.
- Les photos sont prévisualisées puis enregistrées **dans la base de données** du rapport, pas dans un dossier public du serveur.
- Formats acceptés : JPEG, PNG et WebP. Limites : 2 Mo par image et 6 Mo au total. Les images volumineuses sont réduites automatiquement par le navigateur avant l’envoi lorsqu’il le permet.
- La galerie et les images suivent les droits d’accès et le périmètre géographique de l’utilisateur.
- **reCAPTCHA est désactivé sur la page de connexion** : aucun défi Google ni appel de vérification n’est effectué. La protection CSRF et la vérification du mot de passe restent actives.

## 1. Installer la mise à jour

### Site déjà installé

1. Faites une sauvegarde de la base de données et des fichiers actuels.
2. Déployez les fichiers de cette archive sur le site.
3. **Conservez votre configuration locale** (`htdocs/config/local.php`) : elle contient les paramètres propres à votre hébergement et n’est volontairement pas fournie dans l’archive.
4. Dans phpMyAdmin, sélectionnez la base du site, ouvrez l’onglet **Importer**, puis importez `htdocs/database/controls.sql`.
5. Rechargez la page **Contrôles**.

Le script `controls.sql` est idempotent : les tables déjà présentes ne sont pas recréées ni vidées; la table de stockage des photos est ajoutée si elle manque.

**Contenu de l’archive :** le projet complet prêt à être configuré, les exemples de configuration et ce tutoriel. Le fichier privé `htdocs/config/local.php` est volontairement absent : conservez celui de votre hébergement et ne le remplacez pas. Le jeu de démonstration SQL synthétique optionnel `database/schema_demo_6000.sql` est également omis; il ne contient pas les données de production et n’est pas nécessaire au site.

### Nouvelle installation

Utilisez le schéma complet `htdocs/database/schema.sql` dans la procédure d’installation. Il contient aussi la table des photos.

## 2. Ajouter des photos à un contrôle transport

1. Connectez-vous avec un profil **Chef de service** autorisé à gérer les contrôles.
2. Ouvrez **Contrôles**, puis créez un contrôle de type **Transport** ou choisissez **Ajouter** dans la colonne **Photos du véhicule** d’un rapport existant.
3. Remplissez la fiche et faites défiler jusqu’à **Photos facultatives du véhicule**, juste avant le bouton d’enregistrement.
4. Pour chaque angle, touchez/cliquez sur le champ correspondant :
   - sur smartphone, choisissez **Prendre une photo** dans le sélecteur du téléphone;
   - sur ordinateur, choisissez le fichier image voulu.
5. Vérifiez l’aperçu. Les quatre photos ne sont pas obligatoires.
6. Cliquez sur **Enregistrer et générer le rapport** ou **Enregistrer les modifications**. Le rapport et les photos sélectionnées sont enregistrés ensemble.

Si vous modifiez un rapport, une vue laissée vide conserve la photo déjà enregistrée. Sélectionner une nouvelle image remplace seulement la vue correspondante.

## 3. Visualiser les photos

Dans **Rapports de contrôle des transports**, la colonne tout à droite **Photos du véhicule** contient le bouton **Visualiser**. La galerie présente les quatre angles disponibles et signale les vues qui n’ont pas encore de photo. Le bouton **Ajouter** ouvre la fiche à la section des photos.

Les photos sont consultables par les utilisateurs authentifiés autorisés à voir le rapport. Un utilisateur d’une autre province ne peut pas récupérer les images.

## 4. Limites et dépannage

- Si le site indique que le stockage des photos n’est pas installé, importez `htdocs/database/controls.sql`, puis rechargez la page.
- Si l’envoi est rejeté comme trop volumineux, vérifiez la configuration PHP de l’hébergement : `file_uploads=On`, `upload_max_filesize` d’au moins `2M`, `post_max_size` d’au moins `8M`, `max_file_uploads` d’au moins `4`, et l’extension `fileinfo` activée.
- Choisissez des images JPEG, PNG ou WebP. Sur un navigateur qui ne peut pas réduire automatiquement une grande image, utilisez une image de moins de 1,3 Mo.
- Les photos sont affichées dans la galerie dédiée; elles ne sont pas ajoutées à la mise en page imprimable A4 du rapport.

## 5. Vérifications effectuées sur cette version

Les tests PHP du projet couvrent l’envoi réel d’une image via formulaire multipart, sa validation, son stockage en base, son affichage et le refus d’accès hors périmètre. Les tests de contrôle et les autres tests du projet ont réussi sur la copie préparée.
