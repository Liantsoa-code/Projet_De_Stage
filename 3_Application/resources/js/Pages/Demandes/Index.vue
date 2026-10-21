<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { openAuthenticatedPdf } from '@/utils/openPdf.js';
import { statutBadgeClass, formatDate } from '@/utils/statut.js';
import TableSkeleton from '@/Components/TableSkeleton.vue';
import { useNavigation } from '@/composables/useNavigation.js';

const { loading: navLoading, samePage } = useNavigation();

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
    <Head title="Mes demandes" />
    <AuthenticatedLayout>
        <template #header>
            <div class="ph-row">
                <div>
                    <div class="page-title">Mes demandes</div>
                    <div class="page-sub">Suivez vos dossiers, reprenez vos brouillons et répondez aux demandes de compléments</div>
                </div>
                <Link :href="route('demandes.create')" class="btn-accent">
                    <Icon name="plus" />
                    Nouvelle demande
                </Link>
            </div>
        </template>

        <div v-if="compteurs.a_completer > 0 && filters.statut !== 'a_completer'" class="todo-banner">
            <Icon name="warning-circle" />
            <span>{{ compteurs.a_completer }} {{ compteurs.a_completer > 1 ? 'dossiers attendent' : 'dossier attend' }} un complément de votre part.</span>
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
                        <th>Référence</th>
                        <th>Aire protégée</th>
                        <th>Type d'activité</th>
                        <th>Période</th>
                        <th>Statut</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <TableSkeleton v-if="navLoading && samePage" :cols="6" />
                <tbody v-else>
                    <tr v-for="d in demandes.data" :key="d.id" class="row-link" @click="ouvrir(d)">
                        <td class="mono text-sm nowrap">{{ d.reference }}</td>
                        <td><span class="site-label">{{ d.site.nom }}</span></td>
                        <td class="text-dim">{{ d.type_demande.libelle }}</td>
                        <td class="mono text-sm nowrap">
                            <template v-if="d.date_debut">{{ formatDate(d.date_debut) }} → {{ formatDate(d.date_fin) }}</template>
                            <span v-else style="font-style:italic">À définir</span>
                        </td>
                        <td><span :class="statutBadgeClass(d.statut.code)">{{ d.statut.libelle }}</span></td>
                        <td class="text-right" @click.stop>
                            <div class="doc-btns">
                                <template v-if="d.statut.code === 'validee'">
                                    <button type="button" class="btn-sm-edit" @click="openPdf(route('demandes.pdf.autorisation', d.id), `autorisation_${d.id}.pdf`)"><Icon name="file-pdf" />Autorisation</button>
                                    <button type="button" class="btn-sm-edit" @click="openPdf(route('demandes.pdf.cahier-charges', d.id), `cahier_des_charges_${d.id}.pdf`)"><Icon name="file-pdf" />Cahier des charges</button>
                                </template>
                                <Link v-else-if="d.statut.code === 'brouillon'" :href="route('demandes.edit', d.id)" class="btn-sm-edit">Continuer</Link>
                                <Link v-else-if="d.statut.code === 'a_completer'" :href="route('demandes.edit', d.id)" class="btn-sm-edit btn-sm-todo">Compléter</Link>
                                <Link :href="route('demandes.show', d.id)" class="btn-sm-edit">Détails</Link>
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
.doc-btns :deep(svg) { width: 14px; height: 14px; }
.nowrap { white-space: nowrap; }
.btn-sm-todo { background: var(--ochre-soft); border-color: var(--ochre-line); color: var(--ochre); }
.btn-sm-todo:hover { border-color: var(--ochre); color: var(--ochre); }

.todo-banner {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; margin-bottom: 16px;
    background: var(--ochre-soft); border: 1px solid var(--ochre-line);
    border-radius: var(--r);
    font-size: 14px; color: var(--ink);
}
.todo-banner svg { width: 20px; height: 20px; color: var(--ochre); flex-shrink: 0; }
.todo-banner span { flex: 1; }
.todo-banner__btn {
    padding: 6px 12px; border: 1px solid var(--ochre); border-radius: var(--r-sm);
    background: var(--surface); color: var(--ochre); font-size: 13px; font-weight: 600; cursor: pointer;
}
.todo-banner__btn:hover { background: var(--ochre); color: #fff; }
</style>
