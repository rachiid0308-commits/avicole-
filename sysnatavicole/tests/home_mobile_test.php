<?php
declare(strict_types=1);
$root=__DIR__.'/../htdocs/';
$page=file_get_contents($root.'index.php')?:'';
$style=file_get_contents($root.'assets/home-mobile.css')?:'';
$features=file_get_contents($root.'assets/features.css')?:'';
function home_check(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}}
home_check(str_contains($page,'class="home-page"')&&str_contains($page,'assets/home-mobile.css'),'homepage loads its responsive mobile stylesheet');
home_check(str_contains($page,'Le suivi avicole, en un seul portail')&&str_contains($page,'Répertoire, carte et indicateurs selon votre territoire autorisé.'),'mobile hero uses concise, task-focused copy');
home_check(str_contains($page,'<img class="home-hero-logo" src="assets/logo-poussin-jaune.png" alt="" aria-hidden="true" width="84" height="84">')&&strpos($page,'<img class="home-hero-logo"')<strpos($page,'<h1 id="home-title">'),'homepage hero shows the gold logo before the main page title');
home_check(str_contains($features,'.home-hero-logo{display:block;width:84px;height:84px;')&&str_contains($style,'html body.home-page .home-hero-logo{width:56px;height:56px;'),'homepage logo is prominent on desktop and compact on mobile');
home_check(str_contains($page,'Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-')&&str_contains($page,'AvuSafe©')&&str_contains($page,'class="brand-logo"')&&str_contains($page,'assets/logo-poussin-jaune.png')&&str_contains($page,'<link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png">'),'homepage displays the supplied logo in its header and browser tab');
home_check(str_contains($page,'PORTAIL NATIONAL DE SUIVI ET DE CONTRÔLE DU SECTEUR AVICOLE AU MAROC')&&!str_contains($page,'PORTAIL DE SUIVI · MAROC'),'homepage eyebrow uses the requested national portal title');
preg_match('/<section class="home-hero".*?<\/section>/s',$page,$hero);home_check(isset($hero[0])&&!str_contains($hero[0],'<a class="btn"')&&!str_contains($hero[0],'<button'),'hero action buttons are removed');
home_check(str_contains($page,'Réalisations et apports')&&str_contains($page,'contrôles des unités et des véhicules')&&str_contains($page,'décision territoriale'),'homepage explains the tools and their operational benefits');
home_check(substr_count($page,'class="home-mobile-summary"')===5,'each homepage tool has a concise mobile summary');
home_check(substr_count($page,'class="home-mobile-tool-links"')===5,'each homepage tool has a mobile-only link group');
foreach(['units.php','map.php','gps.php','stats.php','distance.php'] as $toolPage)home_check(str_contains($page,'href="'.$toolPage.'"'),'mobile tool links to '.$toolPage);
home_check(str_contains($page,'if (can_create_farm($user))')&&str_contains($page,'href="farm_form.php"'),'add-unit link follows create permissions');
home_check(str_contains($page,'if (can_import_farm($user))')&&str_contains($page,'href="import_csv.php"'),'import link follows import permissions');
home_check(str_contains($style,'.home-mobile-copy,.home-intro-mobile,.home-mobile-summary,.home-mobile-tool-links{display:none}')&&str_contains($style,'html body.home-page .home-mobile-tool-links{display:flex;'),'tool links are hidden on desktop and shown on mobile');
home_check(str_contains($style,'html body.home-page .home-mobile-tool-link{display:inline-flex;flex:1 1 auto;align-items:center;justify-content:center;min-height:44px;'),'mobile tool links have touch-friendly targets');
home_check(str_contains($style,'@media(max-width:767px)')&&str_contains($style,'html body.home-page .home-tool p.home-mobile-summary{display:block!important;')&&str_contains($style,'html body.home-page .home-achievements{display:block!important;'),'mobile descriptions and tool benefits remain visible');
home_check(str_contains($features,'.home-achievements ul{display:grid;grid-template-columns:minmax(0,1fr);')&&str_contains($features,'.home-achievements li::marker'),'achievement points use one full-width column so each text line can span the page');
home_check(str_contains($style,'linear-gradient(120deg,#143d2a 0%,#1e6946 62%,#28734f 100%)')&&!str_contains($style,'url('),'homepage header uses a green gradient instead of a background photo');
echo "OK: mobile homepage content and responsive color tests passed\n";
