<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DashboardMap from '@/Components/DashboardMap.vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import Chart from 'chart.js/auto';

const page = usePage();
const user = computed(() => page.props.auth.user);
const sites = computed(() => page.props.sites || []);
const stats = computed(() => page.props.stats || {});
const chartData = computed(() => page.props.chart_data || {});
const statsParseSite = computed(() => page.props.stats_par_site || []);
const statsParType = computed(() => page.props.stats_par_type || []);
const alertesRetard = computed(() => page.props.alertes_retard || []);
const alertesConflits = computed(() => page.props.alertes_conflits || []);

const chartCanvas = ref(null);
const chartSiteCanvas = ref(null);
const chartTypeCanvas = ref(null);

const totalAlertes = computed(() => alertesRetard.value.length + alertesConflits.value.length);

function joursDepuis(dateStr) {
    if (!dateStr) return '?';
    const d = new Date(dateStr);
    const now = new Date();
    return Math.floor((now - d) / 86400000);
}

const chartTooltip = {
    backgroundColor: 'rgba(17, 24, 39, 0.92)',
    padding: 10,
    cornerRadius: 10,
    displayColors: false,
};

onMounted(() => {
    if (user.value?.role === 'admin' || user.value?.role === 'validateur') {
        const ctx = chartCanvas.value.getContext('2d');
        const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        const dataValues = Array(12).fill(0);
        Object.keys(chartData.value).forEach(month => { dataValues[month - 1] = chartData.value[month]; });

        const areaGradient = ctx.createLinearGradient(0, 0, 0, 220);
        areaGradient.addColorStop(0, 'rgba(16, 185, 129, 0.28)');
        areaGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Demandes',
                    data: dataValues,
                    borderColor: '#10b981',
                    backgroundColor: areaGradient,
                    borderWidth: 2.5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#10b981',
                    pointBorderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: chartTooltip },
                scales: {
                    y: { beginAtZero: true, border: { display: false }, ticks: { stepSize: 1, color: '#9ca3af' }, grid: { color: '#f1f3f2' } },
                    x: { border: { display: false }, ticks: { color: '#9ca3af' }, grid: { display: false } }
                }
            }
        });

        if (chartSiteCanvas.value && statsParseSite.value.length > 0) {
            const ctxSite = chartSiteCanvas.value.getContext('2d');
            new Chart(ctxSite, {
                type: 'bar',
                data: {
                    labels: statsParseSite.value.map(s => s.nom.length > 14 ? s.nom.substring(0, 14) + '…' : s.nom),
                    datasets: [{
                        label: 'Demandes',
                        data: statsParseSite.value.map(s => s.total),
                        backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#f97316'],
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 36,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: chartTooltip },
                    scales: {
                        y: { beginAtZero: true, border: { display: false }, ticks: { stepSize: 1, color: '#9ca3af' }, grid: { color: '#f1f3f2' } },
                        x: { border: { display: false }, ticks: { color: '#6b7280', font: { size: 10 } }, grid: { display: false } }
                    }
                }
            });
        }

        if (chartTypeCanvas.value && statsParType.value.length > 0) {
            const ctxType = chartTypeCanvas.value.getContext('2d');
            new Chart(ctxType, {
                type: 'doughnut',
                data: {
                    labels: statsParType.value.map(t => t.nom),
                    datasets: [{
                        data: statsParType.value.map(t => t.total),
                        backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'],
                        borderColor: '#ffffff',
                        borderWidth: 3,
                        borderRadius: 6,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#6b7280', font: { size: 10 }, padding: 10, boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle' } },
                        tooltip: chartTooltip
                    },
                    cutout: '70%'
                }
            });
        }
    }
});
</script>

<template>
    <Head title="Tableau de bord" />

    <AuthenticatedLayout>
        <!-- Layout Demandeur -->
        <div v-if="user?.role === 'demandeur'" class="dash-grid-simple">
            <div class="glass dash-welcome">
                <div class="dw-left">
                    <span class="dw-greeting">Bonjour,</span>
                    <h2 class="dw-name">{{ user?.name }}</h2>
                    <p class="dw-desc">Bienvenue sur votre espace de gestion des demandes d'accès aux aires protégées de Madagascar.</p>
                </div>
            </div>
            <Link :href="route('demandes.index')" class="glass dash-card">
                <div class="dc-icon dc-icon--lime">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
                <div class="dc-content">
                    <span class="dc-label">Mes Demandes</span>
                    <span class="dc-desc">Soumettre et suivre vos dossiers d'accès</span>
                </div>
                <div class="dc-arrow">→</div>
            </Link>
        </div>

        <!-- Layout Luxe Admin / Validateur -->
        <div v-else class="dash-luxe-layout">

            <!-- Colonne 1 : Sidebar Navigation + Alertes -->
            <div class="dash-col dash-col-left">
                <div class="brand-header">
                    <h1 class="brand-title">Fanamby</h1>
                    <div class="role-pill" :class="`role-pill--${user?.role}`">{{ user?.role }}</div>
                </div>

                <div class="menu-section">
                    <h3 class="menu-title">Actions Rapides</h3>

                    <Link :href="route('validateur.demandes.index')" class="glass menu-card">
                        <div class="mc-icon dc-icon--blue">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="mc-content">
                            <span class="mc-label">Valider Dossiers</span>
                            <span class="mc-count" v-if="stats.demandes_en_attente">{{ stats.demandes_en_attente }} en attente</span>
                        </div>
                    </Link>

                    <Link v-if="user?.role === 'admin'" :href="route('admin.sites.index')" class="glass menu-card">
                        <div class="mc-icon dc-icon--lime">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        </div>
                        <div class="mc-content">
                            <span class="mc-label">Gérer les Sites</span>
                            <span class="mc-count">{{ sites.length }} sites</span>
                        </div>
                    </Link>

                    <Link v-if="user?.role === 'admin'" :href="route('admin.users.index')" class="glass menu-card">
                        <div class="mc-icon dc-icon--amber">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                        </div>
                        <div class="mc-content">
                            <span class="mc-label">Utilisateurs</span>
                            <span class="mc-count">{{ stats.total_users }} inscrits</span>
                        </div>
                    </Link>
                </div>

                <!-- Alertes -->
                <div class="glass alertes-card" v-if="totalAlertes > 0">
                    <div class="alertes-header">
                        <div class="alertes-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        </div>
                        <span class="alertes-title">Alertes</span>
                        <span class="alertes-badge">{{ totalAlertes }}</span>
                    </div>

                    <div v-if="alertesRetard.length > 0" class="alerte-group">
                        <span class="alerte-group-label">En attente depuis longtemps</span>
                        <div v-for="a in alertesRetard" :key="`retard-${a.id}`" class="alerte-item alerte-item--warn">
                            <div class="ai-content">
                                <span class="ai-ref">Dossier #{{ a.id }}</span>
                                <span class="ai-site">{{ a.site?.nom ?? '—' }}</span>
                            </div>
                            <span class="ai-days">{{ joursDepuis(a.date_soumission) }}j</span>
                        </div>
                    </div>

                    <div v-if="alertesConflits.length > 0" class="alerte-group">
                        <span class="alerte-group-label">Conflits non résolus</span>
                        <div v-for="a in alertesConflits" :key="`conflit-${a.id}`" class="alerte-item alerte-item--danger">
                            <div class="ai-content">
                                <span class="ai-ref">Dossier #{{ a.id }}</span>
                                <span class="ai-site">{{ a.site?.nom ?? '—' }}</span>
                            </div>
                            <span class="ai-tag">Conflit</span>
                        </div>
                    </div>
                </div>

                <div class="glass alertes-card alertes-card--ok" v-else>
                    <div class="alertes-header">
                        <div class="alertes-icon alertes-icon--ok">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="alertes-title">Alertes</span>
                    </div>
                    <p class="no-alerte-msg">Aucune alerte en cours</p>
                </div>
            </div>

            <!-- Colonne 2 : KPIs + Carte -->
            <div class="dash-col dash-col-center">

                <!-- Chiffres clés -->
                <div class="kpi-row">
                    <div class="glass kpi-box">
                        <div class="kpi-icon kpi-icon--blue">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        </div>
                        <div class="kpi-val">{{ stats.total_demandes ?? 0 }}</div>
                        <div class="kpi-lbl">Total demandes</div>
                    </div>
                    <div class="glass kpi-box">
                        <div class="kpi-icon kpi-icon--green">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="kpi-val kpi-val--green">{{ stats.pct_validees ?? 0 }}<span class="kpi-unit">%</span></div>
                        <div class="kpi-lbl">% Validées</div>
                    </div>
                    <div class="glass kpi-box">
                        <div class="kpi-icon kpi-icon--purple">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="kpi-val kpi-val--purple">
                            <template v-if="stats.delai_moyen != null">{{ stats.delai_moyen }}<span class="kpi-unit">j</span></template>
                            <template v-else><span class="kpi-na">—</span></template>
                        </div>
                        <div class="kpi-lbl">Délai moyen</div>
                    </div>
                    <div class="glass kpi-box">
                        <div class="kpi-icon kpi-icon--amber">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        </div>
                        <div class="kpi-val kpi-val--amber">{{ stats.demandes_en_attente ?? 0 }}</div>
                        <div class="kpi-lbl">En attente</div>
                    </div>
                </div>

                <!-- Carte -->
                <div class="glass map-header">
                    <span class="mh-title">Aperçu du Territoire (Madagascar)</span>
                    <span class="mh-sub">{{ sites.length }} Aires Protégées Gérées</span>
                </div>
                <div class="dash-map-container">
                    <DashboardMap :sites="sites" />
                </div>
            </div>

            <!-- Colonne 3 : Profil + Stats + Graphiques -->
            <div class="dash-col dash-col-right">

                <!-- Profil -->
                <div class="glass profile-card">
                    <div class="pc-avatar">{{ user?.name?.charAt(0)?.toUpperCase() }}</div>
                    <div class="pc-info">
                        <span class="pc-name">{{ user?.name }}</span>
                        <span class="pc-role">{{ user?.role === 'admin' ? 'Administrateur' : 'Validateur' }}</span>
                    </div>
                </div>

                <!-- Stats en analyse / validées -->
                <div class="stats-grid">
                    <div class="glass stat-box">
                        <div class="sb-icon sb-icon--warn">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="sb-val">{{ stats.demandes_en_attente }}</div>
                        <div class="sb-lbl">En analyse</div>
                    </div>
                    <div class="glass stat-box">
                        <div class="sb-icon sb-icon--ok">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="sb-val">{{ stats.demandes_validees }}</div>
                        <div class="sb-lbl">Validées</div>
                    </div>
                </div>

                <!-- Graphique Évolution -->
                <div class="glass chart-card">
                    <h3 class="chart-title">Évolution des demandes</h3>
                    <p class="chart-sub">Volume d'accès demandés sur l'année</p>
                    <div class="chart-wrapper">
                        <canvas ref="chartCanvas"></canvas>
                    </div>
                </div>

                <!-- Répartition par Site -->
                <div class="glass chart-card">
                    <h3 class="chart-title">Répartition par site</h3>
                    <p class="chart-sub">Nombre de demandes par aire protégée</p>
                    <div class="chart-wrapper chart-wrapper--sm" v-if="statsParseSite.length > 0">
                        <canvas ref="chartSiteCanvas"></canvas>
                    </div>
                    <div class="empty-chart" v-else>
                        <span>Aucune donnée disponible</span>
                    </div>
                </div>

                <!-- Répartition par Type -->
                <div class="glass chart-card">
                    <h3 class="chart-title">Répartition par type</h3>
                    <p class="chart-sub">Catégories de demandes soumises</p>
                    <div class="chart-wrapper chart-wrapper--donut" v-if="statsParType.length > 0">
                        <canvas ref="chartTypeCanvas"></canvas>
                    </div>
                    <div class="empty-chart" v-else>
                        <span>Aucune donnée disponible</span>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.dash-luxe-layout {
    display: grid;
    grid-template-columns: 300px 1fr 500px;
    gap: 18px;
    height: calc(100vh - 64px);
    max-height: 1000px;
}
@media (max-width: 1280px) {
    .dash-luxe-layout { grid-template-columns: 240px 1fr; height: auto; max-height: none; }
    .dash-col-right { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
}
@media (max-width: 900px) {
    .dash-luxe-layout { grid-template-columns: 1fr; }
    .dash-col-right { grid-template-columns: 1fr; }
}

.dash-col { display: flex; flex-direction: column; gap: 16px; overflow-y: auto; padding: 2px 4px 4px 2px; }
.dash-col::-webkit-scrollbar { width: 4px; }
.dash-col::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }

/* Entrée en cascade des colonnes */
.dash-col-left { animation: fade-up 0.5s var(--ease-out) 0.02s backwards; }
.dash-col-center { animation: fade-up 0.5s var(--ease-out) 0.08s backwards; }
.dash-col-right { animation: fade-up 0.5s var(--ease-out) 0.14s backwards; }


/* ── Colonne gauche ── */
.brand-header {
    margin-bottom: 4px;
    padding: 18px;
    border-radius: var(--radius-xl);
    background:
        radial-gradient(120% 140% at 100% 0%, rgba(52, 211, 153, 0.35) 0%, transparent 55%),
        linear-gradient(135deg, #064e3b 0%, #065f46 55%, #047857 100%);
    box-shadow: 0 16px 32px -16px rgba(6, 78, 59, 0.6);
    position: relative;
    overflow: hidden;
}
.brand-header::after {
    content: '';
    position: absolute;
    right: -30px; bottom: -40px;
    width: 120px; height: 120px;
    border-radius: 50%;
    border: 18px solid rgba(255, 255, 255, 0.06);
}
.brand-title { font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: -0.02em; position: relative; }
.role-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 8px; position: relative; backdrop-filter: blur(6px); }
.role-pill--admin { background: rgba(253, 230, 138, 0.18); color: #fde68a; border: 1px solid rgba(253, 230, 138, 0.35); }
.role-pill--validateur { background: rgba(191, 219, 254, 0.18); color: #bfdbfe; border: 1px solid rgba(191, 219, 254, 0.35); }

.menu-title { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--c-muted); margin: 4px 0 2px 4px; }
.menu-section { display: flex; flex-direction: column; gap: 10px; }
.menu-card {
    display: flex; align-items: center; gap: 12px; padding: 12px 14px;
    text-decoration: none; cursor: pointer;
    border-radius: var(--radius-lg); background: var(--c-surface); border: 1px solid var(--c-line);
    transition: transform 0.25s var(--ease-out), box-shadow 0.25s, border-color 0.25s;
    position: relative;
}
.menu-card::after {
    content: '→';
    margin-left: auto;
    color: var(--c-subtle);
    transition: transform 0.25s var(--ease-out), color 0.2s;
}
.menu-card:hover { border-color: #a7f3d0; transform: translateY(-1px); box-shadow: var(--shadow-md); }
.menu-card:hover::after { color: var(--c-brand); transform: translateX(3px); }
.mc-icon { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mc-icon svg { width: 19px; height: 19px; stroke-width: 1.5; }
.mc-content { flex: 1; display: flex; flex-direction: column; gap: 1px; }
.mc-label { font-size: 13px; font-weight: 600; color: var(--c-ink); }
.mc-count { font-size: 11px; color: var(--c-muted); }
.dc-icon--lime { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.15); }
.dc-icon--blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.15); }
.dc-icon--amber { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706; box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.18); }

.alertes-card { padding: 16px; border-radius: var(--radius-lg); }
.alertes-card--ok { border-color: #d1fae5; background: linear-gradient(180deg, #ffffff 0%, #f6fdf9 100%); }
.alertes-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.alertes-icon { width: 32px; height: 32px; border-radius: 10px; background: #fff7ed; color: #f97316; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgba(249, 115, 22, 0.15); }
.alertes-icon svg { width: 17px; height: 17px; stroke-width: 1.5; }
.alertes-icon--ok { background: #ecfdf5; color: #10b981; box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.15); }
.alertes-title { font-size: 13px; font-weight: 700; color: var(--c-ink); flex: 1; }
.alertes-badge { background: linear-gradient(180deg, #f87171, #dc2626); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 20px; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35); }
.alerte-group { margin-bottom: 10px; }
.alerte-group:last-child { margin-bottom: 0; }
.alerte-group-label { font-size: 10.5px; font-weight: 700; color: var(--c-subtle); text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 6px; }
.alerte-item { display: flex; align-items: center; justify-content: space-between; padding: 9px 12px; border-radius: 10px; margin-bottom: 6px; transition: transform 0.2s var(--ease-out); }
.alerte-item:hover { transform: translateX(2px); }
.alerte-item--warn { background: #fffbeb; border: 1px solid #fef3c7; box-shadow: inset 3px 0 0 #f59e0b; }
.alerte-item--danger { background: #fef2f2; border: 1px solid #fecaca; box-shadow: inset 3px 0 0 #ef4444; }
.ai-content { display: flex; flex-direction: column; gap: 1px; }
.ai-ref { font-size: 11.5px; font-weight: 700; color: var(--c-text); }
.ai-site { font-size: 10.5px; color: var(--c-muted); }
.ai-days { font-size: 11px; font-weight: 800; color: #b45309; background: #fef3c7; padding: 2px 8px; border-radius: 12px; }
.ai-tag { font-size: 10px; font-weight: 700; color: #b91c1c; background: #ffffff; border: 1px solid #fecaca; padding: 2px 8px; border-radius: 12px; }
.no-alerte-msg { font-size: 11.5px; color: var(--c-muted); text-align: center; padding: 4px 0; }

/* ── Colonne centrale ── */
.dash-col-center { display: flex; flex-direction: column; height: 100%; overflow-y: auto; gap: 16px; }
.dash-col-center::-webkit-scrollbar { width: 4px; }
.dash-col-center::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }

.kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; flex-shrink: 0; }
@media (max-width: 900px) { .kpi-row { grid-template-columns: repeat(2, 1fr); } }

.kpi-box {
    --kpi-accent: #3b82f6;
    background: var(--c-surface); padding: 16px; border-radius: var(--radius-lg);
    display: flex; flex-direction: column; align-items: flex-start;
    border: 1px solid var(--c-line);
    position: relative; overflow: hidden;
    transition: transform 0.25s var(--ease-out), box-shadow 0.25s, border-color 0.25s;
}
.kpi-box::before {
    content: '';
    position: absolute; inset: 0 0 auto 0; height: 3px;
    background: linear-gradient(90deg, var(--kpi-accent), transparent);
    opacity: 0.85;
}
.kpi-box::after {
    content: '';
    position: absolute; right: -24px; top: -24px;
    width: 80px; height: 80px; border-radius: 50%;
    background: radial-gradient(circle, var(--kpi-accent) 0%, transparent 70%);
    opacity: 0.08;
    transition: opacity 0.25s;
}
.kpi-box:hover { transform: translateY(-2px); border-color: #d9dedc; box-shadow: var(--shadow-md); }
.kpi-box:hover::after { opacity: 0.16; }
.kpi-box:has(.kpi-icon--green) { --kpi-accent: #10b981; }
.kpi-box:has(.kpi-icon--purple) { --kpi-accent: #8b5cf6; }
.kpi-box:has(.kpi-icon--amber) { --kpi-accent: #f59e0b; }
.kpi-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; flex-shrink: 0; }
.kpi-icon svg { width: 17px; height: 17px; stroke-width: 1.5; }
.kpi-icon--blue { background: #eff6ff; color: #3b82f6; box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.15); }
.kpi-icon--green { background: #ecfdf5; color: #10b981; box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.15); }
.kpi-icon--purple { background: #f5f3ff; color: #8b5cf6; box-shadow: inset 0 0 0 1px rgba(139, 92, 246, 0.15); }
.kpi-icon--amber { background: #fffbeb; color: #f59e0b; box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.18); }
.kpi-val { font-size: 24px; font-weight: 700; color: var(--c-ink); line-height: 1; margin-bottom: 6px; letter-spacing: -0.02em; }
.kpi-val--green { color: #059669; }
.kpi-val--purple { color: #7c3aed; }
.kpi-val--amber { color: #d97706; }
.kpi-unit { font-size: 14px; font-weight: 600; margin-left: 2px; color: var(--c-muted); }
.kpi-na { font-size: 20px; color: var(--c-subtle); }
.kpi-lbl { font-size: 12px; color: var(--c-muted); font-weight: 500; letter-spacing: 0.01em; }

.map-header {
    background: var(--c-surface); padding: 14px 18px;
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    border: 1px solid var(--c-line); border-bottom: 1px solid var(--c-line-soft);
    box-shadow: none;
    z-index: 2; position: relative; flex-shrink: 0;
}
.mh-title { font-size: 13px; font-weight: 700; color: var(--c-ink); display: inline-flex; align-items: center; gap: 8px; }
.mh-title::before {
    content: '';
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--c-brand);
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
}
.mh-sub { font-size: 10.5px; font-weight: 600; background: var(--c-brand-soft); color: var(--c-brand-700); padding: 4px 10px; border-radius: 20px; border: 1px solid #d1fae5; white-space: nowrap; }
.dash-map-container { flex: 1; min-height: 250px; position: relative; margin-top: -16px; }
.dash-map-container :deep(.map-wrapper) {
    border-radius: 0 0 var(--radius-xl) var(--radius-xl);
    border: 1px solid var(--c-line); border-top: none;
    box-shadow: var(--shadow-md);
}

/* ── Colonne droite ── */
.profile-card {
    background: var(--c-surface); display: flex; align-items: center; gap: 12px;
    padding: 14px; border-radius: var(--radius-lg); flex-shrink: 0; border: 1px solid var(--c-line);
}
.pc-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #34d399 0%, #047857 100%);
    color: #ffffff;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; font-weight: 800;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.35), 0 6px 14px -4px rgba(4, 120, 87, 0.45);
    flex-shrink: 0;
}
.pc-info { display: flex; flex-direction: column; gap: 2px; }
.pc-name { font-size: 14px; font-weight: 700; color: var(--c-ink); }
.pc-role { font-size: 11px; color: var(--c-muted); }

.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; flex-shrink: 0; }
.stat-box {
    background: var(--c-surface); padding: 16px; border-radius: var(--radius-lg);
    display: flex; flex-direction: column; align-items: flex-start;
    border: 1px solid var(--c-line);
    transition: transform 0.25s var(--ease-out), box-shadow 0.25s, border-color 0.25s;
}
.stat-box:hover { border-color: #d9dedc; transform: translateY(-2px); box-shadow: var(--shadow-md); }
.sb-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
.sb-icon svg { width: 17px; height: 17px; stroke-width: 1.5; }
.sb-icon--warn { background: #fefce8; color: #ca8a04; box-shadow: inset 0 0 0 1px rgba(202, 138, 4, 0.15); }
.sb-icon--ok { background: #ecfdf5; color: #10b981; box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.15); }
.sb-val { font-size: 26px; font-weight: 700; color: var(--c-ink); line-height: 1; margin-bottom: 6px; letter-spacing: -0.02em; }
.sb-lbl { font-size: 12px; color: var(--c-muted); }

.chart-card { background: var(--c-surface); padding: 18px; border-radius: var(--radius-lg); display: flex; flex-direction: column; border: 1px solid var(--c-line); transition: box-shadow 0.25s, border-color 0.25s; }
.chart-card:hover { box-shadow: var(--shadow-md); border-color: #d9dedc; }
.chart-title { font-size: 13px; font-weight: 700; color: var(--c-ink); margin-bottom: 2px; }
.chart-sub { font-size: 11px; color: var(--c-muted); margin-bottom: 14px; }
.chart-wrapper { flex: 1; position: relative; min-height: 130px; }
.chart-wrapper--sm { min-height: 110px; }
.chart-wrapper--donut { min-height: 140px; }
.empty-chart {
    display: flex; align-items: center; justify-content: center; min-height: 70px;
    border: 1px dashed #d9dedc; border-radius: var(--radius-md); background: var(--c-surface-2);
}
.empty-chart span { font-size: 11.5px; color: var(--c-subtle); font-style: italic; }

/* ── Vue demandeur ── */
.dash-grid-simple { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; max-width: 1000px; }
@media (max-width: 900px) { .dash-grid-simple { grid-template-columns: 1fr; } }
.dash-welcome {
    grid-column: 1 / -1;
    padding: 32px 34px;
    display: flex; align-items: center; justify-content: space-between; gap: 20px;
    border: none; border-radius: 24px;
    background:
        radial-gradient(90% 140% at 100% 0%, rgba(52, 211, 153, 0.35) 0%, transparent 55%),
        linear-gradient(135deg, #064e3b 0%, #065f46 55%, #047857 100%);
    box-shadow: 0 24px 48px -20px rgba(6, 78, 59, 0.6);
    position: relative; overflow: hidden;
    animation: fade-up 0.5s var(--ease-out) backwards;
}
.dash-welcome::before,
.dash-welcome::after {
    content: '';
    position: absolute; border-radius: 50%;
    border: 28px solid rgba(255, 255, 255, 0.05);
    pointer-events: none;
}
.dash-welcome::before { width: 240px; height: 240px; right: -60px; top: -90px; }
.dash-welcome::after { width: 160px; height: 160px; right: 120px; bottom: -110px; }
.dw-left { position: relative; z-index: 1; }
.dw-greeting { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #6ee7b7; display: block; margin-bottom: 6px; }
.dw-name { font-size: 24px; font-weight: 700; color: #ffffff; letter-spacing: -0.02em; margin-bottom: 8px; }
.dw-desc { font-size: 13px; color: rgba(236, 253, 245, 0.82); line-height: 1.6; max-width: 380px; }
.dash-card {
    background: var(--c-surface); padding: 20px;
    display: flex; align-items: center; gap: 14px;
    text-decoration: none; cursor: pointer;
    border: 1px solid var(--c-line); border-radius: var(--radius-xl);
    transition: transform 0.25s var(--ease-out), box-shadow 0.25s, border-color 0.25s;
    animation: fade-up 0.5s var(--ease-out) 0.08s backwards;
}
.dash-card:hover { border-color: #a7f3d0; transform: translateY(-3px); box-shadow: var(--shadow-md); }
.dc-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.dc-icon svg { width: 21px; height: 21px; stroke-width: 1.5; }
.dc-content { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.dc-label { font-size: 14px; font-weight: 700; color: var(--c-ink); }
.dc-desc { font-size: 12px; color: var(--c-muted); line-height: 1.4; }
.dc-arrow {
    font-size: 18px; color: var(--c-subtle);
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: var(--c-surface-2);
    transition: color 0.2s, background 0.2s, transform 0.25s var(--ease-out);
}
.dash-card:hover .dc-arrow { color: #ffffff; background: var(--c-brand); transform: translateX(3px); }
</style>
