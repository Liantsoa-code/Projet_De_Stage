<script setup>
import Icon from '@/Components/Icon.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

// Galerie d'une aire protégée (fiche admin) : chaque action est enregistrée aussitôt, indépendamment
// du formulaire de la fiche. Les photos partent une par une (limite d'envoi du serveur) et sont réduites
// à l'arrivée (App\Support\PhotosSite) : l'original n'est pas gardé.
const props = defineProps({
    site: { type: Object, required: true },
    max: { type: Number, required: true },
});

const page = usePage();
const photos = computed(() => props.site.photos ?? []);
const reste = computed(() => props.max - photos.value.length);

// Légende et crédit en cours de saisie, par photo
const textes = reactive({});
watch(photos, (liste) => {
    liste.forEach((p) => { textes[p.id] ??= { legende: p.legende ?? '', credit: p.credit ?? '' }; });
}, { immediate: true });
const modifiee = (p) => textes[p.id] && (textes[p.id].legende !== (p.legende ?? '') || textes[p.id].credit !== (p.credit ?? ''));

const options = { preserveScroll: true, preserveState: true };

// Envoi des fichiers choisis, l'un après l'autre
const envoi = ref(null); // { n, total }
const avertissement = ref('');
const choisir = async (event) => {
    const fichiers = [...event.target.files];
    event.target.value = '';
    avertissement.value = fichiers.length > reste.value
        ? `Seules les ${reste.value} premières photos ont été envoyées : une aire protégée en compte au plus ${props.max}.`
        : '';
    const aEnvoyer = fichiers.slice(0, Math.max(0, reste.value));
    for (const [i, fichier] of aEnvoyer.entries()) {
        envoi.value = { n: i + 1, total: aEnvoyer.length };
        await new Promise((fin) => router.post(route('admin.sites.photos.store', props.site.id), { photo: fichier }, { ...options, forceFormData: true, onFinish: fin }));
        if (page.props.errors?.photo) break;
    }
    envoi.value = null;
};

const enregistrer = (p) => router.put(route('admin.sites.photos.update', [props.site.id, p.id]), textes[p.id], options);

const deplacer = (i, sens) => {
    const ids = photos.value.map((p) => p.id);
    [ids[i], ids[i + sens]] = [ids[i + sens], ids[i]];
    router.put(route('admin.sites.photos.ordre', props.site.id), { ordre: ids }, options);
};

const supprimer = (p) => {
    if (confirm('Supprimer cette photo ?')) router.delete(route('admin.sites.photos.destroy', [props.site.id, p.id]), options);
};
</script>

<template>
    <section class="glass-form-wrap photos">
        <div class="gf-body">
            <div class="photos__head">
                <div>
                    <h2 class="gf-label photos__titre">Photos de l'aire protégée</h2>
                    <p class="gf-hint">
                        Montrées par le bouton « Voir les photos » de la carte. Chaque photo est réduite à l'envoi (1600 px, environ 250 Ko) :
                        gardez les originaux en pleine qualité dans la banque d'images. {{ photos.length }} / {{ max }} photos.
                    </p>
                </div>
                <label class="btn-ghost photos__ajout" :class="{ 'photos__ajout--off': reste <= 0 || envoi }">
                    <Icon :name="envoi ? 'circle-notch' : 'upload-simple'" :class="{ spin: envoi }" />
                    {{ envoi ? `Envoi ${envoi.n} / ${envoi.total}…` : 'Ajouter des photos' }}
                    <input type="file" accept="image/jpeg,image/png,image/webp" multiple :disabled="reste <= 0 || !!envoi" class="sr-only" @change="choisir" />
                </label>
            </div>
            <p v-if="page.props.errors?.photo" class="gf-error">{{ page.props.errors.photo }}</p>
            <p v-if="avertissement" class="gf-hint photos__avert">{{ avertissement }}</p>

            <p v-if="!photos.length" class="photos__vide">Aucune photo pour le moment. JPG, PNG ou WebP, 15 Mo au plus par photo.</p>
            <ul v-else class="photos__liste">
                <li v-for="(p, i) in photos" :key="p.id" class="photo">
                    <img :src="p.url_vignette" alt="" class="photo__img" />
                    <div class="photo__champs">
                        <input v-model="textes[p.id].legende" type="text" class="gf-input" maxlength="255" placeholder="Légende (facultatif)" aria-label="Légende" />
                        <input v-model="textes[p.id].credit" type="text" class="gf-input" maxlength="150" placeholder="Crédit photo (facultatif)" aria-label="Crédit photo" />
                    </div>
                    <div class="photo__actions">
                        <button v-if="modifiee(p)" type="button" class="btn-sm-edit" @click="enregistrer(p)"><Icon name="check-circle" />Enregistrer</button>
                        <button type="button" class="btn-sm-edit" :disabled="i === 0" aria-label="Avancer dans la galerie" @click="deplacer(i, -1)"><Icon name="arrow-left" /></button>
                        <button type="button" class="btn-sm-edit" :disabled="i === photos.length - 1" aria-label="Reculer dans la galerie" @click="deplacer(i, 1)"><Icon name="arrow-right" /></button>
                        <button type="button" class="btn-sm-delete" aria-label="Supprimer la photo" @click="supprimer(p)"><Icon name="trash" /></button>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.photos { margin-top: 16px; }
.photos__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px; }
.photos__titre { margin-bottom: 2px; }
.photos__ajout { cursor: pointer; flex-shrink: 0; }
.photos__ajout--off { opacity: 0.5; cursor: not-allowed; }
.photos__avert { color: var(--ochre); }
.photos__vide { padding: 20px; border: 1px dashed var(--rule); border-radius: var(--r-sm); text-align: center; font-size: 13.5px; color: var(--ink-3); }
.photos__liste { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
.photo { display: grid; grid-template-columns: 120px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 8px; border: 1px solid var(--rule); border-radius: var(--r-sm); }
.photo__img { width: 120px; height: 90px; object-fit: cover; border-radius: 6px; background: var(--sunk); }
.photo__champs { display: grid; gap: 6px; min-width: 0; }
.photo__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }
.photo__actions button:disabled { opacity: 0.4; cursor: not-allowed; }
@media (max-width: 620px) {
    .photo { grid-template-columns: 96px minmax(0, 1fr); }
    .photo__img { width: 96px; height: 72px; }
    .photo__actions { grid-column: 1 / -1; justify-content: flex-start; }
}
</style>
