<script setup>
import Icon from '@/Components/Icon.vue';
import ApercuAutorisation from '@/Components/Apercu/ApercuAutorisation.vue';
import ApercuCahierCharges from '@/Components/Apercu/ApercuCahierCharges.vue';
import ApercuConvention from '@/Components/Apercu/ApercuConvention.vue';
import CadreApercu from '@/Components/Apercu/CadreApercu.vue';
import { useForm } from '@inertiajs/vue3';
import { contraintesRelevees } from '@/utils/analyse.js';
import { montant, redevance } from '@/utils/documents.js';
import { FORMATS, ouvrirDocument } from '@/utils/ouvrirDocument.js';
import { formatDate } from '@/utils/statut.js';
import { computed, ref, watch } from 'vue';

// Autorisation, cahier des charges et convention générés : les données du dossier sont fixes,
// les textes sont modifiables jusqu'à la validation signée. L'aperçu à côté suit la saisie.
const props = defineProps({
    demande: { type: Object, required: true },
    documents: { type: Object, required: true },
    // Observateur : les documents en relecture se lisent, sans modification ni version Word
    lectureSeule: { type: Boolean, default: false },
});
const formats = computed(() => (props.lectureSeule ? FORMATS.filter((f) => f.cle === 'pdf') : FORMATS));

// Modifications non enregistrées : la validation attend qu'elles soient enregistrées.
const modifie = defineModel('modifie', { type: Boolean, default: false });

const ONGLETS = [
    { cle: 'autorisation', titre: 'Autorisation d\'accès', fichier: 'autorisation' },
    { cle: 'cahier_charges', titre: 'Cahier des charges', fichier: 'cahier_des_charges' },
    { cle: 'convention', titre: 'Convention de collaboration', fichier: 'convention_de_collaboration' },
];
const onglet = ref('autorisation');
const ongletActif = computed(() => ONGLETS.find((o) => o.cle === onglet.value));
// Contraintes de l'analyse imprimées dans le cahier des charges (sans les simples informations)
const alertes = computed(() => contraintesRelevees(props.demande.resultats_analyse));

const form = useForm({
    autorisation: { ...props.documents.contenu.autorisation },
    cahier_charges: { ...props.documents.contenu.cahier_charges },
    convention: { ...props.documents.contenu.convention },
});

// Article 4 de la convention : total calculé sur les jours d'activités pendant la saisie
const redevanceConvention = computed(() => redevance(props.demande, form.convention));

watch(() => form.isDirty, (v) => { modifie.value = v; }, { immediate: true });

const enregistrer = () => {
    form.put(route('validateur.demandes.documents.modifier', props.demande.id), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
};

const annuler = () => {
    if (confirm('Supprimer les documents générés ? Vos modifications seront perdues.')) {
        form.delete(route('validateur.demandes.documents.annuler', props.demande.id), { preserveScroll: true });
    }
};

// Format du document en cours de production (PDF ou Word), dans sa dernière version enregistrée
const telechargement = ref(null);
const telecharger = async (o, format) => {
    telechargement.value = format;
    try {
        await ouvrirDocument(route('validateur.demandes.documents.apercu', [props.demande.id, o.cle]), format);
    } catch (e) {
        alert(e.message || 'Erreur lors de l\'ouverture du document.');
    } finally {
        telechargement.value = null;
    }
};

const erreur = (champ) => form.errors[`${onglet.value}.${champ}`];

// Amène le validateur sur les documents (juste après leur génération, ou depuis le bloc Décision)
const bloc = ref(null);
const titre = ref(null);
const montrer = () => {
    const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    bloc.value?.scrollIntoView({ behavior: reduit ? 'auto' : 'smooth', block: 'start' });
    titre.value?.focus({ preventScroll: true });
};
defineExpose({ montrer });
</script>

<template>
    <section ref="bloc" class="panel docs-gen">
        <header class="panel__head">
            <h2 ref="titre" tabindex="-1">Documents à relire avant la signature</h2>
            <small>Générés par {{ documents.genere_par ?? 'un validateur' }} le {{ formatDate(documents.genere_le) }}</small>
        </header>

        <div class="panel__body">
            <p v-if="lectureSeule" class="hint">
                Documents en cours de relecture par le validateur, en lecture seule. Ils ne sont envoyés au demandeur qu'à la signature.
            </p>
            <p v-else class="hint">
                <strong>Rien n'est encore envoyé au demandeur</strong> : il ne recevra l'avis favorable et ces documents qu'à votre signature.
                Relisez les trois documents. Les textes encadrés sont modifiables et l'aperçu se met à jour pendant la saisie ;
                les informations du dossier (<Icon name="lock-simple" class="inline-icon" />) viennent de la demande et ne changent pas.
            </p>

            <div class="tabs" role="tablist">
                <button v-for="o in ONGLETS" :key="o.cle" type="button" role="tab" class="tab"
                    :class="{ 'tab--on': onglet === o.cle }" :aria-selected="onglet === o.cle" @click="onglet = o.cle">
                    <Icon name="file-text" />{{ o.titre }}
                </button>
            </div>

            <div class="edition">
                <fieldset :disabled="lectureSeule" class="lecture">
                <!-- Autorisation -->
                <div v-if="onglet === 'autorisation'" class="saisie">
                    <label class="field">
                        <span class="field__label">Introduction</span>
                        <textarea v-model="form.autorisation.introduction" rows="2" class="field__input"></textarea>
                        <span v-if="erreur('introduction')" class="gf-error">{{ erreur('introduction') }}</span>
                    </label>

                    <dl class="dossier">
                        <div><dt><Icon name="lock-simple" />Bénéficiaire</dt><dd>{{ demande.user.name }} ({{ demande.user.email }})</dd></div>
                        <div><dt><Icon name="lock-simple" />Aire protégée</dt><dd>{{ demande.site.nom }}</dd></div>
                        <div><dt><Icon name="lock-simple" />Type de projet</dt><dd>{{ demande.type_demande.libelle }}<template v-if="demande.type_precision"> ({{ demande.type_precision }})</template></dd></div>
                        <div><dt><Icon name="lock-simple" />Arrivée sur le terrain</dt><dd>Le {{ formatDate(demande.date_arrivee || demande.date_debut) }}</dd></div>
                        <div><dt><Icon name="lock-simple" />Période</dt><dd>Du {{ formatDate(demande.date_debut) }} au {{ formatDate(demande.date_fin) }}</dd></div>
                        <div><dt><Icon name="lock-simple" />Équipe</dt><dd>{{ demande.equipe_nombre }} personne(s)</dd></div>
                    </dl>

                    <label class="field">
                        <span class="field__label">Objet de la mission</span>
                        <textarea v-model="form.autorisation.objet" rows="5" class="field__input"></textarea>
                        <span v-if="erreur('objet')" class="gf-error">{{ erreur('objet') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Conditions particulières <small>facultatif, une par ligne</small></span>
                        <textarea v-model="form.autorisation.conditions" rows="3" class="field__input" placeholder="Ex. : accompagnement obligatoire par un guide local"></textarea>
                        <span v-if="erreur('conditions')" class="gf-error">{{ erreur('conditions') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Mention de réserve</span>
                        <textarea v-model="form.autorisation.reserve" rows="3" class="field__input"></textarea>
                        <span v-if="erreur('reserve')" class="gf-error">{{ erreur('reserve') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Signataire</span>
                        <input v-model="form.autorisation.signataire" type="text" class="field__input" />
                        <span v-if="erreur('signataire')" class="gf-error">{{ erreur('signataire') }}</span>
                    </label>
                </div>

                <!-- Cahier des charges -->
                <div v-else-if="onglet === 'cahier_charges'" class="saisie">
                    <p class="fixed-text">
                        <Icon name="lock-simple" />
                        <span>Annexe obligatoire à l'autorisation d'accès pour <strong>{{ demande.user.name }}</strong>
                            dans l'aire protégée de <strong>{{ demande.site.nom }}</strong>.</span>
                    </p>

                    <label class="field">
                        <span class="field__label">Règles à respecter <small>une par ligne</small></span>
                        <textarea v-model="form.cahier_charges.regles" rows="6" class="field__input" placeholder="Aucune règle : le document l'indiquera"></textarea>
                        <span v-if="erreur('regles')" class="gf-error">{{ erreur('regles') }}</span>
                    </label>

                    <div v-if="alertes.length" class="fixed-text fixed-text--warn">
                        <Icon name="lock-simple" />
                        <span>Avertissements de l'analyse repris automatiquement :
                            <span v-for="(a, i) in alertes" :key="i" class="ligne">{{ a.message }}</span>
                        </span>
                    </div>

                    <label class="field">
                        <span class="field__label">Engagement du demandeur</span>
                        <textarea v-model="form.cahier_charges.engagement" rows="6" class="field__input"></textarea>
                        <span v-if="erreur('engagement')" class="gf-error">{{ erreur('engagement') }}</span>
                    </label>
                </div>

                <!-- Convention de collaboration : un champ par article -->
                <div v-else class="saisie">
                    <label class="field">
                        <span class="field__label">Fanamby (première partie)</span>
                        <textarea v-model="form.convention.partie_fanamby" rows="4" class="field__input"></textarea>
                        <span v-if="erreur('partie_fanamby')" class="gf-error">{{ erreur('partie_fanamby') }}</span>
                    </label>

                    <dl class="dossier">
                        <div><dt><Icon name="lock-simple" />Bénéficiaire</dt><dd>{{ demande.user.name }} ({{ demande.user.email }})</dd></div>
                        <div><dt><Icon name="lock-simple" />Organisation</dt><dd>{{ [demande.user.fonction, demande.user.organisation].filter(Boolean).join(', ') || '—' }}</dd></div>
                        <div><dt><Icon name="lock-simple" />Coordonnées</dt><dd>{{ [demande.user.adresse, demande.user.telephone].filter(Boolean).join(' · ') || '—' }}</dd></div>
                        <div><dt><Icon name="lock-simple" />CIN / passeport</dt><dd>{{ demande.user.piece_identite || '—' }}</dd></div>
                        <div><dt><Icon name="lock-simple" />Arrivée sur le terrain</dt><dd>Le {{ formatDate(demande.date_arrivee || demande.date_debut) }}</dd></div>
                        <div><dt><Icon name="lock-simple" />Activités</dt><dd>Du {{ formatDate(demande.date_debut) }} au {{ formatDate(demande.date_fin) }} ({{ redevanceConvention.jours }} jour{{ redevanceConvention.jours > 1 ? 's' : '' }})</dd></div>
                        <div><dt><Icon name="lock-simple" />Équipe</dt><dd>{{ demande.equipe_nombre }} personne(s)</dd></div>
                    </dl>

                    <label class="field">
                        <span class="field__label">Article 1 – Objet de la convention</span>
                        <textarea v-model="form.convention.objet" rows="5" class="field__input"></textarea>
                        <span v-if="erreur('objet')" class="gf-error">{{ erreur('objet') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Article 2 – Avis favorable</span>
                        <textarea v-model="form.convention.avis" rows="4" class="field__input"></textarea>
                        <span v-if="erreur('avis')" class="gf-error">{{ erreur('avis') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Article 3 – Modalités des opérations <small>facultatif, une par ligne ; les dates viennent du dossier</small></span>
                        <textarea v-model="form.convention.modalites" rows="4" class="field__input"></textarea>
                        <span v-if="erreur('modalites')" class="gf-error">{{ erreur('modalites') }}</span>
                    </label>

                    <div class="field">
                        <label class="field__label" for="redevance-jour">Article 4 – Redevance par jour d'activités <small>en ariary</small></label>
                        <input id="redevance-jour" v-model="form.convention.redevance_jour" type="number" min="0" step="1" class="field__input field__input--court" placeholder="Ex. : 4000000" />
                        <span class="field__aide">
                            <template v-if="redevanceConvention.parJour === null">Vide : la convention indique que le montant sera communiqué par Fanamby. Saisissez 0 s'il n'y a pas de redevance.</template>
                            <template v-else-if="redevanceConvention.parJour === 0">Aucune redevance : la convention le précise.</template>
                            <template v-else>Soit {{ montant(redevanceConvention.total) }} Ariary pour {{ redevanceConvention.jours }} jour{{ redevanceConvention.jours > 1 ? 's' : '' }} d'activités.</template>
                        </span>
                        <span v-if="erreur('redevance_jour')" class="gf-error">{{ erreur('redevance_jour') }}</span>
                    </div>

                    <label class="field">
                        <span class="field__label">Article 4 – Modalités de paiement</span>
                        <textarea v-model="form.convention.paiement" rows="2" class="field__input"></textarea>
                        <span v-if="erreur('paiement')" class="gf-error">{{ erreur('paiement') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Article 5 – Affectation des recettes</span>
                        <textarea v-model="form.convention.affectation" rows="6" class="field__input"></textarea>
                        <span v-if="erreur('affectation')" class="gf-error">{{ erreur('affectation') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Article 6 – Obligations du Bénéficiaire <small>une par ligne</small></span>
                        <textarea v-model="form.convention.obligations_beneficiaire" rows="6" class="field__input"></textarea>
                        <span v-if="erreur('obligations_beneficiaire')" class="gf-error">{{ erreur('obligations_beneficiaire') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Article 7 – Obligations de Fanamby <small>une par ligne</small></span>
                        <textarea v-model="form.convention.obligations_fanamby" rows="4" class="field__input"></textarea>
                        <span v-if="erreur('obligations_fanamby')" class="gf-error">{{ erreur('obligations_fanamby') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Article 8 – Responsabilité du Bénéficiaire <small>une par ligne</small></span>
                        <textarea v-model="form.convention.responsabilite" rows="4" class="field__input"></textarea>
                        <span v-if="erreur('responsabilite')" class="gf-error">{{ erreur('responsabilite') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Article 9 – Litiges</span>
                        <textarea v-model="form.convention.litiges" rows="3" class="field__input"></textarea>
                        <span v-if="erreur('litiges')" class="gf-error">{{ erreur('litiges') }}</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Article 10 – Entrée en vigueur</span>
                        <textarea v-model="form.convention.entree_vigueur" rows="2" class="field__input"></textarea>
                        <span v-if="erreur('entree_vigueur')" class="gf-error">{{ erreur('entree_vigueur') }}</span>
                    </label>

                    <div class="champs-2">
                        <label class="field">
                            <span class="field__label">Fait à</span>
                            <input v-model="form.convention.lieu" type="text" class="field__input" />
                            <span v-if="erreur('lieu')" class="gf-error">{{ erreur('lieu') }}</span>
                        </label>
                        <label class="field">
                            <span class="field__label">Signataire pour Fanamby</span>
                            <input v-model="form.convention.signataire" type="text" class="field__input" />
                            <span v-if="erreur('signataire')" class="gf-error">{{ erreur('signataire') }}</span>
                        </label>
                    </div>
                </div>
                </fieldset>

                <CadreApercu class="edition__apercu" :titre="ongletActif.titre" sous-titre="Projet, tel qu'il sera généré">
                    <template #actions>
                        <button v-for="f in formats" :key="f.cle" type="button" class="btn-sm-edit" :title="f.detail"
                            :disabled="telechargement !== null" @click="telecharger(ongletActif, f.cle)">
                            <Icon :name="telechargement === f.cle ? 'circle-notch' : f.icone" :class="{ spin: telechargement === f.cle }" />{{ f.libelle }}
                        </button>
                    </template>
                    <ApercuAutorisation v-if="onglet === 'autorisation'" :demande="demande" :contenu="form.autorisation" />
                    <ApercuCahierCharges v-else-if="onglet === 'cahier_charges'" :demande="demande" :contenu="form.cahier_charges" />
                    <ApercuConvention v-else :demande="demande" :contenu="form.convention" />
                </CadreApercu>
            </div>

            <template v-if="!lectureSeule">
            <div class="actions">
                <button type="button" class="btn-accent" :disabled="!form.isDirty || form.processing" @click="enregistrer">
                    <Icon :name="form.processing ? 'circle-notch' : 'check-circle'" :class="{ spin: form.processing }" />
                    Enregistrer les modifications
                </button>
            </div>
            <p v-if="form.isDirty" class="hint hint--att">Modifications non enregistrées : l'aperçu les montre déjà, les fichiers PDF et Word contiennent la dernière version enregistrée.</p>
            <button type="button" class="link-danger" :disabled="form.processing" @click="annuler">Annuler la génération</button>
            </template>
        </div>
    </section>
</template>

<style scoped>
/* Marge sous la barre du haut quand la page défile jusqu'aux documents */
.docs-gen { border-color: var(--forest); scroll-margin-top: 72px; }
.docs-gen h2:focus { outline: none; }
.panel__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px 12px; padding: 16px 24px 8px; }
.panel__head h2 { font-size: 15.5px; font-weight: 700; }
.panel__head small { font-size: 12.5px; color: var(--ink-3); }
.panel__body { display: grid; gap: 14px; padding: 16px 24px; font-size: 14px; color: var(--ink-2); container: docs / inline-size; }
.hint { font-size: 13px; color: var(--ink-3); }
.hint--att { color: var(--ochre); font-weight: 600; }
.inline-icon { display: inline-block; width: 13px; height: 13px; vertical-align: -2px; }

.tabs { display: flex; flex-wrap: wrap; gap: 6px; }
.tab { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border: 1px solid var(--rule); border-radius: var(--r-sm); background: var(--surface); font: 600 13px/1 var(--f-body); color: var(--ink-2); cursor: pointer; }
.tab svg { width: 16px; height: 16px; }
.tab--on { border-color: var(--ink); background: var(--paper); color: var(--ink); }

/* Saisie à gauche, aperçu à droite dès que la place le permet ; sinon l'aperçu passe dessous */
.edition { display: grid; gap: 20px; align-items: start; }
@container docs (min-width: 720px) {
    .edition { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    .edition__apercu { position: sticky; top: 84px; }
}
.saisie { display: grid; gap: 14px; min-width: 0; }
.lecture { min-width: 0; margin: 0; padding: 0; border: 0; }
.lecture:disabled .field__input { border-style: solid; background: var(--sunk); color: var(--ink-2); resize: none; }

.field { display: grid; gap: 5px; }
.field__label { font: 600 11px/1.2 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.field__label small { text-transform: none; letter-spacing: 0; font-weight: 400; }
.field__input { width: 100%; padding: 8px 10px; border: 1px dashed var(--rule); border-radius: var(--r-sm); background: var(--surface); font: 400 14px/1.55 var(--f-body); color: var(--ink); resize: vertical; }
.field__input:hover { border-color: var(--ink-3); }
.field__input:focus { outline: none; border-style: solid; border-color: var(--forest); box-shadow: 0 0 0 3px var(--forest-soft); }
.field__input--court { max-width: 220px; font-variant-numeric: tabular-nums; }
.field__aide { font-size: 12.5px; color: var(--ink-3); }
.champs-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }

.dossier { display: grid; gap: 6px; margin: 0; padding: 12px 14px; background: var(--sunk); border-radius: var(--r-sm); }
.dossier div { display: grid; grid-template-columns: 130px minmax(0, 1fr); gap: 10px; }
@media (max-width: 560px) { .dossier div { grid-template-columns: minmax(0, 1fr); gap: 2px; } }
.dossier dt { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; color: var(--ink-3); }
.dossier dt svg, .fixed-text > svg { width: 13px; height: 13px; flex-shrink: 0; color: var(--ink-3); }
.dossier dd { margin: 0; font-weight: 500; color: var(--ink); overflow-wrap: anywhere; }
.fixed-text { display: flex; gap: 8px; align-items: baseline; padding: 10px 14px; background: var(--sunk); border-radius: var(--r-sm); font-size: 13.5px; }
.fixed-text--warn { color: var(--ochre); }
.ligne { display: block; }

.actions { display: flex; flex-wrap: wrap; gap: 8px; }
.link-danger { justify-self: start; padding: 0; border: 0; background: none; font: 600 13px/1.2 var(--f-body); color: var(--signal); cursor: pointer; }
.link-danger:hover { text-decoration: underline; }
.link-danger:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
