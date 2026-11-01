# Coordonnées de démonstration — limites administratives

`morocco_adm2_boundaries.geojson` contient les 75 limites ADM2 utilisées uniquement pour générer des coordonnées synthétiques de démonstration. Les noms et régions de l’application ont été associés aux polygones ADM2, puis vérifiés un-à-un avec `htdocs/database/schema.sql`.

- Source : [geoBoundaries, MAR ADM2](https://www.geoboundaries.org/api/current/gbOpen/MAR/ADM2/)
- Fichier simplifié : [geoBoundaries-MAR-ADM2_simplified.geojson](https://github.com/wmgeolab/geoBoundaries/raw/9469f09/releaseData/gbOpen/MAR/ADM2/geoBoundaries-MAR-ADM2_simplified.geojson)
- Millésime des limites : 2017
- Sources des limites : OpenStreetMap et Wambacher
- Licence des limites : Open Database License (ODbL) 1.0 ; attribution à geoBoundaries et aux contributeurs OpenStreetMap requise.

Le script `bin/seed_demo_6000.php` échantillonne des points à l’intérieur de ces polygones. Les points et les fermes sont **entièrement fictifs** : ils ne correspondent ni à des exploitations existantes, ni à des parcelles agricoles validées. Ne les utilisez pas pour des décisions réglementaires, des itinéraires ou des analyses réelles. Les données dérivées des limites restent soumises aux conditions ODbL indiquées par la source.
