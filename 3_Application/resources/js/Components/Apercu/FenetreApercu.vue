<script setup>
import Icon from '@/Components/Icon.vue';
import EchelleA4 from './EchelleA4.vue';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

// Aperçu d'un document à taille réelle (réduit sur petit écran), par-dessus la page.
// Échap ou un clic à côté le ferme. Le slot « actions » ajoute des boutons à l'en-tête (PDF…).
defineProps({
    titre: { type: String, required: true },
    sousTitre: { type: String, default: '' },
});
const emit = defineEmits(['close']);

const boutonFermer = ref(null);
const fermer = () => emit('close');
const surTouche = (e) => { if (e.key === 'Escape') fermer(); };

onMounted(() => {
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', surTouche);
    nextTick(() => boutonFermer.value?.focus());
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', surTouche);
});
</script>

<template>
    <Teleport to="body">
        <div class="fenetre" role="dialog" aria-modal="true" :aria-label="`Aperçu : ${titre}`" @click.self="fermer">
            <div class="fenetre__boite">
                <header class="fenetre__head">
                    <div class="fenetre__titre">
                        <strong>{{ titre }}</strong>
                        <small v-if="sousTitre">{{ sousTitre }}</small>
                    </div>
                    <div class="fenetre__actions">
                        <slot name="actions" />
                        <button ref="boutonFermer" type="button" class="btn-ghost" aria-label="Fermer l'aperçu" @click="fermer">
                            <Icon name="x" />
                        </button>
                    </div>
                </header>
                <div class="fenetre__corps">
                    <EchelleA4><slot /></EchelleA4>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.fenetre { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 16px; background: rgb(21 32 26 / 0.55); }
.fenetre__boite { display: flex; flex-direction: column; width: min(900px, 100%); height: min(92vh, 100%); background: var(--surface); border-radius: var(--r); box-shadow: var(--shadow-float); overflow: hidden; }
.fenetre__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--rule); }
.fenetre__titre { display: grid; gap: 2px; min-width: 0; }
.fenetre__titre strong { font-size: 15px; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fenetre__titre small { font-size: 12px; color: var(--ink-3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fenetre__actions { display: flex; gap: 8px; flex-shrink: 0; }
.fenetre__corps { flex: 1; min-height: 0; overflow-y: auto; padding: 24px; background: var(--sunk); }
@media (max-width: 560px) { .fenetre__corps { padding: 12px; } }
</style>
