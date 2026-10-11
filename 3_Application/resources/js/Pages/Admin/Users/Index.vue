<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { useListFilters } from '@/composables/useListFilters.js';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import { useNavigation } from '@/composables/useNavigation.js';

const { loading: navLoading, samePage } = useNavigation();

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
    <Head title="Utilisateurs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div class="ph-title">Utilisateurs</div>
            </div>
        </template>

        <div class="filter-bar">
            <div class="filter-search">
                <Icon name="magnifying-glass" />
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
                <TableSkeleton v-if="navLoading && samePage" :cols="5" />
                <tbody v-else>
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="fw-strong">{{ user.name }}</td>
                        <td class="text-dim text-sm">{{ user.email }}</td>
                        <td>
                            <span class="role" :class="`role--${user.role}`">{{ { admin: 'Admin', validateur: 'Validateur', demandeur: 'Demandeur' }[user.role] ?? user.role }}</span>
                        </td>
                        <td class="text-dim text-sm">
                            <span v-if="user.role !== 'validateur'">—</span>
                            <span v-else>
                                <span v-for="site in user.sites" :key="site.id" class="chip">{{ site.nom }}</span>
                                <span v-if="user.sites.length === 0" class="warn-chip">Aucun site assigné</span>
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


<style scoped>
.role { display: inline-flex; padding: 3px 8px; border-radius: var(--r-sm); font-size: 12px; font-weight: 600; background: var(--sunk); color: var(--ink-2); }
.role--validateur { background: var(--forest-soft); color: var(--forest); }
.role--admin { background: var(--ink); color: var(--paper); }
.chip { display: inline-flex; margin: 2px 4px 2px 0; padding: 3px 8px; border-radius: var(--r-sm); background: var(--sunk); color: var(--ink-2); font-size: 12px; }
.warn-chip { display: inline-flex; padding: 3px 8px; border-radius: var(--r-sm); background: var(--ochre-soft); color: var(--ochre); font-size: 12px; font-weight: 600; }
</style>
