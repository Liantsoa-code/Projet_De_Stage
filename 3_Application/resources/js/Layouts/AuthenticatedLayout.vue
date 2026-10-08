<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const notifications = computed(() => page.props.notifications || []);
const isValidateurOrAdmin = computed(
    () => user.value?.role === 'validateur' || user.value?.role === 'admin',
);
const notifOpen = ref(false);
const activeTooltip = ref(null);

const navItems = computed(() => {
    const items = [
        {
            key: 'dashboard',
            label: 'Tableau de bord',
            route: 'dashboard',
            match: 'dashboard',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>`,
        },
        {
            key: 'demandes',
            label: 'Mes Demandes',
            route: 'demandes.index',
            match: 'demandes.*',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M15 18H9"/><path d="M15 14H9"/><path d="M10 10H9"/><path d="M4 22h14a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2z"/></svg>`,
        },
    ];

    if (user.value?.role === 'validateur' || user.value?.role === 'admin') {
        items.push({
            key: 'validateur',
            label: user.value?.role === 'admin' ? 'Toutes les demandes' : 'Dossiers à Valider',
            route: 'validateur.demandes.index',
            match: 'validateur.demandes.*',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="m9 12 2 2 4-4"/></svg>`,
        });
    }

    if (user.value?.role === 'admin') {
        items.push(
            {
                key: 'sites',
                label: 'Sites Protégés',
                route: 'admin.sites.index',
                match: 'admin.sites.*',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>`,
            },
            {
                key: 'periodes',
                label: 'Périodes Sensibles',
                route: 'admin.periode-sensibles.index',
                match: 'admin.periode-sensibles.*',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>`,
            },
            {
                key: 'types',
                label: 'Types de Demandes',
                route: 'admin.type-demandes.index',
                match: 'admin.type-demandes.*',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>`,
            },
            {
                key: 'users',
                label: 'Utilisateurs',
                route: 'admin.users.index',
                match: 'admin.users.*',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
            }
        );
    }
    return items;
});

const isActive = (match) => route().current(match);

function formatNotifDate(value) {
    if (!value) return '';
    const date = new Date(value);
    const now = new Date();
    const diffMs = now - date;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'À l\'instant';
    if (diffMin < 60) return `Il y a ${diffMin} min`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24) return `Il y a ${diffH} h`;
    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    
    <div class="app-shell">
        <!-- Fond d'écran fixe -->
        <div class="app-bg"></div>
        <div class="app-overlay"></div>

        <!-- Sidebar icônes -->
        <aside class="sidebar">
            <!-- Logo -->
            <div class="sidebar__logo">
                <img src="/assets/logo-fanamby-vertical-transparent.png" alt="Logo Fanamby" class="sidebar-logo-img">
            </div>

            <!-- Navigation -->
            <nav class="sidebar__nav">
                <div
                    v-for="item in navItems"
                    :key="item.key"
                    class="nav-item-wrap"
                    @mouseenter="activeTooltip = item.key"
                    @mouseleave="activeTooltip = null"
                >
                    <Link
                        :href="route(item.route)"
                        class="nav-btn"
                        :class="{ 'nav-btn--active': isActive(item.match) }"
                    >
                        <span v-html="item.icon" class="nav-btn__icon"></span>
                        <span v-if="isActive(item.match)" class="nav-btn__dot"></span>
                    </Link>
                    <div class="nav-tooltip" v-if="activeTooltip === item.key">
                        {{ item.label }}
                    </div>
                </div>
            </nav>

            <!-- Footer sidebar -->
            <div class="sidebar__footer">
                <!-- Notifs -->
                <div class="nav-item-wrap" @mouseenter="activeTooltip = 'notif'" @mouseleave="activeTooltip = null">
                    <button class="nav-btn notif-trigger" @click.stop="notifOpen = !notifOpen">
                        <div class="nav-btn__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        </div>
                        <span v-if="notifications.length > 0" class="notif-count">{{ notifications.length }}</span>
                    </button>
                    <div class="nav-tooltip" v-if="activeTooltip === 'notif'">Notifications</div>
                </div>

                <!-- Avatar/Profile -->
                <div class="nav-item-wrap" @mouseenter="activeTooltip = 'profile'" @mouseleave="activeTooltip = null">
                    <Link :href="route('profile.edit')" class="nav-btn">
                        <div class="user-avatar">{{ user?.name?.charAt(0)?.toUpperCase() }}</div>
                    </Link>
                    <div class="nav-tooltip" v-if="activeTooltip === 'profile'">{{ user?.name }}</div>
                </div>

                <!-- Logout -->
                <div class="nav-item-wrap" @mouseenter="activeTooltip = 'logout'" @mouseleave="activeTooltip = null">
                    <Link :href="route('logout')" method="post" as="button" class="nav-btn nav-btn--logout">
                        <div class="nav-btn__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        </div>
                    </Link>
                    <div class="nav-tooltip" v-if="activeTooltip === 'logout'">Déconnexion</div>
                </div>
            </div>
        </aside>

        <!-- Panneau notifications flottant -->
        <div v-if="notifOpen" class="notif-panel" @click.stop>
            <div class="notif-panel__header">
                <span>Vos notifications</span>
                <button type="button" @click="notifOpen = false" class="notif-panel__close" aria-label="Fermer">✕</button>
            </div>
            <div v-if="notifications.length === 0" class="notif-empty">
                Vous êtes à jour — rien de nouveau pour le moment.
            </div>
            <div v-for="notif in notifications" :key="notif.id" class="notif-item">
                <span class="notif-item__dot" aria-hidden="true"></span>
                <div class="notif-item__body">
                    <p v-if="notif.demande?.site?.nom" class="notif-item__site">{{ notif.demande.site.nom }}</p>
                    <p class="notif-item__text">{{ notif.contenu }}</p>
                    <time class="notif-item__time" :datetime="notif.date_envoi">{{ formatNotifDate(notif.date_envoi) }}</time>
                </div>
                <Link
                    :href="route('notifications.read', notif.id)"
                    method="post"
                    as="button"
                    class="notif-item__read"
                    @click="notifOpen = false"
                >{{ isValidateurOrAdmin ? 'Voir le dossier' : 'Voir mes demandes' }}</Link>
            </div>
        </div>
        <div v-if="notifOpen" class="notif-backdrop" @click="notifOpen = false"></div>

        <!-- Zone principale -->
        <div class="main-area">
            <!-- Topbar en verre -->
            <header class="topbar" v-if="$slots.header">
                <div class="topbar__content">
                    <slot name="header" />
                </div>
            </header>

            <!-- Contenu de la page -->
            <main class="page-content">
                <!-- Messages flash (succès / erreur) communs à toutes les pages -->
                <div v-if="page.props.flash?.message" class="flash-ok" role="status">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ page.props.flash.message }}
                </div>
                <div v-if="page.props.flash?.error" class="flash-err" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                    {{ page.props.flash.error }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>


<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700;800&display=swap');

/* ── DESIGN TOKENS ── */
:root {
    --c-brand: #10b981;
    --c-brand-600: #059669;
    --c-brand-700: #047857;
    --c-brand-soft: #ecfdf5;
    --c-brand-ring: rgba(16, 185, 129, 0.18);

    --c-ink: #111827;
    --c-text: #374151;
    --c-muted: #6b7280;
    --c-subtle: #9ca3af;

    --c-bg: #f5f7f6;
    --c-surface: #ffffff;
    --c-surface-2: #f8faf9;
    --c-line: #e7eae9;
    --c-line-soft: #f1f3f2;

    --radius-xl: 20px;
    --radius-lg: 16px;
    --radius-md: 12px;
    --radius-sm: 10px;

    --shadow-xs: 0 1px 2px rgba(16, 24, 40, 0.04);
    --shadow-sm: 0 1px 2px rgba(16, 24, 40, 0.04), 0 2px 6px -1px rgba(16, 24, 40, 0.05);
    --shadow-md: 0 1px 3px rgba(16, 24, 40, 0.04), 0 12px 32px -12px rgba(16, 24, 40, 0.10);
    --shadow-lg: 0 2px 6px rgba(16, 24, 40, 0.04), 0 24px 56px -16px rgba(16, 24, 40, 0.20);

    --ease-out: cubic-bezier(0.22, 1, 0.36, 1);
}

/* ── GLOBAL RESET ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Inter', sans-serif;
    background: var(--c-bg);
    color: var(--c-text);
    -webkit-font-smoothing: antialiased;
}

h1, h2, h3, h4, h5, h6, .page-title, .ph-title {
    font-family: 'JetBrains Mono', monospace;
}

::selection { background: rgba(16, 185, 129, 0.2); color: var(--c-ink); }

a:focus-visible,
button:focus-visible,
label:focus-visible {
    outline: 2px solid var(--c-brand);
    outline-offset: 2px;
}

/* Scrollbars discrets */
* { scrollbar-width: thin; scrollbar-color: #d6dbd9 transparent; }
*::-webkit-scrollbar { width: 8px; height: 8px; }
*::-webkit-scrollbar-thumb { background: #d6dbd9; border-radius: 8px; border: 2px solid transparent; background-clip: content-box; }
*::-webkit-scrollbar-thumb:hover { background-color: #b8c0bd; }
*::-webkit-scrollbar-track { background: transparent; }

/* ── SHARED UTILITIES ── */
.glass {
    background: var(--c-surface);
    border: 1px solid var(--c-line);
    box-shadow: var(--shadow-sm);
    border-radius: var(--radius-xl);
}

.glass-light {
    background: var(--c-surface-2);
    border: 1px solid var(--c-line-soft);
    border-radius: var(--radius-md);
}

/* ── PAGE STRUCTURE ── */
.page-title {
    font-size: 24px;
    font-weight: 700;
    color: var(--c-ink);
    letter-spacing: -0.02em;
}

.page-sub {
    font-size: 13.5px;
    color: var(--c-muted);
    margin-top: 4px;
}

/* ── TABLE ── */
.glass-table-wrap {
    background: var(--c-surface);
    border: 1px solid var(--c-line);
    box-shadow: var(--shadow-md);
    border-radius: var(--radius-xl);
    overflow-x: auto;
}

.glass-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 14px;
}

.glass-table thead tr {
    background: var(--c-surface-2);
}

.glass-table th {
    padding: 14px 20px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-muted);
    border-bottom: 1px solid var(--c-line);
    white-space: nowrap;
}

.glass-table td {
    padding: 16px 20px;
    border-bottom: 1px solid var(--c-line-soft);
    color: #4b5563;
    vertical-align: middle;
    transition: background-color 0.15s ease;
}

.glass-table tbody tr:last-child td { border-bottom: none; }
.glass-table tbody tr:hover td { background: #f6fbf8; }
.glass-table tbody tr:hover td:first-child { box-shadow: inset 3px 0 0 var(--c-brand); }
.glass-table tbody tr:has(.table-empty):hover td { background: transparent; box-shadow: none; }

/* ── FORM ── */
.glass-form-wrap {
    background: var(--c-surface);
    border: 1px solid var(--c-line);
    box-shadow: var(--shadow-md);
    border-radius: var(--radius-xl);
    overflow: hidden;
}

.gf-body { padding: 28px; display: flex; flex-direction: column; gap: 20px; }

.gf-label { font-size: 12.5px; font-weight: 500; color: var(--c-text); display: block; margin-bottom: 6px; }

.gf-input,
.gf-select,
.gf-textarea {
    width: 100%;
    padding: 11px 14px;
    background: var(--c-surface);
    border: 1px solid #d9dedc;
    border-radius: var(--radius-sm);
    font-size: 14px;
    color: var(--c-ink);
    font-family: 'Inter', sans-serif;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
    box-shadow: var(--shadow-xs);
}

.gf-input:hover,
.gf-select:hover,
.gf-textarea:hover { border-color: #bfc7c4; }

.gf-input::placeholder,
.gf-textarea::placeholder { color: var(--c-subtle); }

.gf-input:focus,
.gf-select:focus,
.gf-textarea:focus {
    border-color: var(--c-brand);
    box-shadow: 0 0 0 4px var(--c-brand-ring);
}

.gf-textarea { resize: vertical; line-height: 1.6; }
.gf-error { font-size: 12px; color: #dc2626; margin-top: 5px; font-weight: 500; }
.gf-hint { font-size: 12px; color: var(--c-muted); margin-top: 4px; }

.gf-row { display: grid; gap: 16px; }
.gf-row-2 { grid-template-columns: 1fr 1fr; }
.gf-row-3 { grid-template-columns: 1fr 1fr 1fr; }
@media (max-width: 640px) {
    .gf-row-2, .gf-row-3 { grid-template-columns: 1fr; }
}

/* ── BUTTONS ── */
.btn-accent {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
    color: #ffffff;
    border: 1px solid #0b1220;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    transition: transform 0.2s var(--ease-out), box-shadow 0.2s, background 0.2s;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12), 0 1px 2px rgba(16, 24, 40, 0.12), 0 6px 16px -6px rgba(16, 24, 40, 0.35);
}
.btn-accent svg { width: 16px; height: 16px; }
.btn-accent:hover {
    background: linear-gradient(180deg, #273444 0%, #161f2e 100%);
    transform: translateY(-1px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14), 0 2px 4px rgba(16, 24, 40, 0.12), 0 12px 24px -8px rgba(16, 24, 40, 0.4);
}
.btn-accent:active { transform: translateY(0); }
.btn-accent:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--c-surface);
    color: var(--c-text);
    border: 1px solid #d9dedc;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    transition: background 0.2s, border-color 0.2s, color 0.2s, box-shadow 0.2s;
    box-shadow: var(--shadow-xs);
}
.btn-ghost:hover { background: var(--c-surface-2); border-color: #bfc7c4; color: var(--c-ink); }

.btn-sm-edit {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 12.5px; font-weight: 600; padding: 6px 14px;
    border-radius: 999px; text-decoration: none;
    cursor: pointer; font-family: 'Inter', sans-serif;
    background: var(--c-surface);
    color: var(--c-text);
    border: 1px solid #d9dedc;
    box-shadow: var(--shadow-xs);
    transition: background 0.2s, border-color 0.2s, color 0.2s;
}
.btn-sm-edit:hover { background: var(--c-brand-soft); border-color: #a7f3d0; color: var(--c-brand-700); }

.btn-sm-delete {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 12.5px; font-weight: 600; padding: 6px 14px;
    border-radius: 999px; text-decoration: none;
    cursor: pointer; font-family: 'Inter', sans-serif;
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    transition: background 0.2s, border-color 0.2s;
}
.btn-sm-delete:hover { background: #fee2e2; border-color: #fca5a5; }
.btn-sm-delete:disabled { opacity: 0.45; cursor: not-allowed; }

/* ── BADGES ── */
.badge-glass {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    white-space: nowrap;
}
.badge-glass::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
}

.badge-draft { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
.badge-info { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
.badge-warn { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.badge-ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.badge-err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.badge-todo { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }

/* ── FILTER BAR ── */
.filter-bar {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 12px; margin-bottom: 16px;
    background: var(--c-surface); border: 1px solid var(--c-line);
    border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
}
.filter-search {
    position: relative; flex: 1 1 240px; min-width: 200px;
}
.filter-search svg {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    width: 16px; height: 16px; color: var(--c-subtle); pointer-events: none;
}
.filter-search .gf-input { padding-left: 36px; }
.filter-bar .gf-select,
.filter-bar .gf-input { width: auto; padding-top: 9px; padding-bottom: 9px; }
.filter-bar .filter-search .gf-input { width: 100%; }
.filter-field { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--c-muted); }
.filter-reset {
    background: none; border: none; cursor: pointer;
    font-size: 12.5px; font-weight: 600; color: var(--c-muted);
    padding: 8px 10px; border-radius: 8px;
}
.filter-reset:hover { color: var(--c-ink); background: var(--c-surface-2); }

/* ── TABS (filtre par statut) ── */
.tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
.tab {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 7px 14px; border-radius: 999px;
    background: var(--c-surface); border: 1px solid var(--c-line);
    font-size: 13px; font-weight: 600; color: var(--c-muted);
    cursor: pointer; text-decoration: none;
    transition: background 0.2s, border-color 0.2s, color 0.2s;
}
.tab:hover { color: var(--c-ink); border-color: #d1d5db; }
.tab--active, .tab--active:hover { background: var(--c-ink); border-color: var(--c-ink); color: #fff; }
.tab__count {
    min-width: 20px; padding: 1px 6px; border-radius: 999px;
    background: var(--c-surface-2); color: var(--c-muted);
    font-size: 11px; font-weight: 700; text-align: center;
}
.tab--active .tab__count { background: rgba(255, 255, 255, 0.18); color: #fff; }

/* Ligne de tableau cliquable */
.row-link { cursor: pointer; }

/* ── PAGE HEADER ROW ── */
.ph-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; width: 100%; margin-bottom: 8px; flex-wrap: wrap; }
.ph-title { font-size: 18px; font-weight: 700; color: var(--c-ink); }

/* ── BACK LINK ── */
.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13.5px; font-weight: 500; color: var(--c-muted); text-decoration: none;
    padding: 6px 12px 6px 10px; border-radius: 999px;
    transition: color 0.2s, background 0.2s, border-color 0.2s;
    background: var(--c-surface); border: 1px solid var(--c-line); box-shadow: var(--shadow-xs);
}
.back-link svg { width: 16px; height: 16px; transition: transform 0.2s var(--ease-out); }
.back-link:hover { color: var(--c-ink); background: var(--c-surface-2); border-color: #d1d5db; }
.back-link:hover svg { transform: translateX(-2px); }

/* ── FLASH MSG ── */
.flash-ok {
    display: flex; align-items: center; gap: 10px;
    background: linear-gradient(90deg, #ecfdf5 0%, #f6fdf9 100%);
    border: 1px solid #a7f3d0;
    border-left: 4px solid var(--c-brand);
    border-radius: var(--radius-md); padding: 14px 18px;
    font-size: 14px; font-weight: 500; color: #065f46; margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
    animation: fade-up 0.4s var(--ease-out) both;
}
.flash-ok svg { width: 20px; height: 20px; flex-shrink: 0; color: var(--c-brand); }

.flash-err {
    display: flex; align-items: center; gap: 10px;
    background: linear-gradient(90deg, #fef2f2 0%, #fff7f7 100%);
    border: 1px solid #fecaca;
    border-left: 4px solid #ef4444;
    border-radius: var(--radius-md); padding: 14px 18px;
    font-size: 14px; font-weight: 500; color: #991b1b; margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
    animation: fade-up 0.4s var(--ease-out) both;
}
.flash-err svg { width: 20px; height: 20px; flex-shrink: 0; color: #ef4444; }

/* ── TABLE EMPTY ── */
.table-empty { text-align: center; padding: 56px 20px; color: var(--c-subtle); font-size: 14.5px; }

/* ── TEXT UTILS ── */
.fw-strong { font-weight: 600; color: var(--c-ink); }
.text-dim { color: var(--c-muted); }
.text-sm { font-size: 12.5px; }
.text-right { text-align: right; }
.truncate-cell { max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── ANIMATIONS ── */
.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fade-up {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes pop-in {
    from { opacity: 0; transform: translateY(6px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
}
</style>

<style scoped>
/* ─── APP SHELL ─── */
.app-shell {
    display: flex;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
}

/* ─── BACKGROUND ─── */
.app-bg {
    position: fixed;
    inset: 0;
    z-index: 0;
    background:
        radial-gradient(1200px 600px at 0% -10%, rgba(16, 185, 129, 0.08), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgba(59, 130, 246, 0.05), transparent 60%),
        var(--c-bg);
}

.app-overlay {
    display: none;
}

/* ─── SIDEBAR ─── */
.sidebar {
    width: 68px;
    height: calc(100vh - 32px);
    position: fixed;
    left: 16px; top: 16px;
    z-index: 100;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(16px) saturate(180%);
    -webkit-backdrop-filter: blur(16px) saturate(180%);
    border-radius: 34px;
    border: 1px solid rgba(231, 234, 233, 0.9);
    box-shadow: var(--shadow-md);
    padding: 24px 0;
    gap: 8px;
}

/* Logo */
.sidebar__logo {
    padding: 8px;
    margin-bottom: 16px;
    display: flex;
    justify-content: center;
    width: 100%;
    position: relative;
}
.sidebar__logo::after {
    content: '';
    position: absolute;
    bottom: -8px; left: 50%;
    transform: translateX(-50%);
    width: 28px; height: 1px;
    background: var(--c-line);
}

.sidebar-logo-img {
    width: 44px;
    height: auto;
    object-fit: contain;
}

/* Nav */
.sidebar__nav {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px 10px 0;
}

.sidebar__footer {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 12px 10px 4px;
    border-top: 1px solid var(--c-line-soft);
    margin: 0 12px;
}

.nav-item-wrap {
    position: relative;
    width: 100%;
    display: flex;
    justify-content: center;
}

.nav-btn {
    width: 44px; height: 44px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 14px;
    border: none;
    background: transparent;
    color: var(--c-muted);
    cursor: pointer;
    transition: background 0.2s, color 0.2s, transform 0.2s var(--ease-out), box-shadow 0.2s;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
    position: relative;
}

.nav-btn:hover {
    background: #f1f4f3;
    color: var(--c-ink);
    transform: translateY(-1px);
}

.nav-btn--active,
.nav-btn--active:hover {
    background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
    color: var(--c-brand-700);
    box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.25);
    transform: none;
}

.nav-btn__icon {
    width: 20px; height: 20px;
    display: flex; align-items: center;
}
.nav-btn__icon :deep(svg) { width: 20px; height: 20px; }
.nav-btn--active .nav-btn__icon :deep(svg) { stroke-width: 1.8; }

/* Indicateur actif : barre verticale sur le bord gauche */
.nav-btn__dot {
    position: absolute;
    left: -12px; top: 50%;
    transform: translateY(-50%);
    width: 4px; height: 20px;
    border-radius: 0 4px 4px 0;
    background: var(--c-brand);
    box-shadow: 0 0 12px rgba(16, 185, 129, 0.6);
}

.nav-btn--logout { color: #ef4444; }
.nav-btn--logout:hover { background: #fef2f2; color: #b91c1c; }

/* Tooltip */
.nav-tooltip {
    position: absolute;
    left: calc(100% + 16px);
    top: 50%;
    transform: translateY(-50%);
    background: rgba(17, 24, 39, 0.92);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #ffffff;
    font-size: 12.5px;
    font-weight: 600;
    padding: 8px 14px;
    border-radius: 10px;
    white-space: nowrap;
    pointer-events: none;
    z-index: 200;
    box-shadow: var(--shadow-lg);
    animation: tooltip-in 0.18s var(--ease-out) both;
}

.nav-tooltip::before {
    content: '';
    position: absolute;
    right: 100%;
    top: 50%;
    transform: translateY(-50%);
    border: 6px solid transparent;
    border-right-color: rgba(17, 24, 39, 0.92);
}

@keyframes tooltip-in {
    from { opacity: 0; transform: translate(-4px, -50%); }
    to { opacity: 1; transform: translate(0, -50%); }
}

/* User avatar */
.user-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #34d399 0%, #047857 100%);
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.35), 0 4px 10px -2px rgba(4, 120, 87, 0.45);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 800;
    color: #ffffff;
}

/* Notif badge */
.notif-count {
    position: absolute;
    top: 6px; right: 6px;
    min-width: 16px; height: 16px;
    background: linear-gradient(180deg, #f87171 0%, #dc2626 100%);
    color: #fff;
    font-size: 10px; font-weight: 800;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 4px;
    border: 2px solid #ffffff;
    box-sizing: content-box;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.4);
}

/* Notification panel */
.notif-backdrop {
    position: fixed; inset: 0; z-index: 150;
    background: rgba(17, 24, 39, 0.08);
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}

.notif-panel {
    position: fixed;
    left: 100px; bottom: 32px;
    width: 360px;
    max-height: calc(100vh - 64px);
    overflow-y: auto;
    background: var(--c-surface);
    border: 1px solid var(--c-line);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    z-index: 200;
    animation: pop-in 0.22s var(--ease-out) both;
    transform-origin: bottom left;
}

.notif-panel__header {
    position: sticky; top: 0; z-index: 1;
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--c-line-soft);
    font-size: 13px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--c-text);
}

.notif-panel__close {
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    background: none; border: none; cursor: pointer; border-radius: 8px;
    color: var(--c-subtle); font-size: 16px;
    transition: color 0.15s, background 0.15s;
}
.notif-panel__close:hover { color: var(--c-ink); background: #f3f4f6; }

.notif-empty { padding: 32px; text-align: center; font-size: 14px; color: var(--c-subtle); }

.notif-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 16px 20px; border-bottom: 1px solid var(--c-line-soft);
    transition: background 0.15s;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #f8fbf9; }

.notif-item__dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--c-brand); flex-shrink: 0; margin-top: 5px;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
}

.notif-item__body { flex: 1; min-width: 0; }

.notif-item__site {
    margin: 0 0 4px;
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.04em; color: var(--c-brand-600);
}

.notif-item__text { margin: 0; font-size: 13.5px; color: var(--c-text); line-height: 1.55; }

.notif-item__time {
    display: block; margin-top: 6px;
    font-size: 11px; color: var(--c-subtle);
}

.notif-item__read {
    font-size: 11px; font-weight: 700; color: var(--c-brand-700);
    background: var(--c-brand-soft); border: 1px solid #d1fae5; cursor: pointer;
    text-decoration: none; text-align: center;
    max-width: 96px; line-height: 1.35; flex-shrink: 0;
    padding: 5px 9px; border-radius: 8px;
    transition: background 0.15s, border-color 0.15s;
}
.notif-item__read:hover { background: #d1fae5; border-color: #a7f3d0; }

/* ─── MAIN AREA ─── */
.main-area {
    flex: 1;
    min-width: 0;
    margin-left: 100px; /* Space for the floating pill sidebar */
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    position: relative;
    z-index: 10;
}

/* ─── TOPBAR ─── */
.topbar {
    position: sticky; top: 0; z-index: 50;
    background: rgba(245, 247, 246, 0.72);
    backdrop-filter: blur(16px) saturate(180%);
    -webkit-backdrop-filter: blur(16px) saturate(180%);
    border-bottom: 1px solid rgba(231, 234, 233, 0.8);
    min-height: 72px;
    display: flex; align-items: center;
    padding: 12px 32px;
}

.topbar__content {
    width: 100%;
    display: flex; align-items: center;
    font-size: 16px; font-weight: 700; color: var(--c-ink);
}

/* ─── PAGE CONTENT ─── */
.page-content {
    flex: 1;
    padding: 32px;
    animation: fade-up 0.45s var(--ease-out) backwards;
}

/* ─── RESPONSIVE ─── */
@media (max-width: 640px) {
    .sidebar {
        top: auto; bottom: 12px; left: 12px; right: 12px;
        width: auto; height: 64px;
        flex-direction: row;
        padding: 0 12px;
        border-radius: 22px;
    }
    .sidebar__logo { display: none; }
    .sidebar__nav, .sidebar__footer {
        flex-direction: row; width: auto; padding: 0; gap: 4px;
        border-top: none; margin: 0;
    }
    .sidebar__nav { flex: 1; justify-content: flex-start; overflow-x: auto; }
    .nav-item-wrap { width: auto; }
    .nav-tooltip { display: none; }
    .nav-btn__dot { left: 50%; top: auto; bottom: -10px; transform: translateX(-50%); width: 18px; height: 3px; border-radius: 3px 3px 0 0; }
    .notif-panel { left: 12px; right: 12px; bottom: 88px; width: auto; }
    .main-area { margin-left: 0; padding-bottom: 88px; }
    .topbar { padding: 12px 16px; }
    .page-content { padding: 20px 16px; }
}
</style>
