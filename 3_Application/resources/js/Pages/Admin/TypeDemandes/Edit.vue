<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import ListeEditable from '@/Components/ListeEditable.vue';

const props = defineProps({ type: Object });
const form = useForm({
    libelle: props.type.libelle,
    documents_requis: (props.type.documents_requis ?? []).map(({ id, libelle }) => ({ id, libelle })),
    regles_specifiques: (props.type.regles_specifiques ?? []).map(({ id, libelle }) => ({ id, libelle })),
});
const submit = () => form.put(route('admin.type-demandes.update', props.type.id));
</script>

<template>
    <Head title="Modifier le type" />
    <AuthenticatedLayout>
        <template #header>
            <div class="page-header">
                <Link :href="route('admin.type-demandes.index')" class="back-link">
                    <Icon name="arrow-left" />
                    Retour
                </Link>
                <span>Modifier : {{ type.libelle }}</span>
            </div>
        </template>

        <div class="form-wrapper">
            <div class="form-card">
                <form @submit.prevent="submit" class="form-body">
                    <div class="field-group">
                        <InputLabel value="Libellé *" class="field-label" />
                        <input type="text" class="field-input" v-model="form.libelle" required />
                    </div>
                    <div class="field-group">
                        <InputLabel value="Documents requis" class="field-label" />
                        <ListeEditable
                            v-model="form.documents_requis"
                            champ="documents_requis"
                            :errors="form.errors"
                            placeholder="Ex : Plan de vol drone"
                            ajouter-label="Ajouter une pièce"
                            vide="Aucune pièce exigée pour ce type."
                        />
                        <p class="field-hint">Chaque pièce sera demandée lors de la soumission. Renommer une pièce garde les fichiers déjà déposés.</p>
                    </div>
                    <div class="field-group">
                        <InputLabel value="Règles spécifiques" class="field-label" />
                        <ListeEditable
                            v-model="form.regles_specifiques"
                            champ="regles_specifiques"
                            :errors="form.errors"
                            placeholder="Ex : Équipe limitée à 10 personnes"
                            ajouter-label="Ajouter une règle"
                            vide="Aucune règle particulière."
                        />
                        <p class="field-hint">Reprises dans le cahier des charges remis avec l'autorisation.</p>
                    </div>
                    <div class="form-footer">
                        <Link :href="route('admin.type-demandes.index')" class="btn-cancel">Annuler</Link>
                        <button type="submit" :disabled="form.processing" class="submit-btn">
                            {{ form.processing ? 'Mise à jour...' : 'Mettre à jour' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; font-family: var(--f-body); }

.page-header { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

.back-link {
    display: inline-flex; align-items: center; gap: 5px; font-size: 12px;
    color: var(--c-muted); text-decoration: none; padding: 6px 12px 6px 10px; border-radius: var(--r-sm);
    background: var(--c-surface); border: 1px solid var(--c-line); box-shadow: none;
    transition: background 0.2s, color 0.2s, border-color 0.2s;
}
.back-link svg { width: 14px; height: 14px; transition: transform 0.2s var(--ease-out); }
.back-link:hover { background: var(--c-surface-2); color: var(--c-ink); border-color: var(--rule); }
.back-link:hover svg { transform: translateX(-2px); }

.form-wrapper { max-width: 560px; }

.form-card {
    background: var(--c-surface); border: 1px solid var(--c-line);
    border-radius: var(--radius-xl); box-shadow: none; overflow: hidden;
}

.form-body { padding: 28px; display: flex; flex-direction: column; gap: 16px; }

.field-group { display: flex; flex-direction: column; gap: 4px; }
.field-label { font-size: 12px; font-weight: 600; color: var(--c-text); }

.field-select, .field-input, .field-textarea {
    width: 100%; padding: 11px 14px; border: 1px solid var(--rule);
    border-radius: var(--radius-sm); font-size: 13px; color: var(--c-ink); background: var(--c-surface);
    outline: none; font-family: var(--f-body);
    box-shadow: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.field-select:hover, .field-input:hover, .field-textarea:hover { border-color: var(--rule); }
.field-select:focus, .field-input:focus, .field-textarea:focus {
    border-color: var(--c-brand); box-shadow: 0 0 0 4px var(--c-brand-ring);
}
.field-textarea { resize: vertical; line-height: 1.5; }
.field-error { font-size: 11px; color: var(--signal); }
.field-hint { font-size: 11.5px; color: var(--c-muted); margin: 0; line-height: 1.4; }

.form-footer {
    display: flex; justify-content: flex-end; align-items: center;
    gap: 10px; padding-top: 4px;
}

.btn-cancel {
    padding: 9px 18px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500;
    color: var(--c-text); text-decoration: none; border: 1px solid var(--rule);
    background: var(--c-surface); box-shadow: none;
    transition: background 0.2s, border-color 0.2s;
}
.btn-cancel:hover { background: var(--c-surface-2); border-color: var(--rule); }

.submit-btn {
    padding: 10px 20px;
    background: var(--ink);
    color: #fff; border: 1px solid var(--ink);
    border-radius: var(--radius-sm); font-size: 13.5px; font-weight: 600; cursor: pointer;
    font-family: var(--f-body);
    box-shadow: none;
    transition: transform 0.2s var(--ease-out), box-shadow 0.2s, background 0.2s;
}
.submit-btn:hover { transform: translateY(-1px); background: var(--ink); }
.submit-btn:active { transform: none; }
.submit-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
</style>
