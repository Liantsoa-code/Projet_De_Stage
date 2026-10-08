<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import { openAuthenticatedPdf } from '@/utils/openPdf.js';
import { statutBadgeClass } from '@/utils/statut.js';
import { computed } from 'vue';

const props = defineProps({
    demande: Object,
    statuts: Array,
    viewAllDemandes: { type: Boolean, default: false },
    canDecideOnDemande: { type: Boolean, default: true },
    statutLocked: { type: Boolean, default: false },
});

const actorRoleLabel = (role) => {
    if (role === 'admin') return 'Administrateur';
    if (role === 'validateur') return 'Validateur';
    return 'Demandeur';
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

const sc = statutBadgeClass;

// Code du statut choisi dans le formulaire, pour adapter les explications
const statutChoisi = computed(() => props.statuts.find(s => s.id === form.statut_id)?.code);
</script>

<template>
    <Head title="Analyse du Dossier" />
    <AuthenticatedLayout>
        <template #header>
            <div style="display:flex;align-items:center;gap:12px;">
                <Link :href="route('validateur.demandes.index')" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Retour
                </Link>
                <span class="ph-title">Dossier #{{ demande.id }}</span>
                <span v-if="demande.statut" :class="sc(demande.statut.code)">{{ demande.statut.libelle }}</span>
            </div>
        </template>

        <div class="show-layout">
            <!-- Colonne principale -->
            <div class="show-main">

                <!-- Infos générales -->
                <div class="glass-form-wrap">
                    <div class="section-head">Informations Générales</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-lbl">Demandeur</span>
                            <span class="info-val">{{ demande.user.name }}</span>
                            <span class="info-sub">{{ demande.user.email }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-lbl">Site</span>
                            <span class="info-val">{{ demande.site.nom }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-lbl">Période</span>
                            <span class="info-val">
                                {{ new Date(demande.date_debut).toLocaleDateString('fr-FR') }}
                                → {{ new Date(demande.date_fin).toLocaleDateString('fr-FR') }}
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-lbl">Activité</span>
                            <span class="info-val">{{ demande.type_demande.libelle }}</span>
                            <span class="info-sub">{{ demande.equipe_nombre }} personne(s)</span>
                        </div>
                        <div class="info-item info-item--full">
                            <span class="info-lbl">Description du projet</span>
                            <div class="info-desc">{{ demande.description }}</div>
                        </div>
                    </div>
                </div>

                <!-- Pièces jointes soumises -->
                <div class="glass-form-wrap">
                    <div class="section-head">Pièces jointes du dossier</div>
                    <div style="padding:16px">
                        <ul v-if="demande.documents?.length" class="pj-list">
                            <li v-for="doc in demande.documents" :key="doc.id" class="pj-item">
                                <span class="pj-type">{{ doc.type_document }}</span>
                                <span class="pj-name">{{ doc.nom_fichier }}</span>
                                <a :href="route('documents.show', doc.id)" class="btn-ghost" style="font-size:12px;padding:5px 12px;margin-left:auto">Télécharger</a>
                            </li>
                        </ul>
                        <p v-else style="font-size:13px;color:#9ca3af;font-style:italic">Aucune pièce jointe enregistrée pour cette demande.</p>
                    </div>
                </div>

                <!-- Analyse automatique -->
                <div class="glass-form-wrap">
                    <div class="section-head">Analyse Automatique</div>
                    <div style="padding:16px">
                        <div v-if="demande.resultats_analyse?.length > 0" class="alert-list">
                            <div v-for="(a,i) in demande.resultats_analyse" :key="i" class="alert-item alert-warn">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                <p>{{ a.message }}</p>
                            </div>
                        </div>
                        <div v-else class="alert-item alert-ok">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p>Aucun conflit détecté — aucune période sensible ne chevauche ces dates.</p>
                        </div>
                    </div>
                </div>

                <!-- Historique -->
                <div class="glass-form-wrap">
                    <div class="section-head">Historique des Décisions</div>
                    <div style="padding:16px">
                        <div class="timeline" v-if="demande.historique_statuts?.length > 0">
                            <div class="tl-item" v-for="h in demande.historique_statuts" :key="h.id">
                                <div class="tl-dot"></div>
                                <div class="tl-content">
                                    <div class="tl-meta">
                                        <span class="tl-actor">{{ h.user.name }}</span>
                                        <span
                                            v-if="h.user.role"
                                            class="tl-role"
                                            :class="{ 'tl-role--admin': h.user.role === 'admin' }"
                                        >{{ actorRoleLabel(h.user.role) }}</span>
                                        <span class="tl-sep">→</span>
                                        <span :class="sc(h.nouveau_statut.code)" style="font-size:10px">{{ h.nouveau_statut.libelle }}</span>
                                        <span class="tl-date">{{ new Date(h.date_changement).toLocaleString('fr-FR') }}</span>
                                    </div>
                                    <p v-if="h.commentaire" class="tl-comment">"{{ h.commentaire }}"</p>
                                </div>
                            </div>
                        </div>
                        <p v-else style="font-size:13px;color:#9ca3af;font-style:italic">Aucun historique pour le moment.</p>
                    </div>
                </div>

                <!-- Documents si validée -->
                <div v-if="demande.statut?.code === 'validee'" class="glass-form-wrap docs-ok">
                    <div class="section-head" style="color:#15803d">Documents Officiels Générés</div>
                    <div style="padding:16px;display:flex;gap:10px;flex-wrap:wrap">
                        <button type="button" class="btn-accent" @click="openPdf(route('demandes.pdf.autorisation', demande.id), `autorisation_${demande.id}.pdf`)">↓ Autorisation PDF</button>
                        <button type="button" class="btn-ghost" @click="openPdf(route('demandes.pdf.cahier-charges', demande.id), `cahier_des_charges_${demande.id}.pdf`)">↓ Cahier des Charges</button>
                    </div>
                </div>
            </div>

            <!-- Panneau décision -->
            <div class="decision-panel">
                <div v-if="statutLocked" class="glass-form-wrap self-decision-notice statut-locked-notice">
                    <div class="section-head">Dossier clôturé</div>
                    <p class="self-decision-text">
                        <template v-if="demande.statut?.code === 'rejetee'">
                            Cette demande a été rejetée. Aucune modification de statut n'est possible.
                        </template>
                        <template v-else-if="demande.statut?.code === 'validee'">
                            Cette demande est validée. Le dossier est définitivement clôturé.
                        </template>
                        <template v-else>
                            Ce dossier ne peut plus être modifié.
                        </template>
                    </p>
                </div>
                <div v-else-if="!canDecideOnDemande" class="glass-form-wrap self-decision-notice">
                    <div class="section-head">Décision indisponible</div>
                    <p class="self-decision-text">
                        Vous êtes le demandeur de ce dossier. Un autre administrateur ou un validateur rattaché au site doit examiner cette demande.
                    </p>
                </div>
                <div v-else class="glass-form-wrap">
                    <div class="section-head">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;color:#10b981"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        {{ viewAllDemandes ? 'Décision administrateur' : 'Rendre une Décision' }}
                    </div>
                    <p v-if="demande.statut?.code === 'a_completer'" class="admin-decision-hint waiting-hint">
                        En attente des compléments du demandeur. Le dossier reviendra automatiquement en analyse dès qu'il sera renvoyé.
                    </p>
                    <p v-if="viewAllDemandes" class="admin-decision-hint">
                        En tant qu'administrateur, votre décision sera identifiée comme telle dans l'historique et la notification au demandeur.
                    </p>
                    <form @submit.prevent="submit" class="gf-body">
                        <div>
                            <label class="gf-label">Nouveau statut</label>
                            <select v-model="form.statut_id" class="gf-select">
                                <option v-for="s in statuts" :key="s.id" :value="s.id">{{ s.libelle }}</option>
                            </select>
                            <p v-if="form.errors.statut_id" class="field-error">{{ form.errors.statut_id }}</p>
                            <p v-if="statutChoisi === 'a_completer'" class="gf-hint">
                                Le dossier sera renvoyé au demandeur, qui pourra le modifier puis le renvoyer. Précisez ci-dessous les éléments attendus.
                            </p>
                        </div>
                        <div>
                            <label class="gf-label">{{ statutChoisi === 'a_completer' ? 'Compléments attendus' : 'Commentaire / Justification' }}</label>
                            <textarea v-model="form.commentaire" rows="5" class="gf-textarea" required placeholder="Expliquez votre décision..."></textarea>
                        </div>
                        <button type="submit" :disabled="form.processing" class="btn-accent" style="width:100%;justify-content:center">
                            <svg v-if="form.processing" class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".25"/><path d="M4 12a8 8 0 018-8" fill="currentColor" opacity=".75"/></svg>
                            {{ form.processing ? 'Traitement...' : 'Enregistrer la décision' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.show-layout { display: grid; grid-template-columns: 1fr 290px; gap: 16px; align-items: start; }
@media (max-width: 900px) { .show-layout { grid-template-columns: 1fr; } }

.show-main { display: flex; flex-direction: column; gap: 16px; }

.section-head {
    display: flex; align-items: center; gap: 8px;
    padding: 14px 20px; border-bottom: 1px solid var(--c-line-soft);
    background: linear-gradient(180deg, #ffffff 0%, var(--c-surface-2) 100%);
    font-size: 13px; font-weight: 700;
    color: var(--c-text);
}
.section-head::before {
    content: '';
    width: 4px; height: 14px; border-radius: 4px;
    background: var(--c-brand);
    flex-shrink: 0;
}
.section-head:has(svg)::before { display: none; }

.info-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
    padding: 16px;
}
@media (max-width: 640px) { .info-grid { grid-template-columns: 1fr; } }

.info-item {
    display: flex; flex-direction: column; gap: 3px;
    padding: 12px 14px;
    background: var(--c-surface-2);
    border: 1px solid var(--c-line-soft);
    border-radius: var(--radius-md);
}

.info-item--full { grid-column: 1 / -1; }

.info-lbl {
    font-size: 9.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.1em; color: #6b7280;
}

.info-val { font-size: 13.5px; font-weight: 600; color: #111827; }

.info-sub { font-size: 11.5px; color: #6b7280; }

.info-desc {
    margin-top: 6px; padding: 12px 14px;
    background: var(--c-surface);
    border: 1px solid var(--c-line);
    border-radius: var(--radius-sm); font-size: 13px;
    color: #4b5563; line-height: 1.6;
}

.alert-list { display: flex; flex-direction: column; gap: 8px; }

.alert-item {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 14px; border-radius: var(--radius-md);
    font-size: 13px; line-height: 1.5;
}
.alert-item svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
.alert-item p { margin: 0; }
.alert-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; box-shadow: inset 3px 0 0 #f59e0b; }
.alert-warn svg { color: #d97706; }
.alert-ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; box-shadow: inset 3px 0 0 #10b981; }

.timeline { display: flex; flex-direction: column; gap: 18px; position: relative; }

.tl-item { display: flex; gap: 14px; align-items: flex-start; position: relative; }
.tl-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 5px; top: 16px; bottom: -18px;
    width: 2px; border-radius: 2px;
    background: linear-gradient(180deg, #a7f3d0, #e7eae9);
}

.tl-dot {
    width: 12px; height: 12px; border-radius: 50%;
    background: var(--c-brand); border: 2px solid #ffffff;
    box-shadow: 0 0 0 3px #d1fae5;
    flex-shrink: 0; margin-top: 3px; position: relative; z-index: 1;
}

.tl-content { flex: 1; }

.tl-meta { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-bottom: 5px; }

.tl-actor { font-size: 13px; font-weight: 600; color: #111827; }
.tl-role {
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
    padding: 2px 7px; border-radius: 999px; background: #e5e7eb; color: #4b5563;
}
.tl-role--admin { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
.tl-sep { color: #9ca3af; font-size: 12px; }
.admin-decision-hint {
    margin: 0; padding: 10px 18px 0;
    font-size: 12px; color: #6b7280; line-height: 1.5;
}
.tl-date { font-size: 11px; color: #6b7280; margin-left: auto; }
.waiting-hint { color: #9a3412; }

.tl-comment {
    font-size: 12px; color: #4b5563; font-style: italic;
    background: var(--c-surface-2); border: 1px solid var(--c-line-soft); border-left: 3px solid var(--c-brand);
    padding: 8px 12px; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; margin: 0;
}

.decision-panel { position: sticky; top: 96px; }
.decision-panel > .glass-form-wrap { box-shadow: var(--shadow-lg); }

.self-decision-text {
    margin: 0; padding: 14px 18px 18px;
    font-size: 13px; color: #6b7280; line-height: 1.55;
}
.self-decision-notice { border-color: #fde68a; background: #fffbeb; }
.self-decision-notice .section-head { background: transparent; border-bottom-color: #fde68a; }
.self-decision-notice .section-head::before { background: #f59e0b; }
.statut-locked-notice { border-color: #fecaca; background: #fef2f2; }
.statut-locked-notice .section-head { border-bottom-color: #fecaca; }
.statut-locked-notice .section-head::before { background: #ef4444; }
.field-error { margin: 6px 0 0; font-size: 12px; color: #dc2626; }

.pj-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.pj-item {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 10px 12px; background: var(--c-surface-2); border: 1px solid var(--c-line); border-radius: var(--radius-md);
    transition: border-color 0.2s, background 0.2s;
}
.pj-item:hover { border-color: #a7f3d0; background: #f6fdf9; }
.pj-type { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--c-brand-700); background: var(--c-brand-soft); border: 1px solid #d1fae5; padding: 2px 8px; border-radius: 999px; }
.pj-name { font-size: 13px; color: #111827; }
.docs-ok { border-color: #bbf7d0; background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%); }
.docs-ok .section-head { background: transparent; border-bottom-color: #d1fae5; }
</style>
