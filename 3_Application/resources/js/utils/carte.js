// Outils partagés par les cartes MapLibre : fonds de carte, surface des sites, motif de hachures.

// Fonds proposés en plus du plan vectoriel (tuiles raster gratuites, chargées seulement à la demande)
const FONDS = {
    plan: { label: 'Plan' },
    satellite: {
        label: 'Satellite',
        source: {
            type: 'raster',
            tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
            tileSize: 256,
            maxzoom: 19,
            attribution: 'Imagerie © Esri, Maxar, Earthstar Geographics',
        },
        // Les noms de lieux du plan restent lisibles par-dessus l'image
        sousLesNoms: true,
    },
    relief: {
        label: 'Relief',
        source: {
            type: 'raster',
            tiles: ['a', 'b', 'c'].map((s) => `https://${s}.tile.opentopomap.org/{z}/{x}/{y}.png`),
            tileSize: 256,
            maxzoom: 17,
            attribution: '© OpenTopoMap (CC-BY-SA), © contributeurs OpenStreetMap',
        },
        sousLesNoms: false,
    },
};

/**
 * Contrôle MapLibre « Plan / Satellite / Relief ».
 * `avant` : couche sous laquelle glisser un fond qui masque les noms (les contours des sites restent au-dessus).
 */
export class FondsControl {
    constructor({ avant } = {}) {
        this.avant = avant;
    }

    onAdd(map) {
        this.map = map;
        this.el = document.createElement('div');
        this.el.className = 'maplibregl-ctrl maplibregl-ctrl-group fonds-ctrl';
        this.el.setAttribute('role', 'group');
        this.el.setAttribute('aria-label', 'Fond de carte');
        this.boutons = Object.entries(FONDS).map(([cle, fond]) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = fond.label;
            b.setAttribute('aria-pressed', String(cle === 'plan'));
            b.addEventListener('click', () => this.choisir(cle));
            this.el.append(b);
            return [cle, b];
        });
        return this.el;
    }

    onRemove() {
        this.el.remove();
        this.map = undefined;
    }

    choisir(choix) {
        const map = this.map;
        for (const [cle, fond] of Object.entries(FONDS)) {
            if (!fond.source) continue;
            const id = `fond-${cle}`;
            if (cle === choix && !map.getLayer(id)) {
                if (!map.getSource(id)) map.addSource(id, fond.source);
                const avant = fond.sousLesNoms
                    ? map.getStyle().layers.find((l) => l.type === 'symbol')?.id
                    : (map.getLayer(this.avant) ? this.avant : undefined);
                map.addLayer({ id, type: 'raster', source: id }, avant);
            }
            if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', cle === choix ? 'visible' : 'none');
        }
        this.boutons.forEach(([cle, b]) => b.setAttribute('aria-pressed', String(cle === choix)));
    }
}

// Altitudes pour le relief 3D (Terrain Tiles d'AWS, encodage « terrarium », sans clé).
// Chargées seulement au premier passage en 3D.
export const SOURCE_ALTITUDES = {
    type: 'raster-dem',
    tiles: ['https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png'],
    encoding: 'terrarium',
    tileSize: 256,
    maxzoom: 15,
    attribution: 'Altitudes : Terrain Tiles (AWS, Mapzen, SRTM)',
};

// Surface d'un contour [{lat, lng}] en km² (formule sphérique, même calcul que turf/area)
export function surfaceKm2(points) {
    if (!Array.isArray(points) || points.length < 3) return 0;
    const R = 6378137;
    const rad = Math.PI / 180;
    let total = 0;
    for (let i = 0; i < points.length; i++) {
        const a = points[i];
        const b = points[(i + 1) % points.length];
        total += (Number(b.lng) - Number(a.lng)) * rad * (2 + Math.sin(Number(a.lat) * rad) + Math.sin(Number(b.lat) * rad));
    }
    return Math.abs((total * R * R) / 2) / 1e6;
}

export function formatSurface(km2) {
    if (!km2) return '';
    return `${km2.toLocaleString('fr-FR', { maximumFractionDigits: km2 < 100 ? 1 : 0 })} km²`;
}

// Hachures à 45°, équivalent cartographique des pastilles .hatch de l'interface
export function hachure([r, g, b], taille = 8) {
    const data = new Uint8Array(taille * taille * 4);
    for (let y = 0; y < taille; y++) {
        for (let x = 0; x < taille; x++) {
            const d = (x + y) % 4;
            data.set([r, g, b, d === 0 ? 230 : d === 1 ? 90 : 0], (y * taille + x) * 4);
        }
    }
    return { width: taille, height: taille, data };
}
