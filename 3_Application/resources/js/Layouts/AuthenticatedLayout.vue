<script setup>
import Icon from '@/Components/Icon.vue';
import PageSkeleton from '@/Components/PageSkeleton.vue';
import { useNavigation, skeletonVariant } from '@/composables/useNavigation.js';
import { ref, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const notifications = computed(() => page.props.notifications || []);
const isValidateurOrAdmin = computed(
    () => user.value?.role === 'validateur' || user.value?.role === 'admin',
);
const { loading: navLoading, samePage, target } = useNavigation();
const changementDePage = computed(() => navLoading.value && !samePage.value);
const notifOpen = ref(false);
const navOpen = ref(false);

watch(() => page.url, () => {
    navOpen.value = false;
    notifOpen.value = false;
});

const ROLE_LABELS = { admin: 'Admin', validateur: 'Validateur', demandeur: 'Demandeur' };

const navGroups = computed(() => {
    const groups = [
        {
            label: 'Mon espace',
            items: [
                { label: 'Tableau de bord', route: 'dashboard', match: ['dashboard'], icon: 'squares-four' },
                { label: 'Mes demandes', route: 'demandes.index', match: ['demandes.index', 'demandes.show', 'demandes.edit'], icon: 'file-text' },
                { label: 'Nouvelle demande', route: 'demandes.create', match: ['demandes.create'], icon: 'plus' },
            ],
        },
    ];

    if (isValidateurOrAdmin.value) {
        groups.push({
            label: 'Instruction',
            items: [
                {
                    label: user.value?.role === 'admin' ? 'Toutes les demandes' : 'Dossiers à valider',
                    route: 'validateur.demandes.index',
                    match: ['validateur.demandes.*'],
                    icon: 'stamp',
                },
            ],
        });
    }

    if (user.value?.role === 'admin') {
        groups.push({
            label: 'Administration',
            items: [
                { label: 'Sites protégés', route: 'admin.sites.index', match: ['admin.sites.*'], icon: 'map-trifold' },
                { label: 'Périodes sensibles', route: 'admin.periode-sensibles.index', match: ['admin.periode-sensibles.*'], icon: 'calendar-x' },
                { label: 'Types de demandes', route: 'admin.type-demandes.index', match: ['admin.type-demandes.*'], icon: 'stack' },
                { label: 'Utilisateurs', route: 'admin.users.index', match: ['admin.users.*'], icon: 'users' },
            ],
        });
    }
    return groups;
});

const isActive = (match) => match.some((m) => route().current(m));

const initials = computed(() =>
    (user.value?.name || '')
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

function formatNotifDate(value) {
    if (!value) return '';
    const date = new Date(value);
    const diffMin = Math.floor((new Date() - date) / 60000);
    if (diffMin < 1) return 'À l\'instant';
    if (diffMin < 60) return `Il y a ${diffMin} min`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24) return `Il y a ${diffH} h`;
    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="shell" :class="{ 'shell--nav-open': navOpen }">
        <aside class="side" aria-label="Navigation principale">
            <Link :href="route('dashboard')" class="brand">
                <img src="/assets/logo-fanamby-192.png" alt="" class="brand__logo">
                <span class="brand__text">
                    <strong>FANAMBY</strong>
                    <small>Accès aux aires protégées</small>
                </span>
            </Link>

            <nav v-for="group in navGroups" :key="group.label" class="nav-group" :aria-label="group.label">
                <span class="nav-group__label">{{ group.label }}</span>
                <Link
                    v-for="item in group.items"
                    :key="item.route"
                    :href="route(item.route)"
                    class="nav-item"
                    :aria-current="isActive(item.match) ? 'page' : undefined"
                >
                    <Icon :name="item.icon" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="side-foot">
                <Link :href="route('profile.edit')" class="who" title="Mon profil">
                    <span class="avatar">{{ initials }}</span>
                    <span class="who__text">
                        <span class="who__name">{{ user?.name }}</span>
                        <span class="who__role">{{ ROLE_LABELS[user?.role] ?? user?.role }}</span>
                    </span>
                </Link>
                <Link :href="route('logout')" method="post" as="button" class="icon-btn" aria-label="Se déconnecter" title="Se déconnecter">
                    <Icon name="sign-out" />
                </Link>
            </div>
        </aside>

        <div class="scrim" @click="navOpen = false"></div>

        <div class="main-area">
            <header class="topbar">
                <button type="button" class="icon-btn menu-btn" aria-label="Ouvrir le menu" @click="navOpen = true">
                    <Icon name="list" />
                </button>
                <div class="topbar__content">
                    <div v-if="changementDePage" class="topbar__skeleton" aria-hidden="true">
                        <span class="sk" style="width: min(280px, 60%); height: 22px"></span>
                        <span class="sk" style="width: min(420px, 80%); height: 12px"></span>
                    </div>
                    <slot v-else name="header" />
                </div>
                <div class="notif-wrap">
                    <button
                        type="button"
                        class="icon-btn"
                        :aria-expanded="notifOpen"
                        aria-label="Notifications"
                        @click.stop="notifOpen = !notifOpen"
                    >
                        <Icon name="bell" />
                        <span v-if="notifications.length > 0" class="notif-count">{{ notifications.length }}</span>
                    </button>

                    <div v-if="notifOpen" class="notif-panel" role="dialog" aria-label="Notifications" @click.stop>
                        <div class="notif-panel__header">
                            <strong>Notifications</strong>
                            <div class="notif-panel__actions">
                                <Link
                                    v-if="notifications.length > 0"
                                    :href="route('notifications.read-all')"
                                    method="post"
                                    as="button"
                                    preserve-scroll
                                    class="notif-read-all"
                                >Tout marquer comme lu</Link>
                                <button type="button" class="icon-btn icon-btn--sm" aria-label="Fermer" @click="notifOpen = false">
                                    <Icon name="x" />
                                </button>
                            </div>
                        </div>
                        <p v-if="notifications.length === 0" class="notif-empty">
                            Rien de nouveau pour le moment.
                        </p>
                        <div v-for="notif in notifications" :key="notif.id" class="notif-item">
                            <div class="notif-item__body">
                                <p v-if="notif.demande?.site?.nom" class="site-label">{{ notif.demande.site.nom }}</p>
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
                </div>
            </header>
            <div v-if="notifOpen" class="notif-backdrop" @click="notifOpen = false"></div>

            <main class="page-content">
                <PageSkeleton v-if="changementDePage" :variant="skeletonVariant(target)" />
                <div v-show="!changementDePage">
                <div v-if="page.props.flash?.message" class="flash-ok" role="status">
                    <Icon name="check-circle" />
                    {{ page.props.flash.message }}
                </div>
                <div v-if="page.props.flash?.error" class="flash-err" role="alert">
                    <Icon name="warning-circle" />
                    {{ page.props.flash.error }}
                </div>
                <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.shell {
    display: grid;
    grid-template-columns: 252px minmax(0, 1fr);
    min-height: 100vh;
    background: linear-gradient(to right, var(--surface) 251px, var(--rule) 251px 252px, var(--paper) 252px);
}

.side {
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 18px 12px 14px;
    background: var(--surface);
    border-right: 1px solid var(--rule);
}
.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 8px 4px;
    text-decoration: none;
}
.brand__logo { width: 40px; height: 40px; object-fit: contain; }
.brand__text { display: grid; gap: 3px; }
.brand__text strong {
    font: 800 17px/1 var(--f-display);

    letter-spacing: 0.02em;
    color: var(--forest);
}
.brand__text small {
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ink-3);
}
.nav-group { display: grid; gap: 2px; }
.nav-group__label {
    padding: 0 10px 6px;
    font: 600 10.5px/1 var(--f-display);

    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ink-3);
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 9px 10px;
    border-radius: var(--r-sm);
    color: var(--ink-2);
    font-size: 14px;
    text-decoration: none;
    transition: background-color 0.15s, color 0.15s;
}
.nav-item :deep(svg) { width: 19px; height: 19px; color: var(--ink-3); flex-shrink: 0; }
.nav-item:hover { background: var(--sunk); color: var(--ink); }
.nav-item[aria-current='page'] { background: var(--forest-soft); color: var(--forest); font-weight: 600; }
.nav-item[aria-current='page'] :deep(svg) { color: var(--forest); }

.side-foot {
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 4px 0;
}
.who {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px;
    border-radius: var(--r-sm);
    text-decoration: none;
    color: inherit;
}
.who:hover { background: var(--sunk); }
.who__text { display: grid; min-width: 0; }
.who__name { font-weight: 600; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.who__role { font-size: 12px; color: var(--ink-3); }
.avatar {
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--forest-soft);
    color: var(--forest);
    font: 700 12.5px var(--f-body);
}

.icon-btn {
    position: relative;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border: 1px solid var(--rule);
    border-radius: var(--r-sm);
    background: var(--surface);
    color: var(--ink-2);
    cursor: pointer;
}
.icon-btn:hover { border-color: var(--ink-3); color: var(--ink); }
.icon-btn :deep(svg) { width: 18px; height: 18px; }
.icon-btn--sm { width: 30px; height: 30px; }
.icon-btn--sm :deep(svg) { width: 15px; height: 15px; }

.main-area { min-width: 0; display: flex; flex-direction: column; }
.topbar {
    position: sticky;
    top: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 56px;
    /* Aligné sur la largeur maximale du contenu */
    padding: 10px max(32px, calc((100% - var(--contenu-max)) / 2));
    background: var(--paper);
    border-bottom: 1px solid var(--rule);
}
.topbar__content { flex: 1; min-width: 0; display: flex; align-items: center; }
.topbar__skeleton { display: grid; gap: 8px; width: 100%; }
.menu-btn { display: none; }

.notif-wrap { position: relative; }
.notif-count {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 18px;
    padding: 2px 5px;
    border-radius: 9px;
    background: var(--signal);
    color: #fff;
    font: 700 10px/14px var(--f-body);
    text-align: center;
}
.notif-backdrop { position: fixed; inset: 0; z-index: 40; }
.notif-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    z-index: 60;
    width: min(380px, calc(100vw - 32px));
    max-height: min(520px, calc(100vh - 96px));
    overflow-y: auto;
    background: var(--surface);
    border: 1px solid var(--rule);
    border-radius: var(--r);
    box-shadow: var(--shadow-float);
    animation: fade-up 0.18s var(--ease-out) both;
}
.notif-panel__header {
    position: sticky;
    top: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px 10px 16px;
    background: var(--surface);
    border-bottom: 1px solid var(--rule);
    font-size: 14px;
}
.notif-panel__actions { display: flex; align-items: center; gap: 4px; }
.notif-read-all {
    padding: 4px 8px;
    border-radius: var(--r-sm);
    color: var(--forest);
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
}
.notif-read-all:hover { background: var(--rule-soft); }
.notif-empty { padding: 28px 16px; text-align: center; font-size: 14px; color: var(--ink-3); }
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--rule-soft);
}
.notif-item:last-child { border-bottom: none; }
.notif-item__body { flex: 1; min-width: 0; display: grid; gap: 3px; }
.notif-item__text { font-size: 13.5px; color: var(--ink); line-height: 1.5; }
.notif-item__time { font-size: 12px; color: var(--ink-3); }
.notif-item__read {
    flex-shrink: 0;
    max-width: 104px;
    padding: 6px 9px;
    border: 1px solid var(--rule);
    border-radius: var(--r-sm);
    background: var(--surface);
    color: var(--forest);
    font-size: 12px;
    font-weight: 600;
    line-height: 1.3;
    text-align: center;
    cursor: pointer;
}
.notif-item__read:hover { border-color: var(--forest); }

.page-content {
    flex: 1;
    width: 100%;
    padding: 28px max(32px, calc((100% - var(--contenu-max)) / 2)) 48px;
    animation: fade-up 0.3s var(--ease-out) backwards;
}

.scrim { display: none; }

@media (max-width: 920px) {
    .shell { grid-template-columns: minmax(0, 1fr); background: var(--paper); }
    .side {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 80;
        width: 280px;
        height: 100%;
        transform: translateX(-102%);
        transition: transform 0.2s var(--ease-out);
        box-shadow: var(--shadow-float);
    }
    .shell--nav-open .side { transform: none; }
    .shell--nav-open .scrim { display: block; position: fixed; inset: 0; z-index: 70; background: rgb(21 32 26 / 0.35); }
    .menu-btn { display: grid; }
    .topbar { padding: 10px 16px; }
    .page-content { padding: 20px 16px 32px; }
}
</style>
