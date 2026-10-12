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
const jourChoisi = computed(() => new Date(aujourdhui.getFullYear(), aujourdhui.getMonth(), aujourdhui.getDate() + decalage.value));
const jourIso = computed(() => {
    const d = jourChoisi.value;
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});
const jourLabel = computed(() => jourChoisi.value.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
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
const ecartJours = (iso) => Math.round((new Date(`${iso}T00:00:00`) - aujourdhui) / 86400000);
const prochainePeriode = computed(() => periodes.value.find((p) => p.date_debut > jourIso.value && ecartJours(p.date_debut) <= HORIZON_JOURS));
</script>

<template>
    <section class="panel">
        <header class="panel__head">
            <h2>{{ titre }}</h2>
            <small>{{ sites.length }} site{{ sites.length > 1 ? 's' : '' }}</small>
        </header>
        <div class="map-box">
            <DashboardMap :sites="sites" :etats="etatsDuJour" :charges="chargeSites" />
        </div>
        <div class="map-cal">
            <div class="map-cal__row">
                <label for="jour-carte" class="map-cal__label">Restrictions {{ decalage === 0 ? "aujourd'hui" : 'le ' + jourLabel }}</label>
                <span class="map-cal__resume">
                    <template v-if="nbBloquants || nbSensibles">
                        <span v-if="nbBloquants" class="t-signal">{{ nbBloquants }} bloqué{{ nbBloquants > 1 ? 's' : '' }}</span>
                        <template v-if="nbBloquants && nbSensibles"> · </template>
                        <span v-if="nbSensibles" class="t-ochre">{{ nbSensibles }} sensible{{ nbSensibles > 1 ? 's' : '' }}</span>
                    </template>
                    <template v-else>Tous les sites ouverts</template>
                </span>
            </div>
            <input
                id="jour-carte"
                v-model.number="decalage"
                type="range"
                min="0"
                :max="HORIZON_JOURS"
                step="1"
                class="map-cal__range"
                :aria-valuetext="jourLabel"
            />
            <div class="map-cal__row">
                <button type="button" class="filter-reset" :disabled="decalage === 0" @click="decalage = 0">Aujourd'hui</button>
                <button v-if="prochainePeriode" type="button" class="filter-reset" @click="decalage = ecartJours(prochainePeriode.date_debut)">
                    Prochaine période : {{ formatDate(prochainePeriode.date_debut) }}
                </button>
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
.map-cal { display: grid; gap: 8px; padding: 12px 24px 14px; }
.map-cal__row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 4px 12px; }
.map-cal__label { font-size: 13.5px; font-weight: 600; color: var(--ink); }
.map-cal__resume { font-size: 12.5px; font-weight: 600; color: var(--ink-3); }
.map-cal__range { width: 100%; accent-color: var(--forest); cursor: pointer; }
.map-cal .filter-reset { padding: 4px 6px; }
.map-cal .filter-reset:disabled { opacity: 0.45; cursor: default; background: none; }
.map-legende { display: flex; flex-wrap: wrap; gap: 6px 16px; margin: 0; padding: 0; list-style: none; font-size: 12px; color: var(--ink-2); }
.map-legende li { display: inline-flex; align-items: center; gap: 6px; }
.leg-charge { width: 14px; height: 14px; border-radius: 50%; background: rgb(29 92 138 / 0.22); border: 1.2px solid var(--water); }
.leg-charge--alerte { border: 2px solid var(--signal); }
</style>
