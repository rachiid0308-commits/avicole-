<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
$rows = list_farms($user, [], 'region', 'province');
$farms = array_map(static fn(array $farm): array => [
    'id' => (int)$farm['id'], 'name' => (string)$farm['farm_name'],
    'region' => (string)$farm['region_name'], 'province' => (string)$farm['province_name'], 'type' => (string)$farm['farm_type'],
    'lat' => (float)$farm['latitude'], 'lon' => (float)$farm['longitude'],
], $rows);
$farmsJson = json_encode($farms, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$googleMapsApiKeyJson = json_encode(google_maps_api_key(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light">
  <title>Localiser une Unité — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title>
  <link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="stylesheet" href="assets/gps-proximity.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head>
<body class="page-gps">
<header class="site-header">
  <a class="brand" href="index.php"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><span><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></span></a>
  <?php require __DIR__ . '/navigation.php'; ?>
</header>
<main>
  <section class="card gps-panel">
    <h1>Ma position GPS</h1>
    <p class="help">Autorisez la localisation pour mesurer la distance à vol d’oiseau avec les unités avicoles enregistrées dans votre périmètre.</p>
    <div class="gps-controls">
      <button class="btn gps-button" id="locate" type="button">📍 Obtenir ma position</button>
      <label>Type d’unité envisagé<select id="proposed-type"><option>Chair</option><option>Pondeuses</option><option>Reproducteurs</option><option>Couvoir</option><option>Dinde</option></select></label>
    </div>
    <p id="gps-status" class="gps-status" role="status" aria-live="polite">Appuyez sur « Obtenir ma position » pour commencer.</p>
    <div id="gps-summary" class="gps-summary" hidden></div>
  </section>
  <section class="card gps-proximity-panel" id="gps-proximity">
    <h2 class="gps-proximity-intro-title">Coordonnées GPS et unités sous le seuil</h2>
    <p class="gps-proximity-intro help">Les coordonnées GPS d’une unité correspondent à sa latitude et sa longitude. Sélectionnez une unité enregistrée pour afficher ses coordonnées et repérer les autres unités situées sous la distance minimale réglementaire. Les identités hors de votre périmètre restent masquées.</p>
    <?php if (has_province_scope($user)): ?>
    <div class="gps-coordinate-entry">
      <h3>Localisation de l’unité à partir des coordonnées</h3>
      <p class="help">Saisissez la latitude et la longitude décimales du site envisagé. La carte indiquera l’emplacement et les unités les plus proches qui se trouvent sous le seuil réglementaire.</p>
      <form id="gps-coordinate-form" class="gps-proximity-controls gps-coordinate-form">
        <label for="gps-coordinate-latitude">Latitude<input id="gps-coordinate-latitude" name="latitude" type="number" min="-90" max="90" step="any" inputmode="decimal" required placeholder="Ex. 34.000000"></label>
        <label for="gps-coordinate-longitude">Longitude<input id="gps-coordinate-longitude" name="longitude" type="number" min="-180" max="180" step="any" inputmode="decimal" required placeholder="Ex. -6.000000"></label>
        <label for="gps-coordinate-type">Type d’unité envisagé<select id="gps-coordinate-type" name="farm_type" required><?php foreach (FARM_TYPES as $farmType): ?><option value="<?=e($farmType)?>"><?=e($farmType)?></option><?php endforeach; ?></select></label>
        <button class="btn" type="submit">Localiser et calculer les distances</button>
      </form>
    </div>
    <?php endif; ?>
    <form id="gps-proximity-form" class="gps-proximity-controls">
      <label for="gps-proximity-unit">Unité avicole</label>
      <select id="gps-proximity-unit" required><option value="">Choisir une unité…</option><?php foreach($farms as $farm): if(!is_finite($farm['lat'])||!is_finite($farm['lon'])||abs($farm['lat'])>90||abs($farm['lon'])>180) continue; ?><option value="<?=$farm['id']?>" data-lat="<?=e((string)$farm['lat'])?>" data-lon="<?=e((string)$farm['lon'])?>"><?=e($farm['name'])?> — <?=e($farm['province'])?> (<?=e($farm['type'])?>)</option><?php endforeach; ?></select>
      <button class="btn" type="submit">Voir les unités sous le seuil</button>
    </form>
    <p id="gps-proximity-coordinates" class="gps-proximity-coordinates help" aria-live="polite">Coordonnées GPS : sélectionnez une unité pour les afficher.</p>
    <p id="gps-proximity-status" class="gps-proximity-status help" role="status" aria-live="polite">Sélectionnez une unité puis lancez le calcul.</p>
    <h3>Unités situées sous le seuil réglementaire</h3>
    <div class="responsive-table gps-proximity-results"><table><thead><tr><th>Distance</th><th>Minimum requis</th><th>Unité proche</th><th>Type</th><th>Province</th></tr></thead><tbody id="gps-proximity-rows"><tr><td colspan="5" class="empty-state">Les résultats s’afficheront ici.</td></tr></tbody></table></div>
    <p id="gps-proximity-map-note" class="gps-proximity-map-note help">Repère vert : unité choisie; repère rouge : unité visible sous le seuil (scintillant pour le profil provincial); repère gris : unité proche hors périmètre, sans nom ni province.</p>
  </section>
  <section class="card">
    <div class="page-heading"><div><h2>Unités sur la carte</h2><p class="help">Choisissez le fond : plan, satellite ou hybride Google Maps.</p></div></div>
    <div class="map-toolbar"><label for="gps-map-type">Fond de carte<select id="gps-map-type"><option value="roadmap">Plan</option><option value="hybrid">Hybride</option><option value="satellite" selected>Satellite</option></select></label><p id="gps-map-type-help" class="help">Google Maps offre les trois modes. Sans clé API, le mode hybride est indisponible.</p></div>
    <div id="gps-map" role="application" aria-label="Carte des unités avicoles"></div>
    <p id="gps-map-mode" class="help">Chargement du fond de carte…</p>
    <p id="gps-map-attribution" class="map-attribution" hidden>Imagerie © Esri, Maxar, Earthstar Geographics et contributeurs.</p>
    <p id="gps-map-unavailable" class="help" hidden>La carte ne peut pas être chargée. Vérifiez la connexion Internet.</p>
  </section>
  <section class="card">
    <div class="page-heading"><div><h2>Unités les plus proches</h2><p class="help">La première ligne indique l’unité la plus proche de votre position.</p></div><span class="count-pill" id="near-count">—</span></div>
    <div class="responsive-table"><table><thead><tr><th>Distance</th><th>Minimum</th><th>Conformité</th><th>Unité</th><th>Type</th><th>Province</th><th>Carte</th></tr></thead><tbody id="nearby"><tr><td colspan="7" class="empty-state">La position n’a pas encore été obtenue.</td></tr></tbody></table></div>
  </section>
</main>
<footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
'use strict';
const farms = <?=$farmsJson?>;
const googleMapsApiKey = <?=$googleMapsApiKeyJson?>;
const btn=document.getElementById('locate'),status=document.getElementById('gps-status'),summary=document.getElementById('gps-summary'),typeSelect=document.getElementById('proposed-type'),nearby=document.getElementById('nearby'),nearCount=document.getElementById('near-count');
const mapTypeSelect=document.getElementById('gps-map-type'),mapTypeHelp=document.getElementById('gps-map-type-help'),mapMode=document.getElementById('gps-map-mode'),mapAttribution=document.getElementById('gps-map-attribution');
const coordinateForm=document.getElementById('gps-coordinate-form'),coordinateButton=coordinateForm?.querySelector('button[type="submit"]');
const proximityForm=document.getElementById('gps-proximity-form'),proximityUnit=document.getElementById('gps-proximity-unit'),proximityStatus=document.getElementById('gps-proximity-status'),proximityRows=document.getElementById('gps-proximity-rows'),proximityCoordinates=document.getElementById('gps-proximity-coordinates'),proximityButton=proximityForm?.querySelector('button[type="submit"]');
let currentPosition=null,userMarker=null,gpsMap=null,googleMap=null,leafletLayer=null,proximityData=null;
const proximityLeafletMarkers=[],proximityGoogleMarkers=[],proximityGoogleBlinkIntervals=[];
const animateThresholdMarkers=<?=json_encode(($user['role']??'')===ROLE_PROVINCIAL)?>;
const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const mapPoints=[];
function validFarmPoint(f){return Number.isFinite(f.lat)&&Number.isFinite(f.lon)&&Math.abs(f.lat)<=90&&Math.abs(f.lon)<=180;}
function farmPopup(f){const geography=[f.province,f.region].filter(Boolean).map(escapeHtml).join(', ');return `<strong>${escapeHtml(f.name)}</strong><br>${escapeHtml(f.type)}${geography?`<br>${geography}`:''}<br>${f.lat.toFixed(6)}, ${f.lon.toFixed(6)}<br><a target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(f.lat+','+f.lon)}">Ouvrir dans Google Maps</a>`;}
function applyMapType(){
  const type=mapTypeSelect.value;
  if(googleMap){googleMap.setMapTypeId(({roadmap:google.maps.MapTypeId.ROADMAP,hybrid:google.maps.MapTypeId.HYBRID,satellite:google.maps.MapTypeId.SATELLITE})[type]);mapMode.textContent='Fond Google Maps : '+({roadmap:'Plan',hybrid:'Hybride',satellite:'Satellite'})[type]+'.';mapTypeHelp.textContent='Choisissez Plan, Satellite ou Hybride Google Maps.';mapAttribution.hidden=true;return;}
  if(!gpsMap)return;
  if(type==='hybrid'){mapTypeSelect.value='satellite';mapTypeHelp.textContent='Le fond satellite est affiché à la place du mode hybride.';}
  if(leafletLayer)gpsMap.removeLayer(leafletLayer);
  if(mapTypeSelect.value==='roadmap'){
    leafletLayer=L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap contributors'}).addTo(gpsMap);
    mapMode.textContent='Fond Plan OpenStreetMap.';mapAttribution.textContent='Fond cartographique © OpenStreetMap contributors.';
  }else{
    leafletLayer=L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{maxZoom:19,attribution:'Tiles © Esri, Maxar, Earthstar Geographics, and the GIS User Community'}).addTo(gpsMap);
    mapMode.textContent='Fond satellite Esri — Google Maps n’est pas configuré.';mapAttribution.textContent='Imagerie © Esri, Maxar, Earthstar Geographics et contributeurs.';
  }
  mapTypeHelp.textContent='Fonds disponibles : plan et satellite.';mapAttribution.hidden=false;
}
function initializeLeafletMap(){
  if(!window.L){document.getElementById('gps-map-unavailable').hidden=false;return;}
  mapTypeSelect.querySelector('option[value="hybrid"]').disabled=true;mapTypeSelect.value='satellite';
  gpsMap=L.map('gps-map',{scrollWheelZoom:false,preferCanvas:true,zoomControl:true}).setView([31.8,-6.5],5);
  farms.forEach(f=>{if(!validFarmPoint(f))return;const point=[f.lat,f.lon];mapPoints.push(point);L.circleMarker(point,{radius:7,color:'#fff',weight:2,fillColor:'#26734c',fillOpacity:.95}).bindPopup(farmPopup(f)).addTo(gpsMap);});
  if(mapPoints.length===1)gpsMap.setView(mapPoints[0],12);else if(mapPoints.length)gpsMap.fitBounds(mapPoints,{padding:[24,24],maxZoom:12});
  applyMapType();
  renderProximityMap();
}
function initializeGoogleMap(){
  googleMap=new google.maps.Map(document.getElementById('gps-map'),{center:{lat:31.8,lng:-6.5},zoom:5,mapTypeId:google.maps.MapTypeId.SATELLITE,mapTypeControl:true,mapTypeControlOptions:{style:google.maps.MapTypeControlStyle.DROPDOWN_MENU,mapTypeIds:[google.maps.MapTypeId.ROADMAP,google.maps.MapTypeId.HYBRID,google.maps.MapTypeId.SATELLITE]},streetViewControl:false,fullscreenControl:true});
  const bounds=new google.maps.LatLngBounds();let count=0;
  farms.forEach(f=>{if(!validFarmPoint(f))return;const point={lat:f.lat,lng:f.lon};mapPoints.push([f.lat,f.lon]);bounds.extend(point);count++;const marker=new google.maps.Marker({position:point,map:googleMap,title:f.name});const info=new google.maps.InfoWindow({content:farmPopup(f)});marker.addListener('click',()=>info.open({map:googleMap,anchor:marker}));});
  if(count===1){googleMap.setCenter(bounds.getCenter());googleMap.setZoom(12);}else if(count>1)googleMap.fitBounds(bounds);
  mapTypeSelect.querySelector('option[value="hybrid"]').disabled=false;
  applyMapType();
  renderProximityMap();
}
mapTypeSelect.addEventListener('change',applyMapType);
if(googleMapsApiKey){
  window.initGoogleMap=initializeGoogleMap;
  const googleScript=document.createElement('script');
  googleScript.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(googleMapsApiKey)+'&callback=initGoogleMap';
  googleScript.async=true;googleScript.onerror=initializeLeafletMap;document.head.appendChild(googleScript);
}else initializeLeafletMap();
const minimum=(a,b)=>a==='Reproducteurs'||b==='Reproducteurs'?3:(a==='Couvoir'||b==='Couvoir'||a==='Pondeuses'||b==='Pondeuses'?2:1);
const distance=(a,b)=>{const r=Math.PI/180,dl=(b.lat-a.lat)*r,dn=(b.lon-a.lon)*r,x=Math.sin(dl/2)**2+Math.cos(a.lat*r)*Math.cos(b.lat*r)*Math.sin(dn/2)**2;return 6371.0088*2*Math.atan2(Math.sqrt(x),Math.sqrt(1-x));};
function renderResults(){
  if(!currentPosition)return;
  const point={lat:currentPosition.coords.latitude,lon:currentPosition.coords.longitude};
  const type=typeSelect.value;
  const ranked=farms.map(f=>({...f,distance:distance(point,f),minimum:minimum(type,f.type)})).sort((a,b)=>a.distance-b.distance);
  const coordinateText=`${point.lat.toFixed(6)}, ${point.lon.toFixed(6)}`;
  summary.hidden=false;
  summary.innerHTML=`<div><strong>Coordonnées :</strong> <code>${coordinateText}</code></div><div><a class="btn secondary" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q=${encodeURIComponent(coordinateText)}">Voir ma position sur la carte</a> <button class="btn secondary" id="copy-coordinates" type="button">Copier les coordonnées</button></div>`;
  document.getElementById('copy-coordinates').addEventListener('click',async()=>{try{await navigator.clipboard.writeText(coordinateText);status.textContent='Coordonnées copiées.';}catch(_){status.textContent='Copie impossible; sélectionnez les coordonnées affichées.';}});
  nearCount.textContent=`${ranked.length} unité(s)`;
  if(!ranked.length){nearby.innerHTML='<tr><td colspan="7" class="empty-state">Aucune unité avicole avec des coordonnées dans votre périmètre.</td></tr>';return;}
  nearby.innerHTML=ranked.slice(0,25).map((f,i)=>{const ok=f.distance>=f.minimum;const maps=`https://www.google.com/maps?q=${encodeURIComponent(f.lat+','+f.lon)}`;return `<tr class="${ok?'':'distance-warning'}"><td>${f.distance.toFixed(2)} km${i===0?' <strong>(plus proche)</strong>':''}</td><td>${f.minimum} km</td><td>${ok?'Respectée':'Sous le minimum'}</td><td>${escapeHtml(f.name)}</td><td>${escapeHtml(f.type)}</td><td>${escapeHtml(f.province)}</td><td><a target="_blank" rel="noopener noreferrer" href="${maps}">Ouvrir ↗</a></td></tr>`;}).join('');
}
function escapeHtml(value){const d=document.createElement('div');d.textContent=String(value);return d.innerHTML;}
function clearProximityMarkers(){
  if(gpsMap)for(const marker of proximityLeafletMarkers)gpsMap.removeLayer(marker);
  proximityLeafletMarkers.length=0;
  for(const marker of proximityGoogleMarkers)marker.setMap(null);
  proximityGoogleMarkers.length=0;
  for(const timer of proximityGoogleBlinkIntervals)clearInterval(timer);
  proximityGoogleBlinkIntervals.length=0;
}
function renderProximityMap(){
  if(!proximityData||(!gpsMap&&!googleMap))return;
  clearProximityMarkers();
  const target=proximityData.target;
  const points=[[target.lat,target.lon]];
  if(gpsMap){
    const targetMarker=L.circleMarker([target.lat,target.lon],{radius:12,color:'#fff',weight:3,fillColor:'#16734b',fillOpacity:1}).bindPopup(farmPopup(target)).addTo(gpsMap);
    proximityLeafletMarkers.push(targetMarker);
    for(const neighbor of proximityData.neighbors){
      const point=[neighbor.lat,neighbor.lon];points.push(point);
      let marker;
      if(neighbor.visible){
        const animated=animateThresholdMarkers&&!reducedMotion;
        const icon=L.divIcon({className:'distance-threshold-icon',html:`<span class="distance-threshold-dot${animated?' is-animated':''}" aria-hidden="true"></span>`,iconSize:[22,22],iconAnchor:[11,11]});
        marker=L.marker(point,{icon}).bindPopup(farmPopup(neighbor));
      }else{
        marker=L.circleMarker(point,{radius:8,color:'#fff',weight:2,fillColor:'#59636b',fillOpacity:.98}).bindTooltip(`Unité proche hors périmètre — ${Number(neighbor.distance).toFixed(3)} km`);
      }
      marker.addTo(gpsMap);proximityLeafletMarkers.push(marker);
    }
    const bounds=L.latLngBounds(points);
    gpsMap.fitBounds(bounds,{padding:[48,48],maxZoom:15});
  }else if(googleMap){
    const targetMarker=new google.maps.Marker({position:{lat:target.lat,lng:target.lon},map:googleMap,title:target.name,icon:{path:google.maps.SymbolPath.CIRCLE,scale:12,fillColor:'#16734b',fillOpacity:1,strokeColor:'#fff',strokeWeight:3},zIndex:google.maps.Marker.MAX_ZINDEX+2});
    const targetInfo=new google.maps.InfoWindow({content:farmPopup(target)});targetMarker.addListener('click',()=>targetInfo.open({map:googleMap,anchor:targetMarker}));proximityGoogleMarkers.push(targetMarker);
    const bounds=new google.maps.LatLngBounds();bounds.extend({lat:target.lat,lng:target.lon});
    for(const neighbor of proximityData.neighbors){
      const point={lat:neighbor.lat,lng:neighbor.lon};bounds.extend(point);points.push([neighbor.lat,neighbor.lon]);
      const marker=new google.maps.Marker({position:point,map:googleMap,icon:{path:google.maps.SymbolPath.CIRCLE,scale:neighbor.visible?10:8,fillColor:neighbor.visible?'#c62828':'#59636b',fillOpacity:.98,strokeColor:'#fff',strokeWeight:2},...(neighbor.visible?{title:neighbor.name}:{})});
      const info=new google.maps.InfoWindow({content:neighbor.visible?farmPopup(neighbor):`Unité proche hors périmètre — ${Number(neighbor.distance).toFixed(3)} km`});marker.addListener('click',()=>info.open({map:googleMap,anchor:marker}));proximityGoogleMarkers.push(marker);
      if(neighbor.visible&&animateThresholdMarkers&&!reducedMotion){let dim=false;const timer=window.setInterval(()=>{dim=!dim;marker.setOpacity(dim?0.35:1);},650);proximityGoogleBlinkIntervals.push(timer);}
    }
    if(points.length===1){googleMap.setCenter({lat:target.lat,lng:target.lon});googleMap.setZoom(14);}else googleMap.fitBounds(bounds,48);
  }
}
function renderSelectedUnitCoordinates(){
  const option=proximityUnit?.selectedOptions?.[0];
  if(!option||!option.dataset.lat||!option.dataset.lon){proximityCoordinates.textContent='Coordonnées GPS : sélectionnez une unité pour les afficher.';return;}
  const lat=Number(option.dataset.lat),lon=Number(option.dataset.lon);
  if(!Number.isFinite(lat)||!Number.isFinite(lon)){proximityCoordinates.textContent='Coordonnées GPS indisponibles pour cette unité.';return;}
  const text=`${lat.toFixed(6)}, ${lon.toFixed(6)}`;
  proximityCoordinates.innerHTML=`<strong>Coordonnées GPS de l’unité :</strong> <code>${text}</code> · <a target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q=${encodeURIComponent(text)}">Voir sur la carte</a>`;
}
function renderProximityRows(data){
  if(!data.neighbors.length){proximityRows.innerHTML='<tr><td colspan="5" class="empty-state">Aucune unité ne se trouve sous le seuil réglementaire.</td></tr>';return;}
  proximityRows.innerHTML=data.neighbors.map(neighbor=>`<tr class="${neighbor.visible?'distance-warning':''}"><td>${Number(neighbor.distance).toFixed(3)} km</td><td>${Number(neighbor.minimum).toFixed(1)} km</td><td>${neighbor.visible?escapeHtml(neighbor.name):'Unité proche hors périmètre'}</td><td>${neighbor.visible?escapeHtml(neighbor.type):'—'}</td><td>${neighbor.visible?escapeHtml(neighbor.province):'—'}</td></tr>`).join('');
}
proximityUnit?.addEventListener('change',renderSelectedUnitCoordinates);
renderSelectedUnitCoordinates();
proximityForm?.addEventListener('submit',async event=>{
  event.preventDefault();
  if(!proximityUnit.value){proximityStatus.textContent='Choisissez une unité avicole.';return;}
  proximityButton.disabled=true;proximityStatus.textContent='Calcul des distances réglementaires…';
  proximityRows.innerHTML='<tr><td colspan="5" class="empty-state">Calcul en cours…</td></tr>';
  try{
    const response=await fetch(`gps_proximity.php?farm_id=${encodeURIComponent(proximityUnit.value)}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
    const data=await response.json();
    if(!response.ok)throw new Error(data.error||'Le calcul n’a pas pu être effectué.');
    proximityData=data;renderSelectedUnitCoordinates();renderProximityRows(data);renderProximityMap();
    proximityStatus.textContent=data.neighbors.length?`${data.neighbors.length} unité(s) sous le seuil réglementaire autour de « ${data.target.name} ».`:`Aucune unité sous le seuil réglementaire autour de « ${data.target.name} ». `;
  }catch(error){proximityData=null;clearProximityMarkers();proximityRows.innerHTML='<tr><td colspan="5" class="empty-state">Aucun résultat disponible.</td></tr>';proximityStatus.textContent=error instanceof Error?error.message:'Erreur pendant le calcul.';}
  finally{
        proximityButton.disabled=false;
        // S'assurer que le menu déroulant reste visible et fonctionnel
        if (proximityUnit) {
            proximityUnit.disabled = false;
            proximityUnit.style.display = 'block';
        }
    }
});
coordinateForm?.addEventListener('submit',async event=>{
  event.preventDefault();
  if(!coordinateForm.reportValidity())return;
  coordinateButton.disabled=true;proximityStatus.textContent='Localisation de l’emplacement et calcul des distances…';
  proximityRows.innerHTML='<tr><td colspan="5" class="empty-state">Calcul en cours…</td></tr>';
  const params=new URLSearchParams(new FormData(coordinateForm));
  try{
    const response=await fetch(`gps_proximity.php?${params.toString()}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
    const data=await response.json();
    if(!response.ok)throw new Error(data.error||'La localisation n’a pas pu être effectuée.');
    proximityData=data;renderProximityRows(data);renderProximityMap();
    const point=`${Number(data.target.lat).toFixed(6)}, ${Number(data.target.lon).toFixed(6)}`;
    proximityCoordinates.innerHTML=`<strong>Coordonnées de l’emplacement :</strong> <code>${point}</code> · <a target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q=${encodeURIComponent(point)}">Voir sur Google Maps</a>`;
    proximityStatus.textContent=data.neighbors.length?`${data.neighbors.length} unité(s) sous le seuil réglementaire autour de l’emplacement saisi.`:'Aucune unité ne se trouve sous le seuil réglementaire autour de l’emplacement saisi.';
  }catch(error){proximityData=null;clearProximityMarkers();proximityRows.innerHTML='<tr><td colspan="5" class="empty-state">Aucun résultat disponible.</td></tr>';proximityStatus.textContent=error instanceof Error?error.message:'Erreur pendant le calcul.';}
  finally{coordinateButton.disabled=false;}
});
btn.addEventListener('click',()=>{
  if(!navigator.geolocation){status.textContent='La géolocalisation n’est pas disponible sur cet appareil.';return;}
  btn.disabled=true;status.textContent='Recherche de votre position…';
  navigator.geolocation.getCurrentPosition(position=>{currentPosition=position;status.textContent='Position obtenue. Les distances sont calculées à vol d’oiseau.';const point=[position.coords.latitude,position.coords.longitude];if(googleMap){const latLng={lat:point[0],lng:point[1]};if(userMarker)userMarker.setPosition(latLng);else userMarker=new google.maps.Marker({position:latLng,map:googleMap,title:'Votre position',icon:{url:'https://maps.google.com/mapfiles/ms/icons/blue-dot.png'}});googleMap.panTo(latLng);if(mapPoints.length){const bounds=new google.maps.LatLngBounds();mapPoints.forEach(p=>bounds.extend({lat:p[0],lng:p[1]}));bounds.extend(latLng);googleMap.fitBounds(bounds);}}else if(gpsMap){if(userMarker)userMarker.setLatLng(point);else userMarker=L.marker(point,{title:'Votre position'}).bindPopup('Votre position').addTo(gpsMap);const allPoints=[...mapPoints,point];if(allPoints.length===1)gpsMap.setView(point,12);else gpsMap.fitBounds(allPoints,{padding:[24,24],maxZoom:12});}btn.disabled=false;renderResults();},error=>{const messages={1:'Autorisez l’accès à la localisation dans les réglages du navigateur.',2:'Position indisponible. Vérifiez le GPS ou réessayez.',3:'La recherche a expiré. Réessayez.'};status.textContent=messages[error.code]||'Impossible d’obtenir la position.';btn.disabled=false;},{enableHighAccuracy:true,timeout:15000,maximumAge:30000});
});
typeSelect.addEventListener('change',renderResults);
</script>
<script src="assets/idle-timeout.js" defer></script>
</body></html>
