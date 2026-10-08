<script setup>
import { onMounted, ref, onUnmounted, watch } from 'vue';
import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const props = defineProps({
    sites: {
        type: Array,
        default: () => []
    }
});

const mapContainer = ref(null);
let map = null;
let markers = [];
let mapLoaded = false;

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

function placeSitesOnMap() {
    if (!map || !mapLoaded) return;

    // Nettoyer les anciens marqueurs
    markers.forEach(m => m.remove());
    markers = [];

    // Supprimer les anciennes sources/layers si elles existent deja
    if (map.getLayer('sites-outline')) map.removeLayer('sites-outline');
    if (map.getLayer('sites-fill'))    map.removeLayer('sites-fill');
    if (map.getSource('sites-poly'))   map.removeSource('sites-poly');

    if (!props.sites || props.sites.length === 0) return;

    const polygonFeatures = [];
    const pointFeatures   = [];

    props.sites.forEach(site => {
        let coords = site.polygone_coordonnees;
        if (typeof coords === 'string') {
            try { coords = JSON.parse(coords); } catch(e) { coords = null; }
        }

        let markerCenter = null;

        // Cas 1 : polygone valide (>= 3 pts)
        if (coords && Array.isArray(coords) && coords.length >= 3) {
            const coordinates = coords.map(p => [parseFloat(p.lng), parseFloat(p.lat)]);
            // Fermer le polygone si besoin
            if (coordinates[0][0] !== coordinates[coordinates.length - 1][0] ||
                coordinates[0][1] !== coordinates[coordinates.length - 1][1]) {
                coordinates.push([...coordinates[0]]);
            }
            markerCenter = centroid(coordinates);
            polygonFeatures.push({
                type: 'Feature',
                id: site.id,  // Requis par MapLibre pour setFeatureState
                properties: { id: site.id, nom: site.nom, description: site.description || '' },
                geometry: { type: 'Polygon', coordinates: [coordinates] }
            });

        // Cas 2 : 1 ou 2 points -> on prend le premier point comme centre du marqueur
        } else if (coords && Array.isArray(coords) && coords.length >= 1) {
            markerCenter = [parseFloat(coords[0].lng), parseFloat(coords[0].lat)];

        // Cas 3 : aucune coordonnee -> centre de Madagascar par defaut
        } else {
            markerCenter = [47.5079, -20.0];
        }

        // Le marqueur pulsant est TOUJOURS ajoute, peu importe les coordonnees
        pointFeatures.push({
            type: 'Feature',
            properties: { id: site.id, nom: site.nom, description: site.description || '' },
            geometry: { type: 'Point', coordinates: markerCenter }
        });
    });

    // Polygones (remplissage + contour)
    if (polygonFeatures.length > 0) {
        map.addSource('sites-poly', {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: polygonFeatures }
        });
        map.addLayer({
            id: 'sites-fill',
            type: 'fill',
            source: 'sites-poly',
            paint: {
                'fill-color': '#10b981',
                'fill-opacity': ['case', ['boolean', ['feature-state', 'hover'], false], 0.35, 0.18]
            }
        });
        map.addLayer({
            id: 'sites-outline',
            type: 'line',
            source: 'sites-poly',
            paint: { 'line-color': '#059669', 'line-width': 2 }
        });

        let hoveredId = null;
        map.on('mouseenter', 'sites-fill', (e) => {
            map.getCanvas().style.cursor = 'pointer';
            if (e.features.length > 0) {
                if (hoveredId !== null) map.setFeatureState({ source: 'sites-poly', id: hoveredId }, { hover: false });
                hoveredId = e.features[0].id;
                map.setFeatureState({ source: 'sites-poly', id: hoveredId }, { hover: true });
            }
        });
        map.on('mouseleave', 'sites-fill', () => {
            map.getCanvas().style.cursor = '';
            if (hoveredId !== null) map.setFeatureState({ source: 'sites-poly', id: hoveredId }, { hover: false });
            hoveredId = null;
        });
        map.on('click', 'sites-fill', (e) => {
            const p = e.features[0].properties;
            new maplibregl.Popup({ offset: 12 })
                .setLngLat(e.lngLat)
                .setHTML(
                    `<div>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="width:10px;height:10px;border-radius:50%;background:#10b981;flex-shrink:0;display:inline-block;"></span>
                            <span style="font-size:14px;font-weight:700;color:#065f46;">${p.nom}</span>
                        </div>
                        <p style="font-size:12px;color:#4b5563;margin:0;line-height:1.5;">${p.description ? p.description.substring(0, 120) + (p.description.length > 120 ? '\u2026' : '') : 'Aucune description.'}</p>
                    </div>`
                )
                .addTo(map);
        });
    }

    // Marqueurs HTML avec animation pulse (TOUS les sites, toujours)
    pointFeatures.forEach(f => {
        const [lng, lat] = f.geometry.coordinates;
        const nom         = f.properties.nom;
        const description = f.properties.description;

        const container = document.createElement('div');
        container.style.width  = '0px';
        container.style.height = '0px';

        const el = document.createElement('div');
        el.className      = 'pulse-marker';
        el.style.position = 'absolute';
        el.style.top      = '-10px';
        el.style.left     = '-10px';
        el.innerHTML      = `<div class="pulse-ring"></div><div class="pulse-dot"></div>`;
        container.appendChild(el);

        const popup = new maplibregl.Popup({ offset: 18, closeButton: true })
            .setHTML(
                `<div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <span style="width:10px;height:10px;border-radius:50%;background:#10b981;flex-shrink:0;display:inline-block;"></span>
                        <span style="font-size:14px;font-weight:700;color:#065f46;">${nom}</span>
                    </div>
                    <p style="font-size:12px;color:#4b5563;margin:0;line-height:1.5;">${description ? description.substring(0, 120) + (description.length > 120 ? '\u2026' : '') : 'Aucune description.'}</p>
                </div>`
            );

        const marker = new maplibregl.Marker({ element: container })
            .setLngLat([lng, lat])
            .setPopup(popup)
            .addTo(map);

        markers.push(marker);
    });
}

onMounted(() => {
    map = new maplibregl.Map({
        container: mapContainer.value,
        style: 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json',
        center: [30.0, -5.0],
        zoom: 2.5,
        attributionControl: false
    });

    map.addControl(new maplibregl.NavigationControl(), 'top-right');
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');

    map.on('load', () => {
        mapLoaded = true;
        map.flyTo({
            center: [47.5079, -20.0],
            zoom: 5.2,
            speed: 0.55,
            curve: 1.6,
            easing: t => 1 - Math.pow(1 - t, 3)
        });
        placeSitesOnMap();
    });
});

// Reagir si la liste des sites change apres le montage (navigation Inertia, etc.)
watch(() => props.sites, () => {
    placeSitesOnMap();
}, { deep: true });

onUnmounted(() => {
    markers.forEach(m => m.remove());
    if (map) map.remove();
});
</script>

<template>
    <div class="map-wrapper">
        <div ref="mapContainer" class="map-inner"></div>
    </div>
</template>

<style>
.map-wrapper {
    height: 100%;
    width: 100%;
    min-height: 500px;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #d1fae5;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.08);
}
.map-inner {
    height: 100%;
    width: 100%;
    min-height: 500px;
}
.maplibregl-popup-content {
    background: #ffffff !important;
    border: 1px solid #e7eae9 !important;
    border-radius: 14px !important;
    box-shadow: 0 2px 6px rgba(16, 24, 40, 0.04), 0 24px 56px -16px rgba(16, 24, 40, 0.25) !important;
    color: #374151 !important;
    font-family: 'Inter', sans-serif !important;
    padding: 14px 16px !important;
    min-width: 200px;
}
.maplibregl-popup-tip { border-top-color: #ffffff !important; }
.maplibregl-popup-close-button { color: #9ca3af !important; font-size: 18px !important; right: 8px !important; top: 6px !important; }
.maplibregl-popup-close-button:hover { color: #111827 !important; background: transparent !important; }
.maplibregl-ctrl-scale { background: rgba(255,255,255,0.8) !important; border-color: #9ca3af !important; color: #374151 !important; font-size: 10px !important; font-family: 'Inter', sans-serif !important; }
.maplibregl-ctrl-group { background: #ffffff !important; border: 1px solid #e7eae9 !important; border-radius: 10px !important; overflow: hidden; box-shadow: 0 1px 3px rgba(16,24,40,0.04), 0 8px 20px -8px rgba(16,24,40,0.18) !important; }
.maplibregl-ctrl-group button:hover { background: #f0fdf4 !important; }
.pulse-marker { position: relative; width: 20px; height: 20px; cursor: pointer; }
.pulse-dot {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 12px; height: 12px;
    border-radius: 50%;
    background: #10b981;
    border: 2.5px solid #ffffff;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.5);
    z-index: 2;
}
.pulse-ring {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 28px; height: 28px;
    border-radius: 50%;
    border: 2px solid #10b981;
    animation: pulse-ring 1.8s ease-out infinite;
    z-index: 1;
}
@keyframes pulse-ring {
    0%   { transform: translate(-50%, -50%) scale(0.6); opacity: 0.9; }
    80%  { transform: translate(-50%, -50%) scale(1.8); opacity: 0; }
    100% { transform: translate(-50%, -50%) scale(1.8); opacity: 0; }
}
</style>
