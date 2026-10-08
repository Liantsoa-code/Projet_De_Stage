<script setup>
import { onMounted, ref, watch, onUnmounted } from 'vue';
import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import MapboxDraw from '@mapbox/mapbox-gl-draw';
import '@mapbox/mapbox-gl-draw/dist/mapbox-gl-draw.css';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    editable: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);
const mapContainer = ref(null);
let map = null;
let draw = null;

// ── Charge le polygone existant dans la carte (mode edition ou affichage) ──
function loadExistingPolygon() {
    if (!props.modelValue || props.modelValue.length === 0) return;

    const coordinates = props.modelValue.map(p => [p.lng, p.lat]);

    // Fermer le polygone si necessaire
    if (coordinates.length > 0 &&
        (coordinates[0][0] !== coordinates[coordinates.length - 1][0] ||
         coordinates[0][1] !== coordinates[coordinates.length - 1][1])) {
        coordinates.push([...coordinates[0]]);
    }

    const geojson = {
        type: 'Feature',
        geometry: { type: 'Polygon', coordinates: [coordinates] }
    };

    if (props.editable && draw) {
        // Mode edition : charger dans draw ET passer en mode selection (pas draw_polygon)
        // pour ne pas ecraser accidentellement le polygone existant
        draw.deleteAll();
        draw.add(geojson);
        draw.changeMode('simple_select');
    } else {
        // Mode affichage seul
        if (map.getLayer('site-polygon-outline')) map.removeLayer('site-polygon-outline');
        if (map.getLayer('site-polygon-fill'))    map.removeLayer('site-polygon-fill');
        if (map.getSource('site-polygon'))         map.removeSource('site-polygon');

        map.addSource('site-polygon', { type: 'geojson', data: geojson });

        map.addLayer({
            id: 'site-polygon-fill',
            type: 'fill',
            source: 'site-polygon',
            paint: { 'fill-color': '#10b981', 'fill-opacity': 0.2 }
        });

        map.addLayer({
            id: 'site-polygon-outline',
            type: 'line',
            source: 'site-polygon',
            paint: { 'line-color': '#059669', 'line-width': 2 }
        });

        // Marqueur pulsant au centroide
        const rawCoords = props.modelValue.map(p => [p.lng, p.lat]);
        const cx = rawCoords.reduce((s, c) => s + c[0], 0) / rawCoords.length;
        const cy = rawCoords.reduce((s, c) => s + c[1], 0) / rawCoords.length;

        const container = document.createElement('div');
        container.style.width  = '0px';
        container.style.height = '0px';

        const el = document.createElement('div');
        el.className      = 'pulse-marker-view';
        el.style.position = 'absolute';
        el.style.top      = '-10px';
        el.style.left     = '-10px';
        el.innerHTML      = '<div class="pv-ring"></div><div class="pv-dot"></div>';
        container.appendChild(el);

        new maplibregl.Marker({ element: container }).setLngLat([cx, cy]).addTo(map);
    }

    // Zoomer sur le polygone
    if (coordinates.length > 0) {
        const bounds = coordinates.reduce((b, coord) => b.extend(coord),
            new maplibregl.LngLatBounds(coordinates[0], coordinates[0]));
        map.fitBounds(bounds, { padding: 50 });
    }
}

onMounted(() => {
    map = new maplibregl.Map({
        container: mapContainer.value,
        style: 'https://basemaps.cartocdn.com/gl/voyager-gl-style/style.json',
        center: [47.5079, -18.8792],
        zoom: 5,
        attributionControl: false
    });

    map.addControl(new maplibregl.NavigationControl(), 'top-right');

    if (props.editable) {
        draw = new MapboxDraw({
            displayControlsDefault: false,
            controls: { polygon: true, trash: true },
            // On demarre en simple_select si un polygone existe deja,
            // sinon en draw_polygon pour inviter l'utilisateur a dessiner
            defaultMode: (props.modelValue && props.modelValue.length > 0) ? 'simple_select' : 'draw_polygon'
        });
        map.addControl(draw, 'top-left');

        map.on('draw.create', updateModelValue);
        map.on('draw.delete', updateModelValue);
        map.on('draw.update', updateModelValue);
    }

    map.on('load', () => {
        loadExistingPolygon();
    });
});

onUnmounted(() => {
    if (map) map.remove();
});

const updateModelValue = () => {
    if (!draw) return;
    const data = draw.getAll();
    if (data.features.length > 0 && data.features[0].geometry.type === 'Polygon') {
        // Ignorer le dernier point qui ferme le polygone (identique au premier)
        const coords  = data.features[0].geometry.coordinates[0].slice(0, -1);
        const points  = coords.map(c => ({ lng: c[0], lat: c[1] }));
        emit('update:modelValue', points);
    } else {
        emit('update:modelValue', []);
    }
};

const clearMap = () => {
    if (draw) {
        draw.deleteAll();
        // Repasser en mode dessin apres effacement
        draw.changeMode('draw_polygon');
        emit('update:modelValue', []);
    }
};
</script>

<template>
    <div class="map-container relative rounded-md overflow-hidden shadow-sm">
        <div ref="mapContainer" class="map-view h-full w-full z-10"></div>

        <button
            v-if="editable && modelValue.length > 0"
            @click.prevent="clearMap"
            class="clear-btn absolute bottom-4 right-4 z-[20] btn-sm-delete"
        >
            Effacer et redessiner
        </button>

        <div v-if="editable && modelValue.length === 0" class="hint absolute bottom-4 left-4 z-[20] pointer-events-none">
             Utilisez l'outil polygone en haut à gauche pour délimiter le site.
        </div>

        <div v-if="editable && modelValue.length > 0" class="hint-ok absolute bottom-4 left-4 z-[20] pointer-events-none">
             ✓ Zone délimitée — déplacez les points pour modifier.
        </div>
    </div>
</template>

<style scoped>
.map-container {
    height: 100%;
    min-height: 350px;
}

.map-view {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 100%;
}

.hint {
    background: #ffffff;
    color: #374151;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid #d1d5db;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.hint-ok {
    background: #ecfdf5;
    color: #065f46;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid #a7f3d0;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
}

.clear-btn {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.clear-btn:hover { background: #fee2e2; }

/* Customisation mapbox-gl-draw pour le theme clair */
:deep(.mapboxgl-ctrl-group), :deep(.mapboxgl-ctrl) {
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    pointer-events: auto;
}

:deep(.mapboxgl-ctrl-group button) {
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
}

:deep(.mapboxgl-ctrl-group button:last-child) {
    border-bottom: none;
}

:deep(.mapboxgl-ctrl-icon) {
    filter: none;
}

:deep(.mapboxgl-ctrl-group button:hover) {
    background: #ecfdf5;
}
</style>

<style>
/* Marqueur pulsant pour le mode affichage (non-scoped car ajoute via JS) */
.pulse-marker-view {
    position: relative;
    width: 20px;
    height: 20px;
}

.pv-dot {
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

.pv-ring {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 28px; height: 28px;
    border-radius: 50%;
    border: 2px solid #10b981;
    animation: pv-pulse 1.8s ease-out infinite;
    z-index: 1;
}

@keyframes pv-pulse {
    0%   { transform: translate(-50%, -50%) scale(0.6); opacity: 0.9; }
    80%  { transform: translate(-50%, -50%) scale(1.8); opacity: 0; }
    100% { transform: translate(-50%, -50%) scale(1.8); opacity: 0; }
}
</style>
