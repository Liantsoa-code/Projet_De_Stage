<script setup>
import CompteurAnime from '@/Components/CompteurAnime.vue';
import Icon from '@/Components/Icon.vue';
import CarteSites from '@/Pages/Dashboard/Partials/CarteSites.vue';
import LigneDossier from '@/Pages/Dashboard/Partials/LigneDossier.vue';
import StatsPrincipales from '@/Pages/Dashboard/Partials/StatsPrincipales.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/utils/statut.js';

// Espace observateur : suivre tous les dossiers en cours, sans décision, et l'issue de ses signalements
const page = usePage();
const c = computed(() => page.props.compteurs || {});
const file = computed(() => page.props.file || []);
const signalements = computed(() => page.props.signalements || []);
</script>

<template>
    <div class="dash">
        <StatsPrincipales />

        <div class="kpis kpis--sec">
            <div class="kpi">
                <CompteurAnime class="kpi__n" :valeur="c.en_attente ?? 0" />
                <span class="kpi__l">En instruction</span>
                <span class="kpi__s">dont {{ c.non_pris ?? 0 }} pas encore pris en charge</span>
            </div>
            <div class="kpi" :class="{ 'kpi--alert': c.imminents }">
                <CompteurAnime class="kpi__n" :valeur="c.imminents ?? 0" />
                <span class="kpi__l">Arrivées imminentes</span>
                <span class="kpi__s">séjour dans 14 j ou moins, sans décision</span>
            </div>
            <div class="kpi" :class="{ 'kpi--alert': c.retards }">
                <CompteurAnime class="kpi__n" :valeur="c.retards ?? 0" />
                <span class="kpi__l">En retard</span>
                <span class="kpi__s">plus de 7 jours sans décision</span>
            </div>
            <div class="kpi" :class="{ 'kpi--warn': c.signales }">
                <CompteurAnime class="kpi__n" :valeur="c.signales ?? 0" />
                <span class="kpi__l">Dossiers signalés</span>
                <span class="kpi__s">au moins un signalement ouvert</span>
            </div>
        </div>

        <div class="dash-grid">
            <section class="panel">
                <header class="panel__head">
                    <h2>Dossiers en instruction</h2>
                    <Link :href="route('validateur.demandes.index')" class="link">Tout voir</Link>
                </header>
                <div v-if="file.length" class="rows">
                    <LigneDossier v-for="d in file" :key="d.id" :d="d" />
                </div>
                <div v-else class="empty">
                    <Icon name="check-circle" />
                    <p>Aucun dossier en instruction. Les nouvelles demandes apparaîtront ici, les plus urgentes en premier.</p>
                </div>
            </section>

            <CarteSites titre="Aires protégées" />
        </div>

        <section class="panel">
            <header class="panel__head">
                <h2>Mes signalements</h2>
                <small>les ouverts d'abord</small>
            </header>
            <ul v-if="signalements.length" class="sigs">
                <li v-for="s in signalements" :key="s.id">
                    <Link :href="route('validateur.demandes.show', s.demande_id)" class="sig">
                        <span class="sig__etat" :class="s.ouvert ? 'sig__etat--ouvert' : 'sig__etat--traite'">
                            <Icon :name="s.ouvert ? 'warning' : 'check-circle'" />{{ s.ouvert ? 'Ouvert' : 'Traité' }}
                        </span>
                        <span class="sig__corps">
                            <span class="sig__tete"><span class="mono">{{ s.reference }}</span> · {{ s.site }} · {{ s.partie_libelle }}</span>
                            <span class="sig__message">{{ s.message }}</span>
                            <span v-if="!s.ouvert" class="sig__reponse">
                                Traité par {{ s.traite_par?.name ?? 'un compte supprimé' }} le {{ formatDate(s.traite_le) }}<template v-if="s.reponse"> : {{ s.reponse }}</template>
                            </span>
                        </span>
                        <small class="sig__date">{{ formatDate(s.created_at) }}</small>
                    </Link>
                </li>
            </ul>
            <div v-else class="empty empty--small">
                <p>Vous n'avez encore rien signalé. Sur la fiche d'un dossier, « Signaler un problème » prévient les validateurs de l'aire protégée et les administrateurs.</p>
            </div>
        </section>
    </div>
</template>

<style scoped>
.sigs { list-style: none; margin: 0; padding: 0 0 8px; }
.sig { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 12px; align-items: start; padding: 12px 24px; border-bottom: 1px solid var(--rule-soft); color: inherit; text-decoration: none; }
.sigs li:last-child .sig { border-bottom: 0; }
.sig:hover { background: var(--paper); }
.sig__etat { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: var(--r-pill); font: 600 11.5px/1.4 var(--f-body); white-space: nowrap; }
.sig__etat svg { width: 13px; height: 13px; }
.sig__etat--ouvert { background: var(--ochre-soft); color: var(--ochre); }
.sig__etat--traite { background: var(--forest-soft); color: var(--forest); }
.sig__corps { display: grid; gap: 3px; min-width: 0; }
.sig__tete { font-size: 12.5px; color: var(--ink-3); }
.sig__message { font-size: 14px; color: var(--ink); overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.sig__reponse { font-size: 12.5px; color: var(--forest); }
.sig__date { font-size: 12px; color: var(--ink-3); white-space: nowrap; }
@media (max-width: 620px) {
    .sig { grid-template-columns: minmax(0, 1fr); gap: 6px; }
    .sig__etat { justify-self: start; }
}
</style>
