<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { useListFilters } from '@/composables/useListFilters.js';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import { useNavigation } from '@/composables/useNavigation.js';

const { loading: navLoading, samePage } = useNavigation();

const props = defineProps({ sites: Object, filters: { type: Object, default: () => ({}) } });
const { filters, reset, isActive } = useListFilters('admin.sites.index', { q: props.filters.q ?? '' });
const form = useForm({});
const deleteSite = (id) => {
    if (confirm('Supprimer cette aire protégée ?')) form.delete(route('admin.sites.destroy', id));
};
</script>

<template>
    <Head title="Aires protégées" />
    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div>
                    <div class="page-title">Aires protégées</div>
                    <div class="page-sub">Aires protégées enregistrées dans le système</div>
                </div>
                <Link :href="route('admin.sites.create')" class="btn-accent">
                    <Icon name="plus" />
                    Ajouter une aire protégée
                </Link>
            </div>
        </template>

        <div class="filter-bar">
            <div class="filter-search">
                <Icon name="magnifying-glass" />
                <input v-model="filters.q" type="search" class="gf-input" placeholder="Rechercher une aire protégée…" aria-label="Rechercher une aire protégée" />
            </div>
            <button v-if="isActive()" type="button" class="filter-reset" @click="reset">Réinitialiser</button>
        </div>

        <div class="glass-table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom de l'aire protégée</th>
                        <th>Description</th>
                        <th>Demandes</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <TableSkeleton v-if="navLoading && samePage" :cols="5" />
                <tbody v-else>
                    <tr v-for="s in sites.data" :key="s.id">
                        <td class="text-dim text-sm">{{ s.id }}</td>
                        <td class="fw-strong">{{ s.nom }}</td>
                        <td class="text-dim text-sm truncate-cell">{{ s.description }}</td>
                        <td class="text-dim text-sm">{{ s.demandes_count }}</td>
                        <td class="text-right">
                            <div style="display:flex;gap:8px;justify-content:flex-end">
                                <Link :href="route('admin.sites.edit', s.id)" class="btn-sm-edit">Modifier</Link>
                                <button @click="deleteSite(s.id)" class="btn-sm-delete" :disabled="s.demandes_count > 0" :title="s.demandes_count > 0 ? 'Des demandes sont rattachées à cette aire protégée' : undefined">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="sites.data.length === 0"><td colspan="5" class="table-empty">{{ isActive() ? 'Aucune aire protégée ne correspond à cette recherche.' : 'Aucune aire protégée enregistrée.' }}</td></tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="sites" label="aires protégées" />
    </AuthenticatedLayout>
</template>
