<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from 'vue';
import { formatDate } from '@/utils/statut.js';

// Carte (MapLibre) chargée seulement quand elle est affichée
const DashboardMap = defineAsyncComponent(() => import('@/Components/DashboardMap.vue'));

defineProps({
    titre: { type: String, required: true },
});

const page = usePage();
const sites = computed(() => page.props.sites || []);
const periodes = computed(() => page.props.periodes || []);
const chargeSites = computed(() => page.props.charge_sites || {});

// ── Restrictions à une date choisie ──
const HORIZON_JOURS = 365;
const decalage = ref(0);
const aujourdhui = new Date();
aujourdhui.setHours(0, 0, 0, 0);
const versIso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const jourDecale = (n) => new Date(aujourdhui.getFullYear(), aujourdhui.getMonth(), aujourdhui.getDate() + n);
const jourChoisi = computed(() => jourDecale(decalage.value));
const jourIso = computed(() => versIso(jourChoisi.value));
const jourLabel = computed(() => jourChoisi.value.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
const isoMin = versIso(aujourdhui);
const isoMax = versIso(jourDecale(HORIZON_JOURS));
const ecartJours = (iso) => Math.round((new Date(`${iso}T00:00:00`) - aujourdhui) / 86400000);
const borner = (n) => Math.min(HORIZON_JOURS, Math.max(0, n));

// Saisie directe d'une date précise (champ date)
const dateSaisie = computed({
    get: () => jourIso.value,
    set: (iso) => {
        if (iso) decalage.value = borner(ecartJours(iso));
    },
});
const decaler = (n) => {
    decalage.value = borner(decalage.value + n);
};

// Le niveau bloquant l'emporte quand plusieurs périodes couvrent le même jour
const etatsDuJour = computed(() => {
    const etats = {};
    periodes.value
        .filter((p) => p.date_debut <= jourIso.value && p.date_fin >= jourIso.value)
        .forEach((p) => {
            etats[p.site_id] = p.niveau_restriction === 'bloquant' ? 'bloquant' : (etats[p.site_id] ?? 'avertissement');
        });
    return etats;
});
const nbBloquants = computed(() => Object.values(etatsDuJour.value).filter((e) => e === 'bloquant').length);
const nbSensibles = computed(() => Object.values(etatsDuJour.value).filter((e) => e === 'avertissement').length);

// Débuts de période (en jours depuis aujourd'hui) pour sauter d'une période à l'autre
const debuts = computed(() => [...new Set(periodes.value
    .filter((p) => ecartJours(p.date_debut) <= HORIZON_JOURS)
    .map((p) => borner(ecartJours(p.date_debut))))]
    .sort((a, b) => a - b));
const periodePrecedente = computed(() => [...debuts.value].reverse().find((n) => n < decalage.value));
const periodeSuivante = computed(() => debuts.value.find((n) => n > decalage.value));

// Bandes colorées sous le curseur : niveau le plus fort, tous sites confondus, pour chaque jour
const COULEURS_PISTE = ['var(--sunk)', 'var(--ochre-line)', 'var(--signal)'];
const piste = computed(() => {
    const niveaux = new Array(HORIZON_JOURS + 1).fill(0);
    periodes.value.forEach((p) => {
        if (ecartJours(p.date_debut) > HORIZON_JOURS) return;
        const niveau = p.niveau_restriction === 'bloquant' ? 2 : 1;
        for (let n = borner(ecartJours(p.date_debut)); n <= borner(ecartJours(p.date_fin)); n++) {
            niveaux[n] = Math.max(niveaux[n], niveau);
        }
    });
    const pas = 100 / (HORIZON_JOURS + 1);
    const arrets = [];
    niveaux.forEach((niveau, n) => {
        if (n > 0 && niveau === niveaux[n - 1]) return;
        if (n > 0) arrets.push(`${COULEURS_PISTE[niveaux[n - 1]]} ${(n * pas).toFixed(3)}%`);
        arrets.push(`${COULEURS_PISTE[niveau]} ${(n * pas).toFixed(3)}%`);
    });
    arrets.push(`${COULEURS_PISTE[niveaux[HORIZON_JOURS]]} 100%`);
    return `linear-gradient(to right, ${arrets.join(', ')})`;
});

// Repères de mois sous le curseur (l'année à la place de « janv. »)
const mois = computed(() => {
    const reperes = [];
    for (let d = new Date(aujourdhui.getFullYear(), aujourdhui.getMonth() + 1, 1); ecartJours(versIso(d)) <= HORIZON_JOURS; d.setMonth(d.getMonth() + 1)) {
        const janvier = d.getMonth() === 0;
        reperes.push({
            cle: versIso(d),
            position: (ecartJours(versIso(d)) / HORIZON_JOURS) * 100,
            libelle: janvier ? String(d.getFullYear()) : d.toLocaleDateString('fr-FR', { month: 'short' }).replace('.', ''),
            janvier,
        });
    }
    return reperes;
});
</script>

<template>
    <section class="panel">
        <header class="panel__head">
            <h2>{{ titre }}</h2>
            <small>{{ sites.length }} {{ sites.length > 1 ? 'aires protégées' : 'aire protégée' }}</small>
        </header>
        <div class="map-box">
            <DashboardMap :sites="sites" :etats="etatsDuJour" :charges="chargeSites" />
        </div>
        <div class="map-cal">
            <div class="map-cal__row">
                <span class="map-cal__label">Restrictions {{ decalage === 0 ? "aujourd'hui" : 'le ' + jourLabel }}</span>
                <span class="map-cal__resume">
                    <template v-if="nbBloquants || nbSensibles">
                        <span v-if="nbBloquants" class="t-signal">{{ nbBloquants }} bloqué{{ nbBloquants > 1 ? 's' : '' }}</span>
                        <template v-if="nbBloquants && nbSensibles"> · </template>
                        <span v-if="nbSensibles" class="t-ochre">{{ nbSensibles }} sensible{{ nbSensibles > 1 ? 's' : '' }}</span>
                    </template>
                    <template v-else>Toutes les aires protégées ouvertes</template>
                </span>
            </div>

            <div class="map-cal__row">
                <div class="map-cal__jour">
                    <button type="button" class="map-cal__pas" :disabled="decalage === 0" aria-label="Jour précédent" @click="decaler(-1)">‹</button>
                    <input v-model="dateSaisie" type="date" class="gf-input map-cal__date" :min="isoMin" :max="isoMax" aria-label="Date affichée sur la carte" />
                    <button type="button" class="map-cal__pas" :disabled="decalage === HORIZON_JOURS" aria-label="Jour suivant" @click="decaler(1)">›</button>
                </div>
                <div class="map-cal__sauts">
                    <button type="button" class="filter-reset" :disabled="decalage === 0" @click="decalage = 0">Aujourd'hui</button>
                    <button type="button" class="filter-reset" :disabled="periodePrecedente === undefined" @click="decalage = periodePrecedente">‹ Période précédente</button>
                    <button type="button" class="filter-reset" :disabled="periodeSuivante === undefined" @click="decalage = periodeSuivante">
                        Période suivante<template v-if="periodeSuivante !== undefined"> : {{ formatDate(versIso(jourDecale(periodeSuivante))) }}</template> ›
                    </button>
                </div>
            </div>

            <div class="piste">
                <div class="piste__bandes" :style="{ background: piste }" aria-hidden="true"></div>
                <input
                    v-model.number="decalage"
                    type="range"
                    min="0"
                    :max="HORIZON_JOURS"
                    step="1"
                    class="piste__curseur"
                    aria-label="Faire défiler les jours"
                    :aria-valuetext="jourLabel"
                />
                <div class="piste__mois" aria-hidden="true">
                    <span v-for="m in mois" :key="m.cle" :style="{ left: m.position + '%' }" :class="{ 'piste__mois--an': m.janvier }">{{ m.libelle }}</span>
                </div>
            </div>

            <ul class="map-legende" aria-label="Légende de la carte">
                <li><span class="hatch"></span>Période sensible</li>
                <li><span class="hatch hatch--block"></span>Période bloquante</li>
                <li><span class="leg-charge"></span>Dossiers en attente (taille = nombre)</li>
                <li><span class="leg-charge leg-charge--alerte"></span>Retard ou conflit</li>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.map-box { height: clamp(420px, 52vh, 560px); overflow: hidden; }
.map-box :deep(.map-wrapper) { height: 100%; min-height: 0; border: 0; border-radius: 0; }
.map-cal { display: grid; gap: 10px; padding: 12px 24px 14px; }
.map-cal__row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 6px 12px; }
.map-cal__label { font-size: 13.5px; font-weight: 600; color: var(--ink); }
.map-cal__resume { font-size: 12.5px; font-weight: 600; color: var(--ink-3); }
.map-cal__jour { display: inline-flex; align-items: center; gap: 4px; }
.map-cal__date { width: auto; min-height: 34px; padding: 4px 8px; }
.map-cal__pas {
    width: 34px; height: 34px;
    border: 1px solid var(--rule); border-radius: var(--r-sm);
    background: var(--surface); color: var(--ink-2);
    font-size: 18px; line-height: 1; cursor: pointer;
}
.map-cal__pas:hover:not(:disabled) { border-color: var(--forest); color: var(--forest); }
.map-cal__pas:disabled { opacity: 0.45; cursor: default; }
.map-cal__sauts { display: flex; flex-wrap: wrap; gap: 2px; }
.map-cal .filter-reset { padding: 4px 6px; }
.map-cal .filter-reset:disabled { opacity: 0.45; cursor: default; background: none; }

/* Curseur : la piste montre où tombent les périodes (ocre = sensible, rouge = bloquant) */
.piste { position: relative; padding-bottom: 16px; }
.piste__bandes { position: absolute; top: 7px; left: 9px; right: 9px; height: 8px; border-radius: 4px; }
.piste__curseur { position: relative; display: block; width: 100%; height: 22px; margin: 0; background: transparent; cursor: pointer; -webkit-appearance: none; appearance: none; }
.piste__curseur::-webkit-slider-runnable-track { height: 22px; background: transparent; }
.piste__curseur::-moz-range-track { height: 22px; background: transparent; }
.piste__curseur::-webkit-slider-thumb {
    -webkit-appearance: none; appearance: none;
    width: 18px; height: 18px; margin-top: 2px;
    border: 3px solid var(--surface); border-radius: 50%;
    background: var(--forest); box-shadow: 0 0 0 1px var(--forest), 0 1px 3px rgb(0 0 0 / 0.25);
}
.piste__curseur::-moz-range-thumb {
    width: 12px; height: 12px;
    border: 3px solid var(--surface); border-radius: 50%;
    background: var(--forest); box-shadow: 0 0 0 1px var(--forest), 0 1px 3px rgb(0 0 0 / 0.25);
}
.piste__curseur:focus-visible { outline: none; }
.piste__curseur:focus-visible::-webkit-slider-thumb { box-shadow: 0 0 0 1px var(--forest), 0 0 0 4px var(--c-brand-ring); }
.piste__curseur:focus-visible::-moz-range-thumb { box-shadow: 0 0 0 1px var(--forest), 0 0 0 4px var(--c-brand-ring); }
.piste__mois { position: absolute; left: 9px; right: 9px; bottom: 0; height: 14px; }
.piste__mois span {
    position: absolute; top: 0; transform: translateX(-50%);
    font-size: 10.5px; line-height: 14px; color: var(--ink-4); white-space: nowrap;
}
.piste__mois .piste__mois--an { font-weight: 700; color: var(--ink-2); }
@media (max-width: 640px) {
    .piste__mois span:nth-child(even):not(.piste__mois--an) { display: none; }
}

.map-legende { display: flex; flex-wrap: wrap; gap: 6px 16px; margin: 0; padding: 0; list-style: none; font-size: 12px; color: var(--ink-2); }
.map-legende li { display: inline-flex; align-items: center; gap: 6px; }
.leg-charge { width: 14px; height: 14px; border-radius: 50%; background: rgb(29 92 138 / 0.22); border: 1.2px solid var(--water); }
.leg-charge--alerte { border: 2px solid var(--signal); }
</style>
