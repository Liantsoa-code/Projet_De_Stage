<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import DashboardMap from '@/Components/DashboardMap.vue';
import { computed, ref } from 'vue';
import { formatDate, refDemande } from '@/utils/statut.js';
import { surfaceKm2, formatSurface } from '@/utils/carte.js';

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
const carte = ref(null);
const siteSelectionne = computed(() => props.sites.find(s => s.id === form.site_id));
const surfaceSite = computed(() => formatSurface(surfaceKm2(siteSelectionne.value?.polygone_coordonnees)));

// Pièces déjà envoyées (modification), indexées par nom de document requis
const docsExistants = computed(() => Object.fromEntries((props.demande?.documents ?? []).map(d => [d.type_document, d])));

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
const chevauche = (p) => chevauchements.value.some(c => c.id === p.id);

// Quand le type change, on vide les fichiers precedemment choisis
function onTypeChange() {
    uploadedFiles.value = {};
}

function handleFileChange(index, event) {
    const file = event.target.files[0];
    if (file) {
        uploadedFiles.value['doc_' + index] = file;
    } else {
        delete uploadedFiles.value['doc_' + index];
    }
}

// action : 'soumettre' (dossier complet) ou 'brouillon' (enregistrement partiel)
function envoyer(action) {
    const docsRequis = typeSelectionne.value?.documents_requis ?? [];
    const docs = {};
    docsRequis.forEach((_, index) => {
        const key = 'doc_' + index;
        if (uploadedFiles.value[key]) {
            docs[key] = uploadedFiles.value[key];
        }
    });

    // Laravel attend doc_0, doc_1… à la racine du multipart, pas sous documents[].
    // En modification : POST + _method=PUT, car PHP ne lit pas les fichiers d'un vrai PUT multipart.
    const extra = enEdition.value ? { _method: 'put' } : {};
    const url = enEdition.value ? route('demandes.update', props.demande.id) : route('demandes.store');

    form.transform((data) => ({ ...data, ...docs, ...extra, action }))
        .post(url, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
}

const titre = computed(() => {
    if (aCompleter.value) return `Compléter la demande ${refDemande(props.demande)}`;
    if (enEdition.value) return `Brouillon ${refDemande(props.demande)}`;
    return 'Nouvelle demande d\'accès';
});

const fourni = (i, doc) => uploadedFiles.value['doc_' + i] || docsExistants.value[doc];
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

        <form class="create-layout" novalidate @submit.prevent="envoyer('soumettre')">
            <div class="create-main">
                <div v-if="form.errors.date_debut" class="gf-error-banner" role="alert">
                    <Icon name="prohibit" />
                    {{ form.errors.date_debut }}
                </div>

                <section class="fs">
                    <header class="fs__head"><span class="fs__n">1</span><h2>Activité et site</h2></header>
                    <div class="gf-row gf-row-2">
                        <div>
                            <label class="gf-label" for="type_demande_id">Type d'activité</label>
                            <select id="type_demande_id" v-model="form.type_demande_id" class="gf-select" required @change="onTypeChange">
                                <option disabled value="">Choisissez un type</option>
                                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.libelle }}</option>
                            </select>
                            <InputError class="gf-error" :message="form.errors.type_demande_id" />
                        </div>
                        <div>
                            <label class="gf-label" for="site_id">Aire protégée</label>
                            <select id="site_id" v-model="form.site_id" class="gf-select" required>
                                <option disabled value="">Choisissez un site</option>
                                <option v-for="s in sites" :key="s.id" :value="s.id">{{ s.nom }}</option>
                            </select>
                            <InputError class="gf-error" :message="form.errors.site_id" />
                        </div>
                    </div>
                </section>

                <section class="fs">
                    <header class="fs__head"><span class="fs__n">2</span><h2>Dates et équipe</h2></header>
                    <div class="gf-row gf-row-3">
                        <div>
                            <label class="gf-label" for="date_debut">Arrivée</label>
                            <input id="date_debut" v-model="form.date_debut" type="date" class="gf-input" :min="today" required />
                        </div>
                        <div>
                            <label class="gf-label" for="date_fin">Départ</label>
                            <input id="date_fin" v-model="form.date_fin" type="date" class="gf-input" :min="form.date_debut || today" required />
                            <InputError class="gf-error" :message="form.errors.date_fin" />
                        </div>
                        <div>
                            <label class="gf-label" for="equipe_nombre">Personnes sur le terrain</label>
                            <input id="equipe_nombre" v-model="form.equipe_nombre" type="number" min="1" class="gf-input" required />
                            <InputError class="gf-error" :message="form.errors.equipe_nombre" />
                        </div>
                    </div>
                </section>

                <section class="fs">
                    <header class="fs__head"><span class="fs__n">3</span><h2>Objet de la mission</h2></header>
                    <div>
                        <label class="gf-label" for="description">Description du projet</label>
                        <textarea id="description" v-model="form.description" class="gf-textarea" rows="5" required placeholder="Objectif, protocole, zones visitées, matériel utilisé"></textarea>
                        <InputError class="gf-error" :message="form.errors.description" />
                    </div>
                    <div v-if="aCompleter">
                        <label class="gf-label" for="message_complement">Message au validateur (facultatif)</label>
                        <textarea id="message_complement" v-model="form.message_complement" class="gf-textarea" rows="3" placeholder="Précisez ce que vous avez modifié ou ajouté"></textarea>
                        <InputError class="gf-error" :message="form.errors.message_complement" />
                    </div>
                </section>

                <section class="fs">
                    <header class="fs__head"><span class="fs__n">4</span><h2>Pièces à joindre</h2></header>
                    <p v-if="!typeSelectionne" class="fs__hint">Choisissez d'abord un type d'activité : la liste des pièces obligatoires en dépend.</p>
                    <p v-else-if="!typeSelectionne.documents_requis?.length" class="fs__hint">Aucune pièce n'est exigée pour ce type d'activité.</p>
                    <div v-else class="docs">
                        <div v-for="(doc, i) in typeSelectionne.documents_requis" :key="i" class="doc" :class="{ 'doc--ok': fourni(i, doc) }">
                            <Icon :name="fourni(i, doc) ? 'file-pdf' : 'file-dashed'" class="doc__icon" />
                            <div class="doc__body">
                                <strong>{{ doc }}</strong>
                                <small v-if="uploadedFiles['doc_' + i]">{{ uploadedFiles['doc_' + i].name }}</small>
                                <small v-else-if="docsExistants[doc]">Déjà fourni : <a :href="route('documents.show', docsExistants[doc].id)">{{ docsExistants[doc].nom_fichier }}</a></small>
                                <small v-else>PDF, JPG, PNG, Word ou OpenDocument, 20 Mo au maximum</small>
                                <InputError class="gf-error" :message="form.errors['doc_' + i]" />
                            </div>
                            <label class="btn-ghost btn--sm doc__btn">
                                <Icon :name="fourni(i, doc) ? 'arrows-clockwise' : 'upload-simple'" />
                                {{ fourni(i, doc) ? 'Remplacer' : 'Choisir' }}
                                <input :id="`doc_${i}`" type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.odt" @change="handleFileChange(i, $event)" />
                            </label>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="create-side">
                <div class="check">
                    <header class="check__head"><h2>Vérification du calendrier</h2></header>
                    <div class="check__body">
                        <div class="check__map">
                            <DashboardMap
                                ref="carte"
                                :sites="sites"
                                :etats="etatsSites"
                                :selected-id="form.site_id || null"
                                selectable
                                @select="(id) => (form.site_id = id)"
                            />
                        </div>
                        <ul v-if="etatsSites" class="legende" aria-label="Légende de la carte">
                            <li><span class="legende__ok"></span>Ouvert</li>
                            <li><span class="hatch"></span>Période sensible</li>
                            <li><span class="hatch hatch--block"></span>Bloquant</li>
                        </ul>

                        <p v-if="!siteSelectionne" class="fs__hint">Cliquez sur une aire protégée de la carte ou choisissez-la dans la liste.</p>
                        <template v-else>
                            <div class="fiche">
                                <span class="site-label">{{ siteSelectionne.nom }}</span>
                                <span v-if="surfaceSite" class="fiche__surface mono">≈ {{ surfaceSite }}</span>
                                <p v-if="siteSelectionne.description" class="fiche__desc">{{ siteSelectionne.description }}</p>
                                <button v-if="surfaceSite" type="button" class="btn-sm-edit fiche__3d" @click="carte?.voir3d(siteSelectionne.id)">
                                    <Icon name="mountains" />
                                    Voir le terrain en 3D
                                </button>
                            </div>

                            <div v-if="!form.date_debut" class="res res--none">
                                <Icon name="calendar-blank" />
                                <div><strong>Dates à compléter</strong>Choisissez l'arrivée et le départ pour vérifier le calendrier du site.</div>
                            </div>
                            <div v-else-if="bloquant" class="res res--block" role="alert">
                                <Icon name="prohibit" />
                                <div><strong>Envoi impossible sur ces dates</strong>Une période bloquante couvre tout ou partie du séjour. Choisissez d'autres dates.</div>
                            </div>
                            <div v-else-if="chevauchements.length" class="res res--warn" role="alert">
                                <Icon name="warning" />
                                <div><strong>Période sensible sur ces dates</strong>La demande reste possible. Elle sera signalée au validateur et les précautions figureront au cahier des charges.</div>
                            </div>
                            <div v-else class="res res--ok">
                                <Icon name="check-circle" />
                                <div><strong>Aucune période sensible</strong>Le site est ouvert sur ces dates.</div>
                            </div>

                            <div class="periodes">
                                <p class="eyebrow">Périodes sensibles du site</p>
                                <ul v-if="periodesSite.length" class="periodes__list">
                                    <li v-for="p in periodesSite" :key="p.id" class="periodes__item" :class="{ 'periodes__item--hit': chevauche(p) }">
                                        <span class="hatch" :class="{ 'hatch--block': p.niveau_restriction === 'bloquant' }"></span>
                                        <span class="periodes__text">
                                            <span class="mono periodes__dates">{{ formatDate(p.date_debut) }} → {{ formatDate(p.date_fin) }}</span>
                                            <span>{{ p.description }}</span>
                                        </span>
                                        <span class="periodes__lvl" :class="{ 'periodes__lvl--block': p.niveau_restriction === 'bloquant' }">{{ p.niveau_restriction === 'bloquant' ? 'Bloquant' : 'Avertissement' }}</span>
                                    </li>
                                </ul>
                                <p v-else class="fs__hint">Aucune restriction prévue sur ce site.</p>
                            </div>
                        </template>
                    </div>
                </div>
            </aside>

            <div class="create-actions">
                <span class="create-actions__msg">{{ bloquant ? 'Ces dates tombent pendant une période bloquante.' : '' }}</span>
                <button v-if="!aCompleter" type="button" class="btn-ghost" :disabled="form.processing" @click="envoyer('brouillon')">Enregistrer le brouillon</button>
                <button type="submit" :disabled="form.processing || bloquant" class="btn-accent">
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
.fs__hint { font-size: 13.5px; color: var(--ink-3); }

.gf-error-banner { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: var(--signal-soft); border: 1px solid var(--signal-line); border-radius: var(--r); font-size: 13.5px; color: var(--ink); }
.gf-error-banner svg { width: 20px; height: 20px; color: var(--signal); flex-shrink: 0; }

.docs { display: grid; gap: 8px; }
.doc { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 10px 12px; border: 1px dashed var(--rule); border-radius: var(--r-sm); }
.doc--ok { border-style: solid; }
.doc__icon { width: 26px; height: 26px; color: var(--ink-3); }
.doc--ok .doc__icon { color: var(--forest); }
.doc__body { display: grid; gap: 2px; min-width: 0; }
.doc__body strong { font-weight: 600; font-size: 14px; }
.doc__body small { font-size: 12.5px; color: var(--ink-3); overflow-wrap: anywhere; }
.doc__body a { color: var(--water); font-weight: 600; }
.doc__btn { cursor: pointer; }
.doc__btn:focus-within { outline: 2px solid var(--water); outline-offset: 2px; }
.btn--sm { padding: 7px 10px; font-size: 13px; }

.create-side { min-width: 0; }
.check { position: sticky; top: 84px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); overflow: hidden; }
.check__head { padding: 14px 24px 4px; }
.check__head h2 { font-size: 15.5px; font-weight: 700; }
.check__body { display: grid; gap: 12px; padding: 14px; }
.check__map { height: 300px; }
.check__map :deep(.map-wrapper),
.check__map :deep(.map-inner) { min-height: 0; border-radius: var(--r-sm); }

.legende { display: flex; flex-wrap: wrap; gap: 6px 14px; margin: 0; padding: 0; list-style: none; font-size: 12px; color: var(--ink-2); }
.legende li { display: inline-flex; align-items: center; gap: 6px; }
.legende__ok { width: 14px; height: 14px; background: var(--forest-soft); border: 1px solid var(--forest); }

.fiche { display: grid; gap: 4px; }
.fiche__surface { font-size: 12px; color: var(--ink-3); }
.fiche__3d { justify-self: start; margin-top: 4px; }
.fiche__3d svg { width: 15px; height: 15px; }
.fiche__desc { font-size: 13px; color: var(--ink-2); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

.res { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: var(--r-sm); font-size: 13.5px; color: var(--ink-2); }
.res strong { display: block; color: var(--ink); }
.res svg { width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px; }
.res--ok { background: var(--forest-soft); }
.res--ok svg { color: var(--forest); }
.res--warn { background: var(--ochre-soft); }
.res--warn svg { color: var(--ochre); }
.res--block { background: var(--signal-soft); }
.res--block svg { color: var(--signal); }
.res--none { background: var(--sunk); }
.res--none svg { color: var(--ink-3); }

.periodes { display: grid; gap: 8px; padding-top: 4px; }
.periodes__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.periodes__item { display: grid; grid-template-columns: 14px minmax(0, 1fr) auto; gap: 10px; align-items: start; padding: 8px 10px; border: 1px solid var(--rule-soft); border-radius: var(--r-sm); font-size: 12.5px; color: var(--ink-2); }
.periodes__item .hatch { margin-top: 2px; }
.periodes__item--hit { border-color: var(--ink-3); background: var(--paper); }
.periodes__text { display: grid; gap: 2px; min-width: 0; }
.periodes__dates { font-size: 12px; color: var(--ink); }
.periodes__lvl { font: 600 10.5px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ochre); }
.periodes__lvl--block { color: var(--signal); }

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
.create-actions__msg { margin-right: auto; font-size: 13px; font-weight: 600; color: var(--signal); }

.complement-box { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 14px; margin-bottom: 24px; padding: 16px 24px; background: var(--ochre-soft); border: 1px solid var(--ochre-line); border-radius: var(--r); }
.complement-box__icon { width: 24px; height: 24px; color: var(--ochre); }
.complement-box__tag { font: 700 11px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ochre); }
.complement-box__quote { display: block; margin-top: 6px; font-style: italic; quotes: "« " " »"; }
.complement-box__text { margin-top: 4px; font-size: 13.5px; color: var(--ink-2); }
</style>
