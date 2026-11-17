<script setup>
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import { formatDate } from '@/utils/statut.js';

// Étape « Aire protégée et période » (section II du formulaire). L'aire protégée peut aussi
// être choisie sur la carte de VerificationCalendrier ; l'équipe sert aux statistiques de fréquentation.
// L'arrivée sur le terrain peut précéder le début des activités ; l'analyse porte sur les activités.
defineProps({
    form: { type: Object, required: true },
    sites: { type: Array, required: true },
    // Date du jour (AAAA-MM-JJ) : les dates passées ne sont pas proposées
    today: { type: String, required: true },
    // Fin au plus tard (AAAA-MM-JJ) : une demande ne dépasse pas dureeMaxMois depuis l'arrivée
    finMax: { type: String, default: '' },
    dureeMaxMois: { type: Number, required: true },
    // Périodes bloquantes sur les dates choisies : demande de dérogation, justification obligatoire
    periodesBloquantes: { type: Array, default: () => [] },
});
</script>

<template>
    <fieldset class="choix">
        <legend class="gf-label">Aire protégée concernée</legend>
        <div class="choix__grille">
            <label v-for="(s, i) in sites" :key="s.id" class="choix__case">
                <input :id="i === 0 ? 'site_id' : undefined" v-model="form.site_id" type="radio" name="site_id" :value="s.id" />
                <span>{{ s.nom }}</span>
            </label>
        </div>
        <p class="gf-hint">Une aire protégée par demande. Vous pouvez aussi la choisir sur la carte.</p>
        <InputError class="gf-error" :message="form.errors.site_id" />
    </fieldset>
    <fieldset class="choix">
        <legend class="gf-label">Période prévue</legend>
        <div class="gf-row gf-row-3">
            <div>
                <label class="gf-label gf-label--sub" for="date_arrivee">Arrivée sur le terrain</label>
                <input id="date_arrivee" v-model="form.date_arrivee" type="date" class="gf-input" :min="today" :max="form.date_debut || undefined" required />
                <InputError class="gf-error" :message="form.errors.date_arrivee" />
                <p class="gf-hint">Il est requis d'aller se présenter au bureau de Fanamby.</p>
            </div>
            <div>
                <label class="gf-label gf-label--sub" for="date_debut">Début des activités</label>
                <input id="date_debut" v-model="form.date_debut" type="date" class="gf-input" :min="form.date_arrivee || today" required />
                <InputError class="gf-error" :message="form.errors.date_debut" />
            </div>
            <div>
                <label class="gf-label gf-label--sub" for="date_fin">Fin des activités</label>
                <input id="date_fin" v-model="form.date_fin" type="date" class="gf-input" :min="form.date_debut || today" :max="finMax || undefined" required />
                <InputError class="gf-error" :message="form.errors.date_fin" />
            </div>
        </div>
        <p class="gf-hint">L'arrivée peut précéder les activités (installation, repérage). La vérification du calendrier porte sur la période des activités. Une demande dure au plus {{ dureeMaxMois }} mois, de l'arrivée à la fin des activités.</p>
        <div class="equipe">
            <label class="gf-label gf-label--sub" for="equipe_nombre">Personnes sur le terrain</label>
            <input id="equipe_nombre" v-model="form.equipe_nombre" type="number" min="1" class="gf-input" required />
            <InputError class="gf-error" :message="form.errors.equipe_nombre" />
        </div>
    </fieldset>

    <!-- Période bloquante : la demande devient une demande de dérogation, à justifier -->
    <section v-if="periodesBloquantes.length" class="derogation" role="alert">
        <div class="derogation__tete">
            <Icon name="prohibit" />
            <div>
                <strong>Ces dates tombent pendant une période d'interdiction</strong>
                <ul>
                    <li v-for="p in periodesBloquantes" :key="p.id">« {{ p.description }} », du {{ formatDate(p.date_debut) }} au {{ formatDate(p.date_fin) }}</li>
                </ul>
                <p>L'accès à l'aire protégée y est en principe interdit. Vous pouvez choisir d'autres dates, ou demander une <strong>dérogation</strong> à Fanamby : votre demande sera transmise avec votre justification, et l'équipe pourra l'accepter, vous proposer d'autres dates ou la refuser.</p>
            </div>
        </div>
        <label class="gf-label" for="derogation_motif">Pourquoi ces dates sont-elles indispensables ?</label>
        <textarea id="derogation_motif" v-model="form.derogation_motif" class="gf-textarea" rows="4" maxlength="2000" required placeholder="Ex. : le comportement à filmer n'a lieu qu'à cette période de l'année"></textarea>
        <InputError class="gf-error" :message="form.errors.derogation_motif" />
    </section>
</template>

<style scoped>
.choix { margin: 0; padding: 0; border: 0; min-width: 0; }
.choix__grille { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; }
@media (max-width: 420px) { .choix__grille { grid-template-columns: minmax(0, 1fr); } }
.choix__case { display: flex; align-items: flex-start; gap: 8px; padding: 9px 10px; border: 1px solid var(--rule); border-radius: var(--r-sm); font-size: 13.5px; cursor: pointer; }
.choix__case:hover { border-color: var(--ink-3); }
.choix__case:has(input:checked) { border-color: var(--forest); background: var(--forest-soft); }
.choix__case:has(input:focus-visible) { outline: 2px solid var(--water); outline-offset: 2px; }
.choix__case input { margin-top: 2px; accent-color: var(--forest); flex-shrink: 0; }
.gf-label--sub { font-weight: 500; color: var(--ink-3); }
.equipe { max-width: calc((100% - 32px) / 3); min-width: 160px; margin-top: 12px; }
@media (max-width: 640px) { .equipe { max-width: none; } }
.derogation { display: grid; gap: 8px; padding: 14px 16px; background: var(--signal-soft); border: 1px solid var(--signal-line); border-radius: var(--r-sm); }
.derogation__tete { display: flex; align-items: flex-start; gap: 10px; font-size: 13.5px; color: var(--ink-2); line-height: 1.5; }
.derogation__tete > svg { flex: 0 0 20px; width: 20px; height: 20px; margin-top: 1px; color: var(--signal); }
.derogation__tete strong { color: var(--ink); }
.derogation__tete > div > strong:first-child { display: block; color: var(--signal); }
.derogation__tete ul { margin: 4px 0; padding-left: 18px; }
.derogation .gf-label { margin: 4px 0 0; }
</style>
