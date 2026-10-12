<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';

const props = defineProps({ userEdit: Object, sites: Array });

const currentSiteIds = props.userEdit.sites.map(s => s.id);

const form = useForm({
    role: props.userEdit.role,
    sites: currentSiteIds,
});

// Les erreurs de sites arrivent sous les clés « sites » ou « sites.0 », « sites.1 »…
const sitesError = computed(() => Object.entries(form.errors).find(([key]) => key === 'sites' || key.startsWith('sites.'))?.[1]);

const submit = () => form.put(route('admin.users.update', props.userEdit.id));
</script>

<template>
    <Head title="Modifier le rôle" />
    <AuthenticatedLayout>
        <template #header>
            <div class="page-header">
                <Link :href="route('admin.users.index')" class="back-link">
                    <Icon name="arrow-left" />
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
                                    <Icon name="user" />
                                </div>
                                <div>
                                    <div class="role-option__name">Demandeur</div>
                                    <div class="role-option__desc">Peut soumettre des demandes d'accès</div>
                                </div>
                            </label>
                            <label class="role-option" :class="{ 'role-option--selected': form.role === 'validateur' }">
                                <input type="radio" v-model="form.role" value="validateur" class="sr-only" />
                                <div class="role-option__icon role-icon--validateur">
                                    <Icon name="seal-check" />
                                </div>
                                <div>
                                    <div class="role-option__name">Validateur</div>
                                    <div class="role-option__desc">Peut approuver/rejeter des dossiers</div>
                                </div>
                            </label>
                            <label class="role-option" :class="{ 'role-option--selected': form.role === 'admin' }">
                                <input type="radio" v-model="form.role" value="admin" class="sr-only" />
                                <div class="role-option__icon role-icon--admin">
                                    <Icon name="user-gear" />
                                </div>
                                <div>
                                    <div class="role-option__name">Administrateur</div>
                                    <div class="role-option__desc">Accès complet à la plateforme</div>
                                </div>
                            </label>
                        </div>
                        <InputError :message="form.errors.role" />
                    </div>

                    <div v-if="form.role === 'validateur'" class="sites-section">
                        <div class="sites-section__header">
                            <Icon name="map-pin" />
                            Sites gérés par ce validateur
                        </div>
                        <div class="sites-grid">
                            <label v-for="site in sites" :key="site.id" class="site-checkbox" :class="{ 'site-checkbox--checked': form.sites.includes(site.id) }">
                                <input type="checkbox" :value="site.id" v-model="form.sites" class="sr-only" />
                                <div class="site-checkbox__box">
                                    <Icon name="check-fat" v-if="form.sites.includes(site.id)" />
                                </div>
                                <span>{{ site.nom }}</span>
                            </label>
                        </div>
                        <InputError :message="sitesError" />
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
* { box-sizing: border-box; font-family: var(--f-body); }

.page-header { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

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
    border: 1.5px solid var(--rule); background: #fff;
    flex-shrink: 0;
    transition: border 0.2s;
}

.role-option:hover { border-color: var(--forest-line); background: var(--forest-soft); }
.role-option:focus-within { box-shadow: 0 0 0 4px var(--c-brand-ring); }

.role-option--selected { border-color: var(--c-brand); background: var(--c-brand-soft); box-shadow: none; }
.role-option--selected::after { border: 5px solid var(--c-brand); }

.role-option__icon {
    width: 38px; height: 38px; border-radius: var(--r);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.role-option__icon svg { width: 18px; height: 18px; }

.role-icon--demandeur { background: var(--forest-soft); color: var(--forest); }
.role-icon--validateur { background: var(--water-line); color: var(--water); }
.role-icon--admin { background: var(--ochre-soft); color: var(--ochre); }

.role-option__name { font-size: 13.5px; font-weight: 600; color: var(--c-ink); }
.role-option__desc { font-size: 12px; color: var(--c-muted); margin-top: 1px; }

.sites-section {
    border-radius: var(--radius-md); overflow: hidden;
    background: var(--forest-soft);
    animation: fade-up 0.3s var(--ease-out) backwards;
}

.sites-section__header {
    display: flex; align-items: center; gap: 7px;
    padding: 11px 14px; background: var(--c-brand-soft);
    font-size: 12.5px; font-weight: 700; color: var(--forest);
}
.sites-section__header svg { width: 15px; height: 15px; }

.sites-grid {
    padding: 12px; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px;
}

.site-checkbox {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px; border-radius: var(--r-sm); cursor: pointer;
    border: 1px solid transparent;
    transition: background 0.15s, border-color 0.15s; font-size: 13px; color: var(--c-text);
}
.site-checkbox:hover { background: #ffffff; border-color: var(--forest-soft); }
.site-checkbox--checked { color: var(--forest); font-weight: 500; background: #ffffff; border-color: var(--forest-line); }

.site-checkbox__box {
    width: 18px; height: 18px; border-radius: var(--r-xs);
    border: 1.5px solid var(--rule); display: flex; align-items: center;
    justify-content: center; flex-shrink: 0; background: #fff;
    transition: border-color 0.15s, background 0.15s;
}

.site-checkbox--checked .site-checkbox__box {
    background: var(--c-brand-600); border-color: var(--c-brand-600);
}

.site-checkbox__box svg { width: 11px; height: 11px; color: #fff; }

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
