<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({ userEdit: Object, sites: Array });

const currentSiteIds = props.userEdit.sites.map(s => s.id);

const form = useForm({
    role: props.userEdit.role,
    sites: currentSiteIds,
});

const submit = () => form.put(route('admin.users.update', props.userEdit.id));
</script>

<template>
    <Head title="Modifier le Rôle" />
    <AuthenticatedLayout>
        <template #header>
            <div class="page-header">
                <Link :href="route('admin.users.index')" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    Retour
                </Link>
                <span>{{ userEdit.name }}</span>
                <span class="user-email">{{ userEdit.email }}</span>
            </div>
        </template>

        <div class="form-wrapper">
            <div class="form-card">
                <form @submit.prevent="submit" class="form-body">
                    <div class="field-group">
                        <InputLabel value="Rôle de l'utilisateur" class="field-label" />
                        <div class="role-options">
                            <label class="role-option" :class="{ 'role-option--selected': form.role === 'demandeur' }">
                                <input type="radio" v-model="form.role" value="demandeur" class="sr-only" />
                                <div class="role-option__icon role-icon--demandeur">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                                </div>
                                <div>
                                    <div class="role-option__name">Demandeur</div>
                                    <div class="role-option__desc">Peut soumettre des demandes d'accès</div>
                                </div>
                            </label>
                            <label class="role-option" :class="{ 'role-option--selected': form.role === 'validateur' }">
                                <input type="radio" v-model="form.role" value="validateur" class="sr-only" />
                                <div class="role-option__icon role-icon--validateur">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div>
                                    <div class="role-option__name">Validateur</div>
                                    <div class="role-option__desc">Peut approuver/rejeter des dossiers</div>
                                </div>
                            </label>
                            <label class="role-option" :class="{ 'role-option--selected': form.role === 'admin' }">
                                <input type="radio" v-model="form.role" value="admin" class="sr-only" />
                                <div class="role-option__icon role-icon--admin">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" /></svg>
                                </div>
                                <div>
                                    <div class="role-option__name">Administrateur</div>
                                    <div class="role-option__desc">Accès complet à la plateforme</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div v-if="form.role === 'validateur'" class="sites-section">
                        <div class="sites-section__header">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                            Sites gérés par ce validateur
                        </div>
                        <div class="sites-grid">
                            <label v-for="site in sites" :key="site.id" class="site-checkbox" :class="{ 'site-checkbox--checked': form.sites.includes(site.id) }">
                                <input type="checkbox" :value="site.id" v-model="form.sites" class="sr-only" />
                                <div class="site-checkbox__box">
                                    <svg v-if="form.sites.includes(site.id)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                </div>
                                <span>{{ site.nom }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-footer">
                        <Link :href="route('admin.users.index')" class="btn-cancel">Annuler</Link>
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

.page-header { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.back-link {
    display: inline-flex; align-items: center; gap: 5px; font-size: 12px;
    color: var(--c-muted); text-decoration: none; padding: 6px 12px 6px 10px; border-radius: 999px;
    background: var(--c-surface); border: 1px solid var(--c-line); box-shadow: var(--shadow-xs);
    transition: background 0.2s, color 0.2s, border-color 0.2s;
}
.back-link svg { width: 14px; height: 14px; transition: transform 0.2s var(--ease-out); }
.back-link:hover { background: var(--c-surface-2); color: var(--c-ink); border-color: #d1d5db; }
.back-link:hover svg { transform: translateX(-2px); }

.form-wrapper { max-width: 600px; }

.form-card {
    background: var(--c-surface); border: 1px solid var(--c-line);
    border-radius: var(--radius-xl); box-shadow: var(--shadow-md); overflow: hidden;
}

.form-body { padding: 28px; display: flex; flex-direction: column; gap: 20px; }

.field-group { display: flex; flex-direction: column; gap: 8px; }
.field-label { font-size: 12px; font-weight: 600; color: var(--c-text); }

.user-email { font-size: 12px; color: var(--c-subtle); font-weight: 400; }

.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }

.role-options { display: flex; flex-direction: column; gap: 10px; }

.role-option {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; border: 1.5px solid var(--c-line); border-radius: var(--radius-md);
    cursor: pointer; background: var(--c-surface);
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
    position: relative;
}
/* Pastille radio à droite */
.role-option::after {
    content: '';
    margin-left: auto;
    width: 18px; height: 18px; border-radius: 50%;
    border: 1.5px solid #cfd6d3; background: #fff;
    flex-shrink: 0;
    transition: border 0.2s;
}

.role-option:hover { border-color: #a7f3d0; background: #f6fdf9; }
.role-option:focus-within { box-shadow: 0 0 0 4px var(--c-brand-ring); }

.role-option--selected { border-color: var(--c-brand); background: var(--c-brand-soft); box-shadow: 0 6px 16px -8px rgba(16, 185, 129, 0.45); }
.role-option--selected::after { border: 5px solid var(--c-brand); }

.role-option__icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.role-option__icon svg { width: 18px; height: 18px; }

.role-icon--demandeur { background: #d1fae5; color: #047857; }
.role-icon--validateur { background: #dbeafe; color: #1e40af; }
.role-icon--admin { background: #fef3c7; color: #92400e; }

.role-option__name { font-size: 13.5px; font-weight: 600; color: var(--c-ink); }
.role-option__desc { font-size: 12px; color: var(--c-muted); margin-top: 1px; }

.sites-section {
    border: 1px solid #d1fae5; border-radius: var(--radius-md); overflow: hidden;
    background: #f6fdf9;
    animation: fade-up 0.3s var(--ease-out) backwards;
}

.sites-section__header {
    display: flex; align-items: center; gap: 7px;
    padding: 11px 14px; background: var(--c-brand-soft);
    border-bottom: 1px solid #d1fae5;
    font-size: 12.5px; font-weight: 700; color: #065f46;
}
.sites-section__header svg { width: 15px; height: 15px; }

.sites-grid {
    padding: 12px; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px;
}

.site-checkbox {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px; border-radius: 8px; cursor: pointer;
    border: 1px solid transparent;
    transition: background 0.15s, border-color 0.15s; font-size: 13px; color: var(--c-text);
}
.site-checkbox:hover { background: #ffffff; border-color: #d1fae5; }
.site-checkbox--checked { color: #065f46; font-weight: 500; background: #ffffff; border-color: #a7f3d0; }

.site-checkbox__box {
    width: 18px; height: 18px; border-radius: 5px;
    border: 1.5px solid #cfd6d3; display: flex; align-items: center;
    justify-content: center; flex-shrink: 0; background: #fff;
    transition: border-color 0.15s, background 0.15s;
}

.site-checkbox--checked .site-checkbox__box {
    background: var(--c-brand-600); border-color: var(--c-brand-600);
}

.site-checkbox__box svg { width: 11px; height: 11px; color: #fff; }

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
