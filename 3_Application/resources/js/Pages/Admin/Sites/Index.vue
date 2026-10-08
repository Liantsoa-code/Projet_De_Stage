<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { useListFilters } from '@/composables/useListFilters.js';

const props = defineProps({ sites: Object, filters: { type: Object, default: () => ({}) } });
const { filters, reset, isActive } = useListFilters('admin.sites.index', { q: props.filters.q ?? '' });
const form = useForm({});
const deleteSite = (id) => {
    if (confirm('Supprimer ce site ?')) form.delete(route('admin.sites.destroy', id));
};
</script>

<template>
    <Head title="Sites Protégés" />
    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div>
                    <div class="page-title">Sites Protégés</div>
                    <div class="page-sub">Aires protégées enregistrées dans le système</div>
                </div>
                <Link :href="route('admin.sites.create')" class="btn-accent">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Ajouter un site
                </Link>
            </div>
        </template>

        <div class="filter-bar">
            <div class="filter-search">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                <input v-model="filters.q" type="search" class="gf-input" placeholder="Rechercher un site…" aria-label="Rechercher un site" />
            </div>
            <button v-if="isActive()" type="button" class="filter-reset" @click="reset">Réinitialiser</button>
        </div>

        <div class="glass-table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom du site</th>
                        <th>Description</th>
                        <th>Demandes</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in sites.data" :key="s.id">
                        <td class="text-dim text-sm">{{ s.id }}</td>
                        <td class="fw-strong">{{ s.nom }}</td>
                        <td class="text-dim text-sm truncate-cell">{{ s.description }}</td>
                        <td class="text-dim text-sm">{{ s.demandes_count }}</td>
                        <td class="text-right">
                            <div style="display:flex;gap:8px;justify-content:flex-end">
                                <Link :href="route('admin.sites.edit', s.id)" class="btn-sm-edit">Modifier</Link>
                                <button @click="deleteSite(s.id)" class="btn-sm-delete" :disabled="s.demandes_count > 0" :title="s.demandes_count > 0 ? 'Des demandes sont rattachées à ce site' : undefined">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="sites.data.length === 0"><td colspan="5" class="table-empty">{{ isActive() ? 'Aucun site ne correspond à cette recherche.' : 'Aucun site enregistré.' }}</td></tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="sites" label="sites" />
    </AuthenticatedLayout>
</template>
