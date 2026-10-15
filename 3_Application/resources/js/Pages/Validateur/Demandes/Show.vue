<script setup>
import Icon from '@/Components/Icon.vue';
import StatutSteps from '@/Components/StatutSteps.vue';
import TimelineDemande from '@/Components/TimelineDemande.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { openAuthenticatedPdf } from '@/utils/openPdf.js';
import { statutBadgeClass, formatDate, joursDepuis } from '@/utils/statut.js';
import { computed } from 'vue';

const props = defineProps({
    demande: Object,
    statuts: Array,
    viewAllDemandes: { type: Boolean, default: false },
    canDecideOnDemande: { type: Boolean, default: true },
    statutLocked: { type: Boolean, default: false },
    delaiHabituel: { type: Number, default: null },
});

const CHOIX = {
    en_analyse: { label: 'Prendre en charge', sub: 'Passe le dossier en analyse et prévient le demandeur', icon: 'stamp' },
    a_completer: { label: 'Demander un complément', sub: 'Le dossier retourne au demandeur avec votre commentaire', icon: 'pencil-simple-line' },
    validee: { label: 'Valider la demande', sub: 'Génère l\'autorisation et le cahier des charges en PDF', icon: 'seal-check' },
    rejetee: { label: 'Rejeter la demande', sub: 'Décision définitive, le dossier est clos', icon: 'prohibit' },
    soumise: { label: 'Remettre en file', sub: 'Le dossier repasse au statut soumis', icon: 'arrow-left' },
};

const form = useForm({
    statut_id: props.demande.statut_id,
    commentaire: '',
});

const submit = () => {
    form.post(route('validateur.demandes.update-statut', props.demande.id), {
        onSuccess: () => form.reset('commentaire'),
    });
};

const openPdf = async (url, filename) => {
    try {
        await openAuthenticatedPdf(url, filename);
    } catch (e) {
        alert(e.message || 'Erreur lors de l\'ouverture du PDF.');
    }
};

const reference = computed(() => props.demande.reference);
const code = computed(() => props.demande.statut?.code);
const options = computed(() => props.statuts.filter((s) => s.id !== props.demande.statut_id));
const statutChoisi = computed(() => props.statuts.find((s) => s.id === form.statut_id)?.code);
const aChoisi = computed(() => form.statut_id !== props.demande.statut_id);

const docsRequis = computed(() => props.demande.type_demande?.documents_requis ?? []);
const docsManquants = computed(() => docsRequis.value
    .filter((r) => !(props.demande.documents ?? []).some((d) => d.document_requis_id === r.id))
    .map((r) => r.libelle));
const attente = computed(() => (['soumise', 'en_analyse'].includes(code.value) ? joursDepuis(props.demande.date_soumission) : null));

const boutonLabel = computed(() => {
    if (form.processing) return 'Enregistrement…';
    return {
        validee: 'Valider et générer l\'autorisation',
        a_completer: 'Envoyer la demande de complément',
        rejetee: 'Rejeter la demande',
        en_analyse: 'Prendre en charge',
        soumise: 'Remettre en file',
    }[statutChoisi.value] ?? 'Choisissez une décision';
});
const boutonClasse = computed(() => ({ a_completer: 'btn-warn', rejetee: 'btn-danger' }[statutChoisi.value] ?? 'btn-accent'));
</script>

<template>
    <Head :title="`Dossier ${reference}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="head">
                <Link :href="route('validateur.demandes.index')" class="back-link">
                    <Icon name="arrow-left" />
                    {{ viewAllDemandes ? 'Toutes les demandes' : 'Dossiers à valider' }}
                </Link>
                <div class="head__ref">
                    <span class="mono">{{ reference }}</span>
                    <span v-if="demande.statut" :class="statutBadgeClass(code)">{{ demande.statut.libelle }}</span>
                    <span v-if="attente !== null && attente > 7" class="late"><Icon name="clock" />{{ attente }} jours sans décision</span>
                </div>
            </div>
        </template>

        <div class="intro">
            <h1 class="intro__title">{{ demande.type_demande.libelle }} · {{ demande.site.nom }}</h1>
            <StatutSteps :code="code" large />
        </div>

        <div class="show-layout">
            <div class="stack">
                <dl class="meta">
                    <div><dt>Demandeur</dt><dd>{{ demande.user.name }}<small>{{ demande.user.email }}</small></dd></div>
                    <div><dt>Site</dt><dd>{{ demande.site.nom }}</dd></div>
                    <div><dt>Séjour</dt><dd class="mono">{{ formatDate(demande.date_debut) }} → {{ formatDate(demande.date_fin) }}</dd></div>
                    <div><dt>Équipe</dt><dd>{{ demande.equipe_nombre }} personne{{ demande.equipe_nombre > 1 ? 's' : '' }}</dd></div>
                    <div><dt>Déposée le</dt><dd class="mono">{{ formatDate(demande.date_soumission) }}</dd></div>
                </dl>

                <template v-if="demande.resultats_analyse?.length">
                    <div v-for="(a, i) in demande.resultats_analyse" :key="i" class="conflict">
                        <div class="conflict__band"></div>
                        <div class="conflict__body">
                            <span class="conflict__tag"><Icon name="warning" />Période sensible</span>
                            <p>{{ a.message }}</p>
                        </div>
                    </div>
                </template>
                <div v-else class="res-ok">
                    <Icon name="check-circle" />
                    <span>Aucune période sensible ne chevauche ces dates.</span>
                </div>

                <section class="panel">
                    <header class="panel__head"><h2>Description du projet</h2></header>
                    <p class="panel__text">{{ demande.description }}</p>
                </section>

                <section class="panel">
                    <header class="panel__head">
                        <h2>Pièces du dossier</h2>
                        <small v-if="docsRequis.length">{{ docsRequis.length - docsManquants.length }} sur {{ docsRequis.length }} fournies</small>
                    </header>
                    <ul v-if="demande.documents?.length || docsManquants.length" class="docs">
                        <li v-for="doc in demande.documents" :key="doc.id">
                            <Icon name="file-pdf" class="docs__icon" />
                            <span class="docs__body">
                                <strong>{{ doc.type_document }}</strong>
                                <small>{{ doc.nom_fichier }}</small>
                            </span>
                            <a :href="route('documents.show', doc.id)" class="link">Télécharger</a>
                        </li>
                        <li v-for="n in docsManquants" :key="`m-${n}`" class="docs__missing">
                            <Icon name="file-dashed" class="docs__icon" />
                            <span class="docs__body">
                                <strong>{{ n }}</strong>
                                <small>Pièce obligatoire non fournie</small>
                            </span>
                            <span></span>
                        </li>
                    </ul>
                    <p v-else class="panel__text muted">Aucune pièce jointe enregistrée pour cette demande.</p>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2>Suivi du dossier</h2></header>
                    <TimelineDemande :demande="demande" :delai-habituel="delaiHabituel" />
                </section>
            </div>

            <aside class="side">
                <section v-if="statutLocked" class="panel notice notice--closed">
                    <header class="panel__head"><h2>{{ code === 'validee' ? 'Demande validée' : 'Dossier clôturé' }}</h2></header>
                    <div class="panel__body">
                        <p v-if="code === 'rejetee'">Cette demande a été rejetée. Aucune modification de statut n'est possible.</p>
                        <p v-else-if="code === 'validee'">Le dossier est définitivement clôturé. Les documents officiels sont disponibles.</p>
                        <p v-else>Ce dossier ne peut plus être modifié.</p>
                        <div v-if="code === 'validee'" class="btn-col">
                            <button type="button" class="btn-accent" @click="openPdf(route('demandes.pdf.autorisation', demande.id), `autorisation_${demande.id}.pdf`)"><Icon name="file-pdf" />Autorisation</button>
                            <button type="button" class="btn-ghost" @click="openPdf(route('demandes.pdf.cahier-charges', demande.id), `cahier_des_charges_${demande.id}.pdf`)"><Icon name="file-pdf" />Cahier des charges</button>
                        </div>
                    </div>
                </section>

                <section v-else-if="!canDecideOnDemande" class="panel notice">
                    <header class="panel__head"><h2>Décision indisponible</h2></header>
                    <p class="panel__body">Vous êtes le demandeur de ce dossier. Un autre administrateur ou un validateur rattaché au site doit l'examiner.</p>
                </section>

                <form v-else class="panel decision" @submit.prevent="submit">
                    <header class="panel__head">
                        <h2>{{ viewAllDemandes ? 'Décision administrateur' : 'Décision' }}</h2>
                        <small>Le demandeur reçoit une notification</small>
                    </header>
                    <div class="panel__body">
                        <p v-if="code === 'a_completer'" class="hint hint--att">En attente du demandeur. Le dossier reviendra en analyse dès qu'il sera renvoyé.</p>
                        <p v-if="viewAllDemandes" class="hint">Votre décision sera identifiée comme décision administrateur dans l'historique.</p>

                        <fieldset class="choices">
                            <legend class="sr-only">Choisir une décision</legend>
                            <label v-for="s in options" :key="s.id" class="choice" :class="`choice--${s.code}`">
                                <input v-model="form.statut_id" type="radio" name="statut" :value="s.id" />
                                <Icon :name="CHOIX[s.code]?.icon ?? 'stamp'" class="choice__icon" />
                                <span>
                                    <strong>{{ CHOIX[s.code]?.label ?? s.libelle }}</strong>
                                    <small>{{ CHOIX[s.code]?.sub ?? '' }}</small>
                                </span>
                            </label>
                        </fieldset>
                        <p v-if="form.errors.statut_id" class="gf-error">{{ form.errors.statut_id }}</p>
                        <p v-if="statutChoisi === 'validee' && docsManquants.length" class="hint hint--att">
                            Attention : {{ docsManquants.length }} pièce{{ docsManquants.length > 1 ? 's' : '' }} obligatoire{{ docsManquants.length > 1 ? 's' : '' }} manque{{ docsManquants.length > 1 ? 'nt' : '' }} au dossier.
                        </p>

                        <div>
                            <label class="gf-label" for="commentaire">{{ statutChoisi === 'a_completer' ? 'Compléments attendus' : 'Commentaire pour le demandeur' }}</label>
                            <textarea id="commentaire" v-model="form.commentaire" rows="4" class="gf-textarea" required :placeholder="statutChoisi === 'a_completer' ? 'Pièces ou précisions à fournir' : 'Motif de la décision'"></textarea>
                            <p v-if="form.errors.commentaire" class="gf-error">{{ form.errors.commentaire }}</p>
                        </div>

                        <button type="submit" :disabled="form.processing || !aChoisi" :class="boutonClasse" class="decision__submit">
                            <Icon v-if="form.processing" name="circle-notch" class="spin" />
                            {{ boutonLabel }}
                        </button>
                    </div>
                </form>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.head { display: grid; gap: 6px; }
.head__ref { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: 14px; color: var(--ink-2); }
.late { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 600; color: var(--signal); }
.late svg { width: 14px; height: 14px; }
.intro { display: grid; gap: 12px; margin-bottom: 24px; }
.intro__title { font-size: clamp(20px, 2vw, 26px); font-weight: 700; line-height: 1.15; max-width: 48ch; }

.show-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start; }
@media (max-width: 1000px) { .show-layout { grid-template-columns: minmax(0, 1fr); } }
.stack { display: grid; gap: 16px; min-width: 0; }
.side { position: sticky; top: 84px; }
@media (max-width: 1000px) { .side { position: static; } }

.meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px 20px; margin: 0; padding: 16px 24px; background: var(--sunk); border-radius: var(--r); }
.meta div { display: grid; gap: 3px; align-content: start; min-width: 0; }
.meta dt { font: 600 10.5px/1.2 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.meta dd { margin: 0; font-weight: 500; font-size: 14px; overflow-wrap: anywhere; }
.meta dd small { display: block; font-weight: 400; font-size: 12.5px; color: var(--ink-3); }

.panel { background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); min-width: 0; }
.panel__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px 12px; padding: 16px 24px 8px; }
.panel__head h2 { font-size: 15.5px; font-weight: 700; }
.panel__head small { font-size: 12.5px; color: var(--ink-3); }
.panel__text { padding: 16px 24px; font-size: 14px; color: var(--ink-2); line-height: 1.6; white-space: pre-line; max-width: 75ch; }
.panel__body { display: grid; gap: 14px; padding: 16px 24px; font-size: 14px; color: var(--ink-2); }
.muted { color: var(--ink-3); }
.link { font-size: 13px; font-weight: 600; color: var(--water); text-decoration: none; }
.link:hover { text-decoration: underline; }

.conflict { border: 1px solid var(--ochre); border-radius: var(--r-sm); overflow: hidden; background: var(--surface); }
.conflict__band { height: 10px; background: repeating-linear-gradient(45deg, var(--ochre) 0 2px, transparent 2px 7px); }
.conflict__body { display: grid; gap: 6px; padding: 14px 16px; }
.conflict__tag { display: inline-flex; align-items: center; gap: 6px; font: 700 11px/1 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ochre); }
.conflict__tag svg { width: 14px; height: 14px; }
.conflict__body p { font-size: 14px; color: var(--ink-2); }
.res-ok { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: var(--forest-soft); border-radius: var(--r-sm); font-size: 14px; }
.res-ok svg { width: 20px; height: 20px; color: var(--forest); flex-shrink: 0; }

.docs { list-style: none; margin: 0; padding: 0; }
.docs li { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 10px 24px; border-bottom: 1px solid var(--rule-soft); }
.docs li:last-child { border-bottom: 0; }
.docs__icon { width: 26px; height: 26px; color: var(--ink-2); }
.docs__body { display: grid; gap: 2px; min-width: 0; }
.docs__body strong { font-weight: 500; font-size: 14px; }
.docs__body small { font-size: 12.5px; color: var(--ink-3); overflow-wrap: anywhere; }
.docs__missing .docs__icon, .docs__missing strong { color: var(--ochre); }

.notice { background: var(--ochre-soft); border-color: var(--ochre-line); }
.notice--closed { background: var(--surface); border-color: var(--rule); }
.btn-col { display: grid; gap: 8px; }

.hint { font-size: 13px; color: var(--ink-3); }
.hint--att { color: var(--ochre); font-weight: 600; }
.choices { display: grid; gap: 8px; margin: 0; padding: 0; border: 0; }
.choice { display: grid; grid-template-columns: auto auto minmax(0, 1fr); gap: 10px; align-items: start; padding: 11px 12px; border: 1px solid var(--rule); border-radius: var(--r-sm); cursor: pointer; }
.choice:has(input:checked) { border-color: var(--ink); background: var(--paper); }
.choice input { margin-top: 3px; accent-color: var(--forest); }
.choice__icon { width: 19px; height: 19px; margin-top: 1px; color: var(--ink-3); }
.choice--validee:has(:checked) .choice__icon { color: var(--forest); }
.choice--a_completer:has(:checked) .choice__icon { color: var(--ochre); }
.choice--rejetee:has(:checked) .choice__icon { color: var(--signal); }
.choice--en_analyse:has(:checked) .choice__icon { color: var(--water); }
.choice strong { display: block; font-weight: 600; font-size: 14px; color: var(--ink); }
.choice small { font-size: 12.5px; color: var(--ink-3); }
.decision__submit { width: 100%; }
.btn-warn, .btn-danger {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 10px 16px; border-radius: var(--r-sm); font: 600 14px/1 var(--f-body); color: #fff; cursor: pointer;
}
.btn-warn { background: var(--ochre); border: 1px solid var(--ochre); }
.btn-danger { background: var(--signal); border: 1px solid var(--signal); }
.btn-warn:disabled, .btn-danger:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
