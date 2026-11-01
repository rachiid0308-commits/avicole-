<?php
declare(strict_types=1);
$root=__DIR__.'/../htdocs/';
$page=file_get_contents($root.'gps.php')?:'';
$style=file_get_contents($root.'assets/gps-proximity.css')?:'';
$theme=file_get_contents($root.'assets/reference-theme.css')?:'';
$nav=file_get_contents($root.'navigation.php')?:'';
function gps_check(bool $condition,string $message):void{if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}}
gps_check(str_contains($page,'id="gps-coordinate-form"')&&str_contains($page,'name="latitude"')&&str_contains($page,'name="longitude"')&&str_contains($page,'name="farm_type"')&&str_contains($page,'Localiser et calculer les distances'),'coordinate locator accepts latitude, longitude and intended unit type');
gps_check(str_contains($page,'has_province_scope($user)')&&str_contains($page,'renderProximityMap()')&&str_contains($page,'distance-threshold-dot'),'coordinate locator is role-scoped and renders its target and under-threshold neighbors on the map');
gps_check(str_contains($page,'data-lat=')&&str_contains($page,'gps-proximity-coordinates')&&str_contains($page,'Les coordonnées GPS d’une unité correspondent à sa latitude et sa longitude')&&str_contains($page,'Voir les unités sous le seuil')&&str_contains($page,'Unités situées sous le seuil réglementaire'),'unit selection explains GPS coordinates and provides an explicit under-threshold list action');
gps_check(str_contains($page,'Localiser une Unité')&&str_contains($page,'id="gps-proximity-form"'),'GPS navigation is renamed and distance form remains present');
gps_check(str_contains($page,'distance-threshold-dot')&&str_contains($page,'animateThresholdMarkers')&&str_contains($page,'prefers-reduced-motion'),'under-threshold markers are red and provincial animation respects reduced motion');
gps_check(str_contains($style,'@media(min-width:768px){.page-gps .gps-panel{display:none}}')&&str_contains($style,'@media(max-width:767px){.page-gps .gps-panel{display:block}'),'Ma position GPS is visible only on mobile');
gps_check(str_contains($style,'@media(max-width:767px)')&&str_contains($style,'.gps-proximity-controls{display:grid;'),'mobile distance controls use a single-column layout');
gps_check(str_contains($style,'@keyframes threshold-marker-pulse'),'threshold marker has a blinking animation');
gps_check(str_contains($page,'class="gps-proximity-intro-title"')&&str_contains($page,'class="gps-proximity-intro help"')&&str_contains($page,'<h3>Unités situées sous le seuil réglementaire</h3>')&&str_contains($style,'@media(max-width:767px){.page-gps .gps-proximity-intro-title,.page-gps .gps-proximity-intro{display:none}'),'mobile GPS view hides only the explanatory intro and keeps the regulatory-threshold results heading');
gps_check(str_contains($theme,'html body .mobile-nav{background:#183f2d')&&str_contains($theme,'html body .mobile-nav a{color:#f2d582')&&str_contains($theme,'html body .mobile-nav .nav-icon{color:#f2d582;'),'mobile bottom navigation uses a green bar with yellow labels and icons');
gps_check(str_contains($theme,'@keyframes mobile-nav-icon-bounce')&&str_contains($theme,'@media(prefers-reduced-motion:reduce)')&&str_contains($theme,'html body .mobile-nav a,html body .mobile-nav .nav-icon,html body .mobile-nav a.current .nav-icon{animation:none;transition:none}'),'mobile navigation icons animate on touch and honor reduced-motion preferences');
gps_check(str_contains($theme,'width:44px;height:44px;min-height:44px;margin:10px auto 2px')&&str_contains($theme,'border-radius:14px')&&str_contains($theme,'background:linear-gradient(145deg'),'desktop menu scroll arrow uses a modern compact style');
gps_check(str_contains($nav,'<span aria-hidden="true">⌄</span>')&&str_contains($nav,"control.dataset.direction=bottom?'up':'down'")&&str_contains($nav,"bottom?'⌃':'⌄'"),'desktop menu arrow is a chevron that changes direction at the end of the list');
echo "OK: GPS coordinates, mobile visibility and alert marker tests passed\n";
