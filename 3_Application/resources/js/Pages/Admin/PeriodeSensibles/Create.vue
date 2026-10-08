<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
const props = defineProps({ sites: Array });
const form = useForm({ site_id: '', date_debut: '', date_fin: '', description: '', niveau_restriction: 'avertissement' });
const submit = () => form.post(route('admin.periode-sensibles.store'));
</script>
<template>
    <Head title="Nouvelle Période Sensible" />
    <AuthenticatedLayout>
        <template #header>
            <div style="display:flex;align-items:center;gap:12px">
                <Link :href="route('admin.periode-sensibles.index')" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Retour
                </Link>
                <span class="ph-title">Nouvelle Période Sensible</span>
            </div>
        </template>
        <div style="max-width:580px">
            <div class="glass-form-wrap">
                <div class="gf-notice">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <span>Les périodes <strong>bloquantes</strong> interdisent toute demande. Les périodes <strong>avertissement</strong> alertent sans bloquer.</span>
                </div>
                <form @submit.prevent="submit" class="gf-body">
                    <div>
                        <label class="gf-label">Site protégé *</label>
                        <select v-model="form.site_id" class="gf-select" required>
                            <option disabled value="">Sélectionnez un site</option>
                            <option v-for="s in sites" :key="s.id" :value="s.id">{{ s.nom }}</option>
                        </select>
                        <InputError class="gf-error" :message="form.errors.site_id" />
                    </div>
                    <div class="gf-row gf-row-2">
                        <div>
                            <label class="gf-label">Date de début *</label>
                            <input type="date" class="gf-input" v-model="form.date_debut" required />
                            <InputError class="gf-error" :message="form.errors.date_debut" />
                        </div>
                        <div>
                            <label class="gf-label">Date de fin *</label>
                            <input type="date" class="gf-input" v-model="form.date_fin" required />
                            <InputError class="gf-error" :message="form.errors.date_fin" />
                        </div>
                    </div>
                    <div>
                        <label class="gf-label">Niveau de restriction *</label>
                        <select v-model="form.niveau_restriction" class="gf-select" required>
                            <option value="avertissement">Avertissement — alerte uniquement</option>
                            <option value="bloquant">Bloquant — interdit toute demande</option>
                        </select>
                        <InputError class="gf-error" :message="form.errors.niveau_restriction" />
                    </div>
                    <div>
                        <label class="gf-label">Description / Motif *</label>
                        <textarea class="gf-textarea" v-model="form.description" rows="3" required placeholder="Ex : Période de nidification des tortues marines..."></textarea>
                        <InputError class="gf-error" :message="form.errors.description" />
                    </div>
                    <div class="gf-footer">
                        <Link :href="route('admin.periode-sensibles.index')" class="btn-ghost">Annuler</Link>
                        <button type="submit" :disabled="form.processing" class="btn-accent">
                            {{ form.processing ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.gf-notice {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 14px 20px; border-bottom: 1px solid #fde68a;
    background: linear-gradient(90deg, #fffbeb 0%, #fffdf5 100%);
    box-shadow: inset 4px 0 0 #f59e0b;
    font-size: 12.5px; color: #92400e; line-height: 1.5;
}
.gf-notice svg { color: #d97706; }
.gf-notice strong { color: #78350f; }
.gf-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 16px; border-top: 1px solid var(--c-line-soft); }
</style>
