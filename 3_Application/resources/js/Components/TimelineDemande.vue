<script setup>
import Icon from '@/Components/Icon.vue';
import { computed, ref, watch } from 'vue';
import { formatDate } from '@/utils/statut.js';

// Timeline du dossier : étapes passées (historique des statuts), étape actuelle et étapes à venir.
// Chaque étape s'ouvre au clic pour afficher son détail (commentaire, auteur, pièces, analyse).
const props = defineProps({
    demande: { type: Object, required: true },
    // Côté demandeur, les agents ne sont pas nommés et les actions attendues s'adressent à « vous »
    vueDemandeur: { type: Boolean, default: false },
    // Délai habituel de décision (jours), pour estimer la décision d'un dossier en instruction
    delaiHabituel: { type: Number, default: null },
});

const ROLES = { admin: 'Admin', validateur: 'Validateur', demandeur: 'Demandeur' };
const JOUR = 86400000;
// Tolérance entre l'enregistrement d'une pièce et l'étape qui l'a déposée (même requête)
const TOLERANCE_MS = 5000;

const debutJour = (v) => { const d = new Date(v); d.setHours(0, 0, 0, 0); return d; };
const joursEntre = (a, b) => Math.round((debutJour(b) - debutJour(a)) / JOUR);
const aujourdhui = debutJour(Date.now());
const heure = (v) => new Date(v).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

const code = computed(() => props.demande.statut?.code);
const historique = computed(() => props.demande.historique_statuts ?? []);

const acteur = (h) => {
    if (props.vueDemandeur) {
        if (h.user?.id === props.demande.user_id) return 'Vous';
        return h.user?.role === 'admin' ? 'Administration' : 'Validateur';
    }
    if (!h.user) return 'Compte supprimé';
    return h.user.role ? `${h.user.name} (${ROLES[h.user.role]})` : h.user.name;
};

// Libellé et apparence d'une entrée d'historique, selon le statut atteint et le précédent
function presentation(h, precedent) {
    switch (h.nouveau_statut?.code) {
        case 'soumise': return { titre: 'Demande envoyée', icone: 'arrow-right', ton: 'info', depot: true };
        case 'en_analyse': return precedent === 'a_completer'
            ? { titre: 'Dossier complété et renvoyé', icone: 'file-plus', ton: 'info', depot: true }
            : { titre: 'Prise en charge', icone: 'stamp', ton: 'info' };
        case 'a_completer': return { titre: 'Complément demandé', icone: 'pencil-simple-line', ton: 'att' };
        case 'validee': return { titre: 'Demande validée', icone: 'seal-check', ton: 'ok' };
        case 'rejetee': return { titre: 'Demande rejetée', icone: 'prohibit', ton: 'no' };
        default: return { titre: h.nouveau_statut?.libelle ?? 'Changement de statut', icone: 'stamp', ton: 'info' };
    }
}

const etapePrevue = (cle, titre, texte, extra = {}) => ({ cle, titre, texte, icone: 'clock', ton: 'neutre', etat: 'avenir', ...extra });

// Décision attendue, avec une estimation tirée du délai habituel du site
function decision() {
    const e = etapePrevue('decision', 'Décision', 'Validation ou rejet du dossier, avec le commentaire du validateur.');
    if (props.delaiHabituel == null || !props.demande.date_soumission) return e;
    const jours = Math.round(props.delaiHabituel);
    const prevue = debutJour(props.demande.date_soumission);
    prevue.setDate(prevue.getDate() + jours);
    if (prevue >= aujourdhui) {
        return { ...e, date: prevue, estimee: true, texte: `${e.texte} Elle intervient habituellement ${jours} jour${jours > 1 ? 's' : ''} après l'envoi.` };
    }
    return { ...e, texte: `${e.texte} Le délai habituel (${jours} jour${jours > 1 ? 's' : ''}) est dépassé : elle devrait arriver prochainement.` };
}

// Arrivée et fin du séjour : réalisées pour une demande validée dont les dates sont passées
function sejour() {
    const d = props.demande;
    if (!d.date_debut || code.value === 'rejetee') return [];
    const valide = code.value === 'validee';
    const etat = (date) => (valide && debutJour(date) <= aujourdhui ? 'passe' : 'avenir');
    const equipe = `${d.equipe_nombre} personne${d.equipe_nombre > 1 ? 's' : ''}`;
    const sousReserve = valide ? '' : ' Prévue si la demande est validée.';
    return [
        { cle: 'arrivee', titre: 'Arrivée sur le site', date: d.date_debut, jourSeul: true, icone: 'map-pin', ton: valide ? 'ok' : 'neutre', etat: etat(d.date_debut), texte: `${equipe} sur le terrain jusqu'au ${formatDate(d.date_fin)}.${sousReserve}` },
        { cle: 'fin', titre: 'Fin du séjour', date: d.date_fin, jourSeul: true, icone: 'tree-evergreen', ton: valide ? 'ok' : 'neutre', etat: etat(d.date_fin), texte: valide && etat(d.date_fin) === 'passe' ? 'Le séjour autorisé est terminé.' : `Départ du site.${sousReserve}` },
    ];
}

const evenements = computed(() => {
    const d = props.demande;
    const h = historique.value;
    const passes = [];

    // Un dossier soumis depuis un brouillon a un ancien statut sur sa première entrée
    if (code.value === 'brouillon' || h[0]?.ancien_statut_id != null) {
        passes.push({
            cle: 'brouillon', titre: 'Brouillon créé', date: d.created_at, icone: 'file-dashed', ton: 'neutre', etat: 'passe', depot: true,
            acteur: props.vueDemandeur ? 'Vous' : (d.user?.name ?? 'Demandeur'),
            texte: 'Dossier visible uniquement par son auteur tant qu\'il n\'est pas envoyé.',
        });
    }
    h.forEach((entree, i) => {
        passes.push({
            cle: `h${entree.id}`, date: entree.date_changement, etat: 'passe',
            acteur: acteur(entree), commentaire: entree.commentaire,
            ...presentation(entree, h[i - 1]?.nouveau_statut?.code),
        });
    });

    // Pièces actuelles rattachées à l'étape de dépôt qui les a apportées
    const depots = passes.filter(e => e.depot);
    depots.forEach(e => (e.pieces = []));
    (d.documents ?? []).forEach((doc) => {
        const t = new Date(doc.created_at).getTime() - TOLERANCE_MS;
        const cible = depots.find(e => new Date(e.date).getTime() >= t) ?? depots[depots.length - 1];
        cible?.pieces.push(doc);
    });

    // L'analyse du calendrier est figée au dernier envoi
    const envois = passes.filter(e => e.depot && e.cle !== 'brouillon');
    if (envois.length) envois[envois.length - 1].analyse = d.resultats_analyse ?? [];

    passes.forEach((e, i) => { if (i > 0) e.ecart = joursEntre(passes[i - 1].date, e.date); });

    const avenir = {
        brouillon: [
            etapePrevue('envoi', props.vueDemandeur ? 'Envoi de votre demande' : 'Envoi par le demandeur', 'Toutes les informations et pièces obligatoires sont nécessaires pour envoyer le dossier.'),
            etapePrevue('prise', 'Prise en charge', 'Un validateur du site ouvre le dossier et l\'étudie.'),
            etapePrevue('decision', 'Décision', 'Validation ou rejet du dossier, avec le commentaire du validateur.'),
        ],
        soumise: [etapePrevue('prise', 'Prise en charge', 'Un validateur du site ouvre le dossier et l\'étudie.'), decision()],
        en_analyse: [decision()],
        a_completer: [
            etapePrevue('renvoi', props.vueDemandeur ? 'Renvoi de votre dossier complété' : 'Renvoi par le demandeur', 'Le dossier repart directement en analyse dès qu\'il est renvoyé.', { ton: 'att', icone: 'hourglass-medium' }),
            etapePrevue('decision', 'Décision', 'Validation ou rejet du dossier, avec le commentaire du validateur.'),
        ],
    }[code.value] ?? [];

    const liste = [...passes, ...avenir, ...sejour()];
    const actuel = [...liste].reverse().find(e => e.etat === 'passe');
    if (actuel) actuel.actuel = true;
    return liste;
});

// L'étape actuelle est ouverte par défaut, y compris après une nouvelle décision sur la page
const ouverts = ref(new Set());
watch(() => evenements.value.find(e => e.actuel)?.cle, (cle) => cle && ouverts.value.add(cle), { immediate: true });
const basculer = (cle) => (ouverts.value.has(cle) ? ouverts.value.delete(cle) : ouverts.value.add(cle));
const toutOuvert = computed(() => evenements.value.every(e => ouverts.value.has(e.cle)));
const toutBasculer = () => {
    const tout = !toutOuvert.value;
    ouverts.value.clear();
    if (tout) evenements.value.forEach(e => ouverts.value.add(e.cle));
};
</script>

<template>
    <div class="tl-wrap">
        <button type="button" class="tl-all" @click="toutBasculer">{{ toutOuvert ? 'Tout replier' : 'Tout déplier' }}</button>
        <ol class="tl">
            <li
                v-for="e in evenements"
                :key="e.cle"
                class="tl__item"
                :class="[`tl__item--${e.etat}`, `tl__item--${e.ton}`, { 'tl__item--actuel': e.actuel }]"
                :aria-current="e.actuel ? 'step' : undefined"
            >
                <span class="tl__dot"><Icon :name="e.icone" /></span>
                <div class="tl__content">
                    <button type="button" class="tl__toggle" :aria-expanded="ouverts.has(e.cle)" :aria-controls="`tl-${e.cle}`" @click="basculer(e.cle)">
                        <span class="tl__titre">
                            {{ e.titre }}
                            <span v-if="e.actuel" class="tl__tag">Étape actuelle</span>
                            <span v-else-if="e.etat === 'avenir'" class="tl__tag tl__tag--avenir">À venir</span>
                        </span>
                        <span class="tl__meta">
                            <template v-if="e.date">
                                <span class="mono">{{ e.estimee ? 'vers le ' : '' }}{{ formatDate(e.date) }}</span>
                            </template>
                            <template v-if="e.ecart > 0"> · +{{ e.ecart }} j</template>
                            <template v-if="e.pieces?.length"> · <Icon name="paperclip" class="tl__clip" />{{ e.pieces.length }}</template>
                        </span>
                        <q v-if="e.commentaire && !ouverts.has(e.cle)" class="tl__apercu">{{ e.commentaire }}</q>
                        <Icon name="caret-down" class="tl__caret" />
                    </button>

                    <div v-show="ouverts.has(e.cle)" :id="`tl-${e.cle}`" class="tl__detail">
                        <p v-if="e.etat === 'passe' && !e.jourSeul" class="tl__when">
                            Le <span class="mono">{{ formatDate(e.date) }}</span> à <span class="mono">{{ heure(e.date) }}</span>
                            <template v-if="e.acteur"> · {{ e.acteur }}</template>
                        </p>
                        <q v-if="e.commentaire" class="tl__comment">{{ e.commentaire }}</q>
                        <p v-if="e.texte" class="tl__texte">{{ e.texte }}</p>

                        <div v-if="e.pieces?.length" class="tl__bloc">
                            <span class="tl__label">Pièces déposées</span>
                            <ul class="tl__pieces">
                                <li v-for="doc in e.pieces" :key="doc.id">
                                    <Icon name="file-pdf" />
                                    <a :href="route('documents.show', doc.id)">{{ doc.type_document }}</a>
                                    <small>{{ doc.nom_fichier }}</small>
                                </li>
                            </ul>
                        </div>

                        <div v-if="e.analyse" class="tl__bloc">
                            <span class="tl__label">Analyse du calendrier à l'envoi</span>
                            <p v-if="!e.analyse.length" class="tl__ok"><Icon name="check-circle" />Aucune période sensible sur les dates du séjour.</p>
                            <p v-for="(a, i) in e.analyse" :key="i" class="tl__alerte"><Icon name="warning" />{{ a.message }}</p>
                        </div>
                    </div>
                </div>
            </li>
        </ol>
    </div>
</template>

<style scoped>
.tl-wrap { display: grid; padding: 0 24px 20px; }
.tl-all { justify-self: end; margin-bottom: 4px; padding: 2px 0; font-size: 12.5px; font-weight: 600; color: var(--water); background: none; border: 0; cursor: pointer; }
.tl-all:hover { text-decoration: underline; }

.tl { list-style: none; margin: 0; padding: 0; display: grid; }
.tl__item { position: relative; display: grid; grid-template-columns: 26px minmax(0, 1fr); gap: 12px; padding-bottom: 6px; }
/* Trait vertical reliant les étapes : plein pour le passé, pointillé pour l'avenir */
.tl__item::before { content: ''; position: absolute; left: 12px; top: 28px; bottom: -2px; border-left: 2px solid var(--rule); }
.tl__item--passe::before { border-left-color: var(--forest-line); }
.tl__item--avenir::before { border-left-style: dashed; }
.tl__item:last-child::before { display: none; }

.tl__dot { position: relative; z-index: 1; display: grid; place-items: center; width: 26px; height: 26px; margin-top: 6px; border: 2px solid var(--ink-3); border-radius: 50%; background: var(--surface); color: var(--ink-3); }
.tl__dot svg { width: 13px; height: 13px; }
.tl__item--passe.tl__item--info .tl__dot { border-color: var(--water); color: var(--water); }
.tl__item--passe.tl__item--ok .tl__dot { border-color: var(--forest); color: var(--forest); }
.tl__item--passe.tl__item--att .tl__dot { border-color: var(--ochre); color: var(--ochre); }
.tl__item--passe.tl__item--no .tl__dot { border-color: var(--signal); color: var(--signal); }
.tl__item--avenir .tl__dot { border-style: dashed; border-color: var(--rule); }
.tl__item--actuel .tl__dot { color: #fff; background: var(--water); border-color: var(--water); box-shadow: 0 0 0 4px var(--water-soft); }
.tl__item--actuel.tl__item--ok .tl__dot { background: var(--forest); border-color: var(--forest); box-shadow: 0 0 0 4px var(--forest-soft); }
.tl__item--actuel.tl__item--att .tl__dot { background: var(--ochre); border-color: var(--ochre); box-shadow: 0 0 0 4px var(--ochre-soft); }
.tl__item--actuel.tl__item--no .tl__dot { background: var(--signal); border-color: var(--signal); box-shadow: 0 0 0 4px var(--signal-soft); }
.tl__item--actuel.tl__item--neutre .tl__dot { background: var(--ink-3); border-color: var(--ink-3); box-shadow: 0 0 0 4px var(--sunk); }

.tl__content { min-width: 0; }
.tl__toggle {
    position: relative; display: grid; gap: 2px; width: 100%; padding: 6px 28px 6px 8px; margin-left: -8px;
    text-align: left; background: none; border: 0; border-radius: var(--r-sm); color: inherit; cursor: pointer;
}
.tl__toggle:hover { background: var(--sunk); }
.tl__toggle:focus-visible { outline: 2px solid var(--water); outline-offset: 1px; }
.tl__titre { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; font-weight: 600; font-size: 14px; color: var(--ink); }
.tl__item--avenir .tl__titre { color: var(--ink-3); font-weight: 500; }
.tl__tag { padding: 1px 7px; border-radius: var(--r-pill); background: var(--water-soft); color: var(--water); font: 700 10px/1.6 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; }
.tl__tag--avenir { background: var(--sunk); color: var(--ink-3); }
.tl__meta { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 0 4px; font-size: 12.5px; color: var(--ink-3); }
.tl__clip { width: 13px; height: 13px; }
.tl__apercu { display: block; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 12.5px; font-style: italic; color: var(--ink-2); quotes: "« " " »"; }
.tl__caret { position: absolute; top: 10px; right: 6px; width: 14px; height: 14px; color: var(--ink-3); transition: transform 0.15s var(--ease-out); }
.tl__toggle[aria-expanded="true"] .tl__caret { transform: rotate(180deg); }

.tl__detail { display: grid; gap: 8px; padding: 4px 0 12px; font-size: 13px; color: var(--ink-2); }
.tl__when { color: var(--ink-3); }
.tl__comment { display: block; padding: 8px 10px; border-left: 3px solid var(--rule); background: var(--sunk); border-radius: 0 var(--r-xs) var(--r-xs) 0; font-style: italic; white-space: pre-line; quotes: "« " " »"; }
.tl__item--att .tl__comment { border-left-color: var(--ochre); }
.tl__item--no .tl__comment { border-left-color: var(--signal); }
.tl__item--ok .tl__comment { border-left-color: var(--forest); }
.tl__bloc { display: grid; gap: 4px; }
.tl__label { font: 600 10.5px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.tl__pieces { list-style: none; margin: 0; padding: 0; display: grid; gap: 4px; }
.tl__pieces li { display: grid; grid-template-columns: 16px minmax(0, 1fr); column-gap: 6px; align-items: center; }
.tl__pieces svg { width: 16px; height: 16px; color: var(--ink-3); }
.tl__pieces a { font-weight: 600; color: var(--water); text-decoration: none; overflow-wrap: anywhere; }
.tl__pieces a:hover { text-decoration: underline; }
.tl__pieces small { grid-column: 2; font-size: 12px; color: var(--ink-3); overflow-wrap: anywhere; }
.tl__ok, .tl__alerte { display: flex; gap: 6px; align-items: flex-start; }
.tl__ok svg, .tl__alerte svg { width: 15px; height: 15px; flex-shrink: 0; margin-top: 1px; }
.tl__ok svg { color: var(--forest); }
.tl__alerte svg { color: var(--ochre); }

@media (prefers-reduced-motion: reduce) { .tl__caret { transition: none; } }
</style>
