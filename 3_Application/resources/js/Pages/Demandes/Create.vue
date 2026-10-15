<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EtapeActivite from '@/Pages/Demandes/Partials/EtapeActivite.vue';
import EtapeDates from '@/Pages/Demandes/Partials/EtapeDates.vue';
import EtapeMission from '@/Pages/Demandes/Partials/EtapeMission.vue';
import EtapePieces from '@/Pages/Demandes/Partials/EtapePieces.vue';
import EtapeRecap from '@/Pages/Demandes/Partials/EtapeRecap.vue';
import VerificationCalendrier from '@/Pages/Demandes/Partials/VerificationCalendrier.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

// Formulaire en étapes : la page garde les données, la vérification et l'envoi ;
// chaque étape et la colonne du calendrier sont des composants de Partials/.
const props = defineProps({
    sites: Array,
    types: Array,
    periodes: { type: Array, default: () => [] },
    // Présent en modification (brouillon ou dossier à compléter)
    demande: { type: Object, default: null },
    complement: { type: String, default: null },
});

const enEdition = computed(() => props.demande !== null);
const aCompleter = computed(() => props.demande?.statut?.code === 'a_completer');

// Stockage des fichiers uploades separement (avant transfert dans le form)
const uploadedFiles = ref({});

const form = useForm({
    site_id: props.demande?.site_id ?? '',
    type_demande_id: props.demande?.type_demande_id ?? '',
    date_debut: props.demande?.date_debut?.slice(0, 10) ?? '',
    date_fin: props.demande?.date_fin?.slice(0, 10) ?? '',
    equipe_nombre: String(props.demande?.equipe_nombre ?? '1'),
    description: props.demande?.description ?? '',
    message_complement: '',
});

const typeSelectionne = computed(() => props.types.find(t => t.id === form.type_demande_id));
const siteSelectionne = computed(() => props.sites.find(s => s.id === form.site_id));

// Pièces déjà envoyées (modification), indexées par id de la pièce requise
const docsExistants = computed(() => Object.fromEntries((props.demande?.documents ?? []).map(d => [d.document_requis_id, d])));

// Date du jour au format AAAA-MM-JJ (heure locale) pour bloquer les dates passées
const today = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

// ── Périodes sensibles : alerte en direct avant l'envoi ──
const periodesSite = computed(() => props.periodes.filter(p => p.site_id === form.site_id));
// Comparaison de chaînes AAAA-MM-JJ : deux intervalles se chevauchent si chacun commence avant la fin de l'autre
const surLesDates = (p) => p.date_debut <= (form.date_fin || form.date_debut) && p.date_fin >= form.date_debut;
const chevauchements = computed(() => (form.date_debut ? periodesSite.value.filter(surLesDates) : []));
// État de chaque site sur les dates choisies, pour colorer la carte (null tant qu'il n'y a pas de dates)
const etatsSites = computed(() => {
    if (!form.date_debut) return null;
    const etats = {};
    props.periodes.filter(surLesDates).forEach((p) => {
        etats[p.site_id] = p.niveau_restriction === 'bloquant' ? 'bloquant' : (etats[p.site_id] ?? 'avertissement');
    });
    return etats;
});
const bloquant = computed(() => chevauchements.value.some(p => p.niveau_restriction === 'bloquant'));
// État du calendrier du site choisi, affiché dans le récapitulatif et dans la colonne de vérification
const calendrier = computed(() => {
    if (!form.date_debut) return 'aucun';
    if (bloquant.value) return 'bloquant';
    return chevauchements.value.length ? 'sensible' : 'ouvert';
});

// Quand le type change, on vide les fichiers precedemment choisis
function onTypeChange() {
    uploadedFiles.value = {};
}

// Un fichier choisi efface le message « pièce obligatoire » de cette pièce
function choisirFichier(docId, file) {
    if (file) {
        uploadedFiles.value['doc_' + docId] = file;
        form.clearErrors('doc_' + docId);
    } else {
        delete uploadedFiles.value['doc_' + docId];
    }
}

// action : 'soumettre' (dossier complet) ou 'brouillon' (enregistrement partiel)
function envoyer(action) {
    const docsRequis = typeSelectionne.value?.documents_requis ?? [];
    const docs = {};
    docsRequis.forEach((doc) => {
        const key = 'doc_' + doc.id;
        if (uploadedFiles.value[key]) {
            docs[key] = uploadedFiles.value[key];
        }
    });

    // Laravel attend doc_{id de la pièce requise} à la racine du multipart, pas sous documents[].
    // En modification : POST + _method=PUT, car PHP ne lit pas les fichiers d'un vrai PUT multipart.
    const extra = enEdition.value ? { _method: 'put' } : {};
    const url = enEdition.value ? route('demandes.update', props.demande.id) : route('demandes.store');

    form.transform((data) => ({ ...data, ...docs, ...extra, action }))
        .post(url, {
            forceFormData: true,
            preserveScroll: true,
            onError: allerAuxErreurs,
            onFinish: () => form.transform((data) => data),
        });
}

const titre = computed(() => {
    if (aCompleter.value) return `Compléter la demande ${props.demande.reference}`;
    if (enEdition.value) return `Brouillon ${props.demande.reference}`;
    return 'Nouvelle demande d\'accès';
});

// Pièces exigées par le type choisi, avec le fichier choisi pendant la saisie ou celui déjà envoyé
const piecesRequises = computed(() => typeSelectionne.value?.documents_requis ?? []);
const pieces = computed(() => piecesRequises.value.map((doc) => {
    const fichier = uploadedFiles.value['doc_' + doc.id] ?? null;
    const existant = docsExistants.value[doc.id] ?? null;
    return { ...doc, fichier, existant, fourni: Boolean(fichier || existant) };
}));

// ── Parcours en étapes : chaque étape est vérifiée avant de passer à la suivante ──
const ETAPES = [
    { id: 'activite', titre: 'Activité et site', champs: ['type_demande_id', 'site_id'] },
    { id: 'dates', titre: 'Dates et équipe', champs: ['date_debut', 'date_fin', 'equipe_nombre'] },
    { id: 'mission', titre: 'Objet de la mission', champs: ['description', 'message_complement'] },
    { id: 'pieces', titre: 'Pièces à joindre', champs: [] },
    { id: 'recap', titre: 'Récapitulatif', champs: [] },
];
// L'étape des pièces n'apparaît que si le type d'activité en exige (ou n'est pas encore choisi)
const etapes = computed(() => ETAPES.filter(e => e.id !== 'pieces' || !typeSelectionne.value || typeSelectionne.value.documents_requis?.length));
const champsEtape = (id) => (id === 'pieces' ? piecesRequises.value.map(d => 'doc_' + d.id) : ETAPES.find(e => e.id === id).champs);
const etapeDuChamp = (champ) => (champ.startsWith('doc_') ? 'pieces' : ETAPES.find(e => e.champs.includes(champ))?.id ?? 'activite');

// Mêmes exigences que le serveur pour une soumission (App\Http\Requests\DemandeRequest)
function erreursEtape(id) {
    const err = {};
    if (id === 'activite') {
        if (!form.type_demande_id) err.type_demande_id = 'Choisissez un type d\'activité.';
        if (!form.site_id) err.site_id = 'Choisissez une aire protégée.';
    } else if (id === 'dates') {
        if (!form.date_debut) err.date_debut = 'Indiquez la date d\'arrivée.';
        else if (form.date_debut < today) err.date_debut = 'La date de début ne peut pas être antérieure à aujourd\'hui.';
        else if (bloquant.value) err.date_debut = 'Ces dates chevauchent une période bloquante sur ce site : choisissez d\'autres dates.';
        if (!form.date_fin) err.date_fin = 'Indiquez la date de départ.';
        else if (form.date_debut && form.date_fin < form.date_debut) err.date_fin = 'La date de fin doit être postérieure ou égale à la date de début.';
        const equipe = Number(form.equipe_nombre);
        if (!Number.isInteger(equipe) || equipe < 1) err.equipe_nombre = 'L\'équipe compte au moins une personne.';
    } else if (id === 'mission') {
        if (!String(form.description ?? '').trim()) err.description = 'Décrivez le projet : objectif, protocole, zones visitées.';
    } else if (id === 'pieces') {
        pieces.value.filter(p => !p.fourni).forEach((p) => { err['doc_' + p.id] = `Le document '${p.libelle}' est obligatoire.`; });
    }
    return err;
}
const etapeComplete = (id) => Object.keys(erreursEtape(id)).length === 0;
// Une étape est accessible quand toutes les précédentes sont complètes
const accessible = (i) => etapes.value.slice(0, i).every(e => etapeComplete(e.id));
const etapeEnErreur = (id) => Object.keys(form.errors).some(k => etapeDuChamp(k) === id);

// Création : première étape. Reprise d'un dossier : première étape incomplète, sinon le récapitulatif.
const etape = ref(enEdition.value ? (etapes.value.find(e => !etapeComplete(e.id))?.id ?? 'recap') : 'activite');
const indexEtape = computed(() => Math.max(0, etapes.value.findIndex(e => e.id === etape.value)));
const titreEtape = computed(() => ETAPES.find(e => e.id === etape.value).titre);
const derniere = computed(() => indexEtape.value === etapes.value.length - 1);
const formEl = ref(null);

function allerA(id) {
    etape.value = id;
    nextTick(() => {
        formEl.value?.querySelector('.fs__head h2')?.focus({ preventScroll: true });
        const haut = formEl.value?.getBoundingClientRect().top ?? 0;
        if (haut < 0) window.scrollBy({ top: haut - 96 });
    });
}

// Affiche les erreurs de l'étape ; renvoie false si elle est incomplète
function verifierEtape(id) {
    const err = erreursEtape(id);
    const champs = champsEtape(id);
    if (champs.length) form.clearErrors(...champs);
    if (Object.keys(err).length) {
        form.setError(err);
        return false;
    }
    return true;
}

function focusPremiereErreur() {
    nextTick(() => {
        const champ = champsEtape(etape.value).find(c => form.errors[c]);
        if (champ) document.getElementById(champ)?.focus();
    });
}

function suivant() {
    if (!verifierEtape(etape.value)) return focusPremiereErreur();
    allerA(etapes.value[indexEtape.value + 1].id);
}

const precedent = () => allerA(etapes.value[indexEtape.value - 1].id);

// Entrée dans un champ ou bouton principal : étape suivante, ou envoi depuis le récapitulatif
function valider() {
    if (!derniere.value) return suivant();
    const incomplete = etapes.value.find(e => !verifierEtape(e.id));
    if (incomplete) {
        allerA(incomplete.id);
        return focusPremiereErreur();
    }
    envoyer('soumettre');
}

// Erreurs renvoyées par le serveur : retour à la première étape concernée
function allerAuxErreurs(errors) {
    const ids = etapes.value.map(e => e.id);
    const premiere = Object.keys(errors).map(k => ids.indexOf(etapeDuChamp(k))).filter(i => i >= 0).sort((a, b) => a - b)[0];
    if (premiere !== undefined) {
        allerA(ids[premiere]);
        focusPremiereErreur();
    }
}
</script>

<template>
    <Head :title="titre" />
    <AuthenticatedLayout>
        <template #header>
            <div class="head">
                <Link :href="enEdition ? route('demandes.show', demande.id) : route('demandes.index')" class="back-link">
                    <Icon name="arrow-left" />
                    {{ enEdition ? 'Retour au dossier' : 'Mes demandes' }}
                </Link>
                <div class="page-title">{{ titre }}</div>
            </div>
        </template>

        <section v-if="aCompleter" class="complement-box">
            <Icon name="pencil-simple-line" class="complement-box__icon" />
            <div>
                <p class="complement-box__tag">Complément demandé par le validateur</p>
                <q v-if="complement" class="complement-box__quote">{{ complement }}</q>
                <p class="complement-box__text">Corrigez les informations ou remplacez les pièces concernées, puis renvoyez le dossier.</p>
            </div>
        </section>

        <form ref="formEl" class="create-layout" novalidate @submit.prevent="valider">
            <div class="create-main">
                <nav class="stepper" aria-label="Étapes de la demande">
                    <ol>
                        <li v-for="(e, i) in etapes" :key="e.id">
                            <button
                                type="button"
                                class="stepper__btn"
                                :class="{
                                    'is-current': e.id === etape,
                                    'is-done': e.id !== etape && e.id !== 'recap' && etapeComplete(e.id) && accessible(i),
                                    'is-error': e.id !== etape && etapeEnErreur(e.id),
                                }"
                                :aria-current="e.id === etape ? 'step' : undefined"
                                :disabled="e.id !== etape && !accessible(i)"
                                @click="allerA(e.id)"
                            >
                                <span class="stepper__n">
                                    <Icon v-if="e.id !== etape && e.id !== 'recap' && etapeComplete(e.id) && accessible(i)" name="check-fat" />
                                    <template v-else>{{ i + 1 }}</template>
                                </span>
                                <span class="stepper__label">{{ e.titre }}</span>
                            </button>
                        </li>
                    </ol>
                </nav>

                <section class="fs">
                    <header class="fs__head"><span class="fs__n">{{ indexEtape + 1 }}</span><h2 tabindex="-1">{{ titreEtape }}</h2></header>
                    <EtapeActivite v-if="etape === 'activite'" :form="form" :types="types" :sites="sites" @type-change="onTypeChange" />
                    <EtapeDates v-else-if="etape === 'dates'" :form="form" :today="today" />
                    <EtapeMission v-else-if="etape === 'mission'" :form="form" :a-completer="aCompleter" />
                    <EtapePieces v-else-if="etape === 'pieces'" :form="form" :type-activite="typeSelectionne" :pieces="pieces" @fichier="choisirFichier" />
                    <EtapeRecap
                        v-else
                        :form="form"
                        :type-activite="typeSelectionne"
                        :site="siteSelectionne"
                        :calendrier="calendrier"
                        :pieces="pieces"
                        @modifier="allerA"
                    />
                </section>
            </div>

            <aside class="create-side">
                <VerificationCalendrier
                    :sites="sites"
                    :etats="etatsSites"
                    :site="siteSelectionne"
                    :calendrier="calendrier"
                    :periodes="periodesSite"
                    :chevauchements="chevauchements"
                    @select="(id) => (form.site_id = id)"
                />
            </aside>

            <div class="create-actions">
                <span class="create-actions__info">
                    <span class="create-actions__step">Étape {{ indexEtape + 1 }} sur {{ etapes.length }}</span>
                    <span v-if="bloquant" class="create-actions__msg">Ces dates tombent pendant une période bloquante.</span>
                </span>
                <button v-if="indexEtape > 0" type="button" class="btn-ghost" @click="precedent"><Icon name="arrow-left" />Précédent</button>
                <button v-if="!aCompleter" type="button" class="btn-ghost" :disabled="form.processing" @click="envoyer('brouillon')">Enregistrer le brouillon</button>
                <button v-if="!derniere" type="submit" class="btn-accent">
                    Suivant
                    <Icon name="arrow-right" />
                </button>
                <button v-else type="submit" :disabled="form.processing || bloquant" class="btn-accent">
                    <Icon v-if="form.processing" name="circle-notch" class="spin" />
                    <Icon v-else name="arrow-right" />
                    {{ form.processing ? 'Envoi…' : aCompleter ? 'Renvoyer le dossier complété' : 'Envoyer la demande' }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

<style scoped>
.head { display: grid; gap: 6px; }
.create-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
@media (max-width: 960px) { .create-layout { grid-template-columns: minmax(0, 1fr); } }
.create-main { display: grid; gap: 16px; min-width: 0; }

.fs { display: grid; gap: 16px; padding: 24px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); }
.fs__head { display: flex; align-items: center; gap: 12px; }
.fs__head h2 { font-size: 17px; font-weight: 700; }
.fs__n { display: grid; place-items: center; width: 26px; height: 26px; border: 1.5px solid var(--forest); border-radius: 50%; color: var(--forest); font: 600 12.5px var(--f-mono); }

.fs__head h2:focus { outline: none; }

.stepper ol { list-style: none; margin: 0; padding: 0; display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); gap: 6px; }
.stepper__btn { display: flex; align-items: center; gap: 8px; width: 100%; min-width: 0; padding: 8px 10px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r-sm); font-size: 13px; color: var(--ink-3); text-align: left; cursor: pointer; }
.stepper__btn:hover:not(:disabled) { border-color: var(--ink-3); }
.stepper__btn:focus-visible { outline: 2px solid var(--water); outline-offset: 2px; }
.stepper__btn:disabled { opacity: 0.55; cursor: not-allowed; }
.stepper__n { flex-shrink: 0; display: grid; place-items: center; width: 22px; height: 22px; border: 1.5px solid currentColor; border-radius: 50%; font: 600 11.5px var(--f-mono); }
.stepper__n svg { width: 12px; height: 12px; }
.stepper__label { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.stepper__btn.is-done { color: var(--forest); }
.stepper__btn.is-done .stepper__n { background: var(--forest-soft); }
.stepper__btn.is-current { border-color: var(--forest); color: var(--ink); font-weight: 600; box-shadow: inset 0 -2px 0 var(--forest); }
.stepper__btn.is-current .stepper__n { background: var(--forest); border-color: var(--forest); color: #fff; }
.stepper__btn.is-error { border-color: var(--signal-line); color: var(--signal); }
/* Sur petit écran, seul le libellé de l'étape en cours reste affiché */
@media (max-width: 720px) {
    .stepper ol { display: flex; }
    .stepper li { flex: 0 0 auto; }
    .stepper li:has(.is-current) { flex: 1 1 auto; min-width: 0; }
    .stepper__btn { padding: 8px; }
    .stepper__label { display: none; }
    .stepper__btn.is-current .stepper__label { display: inline; }
}

.create-side { min-width: 0; }

.create-actions {
    grid-column: 1 / -1;
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding: 12px 0;
    background: var(--paper);
    border-top: 1px solid var(--rule);
}
.create-actions__info { display: grid; gap: 2px; margin-right: auto; }
.create-actions__step { font-size: 12.5px; color: var(--ink-3); }
.create-actions__msg { font-size: 13px; font-weight: 600; color: var(--signal); }

.complement-box { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 14px; margin-bottom: 24px; padding: 16px 24px; background: var(--ochre-soft); border: 1px solid var(--ochre-line); border-radius: var(--r); }
.complement-box__icon { width: 24px; height: 24px; color: var(--ochre); }
.complement-box__tag { font: 700 11px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ochre); }
.complement-box__quote { display: block; margin-top: 6px; font-style: italic; quotes: "« " " »"; }
.complement-box__text { margin-top: 4px; font-size: 13.5px; color: var(--ink-2); }
</style>
