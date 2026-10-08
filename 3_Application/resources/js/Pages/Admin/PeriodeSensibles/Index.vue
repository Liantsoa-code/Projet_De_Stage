<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { useListFilters } from '@/composables/useListFilters.js';
const props = defineProps({
    periodes: Object,
    sites: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});
const { filters, reset, isActive } = useListFilters('admin.periode-sensibles.index', {
    site_id: props.filters.site_id ?? '',
    passees: Boolean(props.filters.passees),
}, { textFields: [] });
const form = useForm({});
const del = (id) => { if (confirm('Supprimer cette période ?')) form.delete(route('admin.periode-sensibles.destroy', id)); };
</script>
<template>
    <Head title="Périodes Sensibles" />
    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div>
                    <div class="page-title">Périodes Sensibles</div>
                    <div class="page-sub">Restrictions temporaires sur les sites protégés</div>
                </div>
                <Link :href="route('admin.periode-sensibles.create')" class="btn-accent">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Ajouter une période
                </Link>
            </div>
        </template>
        <div class="filter-bar">
            <select v-model="filters.site_id" class="gf-select" aria-label="Site">
                <option value="">Tous les sites</option>
                <option v-for="s in sites" :key="s.id" :value="String(s.id)">{{ s.nom }}</option>
            </select>
            <label class="filter-field">
                <input v-model="filters.passees" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                Afficher aussi les périodes terminées
            </label>
            <button v-if="isActive()" type="button" class="filter-reset" @click="reset">Réinitialiser</button>
        </div>

        <div class="glass-table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Dates</th>
                        <th>Niveau</th>
                        <th>Description</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in periodes.data" :key="p.id">
                        <td class="fw-strong">{{ p.site.nom }}</td>
                        <td class="text-dim text-sm">
                            {{ new Date(p.date_debut).toLocaleDateString('fr-FR') }}<br>
                            au {{ new Date(p.date_fin).toLocaleDateString('fr-FR') }}
                        </td>
                        <td>
                            <span class="badge-glass" :class="p.niveau_restriction === 'bloquant' ? 'badge-err' : 'badge-warn'">
                                {{ p.niveau_restriction }}
                            </span>
                        </td>
                        <td class="text-dim text-sm truncate-cell">{{ p.description }}</td>
                        <td class="text-right">
                            <div style="display:flex;gap:8px;justify-content:flex-end">
                                <Link :href="route('admin.periode-sensibles.edit', p.id)" class="btn-sm-edit">Modifier</Link>
                                <button @click="del(p.id)" class="btn-sm-delete">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="periodes.data.length === 0"><td colspan="5" class="table-empty">{{ isActive() ? 'Aucune période ne correspond à ces filtres.' : 'Aucune période sensible en cours ou à venir.' }}</td></tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="periodes" label="périodes" />
    </AuthenticatedLayout>
</template>
