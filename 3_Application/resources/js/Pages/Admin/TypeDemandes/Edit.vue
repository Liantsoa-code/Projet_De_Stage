<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({ type: Object });
const form = useForm({
    libelle: props.type.libelle,
    documents_requis: Array.isArray(props.type.documents_requis) ? props.type.documents_requis.join(', ') : '',
    regles_specifiques: Array.isArray(props.type.regles_specifiques)
        ? props.type.regles_specifiques.join(', ')
        : (props.type.regles_specifiques ? JSON.stringify(props.type.regles_specifiques) : ''),
});
const submit = () => form.put(route('admin.type-demandes.update', props.type.id));
</script>

<template>
    <Head title="Modifier le Type" />
    <AuthenticatedLayout>
        <template #header>
            <div class="page-header">
                <Link :href="route('admin.type-demandes.index')" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
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
                        <input type="text" class="field-input" v-model="form.documents_requis" />
                        <p class="field-hint">Noms des documents séparés par des virgules.</p>
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
* { box-sizing: border-box; font-family: 'Inter', sans-serif; }

.page-header { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

.back-link {
    display: inline-flex; align-items: center; gap: 5px; font-size: 12px;
    color: var(--c-muted); text-decoration: none; padding: 6px 12px 6px 10px; border-radius: 999px;
    background: var(--c-surface); border: 1px solid var(--c-line); box-shadow: var(--shadow-xs);
    transition: background 0.2s, color 0.2s, border-color 0.2s;
}
.back-link svg { width: 14px; height: 14px; transition: transform 0.2s var(--ease-out); }
.back-link:hover { background: var(--c-surface-2); color: var(--c-ink); border-color: #d1d5db; }
.back-link:hover svg { transform: translateX(-2px); }

.form-wrapper { max-width: 560px; }

.form-card {
    background: var(--c-surface); border: 1px solid var(--c-line);
    border-radius: var(--radius-xl); box-shadow: var(--shadow-md); overflow: hidden;
}

.form-body { padding: 28px; display: flex; flex-direction: column; gap: 16px; }

.field-group { display: flex; flex-direction: column; gap: 4px; }
.field-label { font-size: 12px; font-weight: 600; color: var(--c-text); }

.field-select, .field-input, .field-textarea {
    width: 100%; padding: 11px 14px; border: 1px solid #d9dedc;
    border-radius: var(--radius-sm); font-size: 13px; color: var(--c-ink); background: var(--c-surface);
    outline: none; font-family: 'Inter', sans-serif;
    box-shadow: var(--shadow-xs);
    transition: border-color 0.2s, box-shadow 0.2s;
}
.field-select:hover, .field-input:hover, .field-textarea:hover { border-color: #bfc7c4; }
.field-select:focus, .field-input:focus, .field-textarea:focus {
    border-color: var(--c-brand); box-shadow: 0 0 0 4px var(--c-brand-ring);
}
.field-textarea { resize: vertical; line-height: 1.5; }
.field-error { font-size: 11px; color: #dc2626; }
.field-hint { font-size: 11.5px; color: var(--c-muted); margin: 0; line-height: 1.4; }

.form-footer {
    display: flex; justify-content: flex-end; align-items: center;
    gap: 10px; padding-top: 16px; border-top: 1px solid var(--c-line-soft);
}

.btn-cancel {
    padding: 9px 18px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500;
    color: var(--c-text); text-decoration: none; border: 1px solid #d9dedc;
    background: var(--c-surface); box-shadow: var(--shadow-xs);
    transition: background 0.2s, border-color 0.2s;
}
.btn-cancel:hover { background: var(--c-surface-2); border-color: #bfc7c4; }

.submit-btn {
    padding: 10px 20px;
    background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
    color: #fff; border: 1px solid #0b1220;
    border-radius: var(--radius-sm); font-size: 13.5px; font-weight: 600; cursor: pointer;
    font-family: 'Inter', sans-serif;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12), 0 1px 2px rgba(16, 24, 40, 0.12), 0 6px 16px -6px rgba(16, 24, 40, 0.35);
    transition: transform 0.2s var(--ease-out), box-shadow 0.2s, background 0.2s;
}
.submit-btn:hover { transform: translateY(-1px); background: linear-gradient(180deg, #273444 0%, #161f2e 100%); }
.submit-btn:active { transform: none; }
.submit-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
</style>
