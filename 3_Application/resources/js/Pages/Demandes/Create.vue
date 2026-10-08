<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import MapComponent from '@/Components/MapComponent.vue';
import { computed, ref } from 'vue';
import { formatDate } from '@/utils/statut.js';

const props = defineProps({
    sites: Array,
    types: Array,
    periodes: { type: Array, default: () => [] },
    // Présent en modification (brouillon ou dossier à compléter)
    demande: { type: Object, default: null },
    complement: { type: String, default: null },
});

const enEdition = computed(() => props.demande !== null);
const aCompleter = computed(() => props.demande?.statut?.code === 'a_completer');

// Stockage des fichiers uploades separement (avant transfert dans le form)
const uploadedFiles = ref({});

const form = useForm({
    site_id: props.demande?.site_id ?? '',
    type_demande_id: props.demande?.type_demande_id ?? '',
    date_debut: props.demande?.date_debut?.slice(0, 10) ?? '',
    date_fin: props.demande?.date_fin?.slice(0, 10) ?? '',
    equipe_nombre: String(props.demande?.equipe_nombre ?? '1'),
    description: props.demande?.description ?? '',
    message_complement: '',
});

const typeSelectionne = computed(() => props.types.find(t => t.id === form.type_demande_id));
const siteSelectionne = computed(() => props.sites.find(s => s.id === form.site_id));

// Pièces déjà envoyées (modification), indexées par nom de document requis
const docsExistants = computed(() => Object.fromEntries((props.demande?.documents ?? []).map(d => [d.type_document, d])));

// Date du jour au format AAAA-MM-JJ (heure locale) pour bloquer les dates passées
const today = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

// ── Périodes sensibles : alerte en direct avant l'envoi ──
const periodesSite = computed(() => props.periodes.filter(p => p.site_id === form.site_id));
const chevauchements = computed(() => {
    if (!form.date_debut) return [];
    const debut = form.date_debut;
    const fin = form.date_fin || form.date_debut;
    // Comparaison de chaînes AAAA-MM-JJ : deux intervalles se chevauchent si chacun commence avant la fin de l'autre
    return periodesSite.value.filter(p => p.date_debut <= fin && p.date_fin >= debut);
});
const bloquant = computed(() => chevauchements.value.some(p => p.niveau_restriction === 'bloquant'));
const chevauche = (p) => chevauchements.value.some(c => c.id === p.id);

// Quand le type change, on vide les fichiers precedemment choisis
function onTypeChange() {
    uploadedFiles.value = {};
}

function handleFileChange(index, event) {
    const file = event.target.files[0];
    if (file) {
        uploadedFiles.value['doc_' + index] = file;
    } else {
        delete uploadedFiles.value['doc_' + index];
    }
}

// action : 'soumettre' (dossier complet) ou 'brouillon' (enregistrement partiel)
function envoyer(action) {
    const docsRequis = typeSelectionne.value?.documents_requis ?? [];
    const docs = {};
    docsRequis.forEach((_, index) => {
        const key = 'doc_' + index;
        if (uploadedFiles.value[key]) {
            docs[key] = uploadedFiles.value[key];
        }
    });

    // Laravel attend doc_0, doc_1… à la racine du multipart, pas sous documents[].
    // En modification : POST + _method=PUT, car PHP ne lit pas les fichiers d'un vrai PUT multipart.
    const extra = enEdition.value ? { _method: 'put' } : {};
    const url = enEdition.value ? route('demandes.update', props.demande.id) : route('demandes.store');

    form.transform((data) => ({ ...data, ...docs, ...extra, action }))
        .post(url, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
}

const titre = computed(() => {
    if (aCompleter.value) return `Compléter la demande #${props.demande.id}`;
    if (enEdition.value) return `Brouillon #${props.demande.id}`;
    return 'Soumettre une Demande';
});
</script>

<template>
    <Head :title="titre" />
    <AuthenticatedLayout>
        <template #header>
            <div style="display:flex;align-items:center;gap:12px;">
                <Link :href="enEdition ? route('demandes.show', demande.id) : route('demandes.index')" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Retour
                </Link>
                <span class="ph-title">{{ titre }}</span>
            </div>
        </template>

        <div v-if="aCompleter" class="complement-box">
            <p class="complement-box__title">Le validateur demande des compléments</p>
            <p v-if="complement" class="complement-box__quote">« {{ complement }} »</p>
            <p class="complement-box__text">Corrigez les informations ou remplacez les pièces concernées, puis renvoyez le dossier.</p>
        </div>

        <div class="create-layout">
            <!-- Formulaire -->
            <div class="glass-form-wrap">
                <div class="gf-section-head">
                    <span class="gf-step">1</span>
                    <span>Détails de la demande</span>
                    <span v-if="!aCompleter" class="gf-section-hint">Vous pouvez enregistrer un brouillon à tout moment</span>
                </div>
                <form @submit.prevent="envoyer('soumettre')" class="gf-body">
                    <div v-if="form.errors.date_debut" class="gf-error-banner">
                        <strong>Erreur :</strong> {{ form.errors.date_debut }}
                    </div>

                    <div class="gf-row gf-row-2">
                        <div>
                            <label class="gf-label">Site protégé</label>
                            <select v-model="form.site_id" class="gf-select" required>
                                <option disabled value="">Sélectionnez un site</option>
                                <option v-for="s in sites" :key="s.id" :value="s.id">{{ s.nom }}</option>
                            </select>
                            <InputError class="gf-error" :message="form.errors.site_id" />
                        </div>
                        <div>
                            <label class="gf-label">Type d'activité</label>
                            <select v-model="form.type_demande_id" class="gf-select" required @change="onTypeChange">
                                <option disabled value="">Sélectionnez un type</option>
                                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.libelle }}</option>
                            </select>
                            <InputError class="gf-error" :message="form.errors.type_demande_id" />
                        </div>
                    </div>

                    <!-- Documents requis -->
                    <div v-if="typeSelectionne?.documents_requis?.length > 0" class="docs-box">
                        <div class="docs-box__head">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:15px;height:15px"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                            Documents requis pour cette activité
                        </div>
                        <div v-for="(doc, i) in typeSelectionne.documents_requis" :key="i" class="docs-box__field">
                            <label class="gf-label">{{ doc }} *</label>
                            <div v-if="docsExistants[doc]" class="doc-existant">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                <span>Déjà fourni : <a :href="route('documents.show', docsExistants[doc].id)">{{ docsExistants[doc].nom_fichier }}</a></span>
                                <span class="doc-existant__hint">Choisissez un fichier ci-dessous pour le remplacer.</span>
                            </div>
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.odt" @change="handleFileChange(i, $event)" class="file-inp" />
                            <p class="gf-hint">PDF, JPG, PNG, Word ou OpenDocument — 20 Mo max.</p>
                            <InputError class="gf-error" :message="form.errors['doc_' + i]" />
                        </div>
                    </div>

                    <div class="gf-row gf-row-3">
                        <div>
                            <label class="gf-label">Date de début</label>
                            <input type="date" class="gf-input" v-model="form.date_debut" :min="today" required />
                        </div>
                        <div>
                            <label class="gf-label">Date de fin</label>
                            <input type="date" class="gf-input" v-model="form.date_fin" :min="form.date_debut || today" required />
                            <InputError class="gf-error" :message="form.errors.date_fin" />
                        </div>
                        <div>
                            <label class="gf-label">Taille équipe</label>
                            <input type="number" min="1" class="gf-input" v-model="form.equipe_nombre" required />
                            <InputError class="gf-error" :message="form.errors.equipe_nombre" />
                        </div>
                    </div>

                    <!-- Alerte en direct : chevauchement avec une période sensible -->
                    <div v-if="chevauchements.length" class="periode-alert" :class="bloquant ? 'periode-alert--block' : 'periode-alert--warn'" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <div>
                            <p class="periode-alert__title">
                                {{ bloquant ? 'Dates interdites sur ce site' : 'Période sensible sur ces dates' }}
                            </p>
                            <p class="periode-alert__text">
                                {{ bloquant
                                    ? 'Une période bloquante empêche toute demande sur ces dates. Choisissez d\'autres dates.'
                                    : 'Votre demande reste possible mais sera signalée au validateur.' }}
                            </p>
                            <ul>
                                <li v-for="p in chevauchements" :key="p.id">
                                    <strong>{{ p.description }}</strong> — du {{ formatDate(p.date_debut) }} au {{ formatDate(p.date_fin) }}
                                    ({{ p.niveau_restriction }})
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <label class="gf-label">Description du projet</label>
                        <textarea v-model="form.description" class="gf-textarea" rows="4" required placeholder="Expliquez brièvement le but de votre demande..."></textarea>
                        <InputError class="gf-error" :message="form.errors.description" />
                    </div>

                    <div v-if="aCompleter">
                        <label class="gf-label">Message au validateur (facultatif)</label>
                        <textarea v-model="form.message_complement" class="gf-textarea" rows="3" placeholder="Précisez ce que vous avez modifié ou ajouté..."></textarea>
                        <InputError class="gf-error" :message="form.errors.message_complement" />
                    </div>

                    <div class="gf-footer">
                        <button
                            v-if="!aCompleter"
                            type="button"
                            class="btn-ghost"
                            :disabled="form.processing"
                            @click="envoyer('brouillon')"
                        >Enregistrer le brouillon</button>
                        <button type="submit" :disabled="form.processing || bloquant" class="btn-accent">
                            <svg v-if="form.processing" class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".25"/><path d="M4 12a8 8 0 018-8" fill="currentColor" opacity=".75"/></svg>
                            {{ form.processing ? 'Envoi...' : aCompleter ? 'Renvoyer le dossier complété' : 'Soumettre le dossier' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Carte + périodes sensibles du site -->
            <div class="map-card">
                <div class="map-card__head">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;color:#10b981"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    Aperçu du site
                </div>
                <div v-if="siteSelectionne" class="map-body">
                    <MapComponent :modelValue="siteSelectionne.polygone_coordonnees || []" :editable="false" :key="siteSelectionne.id" />
                    <p class="map-desc">{{ siteSelectionne.description }}</p>

                    <div class="periodes">
                        <p class="periodes__title">Périodes sensibles à venir</p>
                        <ul v-if="periodesSite.length" class="periodes__list">
                            <li
                                v-for="p in periodesSite"
                                :key="p.id"
                                class="periodes__item"
                                :class="{ 'periodes__item--hit': chevauche(p), 'periodes__item--block': p.niveau_restriction === 'bloquant' }"
                            >
                                <span class="periodes__dates">{{ formatDate(p.date_debut) }} → {{ formatDate(p.date_fin) }}</span>
                                <span class="periodes__desc">{{ p.description }}</span>
                                <span class="periodes__lvl">{{ p.niveau_restriction === 'bloquant' ? 'Bloquant' : 'Avertissement' }}</span>
                            </li>
                        </ul>
                        <p v-else class="periodes__empty">Aucune restriction prévue sur ce site.</p>
                    </div>
                </div>
                <div v-else class="map-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/></svg>
                    <span>Sélectionnez un site pour voir la carte et ses périodes sensibles</span>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.create-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 20px;
    align-items: start;
}

@media (max-width: 900px) { .create-layout { grid-template-columns: 1fr; } }

.gf-section-head {
    display: flex; align-items: center; gap: 10px;
    padding: 16px 24px; border-bottom: 1px solid var(--c-line-soft);
    background: linear-gradient(180deg, #ffffff 0%, var(--c-surface-2) 100%);
    font-size: 13px; font-weight: 700; color: var(--c-ink);
}

.gf-step {
    width: 24px; height: 24px; border-radius: 8px;
    background: linear-gradient(135deg, #34d399 0%, #059669 100%); color: #ffffff;
    font-size: 11px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 10px -2px rgba(5, 150, 105, 0.45);
}

.gf-error-banner {
    padding: 12px 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-left: 4px solid #ef4444;
    border-radius: var(--radius-md); font-size: 13px; color: #b91c1c;
}

.docs-box {
    border: 1px solid #d1fae5;
    border-radius: var(--radius-md); overflow: hidden;
    background: #f6fdf9;
}

.docs-box__head {
    display: flex; align-items: center; gap: 7px;
    padding: 11px 14px; border-bottom: 1px solid #d1fae5;
    font-size: 12px; font-weight: 700; color: #065f46;
    background: #ecfdf5;
}

.docs-box__field {
    padding: 12px 14px; border-bottom: 1px solid #e3f5ec;
    display: flex; flex-direction: column; gap: 4px;
}
.docs-box__field:last-child { border-bottom: none; }

.file-inp {
    font-size: 12.5px;
    color: var(--c-text);
    cursor: pointer;
    width: 100%;
    padding: 6px;
    background: var(--c-surface);
    border: 1px dashed #b9d9c9;
    border-radius: var(--radius-sm);
    transition: border-color 0.2s, background 0.2s;
}
.file-inp:hover { border-color: var(--c-brand); background: #fbfefc; }
.file-inp::file-selector-button {
    margin-right: 10px;
    padding: 6px 12px;
    border: 1px solid #d9dedc;
    border-radius: 8px;
    background: var(--c-surface-2);
    color: var(--c-text);
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
}
.file-inp::file-selector-button:hover { background: var(--c-brand-soft); border-color: #a7f3d0; }

.gf-footer {
    display: flex; justify-content: flex-end;
    padding-top: 16px; border-top: 1px solid var(--c-line-soft);
}

/* Map card */
.map-card {
    background: var(--c-surface);
    border: 1px solid var(--c-line);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    position: sticky; top: 96px;
}

.map-card__head {
    display: flex; align-items: center; gap: 8px;
    padding: 14px 18px; border-bottom: 1px solid var(--c-line-soft);
    background: linear-gradient(180deg, #ffffff 0%, var(--c-surface-2) 100%);
    font-size: 13px; font-weight: 600; color: var(--c-ink);
}

.map-body { padding: 12px; }
.map-body :deep(.map-container) { border-radius: var(--radius-md); }

.map-desc { font-size: 12px; color: var(--c-muted); margin: 10px 2px 2px; line-height: 1.5; }

.map-empty {
    margin: 14px;
    padding: 44px 24px;
    display: flex; flex-direction: column; align-items: center; gap: 10px;
    color: var(--c-subtle); text-align: center;
    border: 1px dashed #d9dedc;
    border-radius: var(--radius-md);
    background:
        radial-gradient(circle at 50% 35%, rgba(16, 185, 129, 0.06), transparent 60%),
        var(--c-surface-2);
}
.map-empty svg { width: 40px; height: 40px; color: #a7c4b8; }
.map-empty span { font-size: 12.5px; }

.gf-section-hint { margin-left: auto; font-size: 11.5px; font-weight: 500; color: var(--c-subtle); }
.gf-footer { gap: 10px; flex-wrap: wrap; }

/* Compléments demandés */
.complement-box {
    padding: 14px 18px; margin-bottom: 16px;
    background: linear-gradient(90deg, #fff7ed, #fffbf5);
    border: 1px solid #fed7aa; border-left: 4px solid #f97316;
    border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
}
.complement-box__title { font-size: 14px; font-weight: 700; color: var(--c-ink); }
.complement-box__quote { margin-top: 4px; font-size: 13px; font-style: italic; color: #9a3412; }
.complement-box__text { margin-top: 2px; font-size: 13px; color: var(--c-muted); }

/* Pièce déjà fournie */
.doc-existant {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    font-size: 12.5px; color: #065f46;
    padding: 6px 10px; margin-bottom: 4px;
    background: #ffffff; border: 1px solid #a7f3d0; border-radius: 8px;
}
.doc-existant svg { width: 14px; height: 14px; color: var(--c-brand); }
.doc-existant a { color: var(--c-brand-700); font-weight: 600; }
.doc-existant__hint { width: 100%; font-size: 11.5px; color: var(--c-muted); }

/* Alerte période sensible */
.periode-alert {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 12px 14px; border-radius: var(--radius-md);
    font-size: 13px; line-height: 1.5;
    animation: fade-up 0.3s var(--ease-out) backwards;
}
.periode-alert svg { width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px; }
.periode-alert--warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; box-shadow: inset 3px 0 0 #f59e0b; }
.periode-alert--warn svg { color: #d97706; }
.periode-alert--block { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; box-shadow: inset 3px 0 0 #ef4444; }
.periode-alert--block svg { color: #ef4444; }
.periode-alert__title { font-weight: 700; }
.periode-alert ul { margin-top: 6px; padding-left: 16px; }

/* Périodes sensibles du site (panneau carte) */
.periodes { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--c-line-soft); }
.periodes__title { font-size: 12px; font-weight: 700; color: var(--c-text); margin-bottom: 8px; }
.periodes__list { list-style: none; display: flex; flex-direction: column; gap: 6px; }
.periodes__item {
    display: grid; grid-template-columns: 1fr auto; gap: 2px 8px;
    padding: 8px 10px; border-radius: 10px;
    background: var(--c-surface-2); border: 1px solid var(--c-line-soft);
    box-shadow: inset 3px 0 0 #f59e0b;
}
.periodes__item--block { box-shadow: inset 3px 0 0 #ef4444; }
.periodes__item--hit { background: #fffbeb; border-color: #fde68a; }
.periodes__item--block.periodes__item--hit { background: #fef2f2; border-color: #fecaca; }
.periodes__dates { font-size: 12px; font-weight: 600; color: var(--c-ink); }
.periodes__lvl { font-size: 10.5px; font-weight: 700; color: var(--c-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.periodes__item--block .periodes__lvl { color: #b91c1c; }
.periodes__desc { grid-column: 1 / -1; font-size: 11.5px; color: var(--c-muted); }
.periodes__empty { font-size: 12px; color: var(--c-subtle); font-style: italic; }
</style>
