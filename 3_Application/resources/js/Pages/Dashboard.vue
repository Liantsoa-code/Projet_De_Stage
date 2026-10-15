<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EspaceDemandeur from '@/Pages/Dashboard/Partials/EspaceDemandeur.vue';
import EspaceValidateur from '@/Pages/Dashboard/Partials/EspaceValidateur.vue';
import EspaceAdmin from '@/Pages/Dashboard/Partials/EspaceAdmin.vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const role = computed(() => page.props.auth.user?.role);
const enCours = computed(() => page.props.en_cours || []);
const aCompleter = computed(() => page.props.a_completer || []);
const nomsSites = computed(() => (page.props.sites || []).map((s) => s.nom).join(', '));
</script>

<template>
    <Head title="Tableau de bord" />

    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div>
                    <div class="page-title">Tableau de bord</div>
                    <div v-if="role === 'admin'" class="page-sub">Pilotage de toutes les aires protégées · {{ page.props.total_users }} comptes</div>
                    <div v-else-if="role === 'validateur'" class="page-sub">Vos sites : {{ nomsSites || 'aucun site assigné' }}</div>
                    <div v-else class="page-sub">
                        {{ enCours.length }} demande{{ enCours.length > 1 ? 's' : '' }} en cours
                        <template v-if="aCompleter.length">
                            · <strong class="t-ochre">{{ aCompleter.length }} attend{{ aCompleter.length > 1 ? 'ent' : '' }} votre réponse</strong>
                        </template>
                    </div>
                </div>
                <Link v-if="role === 'admin' || role === 'validateur'" :href="route('validateur.demandes.index')" class="btn-accent">
                    <Icon name="stamp" />
                    Ouvrir les dossiers
                </Link>
                <Link v-else :href="route('demandes.create')" class="btn-accent">
                    <Icon name="plus" />
                    Nouvelle demande
                </Link>
            </div>
        </template>

        <EspaceAdmin v-if="role === 'admin'" />
        <EspaceValidateur v-else-if="role === 'validateur'" />
        <EspaceDemandeur v-else />
    </AuthenticatedLayout>
</template>

<style>
/* Styles communs aux trois espaces du tableau de bord (préfixés par .dash) */
.dash { display: grid; gap: 24px; }
.dash .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 420px), 1fr)); gap: 24px; align-items: start; }
.dash .dash-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.45fr); gap: 24px; align-items: stretch; }
.dash .dash-grid > .panel { display: flex; flex-direction: column; }
.dash .dash-grid > .panel > .empty, .dash .dash-grid > .panel > .rows { flex: 1; }
.dash .dash-grid > .panel > .empty { align-content: center; }
.dash .dash-grid--charts { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); align-items: start; }
.dash .dash-grid--charts > .panel { display: block; }
@media (max-width: 1100px) { .dash .dash-grid, .dash .dash-grid--charts { grid-template-columns: minmax(0, 1fr); } }
.dash .stack { display: grid; gap: 24px; min-width: 0; align-content: start; }
.t-ochre { color: var(--ochre); }
.t-signal { color: var(--signal); }

.dash .panel { background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); min-width: 0; }
.dash .panel__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px 12px; padding: 16px 24px 8px; }
.dash .panel__head h2 { font-size: 15.5px; font-weight: 700; }
.dash .panel__head small { font-size: 12.5px; color: var(--ink-3); }
.dash .link { font-size: 13px; font-weight: 600; color: var(--water); text-decoration: none; }
.dash .link:hover { text-decoration: underline; }

.dash .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
.dash .kpi { display: grid; align-content: start; gap: 4px; padding: 16px 24px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); }
.dash .kpi__n { font: 700 32px/1 var(--f-display); font-variant-numeric: tabular-nums; color: var(--ink); }
.dash .kpi__n small { font-size: 20px; color: var(--ink-3); }
.dash .kpi__l { font-size: 13.5px; font-weight: 600; }
.dash .kpi__s { font-size: 12.5px; color: var(--ink-3); }
.dash .kpi--alert .kpi__n { color: var(--signal); }
.dash .kpi--warn .kpi__n { color: var(--ochre); }

.dash .rows { display: grid; align-content: start; }
.dash .row { display: grid; align-items: center; gap: 14px; padding: 12px 24px; border-bottom: 1px solid var(--rule-soft); color: inherit; text-decoration: none; }
.dash .row:last-child { border-bottom: 0; }
.dash a.row:hover { background: var(--paper); }
.dash .row__main { display: grid; gap: 3px; min-width: 0; }
.dash .row__main strong { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dash .ref { font-size: 12.5px; color: var(--ink-2); }
.dash .dates { font-size: 12.5px; color: var(--ink-2); }
.dash .tag { display: inline-flex; align-items: center; gap: 5px; font: 600 12.5px var(--f-mono); white-space: nowrap; color: var(--ink-3); }
.dash .tag svg { width: 14px; height: 14px; }
.dash .tag--alert { color: var(--signal); }
.dash .tag--warn { color: var(--ochre); }

.dash .bars { list-style: none; margin: 0; padding: 14px 24px; display: grid; gap: 12px; }
.dash .bars li { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(60px, 1fr) auto; align-items: center; gap: 12px; }
.dash .bars__label { display: grid; gap: 2px; font-size: 13px; line-height: 1.3; overflow-wrap: anywhere; }
.dash .bars__label small { font-size: 12px; color: var(--ink-3); text-transform: none; letter-spacing: 0; font-family: var(--f-body); font-weight: 400; }
.dash .bars__track { height: 8px; background: var(--sunk); border-radius: 4px; overflow: hidden; }
.dash .bars__track i { display: block; height: 100%; background: var(--forest); border-radius: 4px; animation: barre-pousse 0.9s cubic-bezier(0.33, 1, 0.68, 1); }
@keyframes barre-pousse { from { width: 0; } }
@media (prefers-reduced-motion: reduce) { .dash .bars__track i { animation: none; } }
.dash .bars__n { font-size: 13px; text-align: right; color: var(--ink); }

.dash .empty { display: grid; justify-items: center; gap: 10px; padding: 36px 20px; text-align: center; color: var(--ink-3); }
.dash .empty svg { width: 28px; height: 28px; }
.dash .empty p { max-width: 44ch; }
.dash .empty--small { padding: 22px 24px; font-size: 13.5px; }
</style>
