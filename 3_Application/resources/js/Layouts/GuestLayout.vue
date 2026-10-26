<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    // 'connexion' ou 'inscription' affiche le sélecteur entre les deux pages
    onglet: { type: String, default: null },
});
</script>

<template>
    <div class="guest">
        <div class="guest__side">
            <header class="guest__top">
                <Link href="/" class="guest__brand">
                    <img src="/assets/logo-fanamby-192.png" alt="" class="guest__logo" width="44" height="44">
                    <span>
                        <strong>FANAMBY</strong>
                        <small>Accès aux aires protégées</small>
                    </span>
                </Link>
                <Link href="/" class="guest__home">Retour à l'accueil</Link>
            </header>

            <main class="guest__main">
                <nav v-if="onglet" class="guest__tabs" aria-label="Accès au compte">
                    <Link :href="route('login')" class="guest__tab" :aria-current="onglet === 'connexion' ? 'page' : undefined">Se connecter</Link>
                    <Link :href="route('register')" class="guest__tab" :aria-current="onglet === 'inscription' ? 'page' : undefined">Créer un compte</Link>
                </nav>
                <slot />
            </main>

            <footer class="guest__foot">Association FANAMBY · Antananarivo, Madagascar</footer>
        </div>

        <figure class="guest__visual">
            <img
                src="/assets/bg-login-1200.jpg"
                srcset="/assets/bg-login-1200.jpg 1200w, /assets/bg-login-2000.jpg 2000w"
                sizes="(max-width: 900px) 100vw, 55vw"
                alt="Un indri, grand lémurien noir, agrippé à une branche en forêt humide"
                class="guest__photo"
            >
            <figcaption class="guest__caption">
                <slot name="caption">
                    <span class="eyebrow">Demandes d'accès</span>
                    <p>Recherche scientifique et tournage dans les aires protégées gérées par l'Association, avec vérification du calendrier de chaque site.</p>
                </slot>
                <span class="guest__credit">Indri (Indri indri)</span>
            </figcaption>
        </figure>
    </div>
</template>

<style scoped>
.guest {
    display: grid;
    grid-template-columns: minmax(440px, 0.9fr) minmax(0, 1.1fr);
    min-height: 100vh;
    background: var(--paper);
}

.guest__side { display: flex; flex-direction: column; padding: 28px clamp(20px, 5vw, 64px); }
.guest__top { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.guest__brand { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
.guest__logo { width: 44px; height: 44px; object-fit: contain; }
.guest__brand span { display: grid; gap: 3px; }
.guest__brand strong { font: 800 17px/1 var(--f-display); letter-spacing: 0.02em; color: var(--forest); }
.guest__brand small { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.guest__home { font-size: 13px; font-weight: 600; color: var(--ink-2); text-decoration: none; }
.guest__home:hover { color: var(--ink); text-decoration: underline; }

.guest__main { width: 100%; max-width: 400px; margin: auto; padding: 48px 0; }
.guest__tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
    margin-bottom: 32px;
    padding: 4px;
    background: var(--sunk);
    border-radius: var(--r);
}
.guest__tab {
    padding: 9px 12px;
    border-radius: var(--r-sm);
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    color: var(--ink-2);
    text-decoration: none;
    transition: background-color 0.15s, color 0.15s;
}
.guest__tab:hover { color: var(--ink); }
.guest__tab[aria-current='page'] { background: var(--surface); color: var(--ink); box-shadow: 0 0 0 1px var(--rule); }

.guest__foot { font-size: 12.5px; color: var(--ink-3); }

.guest__visual { position: relative; margin: 0; overflow: hidden; background: var(--ink); }
.guest__photo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 46% 38%; }
.guest__caption {
    position: absolute;
    left: 28px;
    right: 28px;
    bottom: 28px;
    display: grid;
    gap: 8px;
    max-width: 420px;
    padding: 18px 20px;
    background: var(--surface);
    border-radius: var(--r);
    font-size: 14px;
    color: var(--ink-2);
}
.guest__caption :deep(p) { margin: 0; line-height: 1.55; }
.guest__credit { font-size: 12px; font-style: italic; color: var(--ink-3); }

@media (max-width: 900px) {
    .guest { grid-template-columns: minmax(0, 1fr); }
    .guest__visual { order: -1; height: 180px; }
    .guest__caption { display: none; }
    .guest__side { padding-top: 20px; }
    .guest__main { padding: 28px 0 36px; }
}
</style>
