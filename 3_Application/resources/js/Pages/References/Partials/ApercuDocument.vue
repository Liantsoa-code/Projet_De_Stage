<script setup>
import Icon from '@/Components/Icon.vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

// Aperçu d'un document déposé : PDF et images affichés dans la page, les autres formats se téléchargent.
// Le fichier est récupéré par une requête authentifiée puis affiché depuis la mémoire du navigateur,
// ce qui évite l'interception par les gestionnaires de téléchargement (comme openPdf.js).
const props = defineProps({ document: { type: Object, required: true } });
const emit = defineEmits(['close']);

const genre = computed(() => {
    if (props.document.type_mime === 'application/pdf') return 'pdf';
    if (props.document.type_mime.startsWith('image/')) return 'image';
    return 'autre';
});

const url = ref(null);
const erreur = ref('');
const boutonFermer = ref(null);
let ferme = false;

const fermer = () => emit('close');
const surTouche = (e) => { if (e.key === 'Escape') fermer(); };

onMounted(async () => {
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', surTouche);
    nextTick(() => boutonFermer.value?.focus());

    if (genre.value === 'autre') return;
    try {
        const response = await fetch(route('references.documents.show', props.document.id), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error();
        const blobUrl = URL.createObjectURL(await response.blob());
        if (ferme) URL.revokeObjectURL(blobUrl);
        else url.value = blobUrl;
    } catch {
        erreur.value = 'Impossible d\'afficher ce document. Essayez de le télécharger.';
    }
});

onBeforeUnmount(() => {
    ferme = true;
    document.body.style.overflow = '';
    window.removeEventListener('keydown', surTouche);
    if (url.value) URL.revokeObjectURL(url.value);
});
</script>

<template>
    <div class="apercu" role="dialog" aria-modal="true" :aria-label="`Aperçu : ${document.titre}`" @click.self="fermer">
        <div class="apercu__boite">
            <header class="apercu__head">
                <div class="apercu__titre">
                    <strong>{{ document.titre }}</strong>
                    <small>{{ document.nom_fichier }}</small>
                </div>
                <div class="apercu__actions">
                    <a :href="route('references.documents.show', { document: document.id, telecharger: 1 })" class="btn-ghost">
                        <Icon name="download-simple" />
                        <span class="apercu__libelle">Télécharger</span>
                    </a>
                    <button ref="boutonFermer" type="button" class="btn-ghost" aria-label="Fermer l'aperçu" @click="fermer">
                        <Icon name="x" />
                    </button>
                </div>
            </header>

            <div class="apercu__corps">
                <p v-if="erreur" class="apercu__message">{{ erreur }}</p>
                <iframe v-else-if="genre === 'pdf' && url" :src="url" :title="document.titre" class="apercu__pdf" />
                <img v-else-if="genre === 'image' && url" :src="url" :alt="document.titre" class="apercu__image">
                <div v-else-if="genre === 'autre'" class="apercu__message">
                    <Icon name="file-text" />
                    <p>L'aperçu n'est pas disponible pour les fichiers Word ou OpenDocument.<br>Téléchargez le fichier pour l'ouvrir.</p>
                </div>
                <p v-else class="apercu__message">Chargement du document…</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.apercu { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 16px; background: rgb(21 32 26 / 0.55); }
.apercu__boite { display: flex; flex-direction: column; width: min(1000px, 100%); height: min(90vh, 100%); background: var(--surface); border-radius: var(--r); box-shadow: var(--shadow-float); overflow: hidden; }
.apercu__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--rule); }
.apercu__titre { display: grid; gap: 2px; min-width: 0; }
.apercu__titre strong { font-size: 15px; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.apercu__titre small { font-size: 12px; color: var(--ink-3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.apercu__actions { display: flex; gap: 8px; flex-shrink: 0; }
.apercu__corps { flex: 1; min-height: 0; display: grid; place-items: center; background: var(--sunk); }
.apercu__pdf { width: 100%; height: 100%; border: 0; background: var(--surface); }
.apercu__image { max-width: 100%; max-height: 100%; object-fit: contain; }
.apercu__message { display: grid; justify-items: center; gap: 10px; padding: 24px; text-align: center; font-size: 14px; color: var(--ink-2); }
.apercu__message svg { width: 40px; height: 40px; color: var(--ink-3); }
@media (max-width: 560px) { .apercu__libelle { display: none; } }
</style>
