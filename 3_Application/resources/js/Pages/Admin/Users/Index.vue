<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { useListFilters } from '@/composables/useListFilters.js';

const props = defineProps({ users: Object, filters: { type: Object, default: () => ({}) } });
const { filters, reset, isActive } = useListFilters('admin.users.index', {
    q: props.filters.q ?? '',
    role: props.filters.role ?? '',
});

const roleClass = (role) => ({
    'role-badge--admin': role === 'admin',
    'role-badge--validateur': role === 'validateur',
    'role-badge--demandeur': role === 'demandeur',
});
</script>

<template>
    <Head title="Gestion des Utilisateurs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div class="ph-title">Gestion des Utilisateurs</div>
            </div>
        </template>

        <div class="filter-bar">
            <div class="filter-search">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                <input v-model="filters.q" type="search" class="gf-input" placeholder="Nom ou email…" aria-label="Rechercher un utilisateur" />
            </div>
            <select v-model="filters.role" class="gf-select" aria-label="Rôle">
                <option value="">Tous les rôles</option>
                <option value="demandeur">Demandeurs</option>
                <option value="validateur">Validateurs</option>
                <option value="admin">Administrateurs</option>
            </select>
            <button v-if="isActive()" type="button" class="filter-reset" @click="reset">Réinitialiser</button>
        </div>

        <div class="glass-table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Sites assignés</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="fw-strong">{{ user.name }}</td>
                        <td class="text-dim text-sm">{{ user.email }}</td>
                        <td>
                            <span class="badge-glass" :class="{
                                'badge-warn': user.role === 'admin',
                                'badge-info': user.role === 'validateur',
                                'badge-ok': user.role === 'demandeur'
                            }">{{ user.role }}</span>
                        </td>
                        <td class="text-dim text-sm">
                            <span v-if="user.role !== 'validateur'">—</span>
                            <span v-else>
                                <span v-for="(site, i) in user.sites" :key="site.id">
                                    {{ site.nom }}<span v-if="i < user.sites.length - 1">, </span>
                                </span>
                                <span v-if="user.sites.length === 0" class="italic">Aucun site</span>
                            </span>
                        </td>
                        <td class="text-right">
                            <Link :href="route('admin.users.edit', user.id)" class="btn-sm-edit">Gérer</Link>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td colspan="5" class="table-empty">Aucun utilisateur ne correspond à cette recherche.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="users" label="utilisateurs" />
    </AuthenticatedLayout>
</template>

