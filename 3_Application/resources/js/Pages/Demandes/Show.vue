<script setup>
import BoutonDocument from '@/Components/BoutonDocument.vue';
import Icon from '@/Components/Icon.vue';
import PanneauAnalyse from '@/Components/PanneauAnalyse.vue';
import StatutSteps from '@/Components/StatutSteps.vue';
import TimelineDemande from '@/Components/TimelineDemande.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { statutBadgeClass, formatDate } from '@/utils/statut.js';

const props = defineProps({
    demande: Object,
    complement: { type: Object, default: null },
    delaiHabituel: { type: Number, default: null },
    canEdit: Boolean,
    canDelete: Boolean,
    canRetirer: Boolean,
});

const code = computed(() => props.demande.statut.code);
const reference = computed(() => props.demande.reference);
const docsRequis = computed(() => props.demande.type_demande?.documents_requis ?? []);
const docsManquants = computed(() => docsRequis.value
    .filter(r => !props.demande.documents.some(d => d.document_requis_id === r.id))
    .map(r => r.libelle));

const confirmDelete = ref(false);
const deleting = ref(false);
const supprimer = () => {
    deleting.value = true;
    router.delete(route('demandes.destroy', props.demande.id), { onFinish: () => (deleting.value = false) });
};

// Annulation d'une demande envoyée (statut « Retirée »), avec un motif facultatif
const confirmRetrait = ref(false);
const retrait = useForm({ motif: '' });
const retirer = () => retrait.post(route('demandes.retirer', props.demande.id), {
    preserveScroll: true,
    onSuccess: () => { confirmRetrait.value = false; retrait.reset(); },
});
</script>

<template>
    <Head :title="`Demande ${reference}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="head">
                <Link :href="route('demandes.index')" class="back-link">
                    <Icon name="arrow-left" />
                    Mes demandes
                </Link>
                <div class="head__ref">
                    <span class="mono">{{ reference }}</span>
                    <span :class="statutBadgeClass(code)">{{ demande.statut.libelle }}</span>
                </div>
            </div>
        </template>

        <div class="intro">
            <h1 class="intro__title">{{ demande.titre || demande.type_demande.libelle }} · {{ demande.site.nom }}</h1>
            <StatutSteps :code="code" large />
        </div>

        <section v-if="code === 'a_completer'" class="callout callout--att">
            <Icon name="pencil-simple-line" class="callout__icon" />
            <div class="callout__body">
                <span class="callout__tag">Complément demandé</span>
                <q v-if="complement?.commentaire">{{ complement.commentaire }}</q>
                <p>Modifiez votre dossier puis renvoyez-le : il repartira directement en analyse.</p>
            </div>
            <Link :href="route('demandes.edit', demande.id)" class="btn-accent">Compléter le dossier</Link>
        </section>

        <section v-else-if="code === 'brouillon'" class="callout callout--draft">
            <Icon name="file-dashed" class="callout__icon" />
            <div class="callout__body">
                <span class="callout__tag">Brouillon non envoyé</span>
                <p>
                    Ce dossier n'est visible que par vous.
                    <template v-if="docsManquants.length"> Pièces encore manquantes : {{ docsManquants.join(', ') }}.</template>
                </p>
            </div>
            <Link :href="route('demandes.edit', demande.id)" class="btn-accent">Reprendre le brouillon</Link>
        </section>

        <section v-else-if="code === 'retiree'" class="callout callout--retire">
            <Icon name="x-circle" class="callout__icon" />
            <div class="callout__body">
                <span class="callout__tag">Demande annulée</span>
                <p>Vous avez annulé cette demande : elle ne sera pas examinée. Pour reprendre le projet, préparez une nouvelle demande.</p>
            </div>
            <Link :href="route('demandes.create')" class="btn-ghost"><Icon name="plus" />Nouvelle demande</Link>
        </section>

        <section v-else-if="code === 'validee'" class="callout callout--ok">
            <Icon name="seal-check" class="callout__icon" />
            <div class="callout__body">
                <span class="callout__tag">Demande validée</span>
                <p>Présentez l'autorisation à l'arrivée dans l'aire protégée et respectez le cahier des charges. La convention de collaboration est à signer avec Fanamby.</p>
            </div>
            <div class="btn-row">
                <BoutonDocument class="btn-accent" :url="route('demandes.pdf.autorisation', demande.id)">Autorisation</BoutonDocument>
                <BoutonDocument class="btn-ghost" :url="route('demandes.pdf.cahier-charges', demande.id)">Cahier des charges</BoutonDocument>
                <BoutonDocument class="btn-ghost" :url="route('demandes.pdf.convention', demande.id)">Convention de collaboration</BoutonDocument>
            </div>
        </section>

        <div class="show-layout">
            <div class="stack">
                <dl class="meta">
                    <div><dt>Aire protégée</dt><dd>{{ demande.site.nom }}</dd></div>
                    <div><dt>Type de projet</dt><dd>{{ demande.type_demande.libelle }}<template v-if="demande.type_precision"> ({{ demande.type_precision }})</template></dd></div>
                    <div><dt>Arrivée sur le terrain</dt><dd class="mono">{{ demande.date_arrivee ? formatDate(demande.date_arrivee) : 'À définir' }}</dd></div>
                    <div>
                        <dt>Activités</dt>
                        <dd class="mono">
                            <template v-if="demande.date_debut">{{ formatDate(demande.date_debut) }} → {{ formatDate(demande.date_fin) }}</template>
                            <template v-else>À définir</template>
                        </dd>
                    </div>
                    <div><dt>Équipe</dt><dd>{{ demande.equipe_nombre }} personne{{ demande.equipe_nombre > 1 ? 's' : '' }}</dd></div>
                    <div><dt>Déposée le</dt><dd class="mono">{{ demande.date_soumission ? formatDate(demande.date_soumission) : '—' }}</dd></div>
                </dl>

                <section class="panel">
                    <header class="panel__head"><h2>Résumé du synopsis</h2></header>
                    <p class="panel__text">{{ demande.description || '—' }}</p>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2>Objectif(s) principal(aux) du projet</h2></header>
                    <p class="panel__text">{{ demande.objectifs || '—' }}</p>
                </section>

                <!-- Un brouillon n'est analysé qu'à son envoi -->
                <section v-if="demande.date_soumission" class="panel">
                    <header class="panel__head">
                        <h2>Analyse de la demande</h2>
                        <small>Faite automatiquement à l'envoi du dossier</small>
                    </header>
                    <PanneauAnalyse :contraintes="demande.resultats_analyse ?? []" />
                </section>

                <section class="panel">
                    <header class="panel__head">
                        <h2>Pièces du dossier</h2>
                        <span class="panel__fin">
                            <small v-if="docsRequis.length">{{ docsRequis.length - docsManquants.length }} sur {{ docsRequis.length }} fournies</small>
                            <a v-if="demande.documents.length > 1" :href="route('demandes.pieces.zip', demande.id)" class="link"><Icon name="download-simple" />Tout télécharger</a>
                        </span>
                    </header>
                    <ul v-if="demande.documents.length || docsManquants.length" class="docs">
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
                    <p v-else class="panel__text muted">Aucune pièce jointe pour le moment.</p>
                </section>
            </div>

            <aside class="stack side">
                <section class="panel">
                    <header class="panel__head"><h2>Suivi du dossier</h2></header>
                    <TimelineDemande :demande="demande" vue-demandeur :delai-habituel="delaiHabituel" />
                </section>

                <section v-if="canEdit || canDelete || code !== 'brouillon'" class="panel">
                    <header class="panel__head"><h2>Actions</h2></header>
                    <div class="side-actions">
                        <BoutonDocument v-if="code !== 'brouillon'" class="btn-ghost" :url="route('demandes.pdf.formulaire', demande.id)">
                            Formulaire de demande
                        </BoutonDocument>
                        <Link v-if="canEdit" :href="route('demandes.edit', demande.id)" class="btn-ghost">
                            <Icon name="pencil-simple" />
                            {{ code === 'a_completer' ? 'Compléter le dossier' : 'Modifier le brouillon' }}
                        </Link>
                        <template v-if="canDelete">
                            <button v-if="!confirmDelete" type="button" class="btn-sm-delete del-btn" @click="confirmDelete = true"><Icon name="trash" />Supprimer le brouillon</button>
                            <div v-else class="del-confirm">
                                <p>Supprimer définitivement ce brouillon et ses pièces jointes ?</p>
                                <div class="del-confirm__btns">
                                    <button type="button" class="btn-ghost" @click="confirmDelete = false">Annuler</button>
                                    <button type="button" class="btn-sm-delete del-btn" :disabled="deleting" @click="supprimer">Oui, supprimer</button>
                                </div>
                            </div>
                        </template>
                        <template v-if="canRetirer">
                            <button v-if="!confirmRetrait" type="button" class="btn-sm-delete del-btn" @click="confirmRetrait = true"><Icon name="x-circle" />Annuler la demande</button>
                            <form v-else class="del-confirm" @submit.prevent="retirer">
                                <p>Annuler définitivement cette demande ? Elle passera au statut « Retirée » et ne sera plus examinée.</p>
                                <label class="del-confirm__label" for="motif-retrait">Motif (facultatif, transmis à l'équipe)</label>
                                <textarea id="motif-retrait" v-model="retrait.motif" class="gf-textarea" rows="2" maxlength="1000"></textarea>
                                <p v-if="retrait.errors.motif" class="gf-error">{{ retrait.errors.motif }}</p>
                                <div class="del-confirm__btns">
                                    <button type="button" class="btn-ghost" @click="confirmRetrait = false">Garder la demande</button>
                                    <button type="submit" class="btn-sm-delete del-btn" :disabled="retrait.processing">Oui, annuler</button>
                                </div>
                            </form>
                        </template>
                    </div>
                </section>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.head { display: grid; gap: 6px; }
.head__ref { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: 14px; color: var(--ink-2); }
.intro { display: grid; gap: 12px; margin-bottom: 24px; }
.intro__title { font-size: clamp(20px, 2vw, 26px); font-weight: 700; line-height: 1.15; max-width: 48ch; }

.show-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 24px; align-items: start; }
@media (max-width: 960px) { .show-layout { grid-template-columns: minmax(0, 1fr); } }
.stack { display: grid; gap: 16px; min-width: 0; }
.side { position: sticky; top: 84px; }
@media (max-width: 960px) { .side { position: static; } }

.callout { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 12px 16px; align-items: start; margin-bottom: 24px; padding: 18px 20px; border: 1px solid; border-radius: var(--r); }
.callout__icon { width: 24px; height: 24px; }
.callout__body { display: grid; gap: 6px; min-width: 0; }
.callout__body p { font-size: 14px; color: var(--ink-2); }
.callout__body q { font-style: italic; quotes: "« " " »"; max-width: 70ch; }
.callout__tag { font: 700 11px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; }
.callout--att { background: var(--ochre-soft); border-color: var(--ochre-line); }
.callout--att .callout__icon, .callout--att .callout__tag { color: var(--ochre); }
.callout--draft { background: transparent; border-style: dashed; border-color: var(--ink-3); }
.callout--draft .callout__icon, .callout--draft .callout__tag { color: var(--ink-2); }
.callout--ok { background: var(--forest-soft); border-color: var(--forest-line); }
.callout--ok .callout__icon, .callout--ok .callout__tag { color: var(--forest); }
.callout--retire { background: var(--sunk); border-color: var(--rule); }
.callout--retire .callout__icon, .callout--retire .callout__tag { color: var(--ink-2); }
.btn-row { display: flex; flex-wrap: wrap; gap: 8px; }
@media (max-width: 640px) {
    .callout { grid-template-columns: auto minmax(0, 1fr); }
    .callout > .btn-accent, .callout > .btn-ghost, .callout > .btn-row { grid-column: 1 / -1; justify-self: start; }
}

.meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px 20px; margin: 0; padding: 16px 24px; background: var(--sunk); border-radius: var(--r); }
.meta div { display: grid; gap: 3px; align-content: start; }
.meta dt { font: 600 10.5px/1.2 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.meta dd { margin: 0; font-weight: 500; font-size: 14px; }

.panel { background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); min-width: 0; }
.panel__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px 12px; padding: 16px 24px 8px; }
.panel__head h2 { font-size: 15.5px; font-weight: 700; }
.panel__head small { font-size: 12.5px; color: var(--ink-3); }
.panel__fin { display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 4px 14px; }
.panel__fin .link { display: inline-flex; align-items: center; gap: 5px; }
.panel__fin .link svg { width: 14px; height: 14px; }
.panel__text { padding: 16px 24px; font-size: 14px; color: var(--ink-2); line-height: 1.6; white-space: pre-line; max-width: 75ch; }
.muted { color: var(--ink-3); }
.link { font-size: 13px; font-weight: 600; color: var(--water); text-decoration: none; }
.link:hover { text-decoration: underline; }

.docs { list-style: none; margin: 0; padding: 0; }
.docs li { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 10px 24px; border-bottom: 1px solid var(--rule-soft); }
.docs li:last-child { border-bottom: 0; }
.docs__icon { width: 26px; height: 26px; color: var(--ink-2); }
.docs__body { display: grid; gap: 2px; min-width: 0; }
.docs__body strong { font-weight: 500; font-size: 14px; }
.docs__body small { font-size: 12.5px; color: var(--ink-3); overflow-wrap: anywhere; }
.docs__missing .docs__icon, .docs__missing strong { color: var(--ochre); }

.side-actions { display: grid; gap: 10px; padding: 16px 24px; }
/* Même gabarit que .btn-ghost (« Modifier le brouillon », « Annuler ») */
.del-btn { justify-content: center; gap: 8px; min-height: 36px; padding: 8px 14px; border-radius: var(--r-sm); font: 600 14px/1 var(--f-body); white-space: nowrap; }
.del-btn :deep(svg) { width: 16px; height: 16px; }
@media (pointer: coarse) { .del-btn { min-height: 40px; } }
.del-confirm { padding: 12px; background: var(--signal-soft); border: 1px solid var(--signal-line); border-radius: var(--r-sm); }
.del-confirm p { margin-bottom: 10px; font-size: 13px; color: var(--ink); }
.del-confirm__btns { display: flex; justify-content: flex-end; gap: 8px; }
.del-confirm__label { display: block; margin-bottom: 4px; font-size: 12.5px; font-weight: 600; color: var(--ink-2); }
.del-confirm .gf-textarea { margin-bottom: 10px; font-size: 13.5px; }
</style>
