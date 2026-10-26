<script setup>
import Icon from '@/Components/Icon.vue';
import DashboardMap from '@/Components/DashboardMap.vue';
import { computed, ref } from 'vue';
import { formatDate } from '@/utils/statut.js';
import { surfaceKm2, formatSurface } from '@/utils/carte.js';

// Colonne « Vérification du calendrier », visible à toutes les étapes du formulaire :
// carte des sites colorée selon les dates, fiche du site choisi et ses périodes sensibles.
const props = defineProps({
    sites: { type: Array, required: true },
    // État de chaque site sur les dates choisies, pour colorer la carte (null tant qu'il n'y a pas de dates)
    etats: { type: Object, default: null },
    site: { type: Object, default: null },
    // État du calendrier du site choisi : aucun (pas de dates), ouvert, sensible ou bloquant
    calendrier: { type: String, required: true },
    // Périodes sensibles à venir du site choisi, et celles qui chevauchent le séjour
    periodes: { type: Array, required: true },
    chevauchements: { type: Array, required: true },
});

defineEmits(['select']);

const carte = ref(null);
const surfaceSite = computed(() => formatSurface(surfaceKm2(props.site?.polygone_coordonnees)));
const chevauche = (p) => props.chevauchements.some(c => c.id === p.id);
</script>

<template>
    <div class="check">
        <header class="check__head"><h2>Vérification du calendrier</h2></header>
        <div class="check__body">
            <div class="check__map">
                <DashboardMap
                    ref="carte"
                    :sites="sites"
                    :etats="etats"
                    :selected-id="site?.id ?? null"
                    selectable
                    @select="(id) => $emit('select', id)"
                />
            </div>
            <ul v-if="etats" class="legende" aria-label="Légende de la carte">
                <li><span class="legende__ok"></span>Ouvert</li>
                <li><span class="hatch"></span>Période sensible</li>
                <li><span class="hatch hatch--block"></span>Bloquant</li>
            </ul>

            <p v-if="!site" class="hint">Cliquez sur une aire protégée de la carte ou choisissez-la dans la liste.</p>
            <template v-else>
                <div class="fiche">
                    <span class="site-label">{{ site.nom }}</span>
                    <span v-if="surfaceSite" class="fiche__surface mono">≈ {{ surfaceSite }}</span>
                    <p v-if="site.description" class="fiche__desc">{{ site.description }}</p>
                    <button v-if="surfaceSite" type="button" class="btn-sm-edit fiche__3d" @click="carte?.voir3d(site.id)">
                        <Icon name="mountains" />
                        Voir le terrain en 3D
                    </button>
                </div>

                <div v-if="calendrier === 'aucun'" class="res res--none">
                    <Icon name="calendar-blank" />
                    <div><strong>Dates à compléter</strong>Choisissez l'arrivée et le départ pour vérifier le calendrier du site.</div>
                </div>
                <div v-else-if="calendrier === 'bloquant'" class="res res--block" role="alert">
                    <Icon name="prohibit" />
                    <div><strong>Envoi impossible sur ces dates</strong>Une période bloquante couvre tout ou partie du séjour. Choisissez d'autres dates.</div>
                </div>
                <div v-else-if="calendrier === 'sensible'" class="res res--warn" role="alert">
                    <Icon name="warning" />
                    <div><strong>Période sensible sur ces dates</strong>La demande reste possible. Elle sera signalée au validateur et les précautions figureront au cahier des charges.</div>
                </div>
                <div v-else class="res res--ok">
                    <Icon name="check-circle" />
                    <div><strong>Aucune période sensible</strong>L'aire protégée est ouverte sur ces dates.</div>
                </div>

                <div class="periodes">
                    <p class="eyebrow">Périodes sensibles de l'aire protégée</p>
                    <ul v-if="periodes.length" class="periodes__list">
                        <li v-for="p in periodes" :key="p.id" class="periodes__item" :class="{ 'periodes__item--hit': chevauche(p) }">
                            <span class="hatch" :class="{ 'hatch--block': p.niveau_restriction === 'bloquant' }"></span>
                            <span class="periodes__text">
                                <span class="mono periodes__dates">{{ formatDate(p.date_debut) }} → {{ formatDate(p.date_fin) }}</span>
                                <span>{{ p.description }}</span>
                            </span>
                            <span class="periodes__lvl" :class="{ 'periodes__lvl--block': p.niveau_restriction === 'bloquant' }">{{ p.niveau_restriction === 'bloquant' ? 'Bloquant' : 'Avertissement' }}</span>
                        </li>
                    </ul>
                    <p v-else class="hint">Aucune restriction prévue sur ce site.</p>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.hint { font-size: 13.5px; color: var(--ink-3); }

.check { position: sticky; top: 84px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); overflow: hidden; }
.check__head { padding: 14px 24px 4px; }
.check__head h2 { font-size: 15.5px; font-weight: 700; }
.check__body { display: grid; gap: 12px; padding: 14px; }
.check__map { height: 300px; }
.check__map :deep(.map-wrapper),
.check__map :deep(.map-inner) { min-height: 0; border-radius: var(--r-sm); }

.legende { display: flex; flex-wrap: wrap; gap: 6px 14px; margin: 0; padding: 0; list-style: none; font-size: 12px; color: var(--ink-2); }
.legende li { display: inline-flex; align-items: center; gap: 6px; }
.legende__ok { width: 14px; height: 14px; background: var(--forest-soft); border: 1px solid var(--forest); }

.fiche { display: grid; gap: 4px; }
.fiche__surface { font-size: 12px; color: var(--ink-3); }
.fiche__3d { justify-self: start; margin-top: 4px; }
.fiche__3d svg { width: 15px; height: 15px; }
.fiche__desc { font-size: 13px; color: var(--ink-2); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

.res { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: var(--r-sm); font-size: 13.5px; color: var(--ink-2); }
.res strong { display: block; color: var(--ink); }
.res svg { width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px; }
.res--ok { background: var(--forest-soft); }
.res--ok svg { color: var(--forest); }
.res--warn { background: var(--ochre-soft); }
.res--warn svg { color: var(--ochre); }
.res--block { background: var(--signal-soft); }
.res--block svg { color: var(--signal); }
.res--none { background: var(--sunk); }
.res--none svg { color: var(--ink-3); }

.periodes { display: grid; gap: 8px; padding-top: 4px; }
.periodes__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.periodes__item { display: grid; grid-template-columns: 14px minmax(0, 1fr) auto; gap: 10px; align-items: start; padding: 8px 10px; border: 1px solid var(--rule-soft); border-radius: var(--r-sm); font-size: 12.5px; color: var(--ink-2); }
.periodes__item .hatch { margin-top: 2px; }
.periodes__item--hit { border-color: var(--ink-3); background: var(--paper); }
.periodes__text { display: grid; gap: 2px; min-width: 0; }
.periodes__dates { font-size: 12px; color: var(--ink); }
.periodes__lvl { font: 600 10.5px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ochre); }
.periodes__lvl--block { color: var(--signal); }
</style>
