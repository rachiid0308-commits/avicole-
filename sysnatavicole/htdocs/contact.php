<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = current_user();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#104b38">
  <meta name="color-scheme" content="light">
  <title>Contact — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title>
  <link rel="stylesheet" href="assets/app.css">
  <link rel="stylesheet" href="assets/features.css">
  <link rel="stylesheet" href="assets/reference-theme.css">
  <link rel="stylesheet" href="assets/contact.css">
  <link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head>
<body>
<header>
  <div class="brand">
    <img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52">
    <div><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><?php if ($user): ?><small><?=e(role_label($user))?></small><?php endif; ?></div>
  </div>
  <?php if ($user): require __DIR__ . '/navigation.php'; else: ?><a class="btn secondary" href="login.php">Ouvrir une session</a><?php endif; ?>
</header>
<main class="contact-page">
  <section class="contact-card card" aria-labelledby="contact-title">
    <div class="contact-heading">
      <span class="eyebrow">NOUS CONTACTER</span>
      <h1 id="contact-title">Écrire à l’administrateur du site</h1>
      <p>Utilisez ce formulaire pour contacter l’administrateur du site. Les champs marqués d’un astérisque sont obligatoires.</p>
    </div>
    <form class="contact-form" id="contact-form" action="mailto:aviculture.maroc@gmail.com" method="post" enctype="text/plain">
      <label class="contact-field"><span>Email <span class="required" aria-hidden="true">*</span></span>
        <input name="email" type="email" autocomplete="email" maxlength="254" placeholder="Email" required>
      </label>
      <label class="contact-field"><span>Objet <span class="required" aria-hidden="true">*</span></span>
        <input name="subject" maxlength="160" placeholder="Objet" required>
      </label>
      <label class="contact-field contact-field-wide"><span>Message <span class="required" aria-hidden="true">*</span></span>
        <textarea name="message" rows="7" maxlength="5000" placeholder="Message" required></textarea>
      </label>
      <p class="contact-privacy contact-field-wide"><strong>Protection des données personnelles :</strong> Conformément au décret n° 2-09-165 du 25 Jumada 1430 (21 mai 2009) pour l’application de la loi n° 09-08 relative à la protection des personnes physiques à l’égard du traitement des données à caractère personnel, les informations que vous fournissez sur ce formulaire ne seront pas utilisées à d’autres fins.</p>
      <label class="contact-consent contact-field-wide"><input name="consent" type="checkbox" value="accepted" required><span>J’ai lu et j’accepte les conditions générales d’utilisation, y compris la clause relative à la protection des données personnelles. <span class="required" aria-hidden="true">*</span></span></label>
      <div class="contact-actions contact-field-wide">
        <button class="btn" type="submit">Soumettre</button>
      </div>
      <p class="contact-note contact-field-wide" id="contact-status" aria-live="polite">Votre message sera envoyé. Une confirmation apparaîtra ici; en cas d’échec, réessayez plus tard.</p>
    </form>
  </section>
</main>
<footer class="copyright-footer">
  <span>AvuSafe© All rights reserved 2026</span>
  <span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span>
</footer>
<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@5/dist/email.min.js"></script>
<script src="assets/contact-emailjs.js"></script>
</body>
</html>
