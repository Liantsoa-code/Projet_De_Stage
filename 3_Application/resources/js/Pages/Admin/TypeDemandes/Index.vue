<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import { useNavigation } from '@/composables/useNavigation.js';

const { loading: navLoading, samePage } = useNavigation();

const props = defineProps({ types: Array });
const form = useForm({});
const deleteType = (id) => {
    if (confirm('Supprimer ce type de demande ?')) {
        form.delete(route('admin.type-demandes.destroy', id));
    }
};
</script>

<template>
    <Head title="Types de demandes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div class="ph-title">Types de demandes</div>
                <Link :href="route('admin.type-demandes.create')" class="btn-accent">
                    <Icon name="plus" />
                    Ajouter un type
                </Link>
            </div>
        </template>

        <div class="glass-table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Libellé</th>
                        <th>Documents requis</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <TableSkeleton v-if="navLoading && samePage" :cols="3" />
                <tbody v-else>
                    <tr v-for="type in types" :key="type.id">
                        <td class="fw-strong">{{ type.libelle }}</td>
                        <td>
                            <div style="display:flex;flex-wrap:wrap;gap:5px;">
                                <span v-for="(doc, i) in type.documents_requis" :key="i" style="padding:3px 10px;background:var(--sunk);border:1px solid var(--rule);border-radius: var(--r-sm);font-size:11.5px;color:var(--ink-2);font-weight:500;">
                                    {{ doc }}
                                </span>
                                <span v-if="!type.documents_requis || type.documents_requis.length === 0" class="text-dim text-sm" style="font-style:italic">Aucun document requis</span>
                            </div>
                        </td>
                        <td class="text-right">
                            <div style="display:flex;gap:8px;justify-content:flex-end;">
                                <Link :href="route('admin.type-demandes.edit', type.id)" class="btn-sm-edit">Modifier</Link>
                                <button @click="deleteType(type.id)" class="btn-sm-delete">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="types.length === 0">
                        <td colspan="3" class="table-empty">Aucun type de demande enregistré.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>

