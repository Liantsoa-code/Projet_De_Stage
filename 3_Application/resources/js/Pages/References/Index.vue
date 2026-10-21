<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ApercuDocument from './Partials/ApercuDocument.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { openAuthenticatedPdf } from '@/utils/openPdf.js';
import { formatDate } from '@/utils/statut.js';
import { ref } from 'vue';

// Modèles des documents officiels (PDF, gérés par les admins) et documents déposés par l'équipe.
defineProps({
    modeles: { type: Array, required: true },
    documents: { type: Array, required: true },
    peutGererModeles: { type: Boolean, default: false },
});

// Nombre de champs affichés sur une carte, les autres sont résumés en « +N »
const CHAMPS_VISIBLES = 6;

const ouverture = ref(null);

const ouvrir = async (modele) => {
    ouverture.value = modele.id;
    try {
        await openAuthenticatedPdf(route('references.modeles.show', modele.id), `modele_${modele.id}.pdf`);
    } catch (e) {
        alert(e.message || 'Erreur lors de l\'ouverture du PDF.');
    } finally {
        ouverture.value = null;
    }
};

const supprimerModele = (modele) => {
    if (confirm(`Supprimer le modèle « ${modele.titre} » ?`)) {
        router.delete(route('references.modeles.destroy', modele.id), { preserveScroll: true });
    }
};

// Dépôt d'un document
const form = useForm({ titre: '', description: '', fichier: null });
const champFichier = ref(null);

const choisirFichier = (e) => {
    form.fichier = e.target.files[0] ?? null;
    form.clearErrors('fichier');
    // Titre proposé à partir du nom du fichier, s'il n'est pas déjà saisi
    if (form.fichier && !form.titre.trim()) {
        form.titre = form.fichier.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
    }
};

const deposer = () => {
    form.post(route('references.documents.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (champFichier.value) champFichier.value.value = '';
        },
    });
};

// Liste des documents
const apercu = ref(null);

const supprimer = (doc) => {
    if (confirm(`Supprimer « ${doc.titre} » des références ?`)) {
        router.delete(route('references.documents.destroy', doc.id), { preserveScroll: true });
    }
};

const iconeDocument = (doc) => {
    if (doc.type_mime === 'application/pdf') return 'file-pdf';
    if (doc.type_mime.startsWith('image/')) return 'image';
    return 'file-text';
};

const formatTaille = (octets) => (octets < 1024 * 1024
    ? `${Math.max(1, Math.round(octets / 1024))} Ko`
    : `${(octets / (1024 * 1024)).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Mo`);
</script>

<template>
    <Head title="Références" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <div class="page-title">Références</div>
                <p class="page-sub">
                    Modèles des documents officiels de la Fondation et documents partagés par l'équipe d'instruction.
                </p>
            </div>
        </template>

        <section class="bloc" aria-labelledby="titre-modeles">
            <div class="bloc__head bloc__head--actions">
                <div>
                    <h2 id="titre-modeles" class="section-title">Modèles officiels</h2>
                    <p class="section-text">
                        Les champs entre crochets sont à compléter ; chaque modèle porte la mention « Modèle » et n'a pas de valeur officielle.
                    </p>
                </div>
                <Link v-if="peutGererModeles" :href="route('references.modeles.create')" class="btn-accent">
                    <Icon name="plus" />
                    Nouveau modèle
                </Link>
            </div>

            <p v-if="!modeles.length" class="docs-vide">Aucun modèle pour l'instant.</p>
            <div v-else class="modeles">
                <article v-for="modele in modeles" :key="modele.id" class="modele">
                    <header class="modele__head">
                        <span class="modele__icon"><Icon :name="modele.icone" /></span>
                        <div>
                            <h3 class="modele__titre">{{ modele.titre }}</h3>
                            <span class="modele__badge" :class="modele.automatique ? 'modele__badge--auto' : 'modele__badge--main'">
                                {{ modele.automatique ? 'Généré à la validation' : 'À compléter à la main' }}
                            </span>
                        </div>
                    </header>

                    <p class="modele__desc">{{ modele.description }}</p>

                    <div v-if="modele.usage" class="modele__bloc">
                        <span class="modele__label">Quand l'utiliser</span>
                        <p>{{ modele.usage }}</p>
                    </div>

                    <div v-if="modele.champs.length" class="modele__bloc">
                        <span class="modele__label">Champs à compléter ({{ modele.champs.length }})</span>
                        <ul class="modele__champs">
                            <li v-for="champ in modele.champs.slice(0, CHAMPS_VISIBLES)" :key="champ">{{ champ }}</li>
                            <li v-if="modele.champs.length > CHAMPS_VISIBLES" :title="modele.champs.slice(CHAMPS_VISIBLES).join(', ')">
                                +{{ modele.champs.length - CHAMPS_VISIBLES }}
                            </li>
                        </ul>
                    </div>

                    <div class="modele__actions">
                        <button type="button" class="btn-ghost" :disabled="ouverture === modele.id" @click="ouvrir(modele)">
                            <Icon :name="ouverture === modele.id ? 'circle-notch' : 'file-pdf'" :class="{ spin: ouverture === modele.id }" />
                            Ouvrir le modèle
                        </button>
                        <template v-if="peutGererModeles">
                            <Link :href="route('references.modeles.edit', modele.id)" class="btn-sm-edit">
                                <Icon name="pencil-simple" />Modifier
                            </Link>
                            <button type="button" class="btn-sm-delete" @click="supprimerModele(modele)">
                                <Icon name="trash" />Supprimer
                            </button>
                        </template>
                    </div>
                </article>
            </div>
        </section>

        <section class="bloc" aria-labelledby="titre-documents">
            <div class="bloc__head">
                <h2 id="titre-documents" class="section-title">Documents de l'équipe</h2>
                <p class="section-text">
                    Déposez vos propres documents de référence (avis signés, courriers types, notes) : ils sont visibles par tous les validateurs et admins.
                </p>
            </div>

            <form class="depot" @submit.prevent="deposer">
                <div class="depot__ligne">
                    <label class="champ">
                        <span class="champ__label">Titre *</span>
                        <input v-model="form.titre" type="text" class="champ__input" maxlength="255" placeholder="Ex : Avis favorable signé — Menabe 2026">
                        <span v-if="form.errors.titre" class="champ__erreur">{{ form.errors.titre }}</span>
                    </label>
                    <label class="champ">
                        <span class="champ__label">Fichier *</span>
                        <input ref="champFichier" type="file" class="champ__input champ__input--fichier"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.odt" @change="choisirFichier">
                        <span v-if="form.errors.fichier" class="champ__erreur">{{ form.errors.fichier }}</span>
                        <span v-else class="champ__aide">PDF, JPG, PNG, DOC, DOCX ou ODT — 20 Mo maximum.</span>
                    </label>
                </div>
                <label class="champ">
                    <span class="champ__label">Description <small>(facultatif)</small></span>
                    <textarea v-model="form.description" class="champ__input" rows="2" maxlength="1000" placeholder="À quoi sert ce document, dans quel cas l'utiliser…" />
                    <span v-if="form.errors.description" class="champ__erreur">{{ form.errors.description }}</span>
                </label>
                <div>
                    <button type="submit" class="btn-accent" :disabled="form.processing">
                        <Icon :name="form.processing ? 'circle-notch' : 'upload-simple'" :class="{ spin: form.processing }" />
                        {{ form.processing ? 'Envoi…' : 'Déposer le document' }}
                    </button>
                    <progress v-if="form.progress" class="depot__progres" :value="form.progress.percentage" max="100">{{ form.progress.percentage }} %</progress>
                </div>
            </form>

            <ul v-if="documents.length" class="docs">
                <li v-for="doc in documents" :key="doc.id" class="doc">
                    <span class="doc__icon"><Icon :name="iconeDocument(doc)" /></span>
                    <div class="doc__infos">
                        <strong class="doc__titre">{{ doc.titre }}</strong>
                        <p v-if="doc.description" class="doc__desc">{{ doc.description }}</p>
                        <small class="doc__meta">
                            {{ doc.nom_fichier }} · {{ formatTaille(doc.taille) }} · déposé par {{ doc.auteur ?? 'un compte supprimé' }} le {{ formatDate(doc.cree_le) }}
                        </small>
                    </div>
                    <div class="doc__actions">
                        <button type="button" class="btn-sm-edit" @click="apercu = doc">
                            <Icon name="eye" />Voir
                        </button>
                        <a :href="route('references.documents.show', { document: doc.id, telecharger: 1 })" class="btn-sm-edit">
                            <Icon name="download-simple" />Télécharger
                        </a>
                        <button v-if="doc.peut_supprimer" type="button" class="btn-sm-delete" @click="supprimer(doc)">
                            <Icon name="trash" />Supprimer
                        </button>
                    </div>
                </li>
            </ul>
            <p v-else class="docs-vide">Aucun document déposé pour l'instant.</p>
        </section>

        <ApercuDocument v-if="apercu" :document="apercu" @close="apercu = null" />
    </AuthenticatedLayout>
</template>

<style scoped>
.bloc { display: grid; gap: 14px; }
.bloc + .bloc { margin-top: 36px; }
.bloc__head { display: grid; gap: 4px; }

.modeles { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr)); gap: 16px; }

.modele { display: flex; flex-direction: column; gap: 14px; padding: 20px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); min-width: 0; }
.modele__head { display: flex; align-items: flex-start; gap: 12px; }
.modele__icon { display: grid; place-items: center; flex-shrink: 0; width: 40px; height: 40px; border-radius: var(--r-sm); background: var(--forest-soft); color: var(--forest); }
.modele__icon svg { width: 22px; height: 22px; }
.modele__titre { font: 700 15.5px/1.25 var(--f-body); color: var(--ink); }
.modele__badge { display: inline-block; margin-top: 5px; padding: 2px 8px; border-radius: var(--r-pill); font-size: 11.5px; font-weight: 600; }
.modele__badge--auto { color: var(--forest); background: var(--forest-soft); }
.modele__badge--main { color: var(--water); background: var(--water-soft); }

.modele__desc { font-size: 14px; color: var(--ink-2); }
.modele__bloc { display: grid; gap: 4px; font-size: 13px; color: var(--ink-2); }
.modele__label { font: 600 11px/1.2 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.modele__champs { display: flex; flex-wrap: wrap; gap: 5px; }
.modele__champs li { padding: 3px 9px; background: var(--sunk); border: 1px solid var(--rule); border-radius: var(--r-sm); font-size: 11.5px; font-weight: 500; color: var(--ink-2); }

.modele__champs li { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.modele__actions { margin-top: auto; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.modele__actions .btn-ghost:disabled { opacity: 0.6; cursor: progress; }
.bloc__head--actions { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

/* Dépôt */
.depot { display: grid; gap: 14px; padding: 20px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); }
.depot__ligne { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 720px) { .depot__ligne { grid-template-columns: 1fr; } }
.depot__progres { display: block; width: 220px; max-width: 100%; margin-top: 8px; accent-color: var(--forest); }

.champ { display: grid; gap: 5px; align-content: start; min-width: 0; }
.champ__label { font-size: 12px; font-weight: 600; color: var(--ink); }
.champ__label small { font-weight: 400; color: var(--ink-3); }
.champ__input { width: 100%; padding: 10px 12px; border: 1px solid var(--rule); border-radius: var(--r-sm); background: var(--surface); font: 400 13.5px/1.45 var(--f-body); color: var(--ink); }
.champ__input:focus { outline: none; border-color: var(--forest); box-shadow: 0 0 0 3px var(--forest-soft); }
.champ__input--fichier { padding: 7px 10px; }
textarea.champ__input { resize: vertical; }
.champ__erreur { font-size: 12px; color: var(--signal); }
.champ__aide { font-size: 12px; color: var(--ink-3); }

/* Liste */
.docs { display: grid; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); }
.doc { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; }
.doc + .doc { border-top: 1px solid var(--rule-soft); }
.doc__icon { display: grid; place-items: center; flex-shrink: 0; width: 36px; height: 36px; border-radius: var(--r-sm); background: var(--sunk); color: var(--ink-2); }
.doc__icon svg { width: 20px; height: 20px; }
.doc__infos { display: grid; gap: 3px; flex: 1; min-width: 0; }
.doc__titre { font-size: 14.5px; color: var(--ink); overflow-wrap: anywhere; }
.doc__desc { font-size: 13px; color: var(--ink-2); white-space: pre-line; }
.doc__meta { font-size: 12px; color: var(--ink-3); overflow-wrap: anywhere; }
.doc__actions { display: flex; flex-wrap: wrap; gap: 6px; flex-shrink: 0; }
@media (max-width: 720px) {
    .doc { flex-wrap: wrap; }
    .doc__actions { width: 100%; padding-left: 48px; }
}
.docs-vide { padding: 20px; text-align: center; font-size: 14px; color: var(--ink-3); background: var(--surface); border: 1px dashed var(--rule); border-radius: var(--r); }

.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .spin { animation: none; } }
</style>
