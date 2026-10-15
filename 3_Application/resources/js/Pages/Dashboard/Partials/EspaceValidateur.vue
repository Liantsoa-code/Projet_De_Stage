<script setup>
import CompteurAnime from '@/Components/CompteurAnime.vue';
import Icon from '@/Components/Icon.vue';
import CarteSites from '@/Pages/Dashboard/Partials/CarteSites.vue';
import LigneDossier from '@/Pages/Dashboard/Partials/LigneDossier.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Espace validateur : traiter sa file de dossiers, du plus urgent au moins urgent
const page = usePage();
const c = computed(() => page.props.compteurs || {});
const file = computed(() => page.props.file || []);
const attenteDemandeur = computed(() => page.props.attente_demandeur || []);
const activite = computed(() => page.props.activite || {});
const delaiMedian = computed(() => page.props.delai_median);
const mois = new Date().toLocaleDateString('fr-FR', { month: 'long' });
</script>

<template>
    <div class="dash">
        <div class="kpis">
            <div class="kpi">
                <CompteurAnime class="kpi__n" :valeur="c.en_attente ?? 0" />
                <span class="kpi__l">À traiter</span>
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
            <div class="kpi" :class="{ 'kpi--warn': c.conflits }">
                <CompteurAnime class="kpi__n" :valeur="c.conflits ?? 0" />
                <span class="kpi__l">Conflits de calendrier</span>
                <span class="kpi__s">période sensible sur les dates</span>
            </div>
            <div class="kpi">
                <CompteurAnime class="kpi__n" :valeur="c.attente_demandeur ?? 0" />
                <span class="kpi__l">Chez le demandeur</span>
                <span class="kpi__s">compléments demandés</span>
            </div>
        </div>

        <div class="dash-grid">
            <section class="panel">
                <header class="panel__head">
                    <h2>Ma file de traitement</h2>
                    <Link :href="route('validateur.demandes.index')" class="link">Tout voir</Link>
                </header>
                <div v-if="file.length" class="rows">
                    <LigneDossier v-for="d in file" :key="d.id" :d="d" />
                </div>
                <div v-else class="empty">
                    <Icon name="check-circle" />
                    <p>Aucun dossier à traiter sur vos sites. Les nouvelles demandes apparaîtront ici, les plus urgentes en premier.</p>
                </div>
            </section>

            <CarteSites titre="Vos sites" />
        </div>

        <div class="grid-2">
            <section class="panel">
                <header class="panel__head">
                    <h2>En attente du demandeur</h2>
                    <small>compléments demandés, les plus anciens d'abord</small>
                </header>
                <div v-if="attenteDemandeur.length" class="rows">
                    <Link v-for="d in attenteDemandeur" :key="d.id" :href="route('validateur.demandes.show', d.id)" class="row row--attente">
                        <span class="mono ref">{{ d.reference }}</span>
                        <span class="row__main">
                            <strong>{{ d.demandeur ?? 'Demandeur' }}</strong>
                            <span class="site-label">{{ d.site ?? '—' }}</span>
                        </span>
                        <span class="tag" :class="{ 'tag--warn': d.depuis > 7 }"><Icon name="clock" />depuis {{ d.depuis ?? '—' }} j</span>
                    </Link>
                </div>
                <p v-else class="empty empty--small">Aucun dossier en attente de complément.</p>
            </section>

            <section class="panel">
                <header class="panel__head">
                    <h2>Mon activité en {{ mois }}</h2>
                    <small v-if="delaiMedian != null">délai médian sur vos sites : {{ delaiMedian }} j</small>
                </header>
                <ul class="activite">
                    <li><CompteurAnime class="activite__n mono" :valeur="activite.en_analyse ?? 0" />prises en charge</li>
                    <li><CompteurAnime class="activite__n mono" :valeur="activite.a_completer ?? 0" />compléments demandés</li>
                    <li><CompteurAnime class="activite__n mono" :valeur="activite.validee ?? 0" />validations</li>
                    <li><CompteurAnime class="activite__n mono" :valeur="activite.rejetee ?? 0" />rejets</li>
                </ul>
            </section>
        </div>
    </div>
</template>

<style scoped>
.row--attente { grid-template-columns: 128px minmax(0, 1fr) auto; }
.activite { list-style: none; margin: 0; padding: 16px 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 14px; }
.activite li { display: grid; gap: 2px; font-size: 13px; color: var(--ink-2); }
.activite__n { font-size: 26px; font-weight: 700; line-height: 1; color: var(--ink); }
@media (max-width: 720px) { .row--attente { grid-template-columns: minmax(0, 1fr); gap: 8px; } }
</style>
