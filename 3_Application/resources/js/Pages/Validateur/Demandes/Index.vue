<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useListFilters } from '@/composables/useListFilters.js';
import { statutBadgeClass, formatDate } from '@/utils/statut.js';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import { useNavigation } from '@/composables/useNavigation.js';

const { loading: navLoading, samePage } = useNavigation();

const props = defineProps({
    demandes: Object, // paginateur Laravel
    viewAllDemandes: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
    sites: { type: Array, default: () => [] },
    statuts: { type: Array, default: () => [] },
});

const { filters, reset, isActive } = useListFilters('validateur.demandes.index', {
    q: props.filters.q ?? '',
    statut: props.filters.statut ?? '',
    site_id: props.filters.site_id ?? '',
    du: props.filters.du ?? '',
    au: props.filters.au ?? '',
});

// L'export reprend exactement les filtres affichés
const exportUrl = computed(() => {
    const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));
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
                        {{ viewAllDemandes ? 'Toutes les demandes de la plateforme' : 'Demandes soumises sur vos sites assignés' }}
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
                <input v-model="filters.q" type="search" class="gf-input" placeholder="Demandeur, e-mail, site ou numéro de dossier" aria-label="Rechercher" />
            </div>
            <select v-model="filters.statut" class="gf-select" aria-label="Statut">
                <option value="">Tous les statuts</option>
                <option v-for="s in statuts" :key="s.id" :value="s.code">{{ s.libelle }}</option>
            </select>
            <select v-model="filters.site_id" class="gf-select" aria-label="Site">
                <option value="">Tous les sites</option>
                <option v-for="s in sites" :key="s.id" :value="String(s.id)">{{ s.nom }}</option>
            </select>
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
                        <th>Site</th>
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
                            <div class="text-dim text-sm">{{ d.type_demande?.libelle }}</div>
                        </td>
                        <td><span class="site-label">{{ d.site.nom }}</span></td>
                        <td class="mono text-sm nowrap">{{ formatDate(d.date_debut) }} → {{ formatDate(d.date_fin) }}</td>
                        <td>
                            <span class="statut-cell">
                                <span :class="statutBadgeClass(d.statut.code)">{{ d.statut.libelle }}</span>
                                <span v-if="d.resultats_analyse?.length" class="hatch" title="Chevauche une période sensible" aria-label="Chevauche une période sensible"></span>
                            </span>
                        </td>
                        <td class="text-right" @click.stop>
                            <Link :href="route('validateur.demandes.show', d.id)" class="btn-sm-edit">Instruire</Link>
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
