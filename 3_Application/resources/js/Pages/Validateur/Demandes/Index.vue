<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useListFilters } from '@/composables/useListFilters.js';
import { statutBadgeClass, formatDate } from '@/utils/statut.js';

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
    <Head title="Dossiers à Valider" />
    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div>
                    <div class="page-title">{{ viewAllDemandes ? 'Toutes les demandes' : 'Dossiers à Valider' }}</div>
                    <div class="page-sub">
                        {{ viewAllDemandes ? 'Toutes les demandes de la plateforme' : 'Demandes soumises sur vos sites assignés' }}
                    </div>
                </div>
                <a :href="exportUrl" class="btn-ghost">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Exporter (CSV)
                </a>
            </div>
        </template>

        <div class="filter-bar">
            <div class="filter-search">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                <input v-model="filters.q" type="search" class="gf-input" placeholder="Demandeur, email, site ou n° de dossier…" aria-label="Rechercher" />
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
                        <th>N°</th>
                        <th>Demandeur</th>
                        <th>Site</th>
                        <th>Période</th>
                        <th>Statut</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in demandes.data" :key="d.id" class="row-link" @click="ouvrir(d)">
                        <td class="text-dim text-sm">#{{ d.id }}</td>
                        <td>
                            <div class="fw-strong">{{ d.user.name }}</div>
                            <div class="text-dim text-sm">{{ d.type_demande?.libelle }}</div>
                        </td>
                        <td class="text-dim">{{ d.site.nom }}</td>
                        <td class="text-dim text-sm">
                            {{ formatDate(d.date_debut) }} — {{ formatDate(d.date_fin) }}
                        </td>
                        <td>
                            <span :class="statutBadgeClass(d.statut.code)">{{ d.statut.libelle }}</span>
                            <span v-if="d.resultats_analyse?.length" class="conflict-flag" title="Chevauchement avec une période sensible">⚠</span>
                        </td>
                        <td class="text-right" @click.stop>
                            <Link :href="route('validateur.demandes.show', d.id)" class="btn-sm-edit">Analyser →</Link>
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
.btn-ghost svg { width: 16px; height: 16px; }
.conflict-flag {
    display: inline-flex; align-items: center; justify-content: center;
    width: 20px; height: 20px; margin-left: 6px; border-radius: 50%;
    background: #fffbeb; color: #d97706; border: 1px solid #fde68a;
    font-size: 11px; vertical-align: middle;
}
</style>
