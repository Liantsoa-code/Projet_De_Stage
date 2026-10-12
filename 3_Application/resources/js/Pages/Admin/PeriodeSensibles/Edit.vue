<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({ periode: Object, sites: Array });

const form = useForm({
    site_id: props.periode.site_id,
    date_debut: props.periode.date_debut,
    date_fin: props.periode.date_fin,
    description: props.periode.description,
    niveau_restriction: props.periode.niveau_restriction,
});

const submit = () => form.put(route('admin.periode-sensibles.update', props.periode.id));
</script>

<template>
    <Head title="Modifier la période sensible" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-header">
                <Link :href="route('admin.periode-sensibles.index')" class="back-link">
                    <Icon name="arrow-left" />
                    Retour
                </Link>
                <span>Modifier la Période Sensible</span>
            </div>
        </template>

        <div class="form-wrapper">
            <div class="form-card">
                <form @submit.prevent="submit" class="form-body">
                    <div class="field-group">
                        <InputLabel for="site_id" value="Site protégé *" class="field-label" />
                        <select id="site_id" v-model="form.site_id" class="field-select" required>
                            <option v-for="site in sites" :key="site.id" :value="site.id">{{ site.nom }}</option>
                        </select>
                        <InputError class="field-error" :message="form.errors.site_id" />
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <InputLabel for="date_debut" value="Date de début *" class="field-label" />
                            <input id="date_debut" type="date" class="field-input" v-model="form.date_debut" required />
                            <InputError class="field-error" :message="form.errors.date_debut" />
                        </div>
                        <div class="field-group">
                            <InputLabel for="date_fin" value="Date de fin *" class="field-label" />
                            <input id="date_fin" type="date" class="field-input" v-model="form.date_fin" required />
                            <InputError class="field-error" :message="form.errors.date_fin" />
                        </div>
                    </div>

                    <div class="field-group">
                        <InputLabel for="niveau_restriction" value="Niveau de restriction *" class="field-label" />
                        <select id="niveau_restriction" v-model="form.niveau_restriction" class="field-select" required>
                            <option value="avertissement">Avertissement — demande possible mais signalée</option>
                            <option value="bloquant">Bloquant — interdit toute demande sur cette période</option>
                        </select>
                        <InputError class="field-error" :message="form.errors.niveau_restriction" />
                    </div>

                    <div class="field-group">
                        <InputLabel for="description" value="Description / Motif *" class="field-label" />
                        <textarea id="description" class="field-textarea" v-model="form.description" rows="3" required></textarea>
                        <InputError class="field-error" :message="form.errors.description" />
                    </div>

                    <div class="form-footer">
                        <Link :href="route('admin.periode-sensibles.index')" class="btn-cancel">Annuler</Link>
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

.form-wrapper { max-width: 600px; }

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

.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 640px) { .field-row { grid-template-columns: 1fr; } }

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
