<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { openAuthenticatedPdf } from '@/utils/openPdf.js';
import { statutBadgeClass, formatDate } from '@/utils/statut.js';

const props = defineProps({
    demande: Object,
    complement: { type: Object, default: null },
    canEdit: Boolean,
    canDelete: Boolean,
});

const code = computed(() => props.demande.statut.code);
const docsRequis = computed(() => props.demande.type_demande?.documents_requis ?? []);
const docsManquants = computed(() => docsRequis.value.filter(n => !props.demande.documents.some(d => d.type_document === n)));

// Étapes du parcours, avec l'état de chacune selon le statut actuel
const etapes = computed(() => {
    const ordre = { brouillon: 0, soumise: 1, en_analyse: 2, a_completer: 2, validee: 3, rejetee: 3 };
    const actuel = ordre[code.value] ?? 0;
    const decision = code.value === 'validee' ? 'Validée' : code.value === 'rejetee' ? 'Rejetée' : 'Décision';
    return [
        { label: 'Brouillon', sub: 'Rédaction du dossier' },
        { label: 'Soumise', sub: formatDate(props.demande.date_soumission) },
        { label: code.value === 'a_completer' ? 'À compléter' : 'En analyse', sub: code.value === 'a_completer' ? 'Action requise' : 'Examen par le validateur' },
        { label: decision, sub: code.value === 'validee' || code.value === 'rejetee' ? 'Dossier clôturé' : 'En attente' },
    ].map((e, i) => ({
        ...e,
        etat: i < actuel ? 'done' : i === actuel ? (code.value === 'rejetee' ? 'ko' : code.value === 'a_completer' ? 'todo' : 'current') : 'next',
    }));
});

// Auteur d'une entrée d'historique, vu par le demandeur (on n'expose pas les noms des agents)
const acteur = (h) => {
    if (h.user?.id === props.demande.user_id) return 'Vous';
    return h.user?.role === 'admin' ? 'Administration' : 'Validateur';
};

const confirmDelete = ref(false);
const deleting = ref(false);
const supprimer = () => {
    deleting.value = true;
    router.delete(route('demandes.destroy', props.demande.id), { onFinish: () => (deleting.value = false) });
};

const openPdf = async (url, filename) => {
    try {
        await openAuthenticatedPdf(url, filename);
    } catch (e) {
        alert(e.message || 'Erreur lors de l\'ouverture du PDF.');
    }
};
</script>

<template>
    <Head :title="`Demande #${demande.id}`" />
    <AuthenticatedLayout>
        <template #header>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <Link :href="route('demandes.index')" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Mes demandes
                </Link>
                <span class="ph-title">Demande #{{ demande.id }}</span>
                <span :class="statutBadgeClass(code)">{{ demande.statut.libelle }}</span>
            </div>
        </template>

        <!-- Compléments demandés par le validateur -->
        <div v-if="code === 'a_completer'" class="callout callout--todo">
            <div class="callout__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
            </div>
            <div class="callout__body">
                <p class="callout__title">Des compléments sont nécessaires</p>
                <p v-if="complement?.commentaire" class="callout__quote">« {{ complement.commentaire }} »</p>
                <p class="callout__text">Modifiez votre dossier puis renvoyez-le : il repartira directement en analyse.</p>
            </div>
            <Link :href="route('demandes.edit', demande.id)" class="btn-accent">Compléter le dossier</Link>
        </div>

        <div v-else-if="code === 'brouillon'" class="callout callout--draft">
            <div class="callout__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            </div>
            <div class="callout__body">
                <p class="callout__title">Brouillon non soumis</p>
                <p class="callout__text">
                    Ce dossier n'est visible que par vous.
                    <template v-if="docsManquants.length"> Pièces encore manquantes : {{ docsManquants.join(', ') }}.</template>
                </p>
            </div>
            <Link :href="route('demandes.edit', demande.id)" class="btn-accent">Continuer</Link>
        </div>

        <div class="show-layout">
            <div class="show-main">
                <!-- Infos générales -->
                <div class="glass-form-wrap">
                    <div class="section-head">Informations générales</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-lbl">Site</span>
                            <span class="info-val">{{ demande.site.nom }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-lbl">Activité</span>
                            <span class="info-val">{{ demande.type_demande.libelle }}</span>
                            <span class="info-sub">{{ demande.equipe_nombre }} personne(s)</span>
                        </div>
                        <div class="info-item">
                            <span class="info-lbl">Période</span>
                            <span class="info-val">
                                <template v-if="demande.date_debut">{{ formatDate(demande.date_debut) }} → {{ formatDate(demande.date_fin) }}</template>
                                <template v-else>À définir</template>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-lbl">Soumise le</span>
                            <span class="info-val">{{ demande.date_soumission ? formatDate(demande.date_soumission) : 'Pas encore soumise' }}</span>
                        </div>
                        <div class="info-item info-item--full">
                            <span class="info-lbl">Description du projet</span>
                            <div class="info-desc">{{ demande.description || '—' }}</div>
                        </div>
                    </div>
                </div>

                <!-- Chevauchements détectés -->
                <div v-if="demande.resultats_analyse?.length" class="glass-form-wrap">
                    <div class="section-head">Points d'attention</div>
                    <div style="padding:16px" class="alert-list">
                        <div v-for="(a, i) in demande.resultats_analyse" :key="i" class="alert-item alert-warn">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            <p>{{ a.message }}</p>
                        </div>
                    </div>
                </div>

                <!-- Pièces jointes -->
                <div class="glass-form-wrap">
                    <div class="section-head">Pièces jointes</div>
                    <div style="padding:16px">
                        <ul v-if="demande.documents.length" class="pj-list">
                            <li v-for="doc in demande.documents" :key="doc.id" class="pj-item">
                                <span class="pj-type">{{ doc.type_document }}</span>
                                <span class="pj-name">{{ doc.nom_fichier }}</span>
                                <a :href="route('documents.show', doc.id)" class="btn-ghost" style="font-size:12px;padding:5px 12px;margin-left:auto">Télécharger</a>
                            </li>
                        </ul>
                        <p v-else class="empty-txt">Aucune pièce jointe pour le moment.</p>
                    </div>
                </div>

                <!-- Historique -->
                <div class="glass-form-wrap">
                    <div class="section-head">Historique et échanges</div>
                    <div style="padding:16px">
                        <div v-if="demande.historique_statuts.length" class="timeline">
                            <div v-for="h in demande.historique_statuts" :key="h.id" class="tl-item">
                                <div class="tl-dot"></div>
                                <div class="tl-content">
                                    <div class="tl-meta">
                                        <span class="tl-actor">{{ acteur(h) }}</span>
                                        <span class="tl-sep">→</span>
                                        <span :class="statutBadgeClass(h.nouveau_statut.code)" style="font-size:10px">{{ h.nouveau_statut.libelle }}</span>
                                        <span class="tl-date">{{ new Date(h.date_changement).toLocaleString('fr-FR') }}</span>
                                    </div>
                                    <p v-if="h.commentaire" class="tl-comment">"{{ h.commentaire }}"</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="empty-txt">
                            {{ code === 'brouillon' ? 'L\'historique démarrera à la soumission.' : 'Aucune décision pour le moment : votre dossier est en file d\'attente.' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Panneau latéral : avancement + actions -->
            <aside class="side-panel">
                <div class="glass-form-wrap">
                    <div class="section-head">Avancement</div>
                    <ol class="steps">
                        <li v-for="(e, i) in etapes" :key="i" class="step" :class="`step--${e.etat}`">
                            <span class="step__dot">
                                <svg v-if="e.etat === 'done'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                <template v-else>{{ i + 1 }}</template>
                            </span>
                            <span class="step__text">
                                <span class="step__label">{{ e.label }}</span>
                                <span class="step__sub">{{ e.sub }}</span>
                            </span>
                        </li>
                    </ol>
                </div>

                <div v-if="code === 'validee'" class="glass-form-wrap docs-ok">
                    <div class="section-head" style="color:#15803d">Documents officiels</div>
                    <div class="side-actions">
                        <button type="button" class="btn-accent" @click="openPdf(route('demandes.pdf.autorisation', demande.id), `autorisation_${demande.id}.pdf`)">↓ Autorisation PDF</button>
                        <button type="button" class="btn-ghost" @click="openPdf(route('demandes.pdf.cahier-charges', demande.id), `cahier_des_charges_${demande.id}.pdf`)">↓ Cahier des charges</button>
                    </div>
                </div>

                <div v-if="canEdit || canDelete" class="glass-form-wrap">
                    <div class="section-head">Actions</div>
                    <div class="side-actions">
                        <Link v-if="canEdit" :href="route('demandes.edit', demande.id)" class="btn-accent">
                            {{ code === 'a_completer' ? 'Compléter le dossier' : 'Modifier le brouillon' }}
                        </Link>
                        <template v-if="canDelete">
                            <button v-if="!confirmDelete" type="button" class="btn-sm-delete del-btn" @click="confirmDelete = true">Supprimer le brouillon</button>
                            <div v-else class="del-confirm">
                                <p>Supprimer définitivement ce brouillon et ses pièces jointes ?</p>
                                <div class="del-confirm__btns">
                                    <button type="button" class="btn-ghost" style="font-size:12.5px;padding:6px 12px" @click="confirmDelete = false">Annuler</button>
                                    <button type="button" class="btn-sm-delete" :disabled="deleting" @click="supprimer">Oui, supprimer</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.show-layout { display: grid; grid-template-columns: 1fr 300px; gap: 16px; align-items: start; }
@media (max-width: 900px) { .show-layout { grid-template-columns: 1fr; } }
.show-main, .side-panel { display: flex; flex-direction: column; gap: 16px; }
.side-panel { position: sticky; top: 96px; }

/* Encadrés d'action en haut de page */
.callout {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    padding: 16px 18px; margin-bottom: 16px;
    border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
    animation: fade-up 0.4s var(--ease-out) backwards;
}
.callout--todo { background: linear-gradient(90deg, #fff7ed, #fffbf5); border: 1px solid #fed7aa; border-left: 4px solid #f97316; }
.callout--draft { background: linear-gradient(90deg, #f8faf9, #ffffff); border: 1px solid var(--c-line); border-left: 4px solid #9ca3af; }
.callout__icon {
    width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: #fff; box-shadow: var(--shadow-xs);
}
.callout--todo .callout__icon { color: #ea580c; }
.callout--draft .callout__icon { color: #6b7280; }
.callout__icon svg { width: 20px; height: 20px; }
.callout__body { flex: 1; min-width: 220px; }
.callout__title { font-size: 14px; font-weight: 700; color: var(--c-ink); }
.callout__quote { margin-top: 4px; font-size: 13px; font-style: italic; color: #9a3412; }
.callout__text { margin-top: 2px; font-size: 13px; color: var(--c-muted); }

.section-head {
    display: flex; align-items: center; gap: 8px;
    padding: 14px 20px; border-bottom: 1px solid var(--c-line-soft);
    background: linear-gradient(180deg, #ffffff 0%, var(--c-surface-2) 100%);
    font-size: 13px; font-weight: 700; color: var(--c-text);
}
.section-head::before { content: ''; width: 4px; height: 14px; border-radius: 4px; background: var(--c-brand); flex-shrink: 0; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 16px; }
@media (max-width: 640px) { .info-grid { grid-template-columns: 1fr; } }
.info-item {
    display: flex; flex-direction: column; gap: 3px; padding: 12px 14px;
    background: var(--c-surface-2); border: 1px solid var(--c-line-soft); border-radius: var(--radius-md);
}
.info-item--full { grid-column: 1 / -1; }
.info-lbl { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #6b7280; }
.info-val { font-size: 13.5px; font-weight: 600; color: #111827; }
.info-sub { font-size: 11.5px; color: #6b7280; }
.info-desc {
    margin-top: 6px; padding: 12px 14px; background: var(--c-surface); border: 1px solid var(--c-line);
    border-radius: var(--radius-sm); font-size: 13px; color: #4b5563; line-height: 1.6; white-space: pre-line;
}

.alert-list { display: flex; flex-direction: column; gap: 8px; }
.alert-item { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: var(--radius-md); font-size: 13px; line-height: 1.5; }
.alert-item svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; color: #d97706; }
.alert-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; box-shadow: inset 3px 0 0 #f59e0b; }

.pj-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
.pj-item {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 10px 12px; background: var(--c-surface-2); border: 1px solid var(--c-line); border-radius: var(--radius-md);
}
.pj-type { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--c-brand-700); background: var(--c-brand-soft); border: 1px solid #d1fae5; padding: 2px 8px; border-radius: 999px; }
.pj-name { font-size: 13px; color: #111827; }
.empty-txt { font-size: 13px; color: #9ca3af; font-style: italic; }

.timeline { display: flex; flex-direction: column; gap: 18px; }
.tl-item { display: flex; gap: 14px; align-items: flex-start; position: relative; }
.tl-item:not(:last-child)::before {
    content: ''; position: absolute; left: 5px; top: 16px; bottom: -18px; width: 2px; border-radius: 2px;
    background: linear-gradient(180deg, #a7f3d0, #e7eae9);
}
.tl-dot {
    width: 12px; height: 12px; border-radius: 50%; background: var(--c-brand); border: 2px solid #fff;
    box-shadow: 0 0 0 3px #d1fae5; flex-shrink: 0; margin-top: 3px; position: relative; z-index: 1;
}
.tl-content { flex: 1; }
.tl-meta { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-bottom: 5px; }
.tl-actor { font-size: 13px; font-weight: 600; color: #111827; }
.tl-sep { color: #9ca3af; font-size: 12px; }
.tl-date { font-size: 11px; color: #6b7280; margin-left: auto; }
.tl-comment {
    font-size: 12px; color: #4b5563; font-style: italic;
    background: var(--c-surface-2); border: 1px solid var(--c-line-soft); border-left: 3px solid var(--c-brand);
    padding: 8px 12px; border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
}

/* Stepper d'avancement */
.steps { list-style: none; padding: 16px 18px; display: flex; flex-direction: column; gap: 14px; }
.step { display: flex; align-items: flex-start; gap: 12px; position: relative; }
.step:not(:last-child)::after {
    content: ''; position: absolute; left: 13px; top: 30px; bottom: -12px; width: 2px; background: var(--c-line);
}
.step--done:not(:last-child)::after { background: #a7f3d0; }
.step__dot {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700;
    background: var(--c-surface-2); border: 1.5px solid var(--c-line); color: var(--c-subtle);
}
.step__dot svg { width: 13px; height: 13px; }
.step--done .step__dot { background: var(--c-brand); border-color: var(--c-brand); color: #fff; }
.step--current .step__dot { background: var(--c-ink); border-color: var(--c-ink); color: #fff; box-shadow: 0 0 0 4px rgba(17, 24, 39, 0.08); }
.step--todo .step__dot { background: #f97316; border-color: #f97316; color: #fff; box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15); }
.step--ko .step__dot { background: #ef4444; border-color: #ef4444; color: #fff; }
.step__text { display: flex; flex-direction: column; gap: 1px; padding-top: 4px; }
.step__label { font-size: 13px; font-weight: 600; color: var(--c-ink); }
.step--next .step__label { color: var(--c-subtle); }
.step__sub { font-size: 11.5px; color: var(--c-muted); }

.docs-ok { border-color: #bbf7d0; background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%); }
.docs-ok .section-head { background: transparent; border-bottom-color: #d1fae5; }
.side-actions { padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.side-actions .btn-accent, .side-actions .btn-ghost { justify-content: center; }
.del-btn { justify-content: center; border-radius: var(--radius-sm); padding: 9px 14px; }
.del-confirm { padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-md); }
.del-confirm p { font-size: 12.5px; color: #991b1b; margin-bottom: 10px; }
.del-confirm__btns { display: flex; gap: 8px; justify-content: flex-end; }
</style>
