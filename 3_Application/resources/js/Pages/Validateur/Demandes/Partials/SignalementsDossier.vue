<script setup>
import Icon from '@/Components/Icon.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref } from 'vue';
import { formatDate } from '@/utils/statut.js';

// Signalements du dossier : un observateur, un validateur ou un admin signale un problème sur une partie
// du dossier (le demandeur en est prévenu, sans le nom de l'auteur) ; un validateur du site ou un admin
// le traite avec une réponse, ou en renvoyant le dossier au demandeur pour qu'il corrige la partie signalée.
const props = defineProps({
    demande: { type: Object, required: true },
    signalements: { type: Array, default: () => [] },
    parties: { type: Object, required: true },
    peutSignaler: { type: Boolean, default: false },
    peutTraiter: { type: Boolean, default: false },
    // Décideur sur un dossier soumis ou en analyse : il peut renvoyer le dossier au demandeur pour correction
    peutDemanderComplement: { type: Boolean, default: false },
    partiesComplement: { type: Object, default: () => ({}) },
});

const ROLES = { admin: 'Admin', validateur: 'Validateur', observateur: 'Observateur' };
const qui = (u) => (u ? `${u.name}${ROLES[u.role] ? ` (${ROLES[u.role]})` : ''}` : 'Compte supprimé');
const quand = (v) => `${formatDate(v)} à ${new Date(v).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;

// Les signalements ouverts d'abord, puis les traités ; les plus récents en tête dans chaque groupe
const liste = computed(() => [...props.signalements].sort((a, b) => Number(b.ouvert) - Number(a.ouvert)));

// Formulaire « Signaler un problème », ouvert depuis le bouton de la section ou un lien « Signaler » d'une partie
const ouvert = ref(false);
const bloc = ref(null);
const champMessage = ref(null);
const form = useForm({ partie: 'autre', message: '' });
const signaler = (partie = 'autre') => {
    form.partie = partie;
    ouvert.value = true;
    nextTick(() => {
        const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        bloc.value?.scrollIntoView({ behavior: reduit ? 'auto' : 'smooth', block: 'start' });
        champMessage.value?.focus({ preventScroll: true });
    });
};
const envoyer = () => form.post(route('validateur.demandes.signalements.store', props.demande.id), {
    preserveScroll: true,
    onSuccess: () => { form.reset(); ouvert.value = false; },
});

// Réponse en cours de saisie, par signalement
const reponses = reactive({});
// Partie à compléter, par signalement, quand le validateur choisit de renvoyer le dossier au demandeur
const complements = reactive({});
const annulerComplement = (id) => { delete complements[id]; };
const traitement = ref(null);
const traiter = (s, demanderComplement = false) => {
    traitement.value = useForm({
        reponse: reponses[s.id] ?? '',
        demander_complement: demanderComplement,
        partie: demanderComplement ? complements[s.id] : null,
    });
    traitement.value.put(route('validateur.demandes.signalements.traiter', [props.demande.id, s.id]), {
        preserveScroll: true,
        onSuccess: () => { delete reponses[s.id]; delete complements[s.id]; },
        onFinish: () => { traitement.value = null; },
    });
};

defineExpose({ signaler });
</script>

<template>
    <section ref="bloc" class="panel signalements">
        <header class="panel__head">
            <h2>Signalements</h2>
            <span class="panel__fin">
                <small>Le demandeur est aussi prévenu</small>
                <button v-if="peutSignaler && !ouvert" type="button" class="link link-btn link-signaler" @click="signaler()"><Icon name="warning" />Signaler un problème</button>
            </span>
        </header>

        <form v-if="ouvert" class="signaler" @submit.prevent="envoyer">
            <div class="signaler__ligne">
                <label class="gf-label" for="signalement-partie">Partie concernée</label>
                <select id="signalement-partie" v-model="form.partie" class="gf-select">
                    <option v-for="(libelle, cle) in parties" :key="cle" :value="cle">{{ libelle }}</option>
                </select>
            </div>
            <div>
                <label class="gf-label" for="signalement-message">Problème constaté</label>
                <textarea id="signalement-message" ref="champMessage" v-model="form.message" rows="3" maxlength="2000" class="gf-textarea" placeholder="Ex. : la copie du passeport est illisible"></textarea>
                <p v-if="form.errors.message" class="gf-error">{{ form.errors.message }}</p>
            </div>
            <p class="hint">Les validateurs de l'aire protégée, les administrateurs et le demandeur sont prévenus (cloche et e-mail). Le demandeur ne voit pas votre nom ; sa notification l'amène à la partie concernée.</p>
            <div class="signaler__actions">
                <button type="button" class="btn-ghost" @click="ouvert = false">Annuler</button>
                <button type="submit" class="btn-danger" :disabled="form.processing || !form.message.trim()">
                    <Icon :name="form.processing ? 'circle-notch' : 'warning'" :class="{ spin: form.processing }" />Envoyer le signalement
                </button>
            </div>
        </form>

        <ul v-if="liste.length" class="liste">
            <li v-for="s in liste" :key="s.id" class="sig" :class="{ 'sig--ouvert': s.ouvert }">
                <div class="sig__tete">
                    <span class="sig__etat" :class="s.ouvert ? 'sig__etat--ouvert' : 'sig__etat--traite'">
                        <Icon :name="s.ouvert ? 'warning' : 'check-circle'" />{{ s.ouvert ? 'Ouvert' : 'Traité' }}
                    </span>
                    <span class="sig__partie">{{ s.partie_libelle }}</span>
                    <small>{{ qui(s.auteur) }} · {{ quand(s.created_at) }}</small>
                </div>
                <p class="sig__message">{{ s.message }}</p>

                <p v-if="!s.ouvert" class="sig__reponse">
                    <strong>Traité par {{ qui(s.traite_par) }}, le {{ quand(s.traite_le) }}</strong>
                    <template v-if="s.reponse"> : {{ s.reponse }}</template>
                </p>
                <div v-else-if="peutTraiter" class="sig__traiter">
                    <textarea
                        v-model="reponses[s.id]"
                        rows="2"
                        maxlength="2000"
                        class="gf-textarea"
                        :aria-label="`Réponse au signalement : ${s.partie_libelle}`"
                        :placeholder="complements[s.id] ? 'Message au demandeur (facultatif) : à défaut, il reçoit le problème signalé' : 'Réponse (facultatif) : ce qui a été fait'"
                    ></textarea>

                    <!-- Renvoi au demandeur : le dossier passe « à compléter », sa notification l'amène à la partie choisie -->
                    <div v-if="complements[s.id]" class="sig__complement">
                        <label class="gf-label" :for="`complement-${s.id}`">Partie à corriger par le demandeur</label>
                        <select :id="`complement-${s.id}`" v-model="complements[s.id]" class="gf-select">
                            <option v-for="(libelle, cle) in partiesComplement" :key="cle" :value="cle">{{ libelle }}</option>
                        </select>
                        <p class="hint">Le dossier repasse « à compléter » : le demandeur est prévenu et peut modifier sa demande avant de la renvoyer.</p>
                        <div class="sig__actions">
                            <button type="button" class="btn-ghost" @click="annulerComplement(s.id)">Annuler</button>
                            <button type="button" class="btn-sm-edit sig__renvoyer" :disabled="traitement !== null" @click="traiter(s, true)">
                                <Icon :name="traitement ? 'circle-notch' : 'pencil-simple-line'" :class="{ spin: traitement }" />Renvoyer au demandeur
                            </button>
                        </div>
                    </div>
                    <div v-else class="sig__actions">
                        <button type="button" class="btn-sm-edit" :disabled="traitement !== null" @click="traiter(s)"><Icon name="check-circle" />Marquer comme traité</button>
                        <button v-if="peutDemanderComplement" type="button" class="btn-sm-edit sig__renvoyer" :disabled="traitement !== null" @click="complements[s.id] = s.partie_complement">
                            <Icon name="pencil-simple-line" />Demander une correction au demandeur
                        </button>
                    </div>
                </div>
            </li>
        </ul>
        <p v-else-if="!ouvert" class="panel__text muted">Aucun signalement sur ce dossier.</p>
    </section>
</template>

<style scoped>
/* Même gabarit que les autres blocs de la fiche d'instruction (styles propres à Show.vue) */
.panel { background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); min-width: 0; }
.panel__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px 12px; padding: 16px 24px 8px; }
.panel__head h2 { font-size: 15.5px; font-weight: 700; }
.panel__head small { font-size: 12.5px; color: var(--ink-3); }
.panel__fin { display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 4px 14px; }
.panel__text { padding: 16px 24px; font-size: 14px; }
.muted { color: var(--ink-3); }
.link { font-size: 13px; font-weight: 600; color: var(--water); text-decoration: none; }
.link:hover { text-decoration: underline; }
.link-signaler { color: var(--signal); }
.link-btn { display: inline-flex; align-items: center; gap: 5px; padding: 0; background: none; border: 0; cursor: pointer; }
.link-btn svg { width: 14px; height: 14px; }
.signalements { scroll-margin-top: 72px; }
.signaler { display: grid; gap: 12px; margin: 0 24px 16px; padding: 14px; background: var(--ochre-soft); border: 1px solid var(--ochre-line); border-radius: var(--r-sm); }
.signaler__ligne .gf-select { width: auto; min-width: 240px; max-width: 100%; }
.signaler__actions { display: flex; justify-content: flex-end; gap: 8px; }
.hint { font-size: 12.5px; color: var(--ink-3); }

.liste { list-style: none; margin: 0; padding: 0 24px 16px; display: grid; gap: 10px; }
.sig { display: grid; gap: 6px; padding: 12px 14px; border: 1px solid var(--rule); border-radius: var(--r-sm); }
.sig--ouvert { border-color: var(--ochre-line); }
.sig__tete { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; }
.sig__tete small { font-size: 12px; color: var(--ink-3); }
.sig__etat { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: var(--r-pill); font: 600 11.5px/1.4 var(--f-body); }
.sig__etat svg { width: 13px; height: 13px; }
.sig__etat--ouvert { background: var(--ochre-soft); color: var(--ochre); }
.sig__etat--traite { background: var(--forest-soft); color: var(--forest); }
.sig__partie { font-size: 13px; font-weight: 600; color: var(--ink); }
.sig__message { font-size: 14px; color: var(--ink); white-space: pre-line; overflow-wrap: anywhere; }
.sig__reponse { font-size: 13px; color: var(--ink-2); white-space: pre-line; }
.sig__reponse strong { font-weight: 600; color: var(--forest); }
.sig__traiter { display: grid; gap: 6px; justify-items: start; }
.sig__traiter .gf-textarea { font-size: 13.5px; }
.sig__actions { display: flex; flex-wrap: wrap; gap: 8px; }
.sig__renvoyer { border-color: var(--ochre-line); color: var(--ochre); }
.sig__renvoyer:hover { border-color: var(--ochre); color: var(--ochre); }
.sig__complement { display: grid; gap: 8px; justify-items: start; width: 100%; padding: 12px; background: var(--ochre-soft); border: 1px solid var(--ochre-line); border-radius: var(--r-sm); }
.sig__complement .gf-select { width: auto; min-width: 240px; max-width: 100%; }
</style>
