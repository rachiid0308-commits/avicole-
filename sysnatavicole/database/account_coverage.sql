-- Lancer dans phpMyAdmin après l’import des comptes.
SELECT role, COUNT(*) AS comptes_actifs,
       CASE role WHEN 'central' THEN 4 WHEN 'regional' THEN 12 WHEN 'provincial' THEN 75 END AS attendu
FROM users WHERE active=1 GROUP BY role ORDER BY role;

-- Régions sans directeur régional actif (résultat attendu : aucune ligne).
SELECT r.id,r.name FROM regions r
LEFT JOIN users u ON u.region_id=r.id AND u.role='regional' AND u.active=1
WHERE u.id IS NULL ORDER BY r.id;

-- Provinces/préfectures sans chef de service actif (résultat attendu : aucune ligne).
SELECT p.id,p.name,r.name AS region FROM provinces p
JOIN regions r ON r.id=p.region_id
LEFT JOIN users u ON u.province_id=p.id AND u.role='provincial' AND u.active=1
WHERE u.id IS NULL ORDER BY r.id,p.id;
