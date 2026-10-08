<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ types: Array });
const form = useForm({});
const deleteType = (id) => {
    if (confirm('Supprimer ce type de demande ?')) {
        form.delete(route('admin.type-demandes.destroy', id));
    }
};
</script>

<template>
    <Head title="Types de Demandes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div class="ph-title">Types de Demandes</div>
                <Link :href="route('admin.type-demandes.create')" class="btn-accent">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
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
                <tbody>
                    <tr v-for="type in types" :key="type.id">
                        <td class="fw-strong">{{ type.libelle }}</td>
                        <td>
                            <div style="display:flex;flex-wrap:wrap;gap:5px;">
                                <span v-for="(doc, i) in type.documents_requis" :key="i" style="padding:3px 10px;background:#f8faf9;border:1px solid #e7eae9;border-radius:999px;font-size:11.5px;color:#4b5563;font-weight:500;">
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

