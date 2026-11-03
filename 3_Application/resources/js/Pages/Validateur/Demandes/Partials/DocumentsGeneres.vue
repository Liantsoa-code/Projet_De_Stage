<script setup>
import Icon from '@/Components/Icon.vue';
import ApercuAutorisation from '@/Components/Apercu/ApercuAutorisation.vue';
import ApercuCahierCharges from '@/Components/Apercu/ApercuCahierCharges.vue';
import CadreApercu from '@/Components/Apercu/CadreApercu.vue';
import { useForm } from '@inertiajs/vue3';
import { contraintesRelevees } from '@/utils/analyse.js';
import { FORMATS, ouvrirDocument } from '@/utils/ouvrirDocument.js';
import { formatDate } from '@/utils/statut.js';
import { computed, ref, watch } from 'vue';

// Autorisation et cahier des charges générés : les données du dossier sont fixes,
// les textes sont modifiables jusqu'à la validation signée. L'aperçu à côté suit la saisie.
const props = defineProps({
    demande: { type: Object, required: true },
    documents: { type: Object, required: true },
});

// Modifications non enregistrées : la validation attend qu'elles soient enregistrées.
const modifie = defineModel('modifie', { type: Boolean, default: false });

const ONGLETS = [
    { cle: 'autorisation', titre: 'Autorisation d\'accès', fichier: 'autorisation' },
    { cle: 'cahier_charges', titre: 'Cahier des charges', fichier: 'cahier_des_charges' },
];
const onglet = ref('autorisation');
const ongletActif = computed(() => ONGLETS.find((o) => o.cle === onglet.value));
// Contraintes de l'analyse imprimées dans le cahier des charges (sans les simples informations)
const alertes = computed(() => contraintesRelevees(props.demande.resultats_analyse));

const form = useForm({
    autorisation: { ...props.documents.contenu.autorisation },
    cahier_charges: { ...props.documents.contenu.cahier_charges },
});

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
            <p class="hint">
                <strong>Rien n'est encore envoyé au demandeur</strong> : il ne recevra l'avis favorable et ces documents qu'à votre signature.
                Relisez les deux documents. Les textes encadrés sont modifiables et l'aperçu se met à jour pendant la saisie ;
                les informations du dossier (<Icon name="lock-simple" class="inline-icon" />) viennent de la demande et ne changent pas.
            </p>

            <div class="tabs" role="tablist">
                <button v-for="o in ONGLETS" :key="o.cle" type="button" role="tab" class="tab"
                    :class="{ 'tab--on': onglet === o.cle }" :aria-selected="onglet === o.cle" @click="onglet = o.cle">
                    <Icon name="file-text" />{{ o.titre }}
                </button>
            </div>

            <div class="edition">
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
                <div v-else class="saisie">
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

                <CadreApercu class="edition__apercu" :titre="ongletActif.titre" sous-titre="Projet, tel qu'il sera généré">
                    <template #actions>
                        <button v-for="f in FORMATS" :key="f.cle" type="button" class="btn-sm-edit" :title="f.detail"
                            :disabled="telechargement !== null" @click="telecharger(ongletActif, f.cle)">
                            <Icon :name="telechargement === f.cle ? 'circle-notch' : f.icone" :class="{ spin: telechargement === f.cle }" />{{ f.libelle }}
                        </button>
                    </template>
                    <ApercuAutorisation v-if="onglet === 'autorisation'" :demande="demande" :contenu="form.autorisation" />
                    <ApercuCahierCharges v-else :demande="demande" :contenu="form.cahier_charges" />
                </CadreApercu>
            </div>

            <div class="actions">
                <button type="button" class="btn-accent" :disabled="!form.isDirty || form.processing" @click="enregistrer">
                    <Icon :name="form.processing ? 'circle-notch' : 'check-circle'" :class="{ spin: form.processing }" />
                    Enregistrer les modifications
                </button>
            </div>
            <p v-if="form.isDirty" class="hint hint--att">Modifications non enregistrées : l'aperçu les montre déjà, les fichiers PDF et Word contiennent la dernière version enregistrée.</p>
            <button type="button" class="link-danger" :disabled="form.processing" @click="annuler">Annuler la génération</button>
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

.field { display: grid; gap: 5px; }
.field__label { font: 600 11px/1.2 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.field__label small { text-transform: none; letter-spacing: 0; font-weight: 400; }
.field__input { width: 100%; padding: 8px 10px; border: 1px dashed var(--rule); border-radius: var(--r-sm); background: var(--surface); font: 400 14px/1.55 var(--f-body); color: var(--ink); resize: vertical; }
.field__input:hover { border-color: var(--ink-3); }
.field__input:focus { outline: none; border-style: solid; border-color: var(--forest); box-shadow: 0 0 0 3px var(--forest-soft); }

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
