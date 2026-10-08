<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { openAuthenticatedPdf } from '@/utils/openPdf.js';
import { statutBadgeClass, formatDate } from '@/utils/statut.js';

const props = defineProps({
    demandes: Object, // paginateur Laravel
    compteurs: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const onglets = [
    { code: '', label: 'Toutes' },
    { code: 'brouillon', label: 'Brouillons' },
    { code: 'a_completer', label: 'À compléter' },
    { code: 'soumise', label: 'Soumises' },
    { code: 'en_analyse', label: 'En analyse' },
    { code: 'validee', label: 'Validées' },
    { code: 'rejetee', label: 'Rejetées' },
];

const total = computed(() => Object.values(props.compteurs).reduce((a, b) => a + Number(b), 0));
const compte = (code) => (code === '' ? total.value : Number(props.compteurs[code] ?? 0));
// On masque les onglets vides, sauf « Toutes » et l'onglet sélectionné
const ongletsVisibles = computed(() => onglets.filter(o => o.code === '' || o.code === props.filters.statut || compte(o.code) > 0));

const filtrer = (code) => {
    router.get(route('demandes.index'), code ? { statut: code } : {}, { preserveScroll: true, preserveState: true, replace: true });
};

const ouvrir = (d) => router.visit(route('demandes.show', d.id));

const openPdf = async (url, filename) => {
    try {
        await openAuthenticatedPdf(url, filename);
    } catch (e) {
        alert(e.message || 'Erreur lors de l\'ouverture du PDF.');
    }
};
</script>

<template>
    <Head title="Mes Demandes" />
    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div>
                    <div class="page-title">Mes Demandes d'Accès</div>
                    <div class="page-sub">Suivez vos dossiers, reprenez vos brouillons et répondez aux demandes de compléments</div>
                </div>
                <Link :href="route('demandes.create')" class="btn-accent">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Nouvelle demande
                </Link>
            </div>
        </template>

        <div v-if="compteurs.a_completer > 0 && filters.statut !== 'a_completer'" class="todo-banner">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <span>{{ compteurs.a_completer }} dossier(s) attendent des compléments de votre part.</span>
            <button type="button" class="todo-banner__btn" @click="filtrer('a_completer')">Voir</button>
        </div>

        <div class="tabs" role="tablist">
            <button
                v-for="o in ongletsVisibles"
                :key="o.code"
                type="button"
                role="tab"
                class="tab"
                :class="{ 'tab--active': (filters.statut || '') === o.code }"
                :aria-selected="(filters.statut || '') === o.code"
                @click="filtrer(o.code)"
            >
                {{ o.label }}
                <span class="tab__count">{{ compte(o.code) }}</span>
            </button>
        </div>

        <div class="glass-table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Site Protégé</th>
                        <th>Type d'activité</th>
                        <th>Période</th>
                        <th>Statut</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in demandes.data" :key="d.id" class="row-link" @click="ouvrir(d)">
                        <td class="text-dim text-sm">#{{ d.id }}</td>
                        <td class="fw-strong">{{ d.site.nom }}</td>
                        <td class="text-dim">{{ d.type_demande.libelle }}</td>
                        <td class="text-dim text-sm">
                            <template v-if="d.date_debut">
                                {{ formatDate(d.date_debut) }}<br>
                                au {{ formatDate(d.date_fin) }}
                            </template>
                            <span v-else style="font-style:italic">À définir</span>
                        </td>
                        <td><span :class="statutBadgeClass(d.statut.code)">{{ d.statut.libelle }}</span></td>
                        <td class="text-right" @click.stop>
                            <div class="doc-btns">
                                <template v-if="d.statut.code === 'validee'">
                                    <button type="button" class="btn-accent" style="font-size:12px;padding:5px 12px;" @click="openPdf(route('demandes.pdf.autorisation', d.id), `autorisation_${d.id}.pdf`)">↓ Autorisation</button>
                                    <button type="button" class="btn-ghost" style="font-size:12px;padding:5px 12px;" @click="openPdf(route('demandes.pdf.cahier-charges', d.id), `cahier_des_charges_${d.id}.pdf`)">↓ Cahier des charges</button>
                                </template>
                                <Link v-else-if="d.statut.code === 'brouillon'" :href="route('demandes.edit', d.id)" class="btn-sm-edit">Continuer</Link>
                                <Link v-else-if="d.statut.code === 'a_completer'" :href="route('demandes.edit', d.id)" class="btn-sm-edit btn-sm-todo">Compléter</Link>
                                <Link :href="route('demandes.show', d.id)" class="btn-sm-edit">Détails →</Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="demandes.data.length === 0">
                        <td colspan="6" class="table-empty">
                            {{ filters.statut ? 'Aucune demande avec ce statut.' : "Vous n'avez soumis aucune demande pour le moment." }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="demandes" label="demandes" />
    </AuthenticatedLayout>
</template>

<style scoped>
.doc-btns { display: flex; gap: 7px; justify-content: flex-end; flex-wrap: wrap; }
.btn-sm-todo { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
.btn-sm-todo:hover { background: #ffedd5; border-color: #fdba74; color: #9a3412; }

.todo-banner {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; margin-bottom: 16px;
    background: #fff7ed; border: 1px solid #fed7aa; border-left: 4px solid #f97316;
    border-radius: var(--radius-md);
    font-size: 13.5px; font-weight: 500; color: #9a3412;
}
.todo-banner svg { width: 20px; height: 20px; color: #f97316; flex-shrink: 0; }
.todo-banner span { flex: 1; }
.todo-banner__btn {
    background: #fff; border: 1px solid #fdba74; color: #c2410c;
    font-size: 12.5px; font-weight: 600; padding: 5px 12px; border-radius: 999px; cursor: pointer;
}
.todo-banner__btn:hover { background: #ffedd5; }
</style>
