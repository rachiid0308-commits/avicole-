<?php
$currentNavigationPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$navigationItems = [
    'index.php' => 'Accueil',
    'units.php' => 'Liste des unités',
];
$navigationItems += [
    'controls.php' => 'Contrôle des unités',
    'vehicle_controls.php' => 'Contrôle des véhicules',
    'map.php' => 'Localisation des unités avicoles',
    'stats.php' => 'Statistiques',
    'distance.php' => 'Calcul de distances',
    'gps.php' => 'Localiser une Unité',
];
if (($user['role'] ?? '') === ROLE_ADMINISTRATOR) $navigationItems['import_export.php'] = 'Importer/Exporter';
if (($user['role'] ?? '') !== ROLE_PROVINCIAL && ($user['role'] ?? '') !== ROLE_VETERINARY_RESPONSIBLE) {
    $navigationItems['users.php'] = (($user['role'] ?? '') === ROLE_ADMINISTRATOR) ? 'Gestion des comptes' : 'Comptes — consultation';
}
$navigationItems['contact.php'] = 'Contact';
$navigationIcons = [
    'index.php' => '<path d="M4 10.8 12 4l8 6.8"/><path d="M6 9.6V20h12V9.6"/><path d="M10 20v-5h4v5"/>',
    'units.php' => '<path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
    'controls.php' => '<path d="M8 4h8l3 3v13H5V4h3Z"/><path d="M8 11h8M8 15h8M8 8h2"/>',
    'vehicle_controls.php' => '<path d="M8 4h8l3 3v13H5V4h3Z"/><path d="M8 11h8M8 15h8M8 8h2"/>',
    'map.php' => '<path d="M9 4 3 6.5v13L9 17l6 2.5 6-2.5v-13L15 6.5 9 4Z"/><path d="M9 4v13M15 6.5v13"/>',
    'stats.php' => '<path d="M5 20v-8M12 20V4M19 20v-5"/>',
    'distance.php' => '<path d="M3 12h18"/><path d="m6 9-3 3 3 3M18 9l3 3-3 3"/>',
    'gps.php' => '<circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
    'farm_form.php' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 8v8M8 12h8"/>',
    'import_export.php' => '<path d="M7 3H4a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3"/><path d="M14 3h7v7M21 3l-9 9M7 8h4M7 12h5M7 16h3"/>',
    'users.php' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.5a3 3 0 0 1 0 5.8"/><path d="M17.5 20a5 5 0 0 0-2.2-4.1"/>',
    'contact.php' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
];
$renderNavItems = static function (array $items, bool $compact = false) use ($navigationItems, $navigationIcons, $currentNavigationPage): void {
    foreach ($items as $path):
        if (!isset($navigationItems[$path])) continue;
        $label = $navigationItems[$path];
        if ($compact) $label = ['units.php' => 'Unités', 'controls.php' => 'Contrôle unités', 'vehicle_controls.php' => 'Contrôle véhicules', 'stats.php' => 'Stats', 'distance.php' => 'Calcul de distances', 'gps.php' => 'Localiser une Unité', 'map.php' => 'Localisation', 'import_export.php' => 'Importer/Exporter', 'users.php' => 'Comptes'][$path] ?? $label;
        $itemPage = basename((string)parse_url($path, PHP_URL_PATH));
        $isCurrent = $currentNavigationPage === $itemPage;
        ?>
        <li><a href="<?=e($path)?>"<?=$isCurrent ? ' class="current" aria-current="page"' : ''?>><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?=$navigationIcons[$path] ?? ''?></svg></span><span><?=e($label)?></span></a></li>
        <?php
    endforeach;
};
$allNavigationPaths = array_keys($navigationItems);
$mobileNavigationPaths = array_values(array_filter($allNavigationPaths, static fn(string $path): bool => $path !== 'index.php'));
?>
<?php if (($user['role'] ?? '') !== ROLE_ADMINISTRATOR): ?><style>@media print{html,body{display:none!important;visibility:hidden!important}}</style><?php endif; ?>
<div class="nav-hover-zone">
  <button class="nav-rail-trigger" type="button" aria-label="Afficher le menu"><span aria-hidden="true">☰</span></button>
  <aside class="nav-rail" aria-label="Menu principal">
    <p class="nav-rail-title">🐔 Menu</p>
    <div class="nav-rail-scroll" id="desktop-nav-scroll">
      <ul class="nav-rail-list"><?php $renderNavItems($allNavigationPaths); ?></ul>
    </div>
    <button class="nav-rail-scroll-control" type="button" data-nav-scroll-down aria-label="Faire défiler le menu vers le bas" aria-controls="desktop-nav-scroll"><span aria-hidden="true">⌄</span></button>
  </aside>
</div>
<nav class="mobile-nav" aria-label="Menu principal">
  <ul class="nav-rail-list"><?php $renderNavItems($mobileNavigationPaths, true); ?></ul>
</nav>
<form class="header-logout" method="post" action="logout.php"><?=csrf_field()?><button class="btn secondary" type="submit">Déconnexion</button></form>
<script>
(()=>{
  const area=document.getElementById('desktop-nav-scroll');
  const control=document.querySelector('[data-nav-scroll-down]');
  if(!area||!control)return;
  const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const atBottom=()=>area.scrollTop+area.clientHeight>=area.scrollHeight-2;
  const update=()=>{
    const bottom=atBottom();
    const icon=control.querySelector('span');
    control.dataset.direction=bottom?'up':'down';
    control.setAttribute('aria-label',bottom?'Remonter au début du menu':'Faire défiler le menu vers le bas');
    control.title=bottom?'Remonter au début du menu':'Faire défiler le menu vers le bas';
    if(icon)icon.textContent=bottom?'⌃':'⌄';
    control.disabled=area.scrollHeight<=area.clientHeight+2;
  };
  control.addEventListener('click',()=>{
    const top=atBottom()?0:Math.min(area.scrollTop+Math.max(160,area.clientHeight*.72),area.scrollHeight);
    area.scrollTo({top,behavior:reducedMotion?'auto':'smooth'});
  });
  area.addEventListener('scroll',update,{passive:true});
  window.addEventListener('resize',update);
  update();
})();
</script>


