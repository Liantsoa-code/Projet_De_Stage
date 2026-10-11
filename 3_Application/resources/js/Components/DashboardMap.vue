<script setup>
import { onMounted, ref, onUnmounted, watch } from 'vue';
import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { FondsControl, SOURCE_ALTITUDES, surfaceKm2, formatSurface, hachure } from '@/utils/carte.js';

const props = defineProps({
    sites: {
        type: Array,
        default: () => []
    },
    // Mode formulaire : un clic choisit le site au lieu d'ouvrir sa fiche
    selectable: { type: Boolean, default: false },
    selectedId: { type: Number, default: null },
    // État de chaque site sur les dates choisies : { [siteId]: 'bloquant' | 'avertissement' }.
    // null = pas de dates choisies (aucune coloration).
    etats: { type: Object, default: null },
    // Charge de travail : { [siteId]: { en_attente, retards, conflits } } -> cercles proportionnels
    charges: { type: Object, default: null },
});

const emit = defineEmits(['select']);

const ETATS = {
    bloquant: 'Période bloquante sur les dates choisies',
    avertissement: 'Période sensible sur les dates choisies',
};

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

const mapContainer = ref(null);
const pret = ref(false);
const en3d = ref(false);
let map = null;
let mapLoaded = false;
let layerEventsBound = false;
let polygones = [];
let selectionPrecedente = null;
let infobulle = null;
let infobulleId = null;
const marqueurs = new Map();
const sitesById = new Map();
const boundsById = new Map();

const etatDe = (id) => props.etats?.[id] ?? null;

function el(tag, className, text) {
    const node = document.createElement(tag);
    node.className = className;
    if (text) node.textContent = text;
    return node;
}

// Fiche construite en DOM : nom et description restent du texte, jamais du HTML.
// `fermer` : fourni pour les fiches ouvertes au clic, qui proposent alors la vue 3D.
function fiche(id, avecDescription, fermer) {
    const site = sitesById.get(id);
    const root = el('div', 'site-popup');
    root.append(el('strong', 'site-popup__title', site.nom));

    const infos = [];
    if (site.surface) infos.push(`Surface ≈ ${formatSurface(site.surface)}`);
    if (props.etats) infos.push(ETATS[etatDe(id)] ?? 'Ouvert sur les dates choisies');
    if (infos.length) root.append(el('p', 'site-popup__meta', infos.join(' · ')));

    if (props.charges) {
        const c = props.charges[id];
        const charge = c?.en_attente
            ? [pluriel(c.en_attente, 'dossier') + ' en attente', c.retards && `${c.retards} en retard`, c.conflits && `${c.conflits} en conflit`].filter(Boolean).join(' · ')
            : 'Aucun dossier en attente';
        root.append(el('p', 'site-popup__meta' + (c?.retards || c?.conflits ? ' site-popup__meta--alerte' : ''), charge));
    }

    if (avecDescription) {
        const d = site.description;
        root.append(el('p', 'site-popup__text', d ? d.substring(0, 160) + (d.length > 160 ? '…' : '') : 'Aucune description.'));
    }

    if (fermer && boundsById.has(id)) {
        const bouton = el('button', 'btn-sm-edit site-popup__3d', 'Voir en 3D');
        bouton.type = 'button';
        bouton.addEventListener('click', () => {
            fermer();
            voir3d(id);
        });
        root.append(bouton);
    }
    return root;
}

function montrerInfobulle(id, lngLat) {
    infobulle ??= new maplibregl.Popup({ closeButton: false, closeOnClick: false, offset: 14 });
    if (infobulleId !== id) {
        infobulle.setDOMContent(fiche(id, false));
        infobulleId = id;
    }
    infobulle.setLngLat(lngLat);
    if (!infobulle.isOpen()) infobulle.addTo(map);
}

function cacherInfobulle() {
    infobulle?.remove();
    infobulleId = null;
}

function focusSite(id) {
    const b = boundsById.get(id);
    if (!map || !b) return false;
    map.fitBounds(b, { padding: 60, maxZoom: 11, duration: 900 });
    return true;
}
// Cadre la carte sur l'ensemble des sites ; Madagascar entier si aucun polygone
function cadrerSites() {
    const all = [...boundsById.values()];
    if (!all.length) {
        map.fitBounds([[43.2, -25.7], [50.6, -11.9]], { padding: 30, duration: 1200 });
        return;
    }
    const bounds = [
        [Math.min(...all.map(b => b[0][0])), Math.min(...all.map(b => b[0][1]))],
        [Math.max(...all.map(b => b[1][0])), Math.max(...all.map(b => b[1][1]))],
    ];
    map.fitBounds(bounds, { padding: 70, maxZoom: 9, duration: 1200 });
}

// ── Vue 3D : relief, ombrage et caméra inclinée ──
function activer3d() {
    if (!map.getSource('altitudes')) {
        // Deux sources distinctes pour le relief et l'ombrage, comme le recommande MapLibre
        map.addSource('altitudes', SOURCE_ALTITUDES);
        map.addSource('altitudes-ombrage', SOURCE_ALTITUDES);
        map.addLayer({
            id: 'ombrage',
            type: 'hillshade',
            source: 'altitudes-ombrage',
            paint: { 'hillshade-exaggeration': 0.4, 'hillshade-shadow-color': '#4B5850' },
        }, map.getLayer('sites-fill') ? 'sites-fill' : undefined);
    }
    map.setLayoutProperty('ombrage', 'visibility', 'visible');
    map.setTerrain({ source: 'altitudes', exaggeration: 1.5 });
    map.setSky?.({ 'sky-color': '#DFEAF3', 'horizon-color': '#F2F4EF', 'sky-horizon-blend': 0.6 });
    en3d.value = true;
}

function voir3d(id) {
    const b = boundsById.get(id);
    if (!map || !mapLoaded || !b) return;
    activer3d();
    const camera = map.cameraForBounds(b, { padding: 40 });
    map.flyTo({ ...camera, zoom: Math.min(camera.zoom, 12.5), pitch: 60, bearing: -20, duration: 2200 });
}

function revenir2d() {
    map.setTerrain(null);
    map.setLayoutProperty('ombrage', 'visibility', 'none');
    map.easeTo({ pitch: 0, bearing: 0, duration: 800 });
    en3d.value = false;
}

defineExpose({ focusSite, voir3d });

function centroid(coordinates) {
    let pts = coordinates;
    if (pts.length > 1 && pts[0][0] === pts[pts.length - 1][0] && pts[0][1] === pts[pts.length - 1][1]) {
        pts = pts.slice(0, -1);
    }
    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    for (const c of pts) {
        if (c[0] < minX) minX = c[0];
        if (c[0] > maxX) maxX = c[0];
        if (c[1] < minY) minY = c[1];
        if (c[1] > maxY) maxY = c[1];
    }
    return [(minX + maxX) / 2, (minY + maxY) / 2];
}

function bindLayerEvents() {
    layerEventsBound = true;
    let hoveredId = null;
    map.on('mouseenter', 'sites-fill', (e) => {
        map.getCanvas().style.cursor = 'pointer';
        if (e.features.length > 0) {
            if (hoveredId !== null) map.setFeatureState({ source: 'sites-poly', id: hoveredId }, { hover: false });
            hoveredId = e.features[0].id;
            map.setFeatureState({ source: 'sites-poly', id: hoveredId }, { hover: true });
        }
    });
    map.on('mousemove', 'sites-fill', (e) => {
        if (props.selectable && e.features.length > 0) montrerInfobulle(e.features[0].id, e.lngLat);
    });
    map.on('mouseleave', 'sites-fill', () => {
        map.getCanvas().style.cursor = '';
        if (hoveredId !== null) map.setFeatureState({ source: 'sites-poly', id: hoveredId }, { hover: false });
        hoveredId = null;
        cacherInfobulle();
    });
    map.on('click', 'sites-fill', (e) => {
        const id = e.features[0].id;
        if (props.selectable) {
            emit('select', id);
            return;
        }
        const popup = new maplibregl.Popup({ offset: 12 });
        popup.setLngLat(e.lngLat)
            .setDOMContent(fiche(id, true, () => popup.remove()))
            .addTo(map);
    });
}

// Marqueur HTML avec animation pulse, présent pour TOUS les sites
function ajouterMarqueur(id, centre) {
    const container = document.createElement('div');
    container.style.width  = '0px';
    container.style.height = '0px';

    const marqueur = el('div', 'pulse-marker');
    marqueur.style.position = 'absolute';
    marqueur.style.top      = '-10px';
    marqueur.style.left     = '-10px';
    marqueur.innerHTML      = `<div class="pulse-ring"></div><div class="pulse-dot"></div>`;
    container.appendChild(marqueur);

    const marker = new maplibregl.Marker({ element: container }).setLngLat(centre);

    if (props.selectable) {
        marqueur.addEventListener('click', () => emit('select', id));
        marqueur.addEventListener('mouseenter', () => montrerInfobulle(id, centre));
        marqueur.addEventListener('mouseleave', cacherInfobulle);
    } else {
        // Contenu recalculé à chaque ouverture : il dépend de la date choisie
        const popup = new maplibregl.Popup({ offset: 18, closeButton: true });
        const remplir = () => popup.setDOMContent(fiche(id, true, () => popup.remove()));
        remplir();
        popup.on('open', remplir);
        marker.setPopup(popup);
    }

    marker.addTo(map);
    marqueurs.set(id, { marker, el: marqueur });
}

function placeSitesOnMap() {
    if (!map || !mapLoaded) return;

    // Nettoyer les anciens marqueurs, couches et sources
    marqueurs.forEach(({ marker }) => marker.remove());
    marqueurs.clear();
    boundsById.clear();
    sitesById.clear();
    cacherInfobulle();
    ['sites-charge', 'sites-outline', 'sites-hatch', 'sites-fill'].forEach((id) => map.getLayer(id) && map.removeLayer(id));
    ['sites-poly', 'sites-charge'].forEach((id) => map.getSource(id) && map.removeSource(id));
    polygones = [];

    if (!props.sites || props.sites.length === 0) return;

    props.sites.forEach(site => {
        let coords = site.polygone_coordonnees;
        if (typeof coords === 'string') {
            try { coords = JSON.parse(coords); } catch(e) { coords = null; }
        }
        const valide = Array.isArray(coords);

        let centre;
        // Cas 1 : polygone valide (>= 3 pts)
        if (valide && coords.length >= 3) {
            const coordinates = coords.map(p => [parseFloat(p.lng), parseFloat(p.lat)]);
            // Fermer le polygone si besoin
            if (coordinates[0][0] !== coordinates[coordinates.length - 1][0] ||
                coordinates[0][1] !== coordinates[coordinates.length - 1][1]) {
                coordinates.push([...coordinates[0]]);
            }
            centre = centroid(coordinates);
            const lngs = coordinates.map(c => c[0]), lats = coordinates.map(c => c[1]);
            boundsById.set(site.id, [[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]]);
            polygones.push({
                type: 'Feature',
                id: site.id,  // Requis par MapLibre pour setFeatureState
                properties: {},
                geometry: { type: 'Polygon', coordinates: [coordinates] }
            });

        // Cas 2 : 1 ou 2 points -> on prend le premier point comme centre du marqueur
        } else if (valide && coords.length >= 1) {
            centre = [parseFloat(coords[0].lng), parseFloat(coords[0].lat)];

        // Cas 3 : aucune coordonnee -> centre de Madagascar par defaut
        } else {
            centre = [47.5079, -20.0];
        }

        sitesById.set(site.id, {
            nom: site.nom,
            description: site.description || '',
            surface: valide ? surfaceKm2(coords) : 0,
            centre,
        });
        ajouterMarqueur(site.id, centre);
    });

    // Polygones : remplissage coloré selon l'état, hachures des périodes, contour
    if (polygones.length > 0) {
        map.addSource('sites-poly', {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: polygones }
        });
        const actif = ['any', ['boolean', ['feature-state', 'hover'], false], ['boolean', ['feature-state', 'selected'], false]];
        map.addLayer({
            id: 'sites-fill',
            type: 'fill',
            source: 'sites-poly',
            paint: {
                'fill-color': ['match', ['get', 'etat'], 'bloquant', '#C80000', 'avertissement', '#9A5F0C', '#00683A'],
                'fill-opacity': ['case', actif, 0.35, 0.18]
            }
        });
        map.addLayer({
            id: 'sites-hatch',
            type: 'fill',
            source: 'sites-poly',
            filter: ['has', 'etat'],
            paint: { 'fill-pattern': ['match', ['get', 'etat'], 'bloquant', 'hachure-bloquant', 'hachure-avertissement'] }
        });
        map.addLayer({
            id: 'sites-outline',
            type: 'line',
            source: 'sites-poly',
            paint: {
                'line-color': ['match', ['get', 'etat'], 'bloquant', '#C80000', 'avertissement', '#9A5F0C', '#00532E'],
                'line-width': ['case', ['boolean', ['feature-state', 'selected'], false], 3.5, 2]
            }
        });

        // Les écouteurs liés à la couche survivent à sa suppression : on ne les pose qu'une fois
        if (!layerEventsBound) bindLayerEvents();
    }

    // Charge : cercle dont la surface suit le nombre de dossiers en attente, contour rouge si retard ou conflit.
    // Les marqueurs HTML restent au-dessus, au centre du cercle.
    if (props.charges) {
        map.addSource('sites-charge', { type: 'geojson', data: donneesCharges() });
        map.addLayer({
            id: 'sites-charge',
            type: 'circle',
            source: 'sites-charge',
            filter: ['>', ['get', 'n'], 0],
            paint: {
                'circle-radius': ['interpolate', ['linear'], ['sqrt', ['get', 'n']], 1, 14, 5, 34],
                'circle-color': '#1D5C8A',
                'circle-opacity': 0.22,
                'circle-stroke-color': ['case', ['get', 'alerte'], '#C80000', '#1D5C8A'],
                'circle-stroke-width': ['case', ['get', 'alerte'], 2.5, 1.2],
            }
        });
    }

    selectionPrecedente = null;
    appliquerEtats();
    appliquerSelection();
}

function donneesCharges() {
    const features = [...sitesById].map(([id, site]) => {
        const c = props.charges?.[id];
        return {
            type: 'Feature',
            properties: { n: c?.en_attente ?? 0, alerte: Boolean(c?.retards || c?.conflits) },
            geometry: { type: 'Point', coordinates: site.centre },
        };
    });
    return { type: 'FeatureCollection', features };
}

// Couleurs selon les dates choisies, sans reconstruire la carte
function appliquerEtats() {
    if (!map || !mapLoaded) return;
    polygones.forEach((f) => {
        const etat = etatDe(f.id);
        if (etat) f.properties.etat = etat;
        else delete f.properties.etat;
    });
    map.getSource('sites-poly')?.setData({ type: 'FeatureCollection', features: polygones });
    marqueurs.forEach(({ el: m }, id) => {
        m.classList.toggle('pulse-marker--bloquant', etatDe(id) === 'bloquant');
        m.classList.toggle('pulse-marker--avertissement', etatDe(id) === 'avertissement');
    });
    infobulleId = null; // le texte de l'infobulle dépend des dates
}

function appliquerSelection() {
    if (!map || !mapLoaded) return;
    const id = props.selectedId;
    if (map.getSource('sites-poly')) {
        if (selectionPrecedente !== null) map.setFeatureState({ source: 'sites-poly', id: selectionPrecedente }, { selected: false });
        if (id !== null) map.setFeatureState({ source: 'sites-poly', id }, { selected: true });
    }
    selectionPrecedente = id;
    marqueurs.forEach(({ el: m }, siteId) => m.classList.toggle('pulse-marker--selected', siteId === id));
}

onMounted(() => {
    map = new maplibregl.Map({
        container: mapContainer.value,
        style: 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json',
        center: [30.0, -5.0],
        zoom: 2.5,
        attributionControl: false
    });

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
    map.addControl(new maplibregl.FullscreenControl(), 'top-right');
    map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');

    map.on('load', () => {
        map.addImage('hachure-bloquant', hachure([200, 0, 0]));
        map.addImage('hachure-avertissement', hachure([154, 95, 12]));
        map.addControl(new FondsControl({ avant: 'sites-fill' }), 'top-left');

        mapLoaded = true;
        pret.value = true;
        placeSitesOnMap();
        if (!focusSite(props.selectedId)) cadrerSites();
    });
});

// Inertia remplace les props à chaque visite : une surveillance par référence suffit
watch(() => props.sites, placeSitesOnMap);
watch(() => props.etats, appliquerEtats);
watch(() => props.charges, () => map?.getSource('sites-charge')?.setData(donneesCharges()));
watch(() => props.selectedId, (id) => {
    appliquerSelection();
    focusSite(id);
});

onUnmounted(() => {
    marqueurs.forEach(({ marker }) => marker.remove());
    if (map) map.remove();
});
</script>

<template>
    <div class="map-wrapper">
        <div ref="mapContainer" class="map-inner"></div>
        <div v-if="!pret" class="sk map-skeleton" aria-hidden="true"></div>
        <div v-if="en3d" class="vue3d">
            <span class="vue3d__aide">Clic droit + glisser pour pivoter</span>
            <button type="button" class="btn-sm-edit" @click="revenir2d">Revenir en 2D</button>
        </div>
    </div>
</template>

<style>
.map-wrapper {
    position: relative;
    height: 100%;
    width: 100%;
    min-height: 500px;
    border-radius: var(--r);
    overflow: hidden;
    border: 1px solid var(--forest-soft);
    box-shadow: none;
    container-type: inline-size;
}
.map-skeleton { position: absolute; inset: 0; z-index: 2; border-radius: 0; }
.vue3d {
    position: absolute;
    bottom: 34px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 5px 5px 12px;
    background: #ffffff;
    border: 1px solid var(--rule);
    border-radius: var(--r);
    font-size: 12px;
    color: var(--ink-3);
    white-space: nowrap;
}
@container (max-width: 480px) {
    .vue3d { padding-left: 5px; }
    .vue3d__aide { display: none; }
}
.site-popup__3d { margin-top: 10px; }
.map-inner {
    height: 100%;
    width: 100%;
    min-height: 500px;
}
.maplibregl-popup-content {
    background: #ffffff !important;
    border: 1px solid var(--rule) !important;
    border-radius: var(--r) !important;
    box-shadow: none;
    color: var(--ink-2) !important;
    font-family: var(--f-body);
    padding: 14px 16px !important;
    min-width: 200px;
}
.maplibregl-popup-tip { border-top-color: #ffffff !important; }
.site-popup__title { display: block; margin-bottom: 6px; font: 600 11px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--forest); }
.site-popup__meta { margin: 0 0 6px; font-size: 12px; font-weight: 600; color: var(--ink); }
.site-popup__meta:last-child { margin-bottom: 0; }
.site-popup__meta--alerte { color: var(--signal); }
.site-popup__text { margin: 0; font-size: 12.5px; line-height: 1.5; color: var(--ink-2); }
.maplibregl-popup-close-button { color: var(--ink-4) !important; font-size: 18px !important; right: 8px !important; top: 6px !important; }
.maplibregl-popup-close-button:hover { color: var(--ink) !important; background: transparent !important; }
.maplibregl-ctrl-scale { background: rgba(255,255,255,0.8) !important; border-color: var(--ink-4) !important; color: var(--ink-2) !important; font-size: 10px !important; font-family: var(--f-body); }
.maplibregl-ctrl-group { background: #ffffff !important; border: 1px solid var(--rule) !important; border-radius: var(--r) !important; overflow: hidden; box-shadow: none; }
.maplibregl-ctrl-group button:hover { background: var(--forest-soft) !important; }

/* Choix du fond de carte */
.fonds-ctrl { display: flex; }
.fonds-ctrl button { width: auto; height: 29px; padding: 0 10px; font: 600 11.5px/1 var(--f-body); color: var(--ink-2); }
.fonds-ctrl button + button { border-top: 0; border-left: 1px solid var(--rule); }
.fonds-ctrl button[aria-pressed="true"],
.fonds-ctrl button[aria-pressed="true"]:hover { background: var(--forest) !important; color: #ffffff; }

.pulse-marker { position: relative; width: 20px; height: 20px; cursor: pointer; --marqueur: var(--forest); }
.pulse-marker--avertissement { --marqueur: var(--ochre); }
.pulse-marker--bloquant { --marqueur: var(--signal); }
.pulse-dot {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 12px; height: 12px;
    border-radius: 50%;
    background: var(--marqueur);
    border: 2.5px solid #ffffff;
    box-shadow: none;
    z-index: 2;
}
.pulse-marker--selected .pulse-dot { width: 16px; height: 16px; box-shadow: 0 0 0 2px var(--marqueur); }
.pulse-ring {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 28px; height: 28px;
    border-radius: 50%;
    border: 2px solid var(--marqueur);
    animation: pulse-ring 1.8s ease-out infinite;
    z-index: 1;
}
@keyframes pulse-ring {
    0%   { transform: translate(-50%, -50%) scale(0.6); opacity: 0.9; }
    80%  { transform: translate(-50%, -50%) scale(1.8); opacity: 0; }
    100% { transform: translate(-50%, -50%) scale(1.8); opacity: 0; }
}
</style>
