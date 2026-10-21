<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { openPostedPdf } from '@/utils/openPdf.js';
import { computed, ref } from 'vue';

// Création ou modification d'un modèle de la page Références (admins).
const props = defineProps({
    modele: { type: Object, default: null },
    icones: { type: Array, required: true },
});

const CONTENU_VIDE = `N° [numéro]/FANAMBY/[année]

> Antananarivo, le [date]

# Titre du document

**Objet :** [objet du courrier]

[Monsieur / Madame],

Texte du document…

> **Pour la Fondation FANAMBY,**
> [nom et fonction du signataire]`;

const form = useForm({
    titre: props.modele?.titre ?? '',
    description: props.modele?.description ?? '',
    usage: props.modele?.usage ?? '',
    icone: props.modele?.icone ?? 'file-text',
    automatique: props.modele?.automatique ?? false,
    contenu: props.modele?.contenu ?? CONTENU_VIDE,
});

// Même règle que ModelesReference::champs : textes entre crochets, sans doublon
const champs = computed(() => [...new Set([...form.contenu.matchAll(/\[([^[\]\r\n]+)\]/g)].map((m) => m[1].trim()))]);

const enregistrer = () => {
    if (props.modele) form.put(route('references.modeles.update', props.modele.id));
    else form.post(route('references.modeles.store'));
};

const apercuEnCours = ref(false);
const apercu = async () => {
    apercuEnCours.value = true;
    try {
        await openPostedPdf(route('references.modeles.apercu'), { titre: form.titre, contenu: form.contenu });
    } catch (e) {
        alert(e.message);
    } finally {
        apercuEnCours.value = false;
    }
};
</script>

<template>
    <Head :title="modele ? 'Modifier le modèle' : 'Nouveau modèle'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="entete">
                <Link :href="route('references.index')" class="retour">
                    <Icon name="arrow-left" />
                    Références
                </Link>
                <span class="page-title">{{ modele ? `Modifier : ${modele.titre}` : 'Nouveau modèle' }}</span>
            </div>
        </template>

        <form class="grille" @submit.prevent="enregistrer">
            <section class="carte">
                <h2 class="carte__titre">Présentation</h2>

                <label class="champ">
                    <span class="champ__label">Titre *</span>
                    <input v-model="form.titre" type="text" class="champ__input" maxlength="150" placeholder="Ex : Avis favorable">
                    <span v-if="form.errors.titre" class="champ__erreur">{{ form.errors.titre }}</span>
                </label>

                <label class="champ">
                    <span class="champ__label">À quoi sert ce document *</span>
                    <textarea v-model="form.description" class="champ__input" rows="2" maxlength="1000" />
                    <span v-if="form.errors.description" class="champ__erreur">{{ form.errors.description }}</span>
                </label>

                <label class="champ">
                    <span class="champ__label">Quand l'utiliser <small>(facultatif)</small></span>
                    <textarea v-model="form.usage" class="champ__input" rows="2" maxlength="1000" />
                    <span v-if="form.errors.usage" class="champ__erreur">{{ form.errors.usage }}</span>
                </label>

                <fieldset class="champ">
                    <legend class="champ__label">Icône *</legend>
                    <div class="icones" role="radiogroup">
                        <label v-for="icone in icones" :key="icone" class="icone" :class="{ 'icone--on': form.icone === icone }" :title="icone">
                            <input v-model="form.icone" type="radio" name="icone" :value="icone" class="sr-only">
                            <Icon :name="icone" />
                        </label>
                    </div>
                    <span v-if="form.errors.icone" class="champ__erreur">{{ form.errors.icone }}</span>
                </fieldset>

                <label class="case">
                    <input v-model="form.automatique" type="checkbox">
                    <span>
                        Document produit par la plateforme à la validation d'un dossier
                        <small>Affiche « Généré à la validation » sur la carte. Modifier ce modèle ne change pas les documents générés : ceux-ci se relisent dans chaque dossier.</small>
                    </span>
                </label>
            </section>

            <section class="carte carte--contenu">
                <div class="carte__head">
                    <h2 class="carte__titre">Contenu du document</h2>
                    <button type="button" class="btn-ghost" :disabled="apercuEnCours" @click="apercu">
                        <Icon :name="apercuEnCours ? 'circle-notch' : 'eye'" :class="{ spin: apercuEnCours }" />
                        Aperçu PDF
                    </button>
                </div>

                <label class="champ">
                    <span class="sr-only">Contenu</span>
                    <textarea v-model="form.contenu" class="champ__input champ__input--contenu" rows="24" spellcheck="true" />
                    <span v-if="form.errors.contenu" class="champ__erreur">{{ form.errors.contenu }}</span>
                </label>

                <details class="aide" open>
                    <summary>Mise en forme</summary>
                    <dl>
                        <div><dt><code># Titre</code></dt><dd>titre du document</dd></div>
                        <div><dt><code>## Partie</code></dt><dd>titre de partie</dd></div>
                        <div><dt><code>- élément</code></dt><dd>liste à puces</dd></div>
                        <div><dt><code>&gt; texte</code></dt><dd>aligné à droite (date, destinataire, signature)</dd></div>
                        <div><dt><code>**gras**</code></dt><dd>texte en gras</dd></div>
                        <div><dt><code>[champ]</code></dt><dd>champ à compléter, surligné</dd></div>
                        <div><dt><code>...</code></dt><dd>seul sur une ligne : ligne pointillée à remplir</dd></div>
                        <div><dt>ligne vide</dt><dd>nouveau paragraphe</dd></div>
                    </dl>
                </details>

                <div class="champs-detectes">
                    <span class="champ__label">Champs à compléter détectés ({{ champs.length }})</span>
                    <ul v-if="champs.length">
                        <li v-for="c in champs" :key="c">{{ c }}</li>
                    </ul>
                    <p v-else>Aucun : écrivez un champ entre crochets, par exemple [date].</p>
                </div>
            </section>

            <div class="actions">
                <Link :href="route('references.index')" class="btn-ghost">Annuler</Link>
                <button type="submit" class="btn-accent" :disabled="form.processing">
                    <Icon name="check-circle" />
                    {{ form.processing ? 'Enregistrement…' : (modele ? 'Enregistrer les modifications' : 'Créer le modèle') }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

<style scoped>
.entete { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.retour { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px 6px 10px; border: 1px solid var(--rule); border-radius: var(--r-sm); background: var(--surface); font-size: 12.5px; color: var(--ink-2); }
.retour:hover { color: var(--ink); border-color: var(--ink-3); }
.retour svg { width: 14px; height: 14px; }

.grille { display: grid; grid-template-columns: minmax(0, 380px) minmax(0, 1fr); gap: 16px; align-items: start; }
@media (max-width: 960px) { .grille { grid-template-columns: minmax(0, 1fr); } }

.carte { display: grid; gap: 16px; padding: 20px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); min-width: 0; }
.carte__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.carte__titre { font: 700 15px/1.25 var(--f-body); color: var(--ink); }

.champ { display: grid; gap: 5px; min-width: 0; border: 0; padding: 0; margin: 0; }
.champ__label { font-size: 12px; font-weight: 600; color: var(--ink); padding: 0; }
.champ__label small { font-weight: 400; color: var(--ink-3); }
.champ__input { width: 100%; padding: 10px 12px; border: 1px solid var(--rule); border-radius: var(--r-sm); background: var(--surface); font: 400 13.5px/1.5 var(--f-body); color: var(--ink); resize: vertical; }
.champ__input:focus { outline: none; border-color: var(--forest); box-shadow: 0 0 0 3px var(--forest-soft); }
.champ__input--contenu { font: 400 13px/1.6 var(--f-mono); min-height: 360px; }
.champ__erreur { font-size: 12px; color: var(--signal); }

.icones { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
.icone { display: grid; place-items: center; width: 38px; height: 38px; border: 1px solid var(--rule); border-radius: var(--r-sm); color: var(--ink-2); cursor: pointer; }
.icone svg { width: 20px; height: 20px; }
.icone:hover { border-color: var(--ink-3); }
.icone--on { border-color: var(--forest); background: var(--forest-soft); color: var(--forest); }
.icone:has(input:focus-visible) { box-shadow: 0 0 0 3px var(--forest-soft); }

.case { display: flex; gap: 10px; align-items: flex-start; font-size: 13.5px; color: var(--ink); cursor: pointer; }
.case input { margin-top: 3px; accent-color: var(--forest); }
.case small { display: block; margin-top: 2px; font-size: 12px; color: var(--ink-3); }

.aide { font-size: 12.5px; color: var(--ink-2); background: var(--sunk); border-radius: var(--r-sm); padding: 10px 14px; }
.aide summary { cursor: pointer; font-weight: 600; color: var(--ink); }
.aide dl { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 4px 16px; margin-top: 8px; }
.aide dl div { display: flex; gap: 8px; align-items: baseline; }
.aide dt { flex-shrink: 0; }
.aide code { font: 500 12px/1.4 var(--f-mono); background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r-xs); padding: 1px 5px; }

.champs-detectes { display: grid; gap: 6px; }
.champs-detectes ul { display: flex; flex-wrap: wrap; gap: 5px; }
.champs-detectes li { padding: 2px 8px; border-radius: var(--r-sm); background: var(--water-soft); color: var(--water); font-size: 12px; }
.champs-detectes p { font-size: 12.5px; color: var(--ink-3); }

.actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }

.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .spin { animation: none; } }
</style>
