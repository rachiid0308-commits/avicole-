<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$user = require_auth();
$rows = list_farms($user, [], 'region', 'province');
$farms = array_map(static fn(array $farm): array => [
    'id' => (int)$farm['id'],
    'name' => (string)$farm['farm_name'],
    'region' => (string)$farm['region_name'],
    'province' => (string)$farm['province_name'],
    'status' => (string)$farm['authorization_status'],
    'type' => (string)$farm['farm_type'],
    'lat' => (float)$farm['latitude'],
    'lon' => (float)$farm['longitude'],
], $rows);
$farmsJson = json_encode($farms, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$googleMapsApiKeyJson = json_encode(google_maps_api_key(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light">
  <title>Carte — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title>
  <link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head>
<body class="page-map">
<header class="site-header">
  <a class="brand" href="index.php"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><span><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small><?=e(role_label($user))?></small></span></a>
  <?php require __DIR__ . '/navigation.php'; ?>
</header>
<main>
  <div class="card">
    <div class="page-heading"><div><h1>Carte des élevages avicoles</h1><p class="help">Cliquez sur un repère pour afficher l’unité et ouvrir ses coordonnées.</p></div><span class="count-pill"><?=count($farms)?> unité(s)</span></div>
    <div class="map-toolbar">
      <label for="map-type">Fond de carte<select id="map-type"><option value="roadmap">Plan</option><option value="hybrid">Hybride</option><option value="satellite" selected>Satellite</option></select></label>
      <p id="map-type-help" class="help">Choisissez Plan, Satellite ou Hybride. Les trois fonds Google Maps sont disponibles avec une clé API configurée.</p>
    </div>
    <ul class="map-legend" aria-label="Légende des types d’unités">
      <li><i class="dot dot-chair"></i>Chair</li><li><i class="dot dot-pondeuses"></i>Pondeuses</li><li><i class="dot dot-reproducteurs"></i>Reproducteurs</li><li><i class="dot dot-couvoir"></i>Couvoir</li><li><i class="dot dot-dinde"></i>Dinde</li><li><i class="dot dot-alert"></i>Distance sous le minimum</li>
    </ul>
    <div id="map" role="application" aria-label="Carte des élevages avicoles au Maroc"></div>
    <p id="map-attribution" class="map-attribution">Fond cartographique © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>.</p>
    <div id="map-empty" class="empty-state" hidden>Aucune unité avec des coordonnées à afficher dans votre périmètre.</div>
  </div>
</main>
<footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
'use strict';
const farms = <?=$farmsJson?>;
const googleMapsApiKey = <?=$googleMapsApiKeyJson?>;
const colors = {Chair:'#2d6cdf', Pondeuses:'#d89b16', Reproducteurs:'#7b4db1', Couvoir:'#16816b', Dinde:'#e36e32'};
const mapTypeSelect=document.getElementById('map-type'),mapTypeHelp=document.getElementById('map-type-help'),mapAttribution=document.getElementById('map-attribution');
let leafletMap=null,leafletLayer=null,googleMap=null;
function minDistance(a,b){if(a==='Reproducteurs'||b==='Reproducteurs')return 3;if(a==='Couvoir'||b==='Couvoir'||a==='Pondeuses'||b==='Pondeuses')return 2;return 1;}
function haversine(a,b){const r=Math.PI/180,dLat=(b.lat-a.lat)*r,dLon=(b.lon-a.lon)*r,x=Math.sin(dLat/2)**2+Math.cos(a.lat*r)*Math.cos(b.lat*r)*Math.sin(dLon/2)**2;return 6371.0088*2*Math.atan2(Math.sqrt(x),Math.sqrt(1-x));}
function detectViolations(records){
  const flags=records.map(()=>false),cellSizeKm=2,kilometresPerDegree=111.32,cosReference=Math.cos(31*Math.PI/180),grid=new Map();
  const projected=records.map(f=>({x:f.lon*kilometresPerDegree*cosReference,y:f.lat*kilometresPerDegree}));
  const key=(x,y)=>`${x}:${y}`;
  projected.forEach((p,i)=>{const x=Math.floor(p.x/cellSizeKm),y=Math.floor(p.y/cellSizeKm),k=key(x,y);if(!grid.has(k))grid.set(k,[]);grid.get(k).push(i);});
  for(let i=0;i<records.length;i++){
    const p=projected[i],cx=Math.floor(p.x/cellSizeKm),cy=Math.floor(p.y/cellSizeKm);
    for(let dx=-2;dx<=2;dx++)for(let dy=-2;dy<=2;dy++){
      for(const j of grid.get(key(cx+dx,cy+dy))||[]){if(j<=i)continue;if(haversine(records[i],records[j])<minDistance(records[i].type,records[j].type)){flags[i]=true;flags[j]=true;}}
    }
  }
  return flags;
}
const violationFlags=detectViolations(farms);
function escapeHtml(value){const node=document.createElement('span');node.textContent=String(value??'');return node.innerHTML;}
function popupContent(farm,violation){return `<strong>${escapeHtml(farm.name)}</strong><br>${escapeHtml(farm.type)} · ${escapeHtml(farm.status)}<br>${escapeHtml(farm.province)}, ${escapeHtml(farm.region)}<br>${farm.lat.toFixed(6)}, ${farm.lon.toFixed(6)}<br>${violation?'⚠ Distance sous le minimum réglementaire.<br>':''}<a target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q=${encodeURIComponent(farm.lat+','+farm.lon)}">Ouvrir dans Google Maps</a>`;}
function applyMapType(){
  const type=mapTypeSelect.value;
  if(googleMap){googleMap.setMapTypeId(({roadmap:google.maps.MapTypeId.ROADMAP,hybrid:google.maps.MapTypeId.HYBRID,satellite:google.maps.MapTypeId.SATELLITE})[type]);mapTypeHelp.textContent='Google Maps : plan, satellite et hybride.';mapAttribution.hidden=true;return;}
  if(!leafletMap)return;
  if(type==='hybrid'){mapTypeSelect.value='satellite';mapTypeHelp.textContent='Le mode hybride requiert une clé Google Maps API. Fond satellite Esri actif.';}
  if(leafletLayer)leafletMap.removeLayer(leafletLayer);
  if(mapTypeSelect.value==='roadmap'){
    leafletLayer=L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap contributors'}).addTo(leafletMap);
    mapAttribution.textContent='Fond cartographique © OpenStreetMap contributors.';
    if(!googleMapsApiKey)mapTypeHelp.textContent='Fonds disponibles : plan et satellite.';
  }else{
    leafletLayer=L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{maxZoom:19,attribution:'Tiles © Esri, Maxar, Earthstar Geographics, and the GIS User Community'}).addTo(leafletMap);
    mapAttribution.textContent='Imagerie © Esri, Maxar, Earthstar Geographics et contributeurs.';
    if(!googleMapsApiKey)mapTypeHelp.textContent='Fonds disponibles : plan et satellite.';
  }
  mapAttribution.hidden=false;
}
function initializeLeafletMap(){
  if(!window.L){document.getElementById('map-empty').hidden=false;document.getElementById('map-empty').textContent='La carte ne peut pas être chargée. Vérifiez la connexion Internet.';return;}
  const hybridOption=mapTypeSelect.querySelector('option[value="hybrid"]');hybridOption.disabled=true;mapTypeSelect.value='satellite';
  leafletMap=L.map('map',{scrollWheelZoom:false,preferCanvas:true,zoomControl:true}).setView([31.8,-6.5],5);
  const points=[];
  farms.forEach((farm,i)=>{
    const violation=violationFlags[i];
    const marker=L.circleMarker([farm.lat,farm.lon],{radius:8,color:violation?'#c62828':'#365647',weight:violation?3:1.5,fillColor:colors[farm.type]||'#536b5b',fillOpacity:.92});
    marker.bindPopup(popupContent(farm,violation)).addTo(leafletMap);points.push([farm.lat,farm.lon]);
  });
  if(points.length===1)leafletMap.setView(points[0],12);else if(points.length)leafletMap.fitBounds(points,{padding:[24,24],maxZoom:12});
  applyMapType();
}
function initializeGoogleMap(){
  googleMap=new google.maps.Map(document.getElementById('map'),{center:{lat:31.8,lng:-6.5},zoom:5,mapTypeId:google.maps.MapTypeId.SATELLITE,mapTypeControl:true,mapTypeControlOptions:{style:google.maps.MapTypeControlStyle.DROPDOWN_MENU,mapTypeIds:[google.maps.MapTypeId.ROADMAP,google.maps.MapTypeId.HYBRID,google.maps.MapTypeId.SATELLITE]},streetViewControl:false,fullscreenControl:true});
  const bounds=new google.maps.LatLngBounds();const points=[];
  farms.forEach((farm,i)=>{
    const violation=violationFlags[i];
    const position={lat:farm.lat,lng:farm.lon};points.push(position);bounds.extend(position);
    const marker=new google.maps.Marker({position,map:googleMap,title:farm.name,icon:{path:google.maps.SymbolPath.CIRCLE,scale:8,fillColor:colors[farm.type]||'#536b5b',fillOpacity:.92,strokeColor:violation?'#c62828':'#365647',strokeWeight:violation?3:1.5}});
    const info=new google.maps.InfoWindow({content:popupContent(farm,violation)});marker.addListener('click',()=>info.open({map:googleMap,anchor:marker}));
  });
  if(points.length===1){googleMap.setCenter(points[0]);googleMap.setZoom(12);}else if(points.length)googleMap.fitBounds(bounds);
  mapTypeSelect.querySelector('option[value="hybrid"]').disabled=false;
  applyMapType();
}
mapTypeSelect.addEventListener('change',applyMapType);
if(!farms.length){document.getElementById('map').hidden=true;document.getElementById('map-empty').hidden=false;}
else if(googleMapsApiKey){
  window.initGoogleMap=initializeGoogleMap;
  const googleScript=document.createElement('script');googleScript.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(googleMapsApiKey)+'&callback=initGoogleMap';googleScript.async=true;googleScript.onerror=initializeLeafletMap;document.head.appendChild(googleScript);
}else initializeLeafletMap();
</script>
<script src="assets/idle-timeout.js" defer></script>
</body></html>
