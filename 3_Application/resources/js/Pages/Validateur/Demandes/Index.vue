<script setup>
import FiltreMultiple from '@/Components/FiltreMultiple.vue';
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useListFilters } from '@/composables/useListFilters.js';
import { statutBadgeClass, formatDate } from '@/utils/statut.js';
import { verdictAnalyse } from '@/utils/analyse.js';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import { useNavigation } from '@/composables/useNavigation.js';

const { loading: navLoading, samePage } = useNavigation();

const props = defineProps({
    demandes: Object, // paginateur Laravel
    viewAllDemandes: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
    sites: { type: Array, default: () => [] },
    statuts: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    // Observateur : consultation seule
    lectureSeule: { type: Boolean, default: false },
});

const { filters, reset, isActive } = useListFilters('validateur.demandes.index', {
    q: props.filters.q ?? '',
    statut: props.filters.statut ?? [],
    site_id: props.filters.site_id ?? [],
    type_demande_id: props.filters.type_demande_id ?? [],
    du: props.filters.du ?? '',
    au: props.filters.au ?? '',
});

const optionsStatut = computed(() => props.statuts.map((s) => ({ value: s.code, label: s.libelle })));
const optionsSite = computed(() => props.sites.map((s) => ({ value: String(s.id), label: s.nom })));
const optionsType = computed(() => props.types.map((t) => ({ value: String(t.id), label: t.libelle })));

// L'export reprend exactement les filtres affichés
const exportUrl = computed(() => {
    const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => (Array.isArray(v) ? v.length : v !== '')));
    return route('validateur.demandes.export', params);
});

const ouvrir = (d) => router.visit(route('validateur.demandes.show', d.id));
</script>

<template>
    <Head :title="viewAllDemandes ? 'Toutes les demandes' : 'Dossiers à valider'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div>
                    <div class="page-title">{{ viewAllDemandes ? 'Toutes les demandes' : 'Dossiers à valider' }}</div>
                    <div class="page-sub">
                        {{ viewAllDemandes ? 'Toutes les demandes de la plateforme' : 'Demandes soumises sur vos aires protégées assignées' }}
                    </div>
                </div>
                <a :href="exportUrl" class="btn-ghost">
                    <Icon name="download-simple" />
                    Exporter en CSV
                </a>
            </div>
        </template>

        <div class="filter-bar">
            <div class="filter-search">
                <Icon name="magnifying-glass" />
                <input v-model="filters.q" type="search" class="gf-input" placeholder="Demandeur, e-mail, aire protégée ou numéro de dossier" aria-label="Rechercher" />
            </div>
            <FiltreMultiple v-model="filters.statut" :options="optionsStatut" tous="Tous les statuts" pluriel="statuts" />
            <FiltreMultiple v-model="filters.site_id" :options="optionsSite" tous="Toutes les aires protégées" pluriel="aires protégées" />
            <FiltreMultiple v-model="filters.type_demande_id" :options="optionsType" tous="Tous les types de projet" pluriel="types de projet" />
            <label class="filter-field">
                Du <input v-model="filters.du" type="date" class="gf-input" />
            </label>
            <label class="filter-field">
                au <input v-model="filters.au" type="date" class="gf-input" :min="filters.du || undefined" />
            </label>
            <button v-if="isActive()" type="button" class="filter-reset" @click="reset">Réinitialiser</button>
        </div>

        <div class="glass-table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Demandeur</th>
                        <th>Aire protégée</th>
                        <th>Période</th>
                        <th>Statut</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <TableSkeleton v-if="navLoading && samePage" :cols="6" />
                <tbody v-else>
                    <tr v-for="d in demandes.data" :key="d.id" class="row-link" @click="ouvrir(d)">
                        <td class="mono text-sm nowrap">{{ d.reference }}</td>
                        <td>
                            <div class="fw-strong">{{ d.user.name }}</div>
                            <div class="text-dim text-sm">{{ d.titre ? `${d.titre} · ` : '' }}{{ d.type_demande?.libelle }}</div>
                        </td>
                        <td><span class="site-label">{{ d.site.nom }}</span></td>
                        <td class="mono text-sm nowrap">{{ formatDate(d.date_debut) }} → {{ formatDate(d.date_fin) }}</td>
                        <td>
                            <span class="statut-cell">
                                <span :class="statutBadgeClass(d.statut.code)">{{ d.statut.libelle }}</span>
                                <span v-if="verdictAnalyse(d.resultats_analyse) === 'conflit'" class="hatch hatch--block" title="Analyse : conflit détecté" aria-label="Analyse : conflit détecté"></span>
                                <span v-else-if="verdictAnalyse(d.resultats_analyse) === 'conditions'" class="hatch" title="Analyse : compatible sous conditions" aria-label="Analyse : compatible sous conditions"></span>
                            </span>
                        </td>
                        <td class="text-right" @click.stop>
                            <Link :href="route('validateur.demandes.show', d.id)" class="btn-sm-edit">{{ lectureSeule ? 'Consulter' : 'Examiner' }}</Link>
                        </td>
                    </tr>
                    <tr v-if="demandes.data.length === 0">
                        <td colspan="6" class="table-empty">
                            <template v-if="isActive()">Aucune demande ne correspond à ces filtres.</template>
                            <template v-else>{{ viewAllDemandes ? 'Aucune demande enregistrée.' : 'Aucune demande en attente pour vos sites.' }}</template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="demandes" label="demandes" />
    </AuthenticatedLayout>
</template>

<style scoped>
.nowrap { white-space: nowrap; }
.statut-cell { display: inline-flex; align-items: center; gap: 8px; }
</style>
