<script setup>
import Icon from '@/Components/Icon.vue';
import { FORMATS, ouvrirDocument } from '@/utils/ouvrirDocument.js';
import { nextTick, onBeforeUnmount, ref } from 'vue';

// Bouton d'un document produit par la plateforme. Par défaut, un clic ouvre le PDF dans le navigateur ;
// avec « word » (validateurs, modèles), il propose le format, PDF ou Word (téléchargé, modifiable). Le
// demandeur n'a jamais le Word, refusé aussi par le serveur. Le libellé est le slot, l'apparence la classe
// donnée par la page (btn-accent, btn-ghost…). Le menu est placé dans <body> pour ne pas être coupé par un
// tableau qui défile ; Échap, un clic à côté, un défilement ou le choix d'un format le ferment.
const props = defineProps({
    url: { type: String, required: true },
    word: { type: Boolean, default: false },
});

const bouton = ref(null);
const menu = ref(null);
const ouvert = ref(false);
const enCours = ref(false);
const position = ref({});

// Sous le bouton, ou au-dessus s'il n'y a pas la place ; toujours dans la fenêtre
const placer = () => {
    const b = bouton.value.getBoundingClientRect();
    const m = menu.value.getBoundingClientRect();
    const marge = 8;
    const left = Math.max(marge, Math.min(b.left, window.innerWidth - m.width - marge));
    const top = b.bottom + 6 + m.height <= window.innerHeight - marge ? b.bottom + 6 : Math.max(marge, b.top - 6 - m.height);
    position.value = { left: `${left}px`, top: `${top}px` };
};

const surClicAilleurs = (e) => {
    if (!menu.value?.contains(e.target) && !bouton.value?.contains(e.target)) fermer();
};

const ouvrir = async () => {
    position.value = { visibility: 'hidden' };
    ouvert.value = true;
    await nextTick();
    placer();
    menu.value.querySelector('[role="menuitem"]')?.focus({ preventScroll: true });
    document.addEventListener('pointerdown', surClicAilleurs);
    window.addEventListener('scroll', fermer, true);
    window.addEventListener('resize', fermer);
};

function fermer() {
    ouvert.value = false;
    document.removeEventListener('pointerdown', surClicAilleurs);
    window.removeEventListener('scroll', fermer, true);
    window.removeEventListener('resize', fermer);
}

onBeforeUnmount(fermer);

// Flèches pour passer d'un format à l'autre ; Échap rend la main au bouton sans fermer une fenêtre parente
const surTouche = (e) => {
    const items = [...menu.value.querySelectorAll('[role="menuitem"]')];
    const i = items.indexOf(document.activeElement);
    if (e.key === 'Escape') {
        e.stopPropagation();
        fermer();
        bouton.value.focus();
    } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        items[(i + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length].focus();
    } else if (e.key === 'Tab') {
        fermer();
    }
};

const cliquer = () => {
    if (!props.word) choisir('pdf');
    else if (ouvert.value) fermer();
    else ouvrir();
};

const choisir = async (format) => {
    fermer();
    bouton.value.focus();
    enCours.value = true;
    try {
        await ouvrirDocument(props.url, format);
    } catch (e) {
        alert(e.message || 'Erreur lors de l\'ouverture du document.');
    } finally {
        enCours.value = false;
    }
};
</script>

<template>
    <button ref="bouton" type="button" :aria-haspopup="word ? 'menu' : null" :aria-expanded="word ? ouvert : null" :disabled="enCours" @click="cliquer">
        <Icon :name="enCours ? 'circle-notch' : word ? 'file-text' : 'file-pdf'" :class="{ spin: enCours }" />
        <slot />
        <Icon v-if="word" name="caret-down" class="caret" />

        <Teleport to="body">
            <div v-if="ouvert" ref="menu" class="formats" role="menu" :style="position" @keydown="surTouche">
                <button v-for="f in FORMATS" :key="f.cle" type="button" role="menuitem" class="formats__item" @click="choisir(f.cle)">
                    <Icon :name="f.icone" />
                    <span><strong>{{ f.libelle }}</strong><small>{{ f.detail }}</small></span>
                </button>
            </div>
        </Teleport>
    </button>
</template>

<style scoped>
.caret { width: 12px; height: 12px; margin-left: -2px; opacity: 0.7; }

.formats { position: fixed; z-index: 90; display: grid; gap: 2px; min-width: 230px; padding: 6px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r-sm); box-shadow: var(--shadow-float); }
.formats__item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 0; border-radius: 6px; background: none; font: inherit; text-align: left; color: var(--ink); cursor: pointer; }
.formats__item:hover, .formats__item:focus-visible { background: var(--sunk); }
.formats__item:focus-visible { outline-offset: -2px; }
.formats__item svg { width: 22px; height: 22px; flex-shrink: 0; color: var(--forest); }
.formats__item span { display: grid; gap: 1px; }
.formats__item strong { font-size: 13.5px; font-weight: 600; }
.formats__item small { font-size: 12px; color: var(--ink-3); }
</style>
