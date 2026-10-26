<script setup>
import Icon from '@/Components/Icon.vue';
import { useForm } from '@inertiajs/vue3';
import { openAuthenticatedPdf } from '@/utils/openPdf.js';
import { formatDate } from '@/utils/statut.js';
import { ref, watch } from 'vue';

// Autorisation et cahier des charges générés : les données du dossier sont fixes,
// les textes sont modifiables jusqu'à la validation signée.
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

const apercu = async (o) => {
    try {
        await openAuthenticatedPdf(route('validateur.demandes.documents.apercu', [props.demande.id, o.cle]), `projet_${o.fichier}_${props.demande.id}.pdf`);
    } catch (e) {
        alert(e.message || 'Erreur lors de l\'ouverture du PDF.');
    }
};

const erreur = (champ) => form.errors[`${onglet.value}.${champ}`];
</script>

<template>
    <section class="panel docs-gen">
        <header class="panel__head">
            <h2>Documents à valider</h2>
            <small>Générés par {{ documents.genere_par ?? 'un validateur' }} le {{ formatDate(documents.genere_le) }}</small>
        </header>

        <div class="panel__body">
            <p class="hint">
                Relisez les deux documents. Les textes encadrés sont modifiables ; les informations du dossier
                (<Icon name="lock-simple" class="inline-icon" />) viennent de la demande et ne changent pas.
            </p>

            <div class="tabs" role="tablist">
                <button v-for="o in ONGLETS" :key="o.cle" type="button" role="tab" class="tab"
                    :class="{ 'tab--on': onglet === o.cle }" :aria-selected="onglet === o.cle" @click="onglet = o.cle">
                    <Icon name="file-text" />{{ o.titre }}
                </button>
            </div>

            <!-- Autorisation -->
            <article v-if="onglet === 'autorisation'" class="sheet">
                <p class="sheet__org">ASSOCIATION FANAMBY</p>
                <h3 class="sheet__title">Autorisation officielle d'accès</h3>

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

                <label class="field field--right">
                    <span class="field__label">Signataire</span>
                    <input v-model="form.autorisation.signataire" type="text" class="field__input" />
                    <span v-if="erreur('signataire')" class="gf-error">{{ erreur('signataire') }}</span>
                </label>
            </article>

            <!-- Cahier des charges -->
            <article v-else class="sheet">
                <p class="sheet__org">ASSOCIATION FANAMBY</p>
                <h3 class="sheet__title">Cahier des charges à respecter</h3>

                <p class="fixed-text">
                    <Icon name="lock-simple" />
                    Annexe obligatoire à l'autorisation d'accès pour <strong>{{ demande.user.name }}</strong>
                    dans l'aire protégée de <strong>{{ demande.site.nom }}</strong>.
                </p>

                <label class="field">
                    <span class="field__label">Règles à respecter <small>une par ligne</small></span>
                    <textarea v-model="form.cahier_charges.regles" rows="6" class="field__input" placeholder="Aucune règle : le document l'indiquera"></textarea>
                    <span v-if="erreur('regles')" class="gf-error">{{ erreur('regles') }}</span>
                </label>

                <div v-if="demande.resultats_analyse?.length" class="fixed-text fixed-text--warn">
                    <Icon name="lock-simple" />
                    <span>Avertissements de l'analyse repris automatiquement :
                        <span v-for="(a, i) in demande.resultats_analyse" :key="i" class="ligne">{{ a.message }}</span>
                    </span>
                </div>

                <label class="field">
                    <span class="field__label">Engagement du demandeur</span>
                    <textarea v-model="form.cahier_charges.engagement" rows="4" class="field__input"></textarea>
                    <span v-if="erreur('engagement')" class="gf-error">{{ erreur('engagement') }}</span>
                </label>
            </article>

            <div class="actions">
                <button type="button" class="btn-accent" :disabled="!form.isDirty || form.processing" @click="enregistrer">
                    <Icon :name="form.processing ? 'circle-notch' : 'check-circle'" :class="{ spin: form.processing }" />
                    Enregistrer les modifications
                </button>
                <button v-for="o in ONGLETS" :key="o.cle" type="button" class="btn-ghost" @click="apercu(o)">
                    <Icon name="file-pdf" />Aperçu {{ o.cle === 'autorisation' ? 'autorisation' : 'cahier des charges' }}
                </button>
            </div>
            <p v-if="form.isDirty" class="hint hint--att">Modifications non enregistrées : l'aperçu PDF montre la dernière version enregistrée.</p>
            <button type="button" class="link-danger" :disabled="form.processing" @click="annuler">Annuler la génération</button>
        </div>
    </section>
</template>

<style scoped>
.docs-gen { border-color: var(--forest); }
.panel__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px 12px; padding: 16px 24px 8px; }
.panel__head h2 { font-size: 15.5px; font-weight: 700; }
.panel__head small { font-size: 12.5px; color: var(--ink-3); }
.panel__body { display: grid; gap: 14px; padding: 16px 24px; font-size: 14px; color: var(--ink-2); }
.hint { font-size: 13px; color: var(--ink-3); }
.hint--att { color: var(--ochre); font-weight: 600; }
.inline-icon { display: inline-block; width: 13px; height: 13px; vertical-align: -2px; }

.tabs { display: flex; flex-wrap: wrap; gap: 6px; }
.tab { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border: 1px solid var(--rule); border-radius: var(--r-sm); background: var(--surface); font: 600 13px/1 var(--f-body); color: var(--ink-2); cursor: pointer; }
.tab svg { width: 16px; height: 16px; }
.tab--on { border-color: var(--ink); background: var(--paper); color: var(--ink); }

.sheet { display: grid; gap: 14px; padding: 24px clamp(16px, 4vw, 40px); background: var(--paper); border: 1px solid var(--rule); border-radius: var(--r-sm); box-shadow: 0 1px 3px rgb(0 0 0 / 0.06); }
.sheet__org { text-align: center; font: 700 15px/1.2 var(--f-display); color: var(--forest); letter-spacing: 0.04em; }
.sheet__title { text-align: center; font: 700 14px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.03em; color: var(--ink); padding-bottom: 6px; border-bottom: 2px solid var(--forest); }

.field { display: grid; gap: 5px; }
.field--right { justify-items: end; text-align: right; }
.field--right .field__input { max-width: 320px; text-align: right; }
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
