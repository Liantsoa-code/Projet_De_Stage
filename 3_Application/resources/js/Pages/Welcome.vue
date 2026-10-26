<script setup>
import Icon from '@/Components/Icon.vue';
import DashboardMap from '@/Components/DashboardMap.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
    sites: { type: Array, default: () => [] },
});

const user = computed(() => usePage().props.auth?.user);
const carte = ref(null);
</script>

<template>
    <Head title="Accès aux aires protégées" />

    <div class="welcome">
        <header class="top">
            <Link href="/" class="brand">
                <img src="/assets/logo-fanamby-192.png" alt="" class="brand__logo">
                <span>
                    <strong>FANAMBY</strong>
                    <small>Accès aux aires protégées</small>
                </span>
            </Link>
            <nav v-if="canLogin" class="top__nav" aria-label="Compte">
                <Link v-if="user" :href="route('dashboard')" class="btn-accent">Tableau de bord</Link>
                <template v-else>
                    <Link :href="route('login')" class="btn-ghost">Se connecter</Link>
                    <Link v-if="canRegister" :href="route('register')" class="btn-accent">Créer un compte</Link>
                </template>
            </nav>
        </header>

        <main class="hero">
            <div class="hero__text">
                <span class="eyebrow">Association FANAMBY · Madagascar</span>
                <h1>Demandes d'accès aux aires protégées</h1>
                <p class="hero__lead">
                    Chercheurs, équipes de tournage et partenaires déposent ici leurs demandes d'accès.
                    Le calendrier de chaque aire protégée est vérifié pendant la saisie, et l'autorisation officielle est délivrée en PDF une fois le dossier validé.
                </p>
                <div class="hero__cta">
                    <Link v-if="user" :href="route('demandes.create')" class="btn-accent"><Icon name="plus" />Nouvelle demande</Link>
                    <template v-else>
                        <Link v-if="canRegister" :href="route('register')" class="btn-accent"><Icon name="arrow-right" />Déposer une demande</Link>
                        <Link :href="route('login')" class="btn-ghost">J'ai déjà un compte</Link>
                    </template>
                </div>

                <ol class="steps">
                    <li>
                        <span class="steps__n">1</span>
                        <div>
                            <strong>Déposer la demande</strong>
                            <p>Coordonnées, projet, aire protégée, période et pièces exigées pour le type de projet. Un brouillon peut être repris à tout moment.</p>
                        </div>
                    </li>
                    <li>
                        <span class="steps__n">2</span>
                        <div>
                            <strong>Instruction par le validateur de l'aire protégée</strong>
                            <p>Les chevauchements avec les périodes sensibles sont signalés. Le validateur peut demander un complément.</p>
                        </div>
                    </li>
                    <li>
                        <span class="steps__n">3</span>
                        <div>
                            <strong>Autorisation et cahier des charges</strong>
                            <p>Les deux documents officiels sont générés à la validation et restent disponibles dans votre espace.</p>
                        </div>
                    </li>
                </ol>
            </div>

            <aside class="hero__map" aria-label="Aires protégées ouvertes aux demandes">
                <header class="hero__map-head">
                    <span class="eyebrow">{{ sites.length }} {{ sites.length > 1 ? 'aires protégées ouvertes' : 'aire protégée ouverte' }} aux demandes</span>
                    <span class="hero__map-hint">Choisissez une aire protégée pour la situer</span>
                </header>
                <div class="hero__map-box">
                    <DashboardMap ref="carte" :sites="sites" />
                </div>
                <ul class="hero__sites">
                    <li v-for="s in sites" :key="s.id">
                        <button type="button" class="hero__site" @click="carte?.focusSite(s.id)">
                            <span class="hero__dot"></span>
                            <span class="site-label">{{ s.nom }}</span>
                        </button>
                    </li>
                </ul>
            </aside>
        </main>

        <footer class="foot">Association FANAMBY · Antananarivo, Madagascar</footer>
    </div>
</template>

<style scoped>
.welcome { min-height: 100vh; display: flex; flex-direction: column; padding: 0 clamp(16px, 5vw, 64px); background: var(--paper); }
.top { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 0; flex-wrap: wrap; }
.brand { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
.brand__logo { width: 44px; height: 44px; object-fit: contain; }
.brand span { display: grid; gap: 3px; }
.brand strong { font: 800 18px/1 var(--f-display); letter-spacing: 0.02em; color: var(--forest); }
.brand small { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.top__nav { display: flex; gap: 8px; flex-wrap: wrap; }

.hero { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); gap: clamp(28px, 4vw, 56px); align-items: start; padding: clamp(28px, 5vw, 56px) 0 48px; max-width: 1560px; width: 100%; margin: 0 auto; }
.hero__text { display: grid; gap: 20px; min-width: 0; }
.hero h1 { font-size: clamp(30px, 3.4vw, 50px); font-weight: 700; line-height: 0.95; letter-spacing: -0.005em; max-width: 20ch; }
.hero__lead { font-size: 17px; line-height: 1.6; color: var(--ink-2); max-width: 56ch; }
.hero__cta { display: flex; gap: 10px; flex-wrap: wrap; }

.steps { list-style: none; margin: 16px 0 0; padding: 0; display: grid; }
.steps li { display: grid; grid-template-columns: 32px minmax(0, 1fr); gap: 14px; padding: 16px 0; border-bottom: 1px solid var(--rule); }
.steps li:last-child { border-bottom: 0; }
.steps__n { display: grid; place-items: center; width: 28px; height: 28px; border: 1.5px solid var(--forest); border-radius: 50%; color: var(--forest); font: 600 13px var(--f-mono); }
.steps strong { display: block; font-weight: 600; font-size: 15px; margin-bottom: 2px; }
.steps p { font-size: 14px; color: var(--ink-2); max-width: 60ch; }

.hero__map { display: grid; min-width: 0; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); overflow: hidden; }
.hero__map-head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px 16px; flex-wrap: wrap; padding: 16px 24px 10px; }
.hero__map-hint { font-size: 12.5px; color: var(--ink-3); }
.hero__map-box { height: clamp(380px, 58vh, 600px); }
.hero__map-box :deep(.map-wrapper), .hero__map-box :deep(.map-inner) { min-height: 0; height: 100%; border: 0; border-radius: 0; }
.hero__sites { list-style: none; margin: 0; padding: 8px; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 220px), 1fr)); gap: 4px; }
.hero__site { display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 10px; border: 0; border-radius: var(--r-sm); background: none; text-align: left; cursor: pointer; }
.hero__site:hover { background: var(--forest-soft); }
.hero__dot { width: 9px; height: 9px; border-radius: 50%; background: var(--forest); border: 2px solid var(--surface); box-shadow: 0 0 0 1px var(--forest); flex-shrink: 0; }

.foot { padding: 18px 0 24px; font-size: 12.5px; color: var(--ink-3); }

@media (max-width: 960px) {
    .hero { grid-template-columns: minmax(0, 1fr); }
}
</style>
