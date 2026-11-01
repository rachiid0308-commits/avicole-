# Avicole

Application web PHP de suivi et de traçabilité des unités avicoles au Maroc.

## Prérequis

- PHP 8.2+
- MySQL/MariaDB
- Serveur web Apache ou Nginx

## Installation

1. Clonez le dépôt.
2. Copiez le fichier de configuration exemple :
   ```bash
   cp config/local.example.php config/local.php
   cp config/maps.example.php config/maps.local.php
   ```
3. Renseignez les paramètres de connexion MySQL et la clé Google Maps.
4. Importez le schéma SQL depuis `database/schema.sql` (et les scripts additionnels si nécessaire).
5. Démarrez le projet sur votre environnement local.

## Fichiers de configuration

- `config/local.php` : connexion à la base de données locale
- `config/maps.local.php` : clé API Google Maps

Ces fichiers sont ignorés par Git pour éviter d’exposer les identifiants et clés secrets.

## Structure principale

- `app.php` : fonctions utilitaires et logique globale
- `index.php` : page d’accueil
- `database/` : schémas SQL et données de référence
- `assets/` : ressources front-office

## Notes

- La configuration locale ne doit jamais être versionnée.
- Les clés sensibles doivent être fournies via variables d’environnement ou fichiers locaux non suivis.
