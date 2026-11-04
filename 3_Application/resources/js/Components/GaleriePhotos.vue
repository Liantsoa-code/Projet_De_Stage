<script setup>
import Icon from '@/Components/Icon.vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

// Galerie « Voir les photos » d'une aire protégée, par-dessus la page. Rien n'est chargé avant l'ouverture :
// la liste arrive du serveur, puis les vignettes (~30 Ko), et chaque grande image seulement quand on l'ouvre.
// Le lien vers la banque d'images complète (pleine qualité, hébergée ailleurs) est proposé s'il existe.
const props = defineProps({
    siteId: { type: Number, required: true },
    nom: { type: String, required: true },
});
const emit = defineEmits(['close']);

const chargement = ref(true);
const erreur = ref(false);
const photos = ref([]);
const lien = ref(null);
// Photo affichée en grand ; null = la grille des vignettes
const index = ref(null);
const photo = computed(() => (index.value === null ? null : photos.value[index.value]));

const boutonFermer = ref(null);
const fermer = () => emit('close');
const precedente = () => { index.value = (index.value - 1 + photos.value.length) % photos.value.length; };
const suivante = () => { index.value = (index.value + 1) % photos.value.length; };

// Échap revient d'abord à la grille, puis ferme ; les flèches parcourent les photos
const surTouche = (e) => {
    if (e.key === 'Escape') {
        if (index.value !== null) index.value = null;
        else fermer();
    } else if (index.value !== null && e.key === 'ArrowLeft') precedente();
    else if (index.value !== null && e.key === 'ArrowRight') suivante();
};

onMounted(async () => {
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', surTouche);
    nextTick(() => boutonFermer.value?.focus());
    try {
        const reponse = await fetch(route('sites.photos', props.siteId), { headers: { Accept: 'application/json' } });
        if (!reponse.ok) throw new Error();
        const donnees = await reponse.json();
        photos.value = donnees.photos;
        lien.value = donnees.lien_photos;
    } catch {
        erreur.value = true;
    } finally {
        chargement.value = false;
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', surTouche);
});
</script>

<template>
    <Teleport to="body">
        <div class="galerie" role="dialog" aria-modal="true" :aria-label="`Photos : ${nom}`" @click.self="fermer">
            <div class="galerie__boite">
                <header class="galerie__head">
                    <div class="galerie__titre">
                        <button v-if="photo" type="button" class="btn-ghost btn--icone" aria-label="Revenir à toutes les photos" @click="index = null">
                            <Icon name="arrow-left" />
                        </button>
                        <div>
                            <strong>{{ nom }}</strong>
                            <small v-if="photos.length">{{ photo ? `${index + 1} / ${photos.length}` : `${photos.length} photo${photos.length > 1 ? 's' : ''}` }}</small>
                        </div>
                    </div>
                    <div class="galerie__actions">
                        <a v-if="lien" :href="lien" target="_blank" rel="noopener noreferrer" class="btn-ghost">
                            <Icon name="image" />Banque d'images complète
                        </a>
                        <button ref="boutonFermer" type="button" class="btn-ghost btn--icone" aria-label="Fermer la galerie" @click="fermer">
                            <Icon name="x" />
                        </button>
                    </div>
                </header>

                <div class="galerie__corps">
                    <p v-if="chargement" class="galerie__info"><Icon name="circle-notch" class="spin" />Chargement des photos…</p>
                    <p v-else-if="erreur" class="galerie__info">Les photos n'ont pas pu être chargées. Réessayez dans un instant.</p>
                    <div v-else-if="!photos.length" class="galerie__info">
                        <p>Pas encore de photos de cette aire protégée sur la plateforme.</p>
                        <a v-if="lien" :href="lien" target="_blank" rel="noopener noreferrer" class="btn-accent">Ouvrir la banque d'images</a>
                    </div>

                    <!-- Une photo en grand, avec sa légende et son crédit -->
                    <figure v-else-if="photo" class="vue">
                        <div class="vue__cadre">
                            <button v-if="photos.length > 1" type="button" class="vue__nav vue__nav--prec" aria-label="Photo précédente" @click="precedente"><Icon name="arrow-left" /></button>
                            <img :key="photo.id" :src="photo.url" :width="photo.largeur" :height="photo.hauteur" :alt="photo.legende || `Photo de ${nom}`" />
                            <button v-if="photos.length > 1" type="button" class="vue__nav vue__nav--suiv" aria-label="Photo suivante" @click="suivante"><Icon name="arrow-right" /></button>
                        </div>
                        <figcaption v-if="photo.legende || photo.credit">
                            <span v-if="photo.legende">{{ photo.legende }}</span>
                            <small v-if="photo.credit">Photo : {{ photo.credit }}</small>
                        </figcaption>
                    </figure>

                    <!-- Grille des vignettes -->
                    <ul v-else class="grille">
                        <li v-for="(p, i) in photos" :key="p.id">
                            <button type="button" class="grille__case" :aria-label="`Agrandir : ${p.legende || `photo ${i + 1}`}`" @click="index = i">
                                <img :src="p.url_vignette" loading="lazy" alt="" />
                                <span v-if="p.legende" class="grille__legende">{{ p.legende }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.galerie { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 16px; background: rgb(21 32 26 / 0.7); }
.galerie__boite { display: flex; flex-direction: column; width: min(1100px, 100%); max-height: min(92vh, 100%); background: var(--surface); border-radius: var(--r); box-shadow: var(--shadow-float); overflow: hidden; }
.galerie__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--rule); }
.galerie__titre { display: flex; align-items: center; gap: 10px; min-width: 0; }
.galerie__titre div { display: grid; gap: 2px; min-width: 0; }
.galerie__titre strong { font-size: 15px; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.galerie__titre small { font-size: 12px; color: var(--ink-3); }
.galerie__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
.btn--icone { padding: 8px; }
.galerie__corps { flex: 1; min-height: 0; overflow-y: auto; padding: 16px; background: var(--sunk); }
.galerie__info { display: grid; justify-items: center; gap: 12px; padding: 40px 16px; text-align: center; color: var(--ink-3); }
.galerie__info svg { width: 20px; height: 20px; }
@media (max-width: 560px) {
    .galerie__corps { padding: 10px; }
    .galerie__actions .btn-ghost:not(.btn--icone) { font-size: 12.5px; padding: 6px 10px; }
}

.grille { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; margin: 0; padding: 0; list-style: none; }
.grille__case { position: relative; display: block; width: 100%; aspect-ratio: 4 / 3; padding: 0; border: 0; border-radius: var(--r-sm); overflow: hidden; background: var(--rule-soft); cursor: zoom-in; }
.grille__case img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s var(--ease-out); }
.grille__case:hover img { transform: scale(1.04); }
.grille__case:focus-visible { outline: 2px solid var(--water); outline-offset: 2px; }
.grille__legende { position: absolute; inset: auto 0 0; padding: 18px 10px 8px; background: linear-gradient(transparent, rgb(0 0 0 / 0.6)); color: #fff; font-size: 12.5px; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.vue { display: grid; gap: 10px; margin: 0; }
.vue__cadre { position: relative; display: grid; place-items: center; }
.vue__cadre img { max-width: 100%; max-height: calc(92vh - 170px); width: auto; height: auto; border-radius: var(--r-sm); }
.vue__nav { position: absolute; top: 50%; transform: translateY(-50%); display: grid; place-items: center; width: 40px; height: 40px; border: 0; border-radius: 50%; background: rgb(255 255 255 / 0.85); color: var(--ink); cursor: pointer; box-shadow: 0 1px 4px rgb(0 0 0 / 0.25); }
.vue__nav svg { width: 18px; height: 18px; }
.vue__nav--prec { left: 8px; }
.vue__nav--suiv { right: 8px; }
.vue figcaption { display: grid; gap: 2px; text-align: center; font-size: 14px; color: var(--ink); }
.vue figcaption small { font-size: 12px; color: var(--ink-3); }
@media (prefers-reduced-motion: reduce) { .grille__case img { transition: none; } }
</style>
