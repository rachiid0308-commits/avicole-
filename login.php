<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
start_session();
if (current_user()) { header('Location: index.php'); exit; }
$error = isset($_GET['expired']) ? 'Votre session a expiré après 5 minutes d’inactivité. Veuillez vous reconnecter.' : '';
$notice = isset($_GET['installed']) ? 'Installation terminée. Connectez-vous avec le compte administrateur initial que vous venez de créer.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id,password_hash FROM users WHERE email=:email AND active=1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['csrf']);
        db()->prepare('UPDATE users SET last_login_at=CURRENT_TIMESTAMP WHERE id=:id')->execute([':id' => (int)$row['id']]);
        header('Location: index.php'); exit;
    }
    $error = 'Identifiants incorrects ou compte désactivé.';
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light"><title>Connexion — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header class="site-header"><div class="brand"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><div><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small>Accès sécurisé — données nationales</small></div></div></header>
<main class="login"><div class="card"><h1>Connexion</h1><p class="help">Votre profil détermine automatiquement les données accessibles.</p><?php if ($notice): ?><div class="flash"><?=e($notice)?></div><?php endif; ?><?php if ($error): ?><div class="flash error"><?=e($error)?></div><?php endif; ?><form method="post" autocomplete="on"><?=csrf_field()?>
<label for="email">Adresse e-mail</label><input id="email" name="email" type="email" required autocomplete="username" autofocus>
<label for="password">Mot de passe</label><input id="password" name="password" type="password" required autocomplete="current-password">
<p><button class="btn" type="submit">Ouvrir la session</button></p></form></div><p class="footer">Comptes créés par l’administrateur système. Aucun mot de passe par défaut.</p></main><footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer></body></html>
