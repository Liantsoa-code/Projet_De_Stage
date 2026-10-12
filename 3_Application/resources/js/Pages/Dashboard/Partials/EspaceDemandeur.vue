<script setup>
import Icon from '@/Components/Icon.vue';
import StatutSteps from '@/Components/StatutSteps.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { refDemande, formatDate } from '@/utils/statut.js';
import { openAuthenticatedPdf } from '@/utils/openPdf.js';

// Espace demandeur : où en sont mes dossiers, et que dois-je faire ?
const page = usePage();
const enCours = computed(() => page.props.en_cours || []);
const aCompleter = computed(() => page.props.a_completer || []);
const sejours = computed(() => page.props.sejours || []);

const joursAvant = (date) => Math.ceil((new Date(date).setHours(0, 0, 0, 0) - new Date().setHours(0, 0, 0, 0)) / 86400000);

// Ce qu'il reste à faire ou à attendre pour chaque dossier en cours
function suivi(d) {
    const code = d.statut?.code;
    if (code === 'brouillon') {
        if (d.pieces_manquantes) return { texte: `${d.pieces_manquantes} pièce${d.pieces_manquantes > 1 ? 's' : ''} à joindre avant l'envoi`, alerte: true };
        if (!d.date_debut) return { texte: 'Dates à préciser avant l\'envoi', alerte: true };
        return { texte: 'Prêt à être envoyé', alerte: false };
    }
    if (code === 'soumise' || code === 'en_analyse') {
        const attente = d.attente != null ? `En instruction depuis ${d.attente} j` : 'En instruction';
        return { texte: d.delai_habituel != null ? `${attente} · décision habituelle en ${Math.round(d.delai_habituel)} j environ` : attente, alerte: false };
    }
    return null;
}

const openPdf = async (url, filename) => {
    try {
        await openAuthenticatedPdf(url, filename);
    } catch (e) {
        alert(e.message || 'Erreur lors de l\'ouverture du PDF.');
    }
};
</script>

<template>
    <div class="dash">
        <section v-for="d in aCompleter" :key="`todo-${d.id}`" class="callout callout--att">
            <Icon name="pencil-simple-line" class="callout__icon" />
            <div class="callout__body">
                <span class="callout__tag">Complément demandé · <span class="mono">{{ refDemande(d) }}</span></span>
                <h2>{{ d.type_demande?.libelle }} · {{ d.site?.nom }}</h2>
                <q v-if="d.dernier_commentaire">{{ d.dernier_commentaire }}</q>
                <small v-if="d.commentaire_par">{{ d.commentaire_par }}, le {{ formatDate(d.commentaire_le) }}</small>
            </div>
            <Link :href="route('demandes.edit', d.id)" class="btn-warn">Compléter le dossier</Link>
        </section>

        <div class="grid-2">
            <section class="panel">
                <header class="panel__head">
                    <h2>Demandes en cours</h2>
                    <Link :href="route('demandes.index')" class="link">Tout voir</Link>
                </header>
                <div v-if="enCours.length" class="rows">
                    <Link v-for="d in enCours" :key="d.id" :href="route('demandes.show', d.id)" class="row row--mine">
                        <span class="row__main">
                            <span class="mono ref">{{ refDemande(d) }}</span>
                            <strong>{{ d.type_demande?.libelle }}</strong>
                        </span>
                        <span class="row__main">
                            <span class="site-label">{{ d.site?.nom }}</span>
                            <span class="mono dates" v-if="d.date_debut">{{ formatDate(d.date_debut) }} → {{ formatDate(d.date_fin) }}</span>
                            <span class="dates" v-else>Dates à définir</span>
                        </span>
                        <StatutSteps :code="d.statut.code" />
                        <span v-if="suivi(d)" class="row__suivi" :class="{ 'row__suivi--alerte': suivi(d).alerte }">{{ suivi(d).texte }}</span>
                    </Link>
                </div>
                <div v-else class="empty">
                    <Icon name="file-text" />
                    <p>Aucune demande en cours. Une demande se prépare en quelques minutes, avec vérification du calendrier du site.</p>
                    <Link :href="route('demandes.create')" class="btn-accent"><Icon name="plus" />Préparer une demande</Link>
                </div>
            </section>

            <section class="panel">
                <header class="panel__head"><h2>Séjours autorisés à venir</h2></header>
                <div v-if="sejours.length">
                    <div v-for="d in sejours" :key="d.id" class="stay">
                        <div class="stay__when">
                            <template v-if="joursAvant(d.date_debut) > 0">
                                <span class="stay__n">{{ joursAvant(d.date_debut) }}</span>
                                <span>jour{{ joursAvant(d.date_debut) > 1 ? 's' : '' }} avant l'arrivée</span>
                            </template>
                            <template v-else>
                                <Icon name="map-trifold" class="stay__icon" />
                                <span>Séjour en cours</span>
                            </template>
                        </div>
                        <div class="stay__body">
                            <span class="site-label">{{ d.site?.nom }}</span>
                            <strong>{{ d.type_demande?.libelle }}</strong>
                            <span class="mono dates">{{ formatDate(d.date_debut) }} → {{ formatDate(d.date_fin) }}</span>
                            <span v-if="d.resultats_analyse?.length" class="note-hatch"><span class="hatch"></span>Période sensible : précautions au cahier des charges</span>
                            <div class="btn-row">
                                <button type="button" class="btn-ghost btn--sm" @click="openPdf(route('demandes.pdf.autorisation', d.id), `autorisation_${d.id}.pdf`)"><Icon name="file-pdf" />Autorisation</button>
                                <button type="button" class="btn-ghost btn--sm" @click="openPdf(route('demandes.pdf.cahier-charges', d.id), `cahier_des_charges_${d.id}.pdf`)"><Icon name="file-pdf" />Cahier des charges</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="empty empty--small">
                    <p>Aucun séjour autorisé pour le moment. Les autorisations validées apparaîtront ici avec leurs PDF.</p>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.row--mine { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(140px, 180px); }
.row__suivi { grid-column: 1 / -1; margin-top: -6px; font-size: 12.5px; color: var(--ink-3); }
.row__suivi--alerte { color: var(--ochre); font-weight: 600; }
@media (max-width: 720px) { .row--mine { grid-template-columns: minmax(0, 1fr); gap: 8px; } }

.callout { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 12px 16px; align-items: start; padding: 18px 20px; border: 1px solid; border-radius: var(--r); }
.callout--att { background: var(--ochre-soft); border-color: var(--ochre-line); }
.callout__icon { width: 24px; height: 24px; color: var(--ochre); }
.callout__body { display: grid; gap: 6px; min-width: 0; }
.callout__tag { font: 700 11px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ochre); }
.callout h2 { font-size: 18px; font-weight: 700; }
.callout q { font-style: italic; max-width: 70ch; quotes: "« " " »"; }
.callout small { font-size: 12.5px; color: var(--ink-3); }
.btn-warn { display: inline-flex; align-items: center; padding: 10px 14px; border-radius: var(--r-sm); background: var(--ochre); color: #fff; font-weight: 600; font-size: 14px; text-decoration: none; white-space: nowrap; }
.btn-warn:hover { filter: brightness(1.08); }
@media (max-width: 620px) {
    .callout { grid-template-columns: auto minmax(0, 1fr); }
    .callout .btn-warn { grid-column: 1 / -1; justify-self: start; }
}

.stay { display: grid; grid-template-columns: 90px minmax(0, 1fr); gap: 16px; padding: 16px 24px; border-bottom: 1px solid var(--rule-soft); }
.stay:last-child { border-bottom: 0; }
.stay__when { display: grid; align-content: start; gap: 4px; font-size: 12px; line-height: 1.3; color: var(--ink-3); }
.stay__n { font: 700 34px/0.95 var(--f-display); color: var(--forest); font-variant-numeric: tabular-nums; }
.stay__icon { width: 34px; height: 34px; color: var(--forest); }
.stay__body { display: grid; gap: 5px; min-width: 0; }
.stay__body strong { font-weight: 600; }
.note-hatch { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; color: var(--ochre); }
.btn-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.btn--sm { padding: 7px 10px; font-size: 13px; }
</style>
