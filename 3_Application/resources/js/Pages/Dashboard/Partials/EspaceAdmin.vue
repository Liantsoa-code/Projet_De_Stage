<script setup>
import CompteurAnime from '@/Components/CompteurAnime.vue';
import Icon from '@/Components/Icon.vue';
import CarteSites from '@/Pages/Dashboard/Partials/CarteSites.vue';
import LigneDossier from '@/Pages/Dashboard/Partials/LigneDossier.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { formatDate } from '@/utils/statut.js';

// Espace admin : qualité du service, organisation, fréquentation des aires protégées
const page = usePage();
const c = computed(() => page.props.compteurs || {});
const q = computed(() => page.props.qualite || {});
const terrain = computed(() => page.props.terrain || { sejours: 0, personnes: 0, liste: [] });
const urgents = computed(() => page.props.urgents || []);
const sansValidateur = computed(() => page.props.sites_sans_validateur || []);
const chargeValidateurs = computed(() => page.props.charge_validateurs || []);
const frequentation = computed(() => page.props.frequentation || []);
const parType = computed(() => page.props.par_type || []);
const parMois = computed(() => page.props.par_mois || { courante: [], precedente: [] });

const maxFrequentation = computed(() => Math.max(1, ...frequentation.value.map((s) => s.personnes_jours)));
const maxParType = computed(() => Math.max(1, ...parType.value.map((t) => Number(t.total))));
const maxValidateur = computed(() => Math.max(1, ...chargeValidateurs.value.map((v) => v.en_attente)));

const annee = new Date().getFullYear();
const moisCourant = new Date().getMonth();
const chartCanvas = ref(null);
let chart = null;

onMounted(async () => {
    if (!chartCanvas.value) return;
    // Chart.js chargé à la demande, avec les seuls modules de l'histogramme
    const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } = await import('chart.js');
    Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);
    if (!chartCanvas.value) return; // page quittée pendant le chargement

    const courante = parMois.value.courante;
    chart = new Chart(chartCanvas.value.getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
            datasets: [
                {
                    label: String(annee - 1),
                    data: parMois.value.precedente,
                    backgroundColor: '#D3DAD1',
                    borderRadius: 2,
                    maxBarThickness: 16,
                },
                {
                    label: String(annee),
                    data: courante,
                    backgroundColor: courante.map((_, i) => (i === moisCourant ? '#DCEDE2' : '#00683A')),
                    borderColor: '#00683A',
                    borderWidth: courante.map((_, i) => (i === moisCourant ? 1 : 0)),
                    borderRadius: 2,
                    maxBarThickness: 16,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: '#15201A', padding: 10, cornerRadius: 4 },
            },
            scales: {
                y: { beginAtZero: true, border: { display: false }, ticks: { precision: 0, color: '#6E7A72', font: { family: 'JetBrains Mono', size: 11 } }, grid: { color: '#E4E9E2' } },
                x: { border: { color: '#D3DAD1' }, ticks: { color: '#6E7A72', font: { family: 'Inter', size: 11 } }, grid: { display: false } },
            },
        },
    });
});

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div class="dash">
        <div class="kpis">
            <div class="kpi" :class="{ 'kpi--warn': q.pct_objectif != null && q.pct_objectif < 80 }">
                <span class="kpi__n">
                    <template v-if="q.delai_median != null"><CompteurAnime :valeur="q.delai_median" /><small>&nbsp;j</small></template>
                    <template v-else>—</template>
                </span>
                <span class="kpi__l">Délai médian de décision</span>
                <span class="kpi__s">
                    <template v-if="q.pct_objectif != null">{{ q.pct_objectif }} % décidés en {{ q.objectif_jours }} j ou moins</template>
                    <template v-else>aucune décision pour le moment</template>
                </span>
            </div>
            <div class="kpi">
                <span class="kpi__n">
                    <template v-if="q.taux_validation != null"><CompteurAnime :valeur="q.taux_validation" /><small>&nbsp;%</small></template>
                    <template v-else>—</template>
                </span>
                <span class="kpi__l">Taux de validation</span>
                <span class="kpi__s">{{ q.decisions ?? 0 }} décisions<template v-if="q.pct_complement != null"> · {{ q.pct_complement }} % avec complément</template></span>
            </div>
            <div class="kpi" :class="{ 'kpi--alert': c.imminents || c.retards }">
                <CompteurAnime class="kpi__n" :valeur="c.en_attente ?? 0" />
                <span class="kpi__l">Dossiers en attente</span>
                <span class="kpi__s">{{ c.imminents ?? 0 }} arrivées imminentes · {{ c.retards ?? 0 }} en retard</span>
            </div>
            <div class="kpi">
                <CompteurAnime class="kpi__n" :valeur="terrain.personnes" />
                <span class="kpi__l">Personnes sur le terrain</span>
                <span class="kpi__s">aujourd'hui, {{ terrain.sejours }} séjour{{ terrain.sejours > 1 ? 's' : '' }} en cours</span>
            </div>
        </div>

        <div class="dash-grid">
            <section class="panel">
                <header class="panel__head">
                    <h2>Points d'attention</h2>
                    <Link :href="route('validateur.demandes.index')" class="link">Toutes les demandes</Link>
                </header>
                <div v-if="sansValidateur.length" class="alerte-orga">
                    <Icon name="warning" />
                    <div>
                        <strong>{{ sansValidateur.length }} site{{ sansValidateur.length > 1 ? 's' : '' }} sans validateur</strong>
                        <p>{{ sansValidateur.map((s) => s.nom).join(', ') }} : les nouvelles demandes n'y sont notifiées à personne.</p>
                        <Link :href="route('admin.users.index', { role: 'validateur' })" class="link">Affecter un validateur</Link>
                    </div>
                </div>
                <div v-if="urgents.length" class="rows">
                    <LigneDossier v-for="d in urgents" :key="d.id" :d="d" />
                </div>
                <div v-else-if="!sansValidateur.length" class="empty">
                    <Icon name="check-circle" />
                    <p>Aucun dossier urgent, en retard ou en conflit, et chaque site a son validateur.</p>
                </div>
            </section>

            <CarteSites titre="Aires protégées" />
        </div>

        <div class="dash-grid dash-grid--charts">
            <section class="panel">
                <header class="panel__head">
                    <h2>Demandes reçues par mois</h2>
                    <small class="legende-annees">
                        <span><i class="carre carre--n1"></i>{{ annee - 1 }}</span>
                        <span><i class="carre"></i>{{ annee }}</span>
                    </small>
                </header>
                <div class="chart-box"><canvas ref="chartCanvas" :aria-label="`Demandes reçues par mois en ${annee} et ${annee - 1}`" role="img"></canvas></div>
            </section>

            <div class="stack">
                <section class="panel">
                    <header class="panel__head">
                        <h2>Fréquentation autorisée</h2>
                        <small>12 prochains mois, en personnes × jours</small>
                    </header>
                    <ul v-if="frequentation.length" class="bars">
                        <li v-for="s in frequentation" :key="s.nom">
                            <span class="bars__label site-label">{{ s.nom }}<small>{{ s.sejours }} séjour{{ s.sejours > 1 ? 's' : '' }} · {{ s.personnes }} pers.</small></span>
                            <span class="bars__track"><i :style="{ width: `${(s.personnes_jours / maxFrequentation) * 100}%` }"></i></span>
                            <CompteurAnime class="bars__n mono" :valeur="s.personnes_jours" />
                        </li>
                    </ul>
                    <p v-else class="empty empty--small">Aucun séjour autorisé sur les 12 prochains mois.</p>
                </section>

                <section class="panel">
                    <header class="panel__head">
                        <h2>Charge des validateurs</h2>
                        <small>dossiers en attente sur leurs sites</small>
                    </header>
                    <ul v-if="chargeValidateurs.length" class="bars">
                        <li v-for="v in chargeValidateurs" :key="v.id">
                            <span class="bars__label">{{ v.nom }}<small>{{ v.sites }} site{{ v.sites > 1 ? 's' : '' }}</small></span>
                            <span class="bars__track"><i :style="{ width: `${(v.en_attente / maxValidateur) * 100}%` }"></i></span>
                            <CompteurAnime class="bars__n mono" :valeur="v.en_attente" />
                        </li>
                    </ul>
                    <p v-else class="empty empty--small">Aucun validateur enregistré.</p>
                </section>
            </div>
        </div>

        <div class="grid-2">
            <section class="panel">
                <header class="panel__head">
                    <h2>Sur le terrain aujourd'hui</h2>
                    <small>{{ terrain.sejours }} séjour{{ terrain.sejours > 1 ? 's' : '' }} · {{ terrain.personnes }} personne{{ terrain.personnes > 1 ? 's' : '' }}</small>
                </header>
                <div v-if="terrain.liste.length" class="rows">
                    <Link v-for="s in terrain.liste" :key="s.id" :href="route('validateur.demandes.show', s.id)" class="row row--terrain">
                        <span class="mono ref">{{ s.reference }}</span>
                        <span class="row__main">
                            <strong>{{ s.demandeur ?? 'Demandeur' }}</strong>
                            <span class="site-label">{{ s.site ?? '—' }}</span>
                        </span>
                        <span class="row__main terrain__fin">
                            <span class="tag"><Icon name="users" />{{ s.equipe }} pers.</span>
                            <span class="dates">jusqu'au {{ formatDate(s.date_fin) }}</span>
                        </span>
                    </Link>
                </div>
                <p v-else class="empty empty--small">Aucune équipe sur le terrain aujourd'hui.</p>
            </section>

            <section class="panel">
                <header class="panel__head"><h2>Par type d'activité</h2></header>
                <ul v-if="parType.length" class="bars">
                    <li v-for="t in parType" :key="t.nom">
                        <span class="bars__label">{{ t.nom }}</span>
                        <span class="bars__track"><i :style="{ width: `${(Number(t.total) / maxParType) * 100}%` }"></i></span>
                        <CompteurAnime class="bars__n mono" :valeur="t.total" />
                    </li>
                </ul>
                <p v-else class="empty empty--small">Aucune demande pour le moment.</p>
            </section>
        </div>
    </div>
</template>

<style scoped>
.chart-box { position: relative; height: 300px; padding: 16px 24px; }
.legende-annees { display: inline-flex; gap: 12px; }
.legende-annees span { display: inline-flex; align-items: center; gap: 5px; }
.carre { display: inline-block; width: 10px; height: 10px; border-radius: 2px; background: var(--forest); }
.carre--n1 { background: var(--rule); }

.alerte-orga { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 12px; margin: 16px 24px 4px; padding: 12px 14px; background: var(--signal-soft); border: 1px solid var(--signal-line); border-radius: var(--r-sm); font-size: 13.5px; }
.alerte-orga > svg { width: 20px; height: 20px; color: var(--signal); }
.alerte-orga div { display: grid; gap: 4px; }
.alerte-orga p { color: var(--ink-2); }
.alerte-orga .link { justify-self: start; }

.row--terrain { grid-template-columns: 128px minmax(0, 1fr) auto; }
.terrain__fin { justify-items: end; }
@media (max-width: 720px) {
    .row--terrain { grid-template-columns: minmax(0, 1fr); gap: 8px; }
    .terrain__fin { justify-items: start; }
}
</style>
