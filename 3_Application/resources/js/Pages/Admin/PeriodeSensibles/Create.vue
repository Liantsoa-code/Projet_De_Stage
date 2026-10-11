<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
const props = defineProps({ sites: Array });
const form = useForm({ site_id: '', date_debut: '', date_fin: '', description: '', niveau_restriction: 'avertissement' });
const submit = () => form.post(route('admin.periode-sensibles.store'));
</script>
<template>
    <Head title="Nouvelle période sensible" />
    <AuthenticatedLayout>
        <template #header>
            <div style="display:flex;align-items:center;gap:12px">
                <Link :href="route('admin.periode-sensibles.index')" class="back-link">
                    <Icon name="arrow-left" />
                    Retour
                </Link>
                <span class="ph-title">Nouvelle période sensible</span>
            </div>
        </template>
        <div style="max-width:580px">
            <div class="glass-form-wrap">
                <div class="gf-notice">
                    <Icon name="warning" style="width:16px;height:16px;flex-shrink:0;margin-top:1px" />
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
    padding: 14px 20px;
    background: var(--ochre-soft);
    box-shadow: none;
    font-size: 12.5px; color: var(--ochre); line-height: 1.5;
}
.gf-notice svg { color: var(--ochre); }
.gf-notice strong { color: var(--ochre); }
.gf-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 4px; }
</style>
