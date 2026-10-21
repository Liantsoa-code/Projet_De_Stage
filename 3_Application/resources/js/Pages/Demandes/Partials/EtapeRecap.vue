<script setup>
import Icon from '@/Components/Icon.vue';
import { computed } from 'vue';
import { formatDate } from '@/utils/statut.js';

// Étape « Récapitulatif » : tout le dossier avant l'envoi, avec un lien « Modifier » par partie
const props = defineProps({
    form: { type: Object, required: true },
    typeActivite: { type: Object, default: null },
    site: { type: Object, default: null },
    // État du calendrier sur les dates choisies : aucun (pas de dates), ouvert, sensible ou bloquant
    calendrier: { type: String, required: true },
    // Pièces exigées par le type, avec le fichier choisi (fichier), déjà envoyé (existant) ou repris d'un autre dossier (reprise)
    pieces: { type: Array, required: true },
});

defineEmits(['modifier']);

const joursSejour = computed(() => (props.form.date_debut && props.form.date_fin && props.form.date_fin >= props.form.date_debut
    ? Math.round((new Date(props.form.date_fin) - new Date(props.form.date_debut)) / 86400000) + 1
    : null));
</script>

<template>
    <p class="hint">Vérifiez votre dossier avant de l'envoyer. Chaque partie peut encore être modifiée.</p>
    <dl class="recap">
        <div class="recap__bloc">
            <dt>Activité et aire protégée <button type="button" class="recap__edit" @click="$emit('modifier', 'activite')">Modifier</button></dt>
            <dd><strong>{{ typeActivite?.libelle ?? '—' }}</strong> · {{ site?.nom ?? '—' }}</dd>
        </div>
        <div class="recap__bloc">
            <dt>Séjour <button type="button" class="recap__edit" @click="$emit('modifier', 'dates')">Modifier</button></dt>
            <dd>
                <span class="mono">{{ formatDate(form.date_debut) }} → {{ formatDate(form.date_fin) }}</span>
                <template v-if="joursSejour"> · {{ joursSejour }} jour{{ joursSejour > 1 ? 's' : '' }}</template>
                · {{ form.equipe_nombre }} personne{{ form.equipe_nombre > 1 ? 's' : '' }}
            </dd>
            <dd v-if="calendrier === 'bloquant'" class="recap__res recap__res--block"><Icon name="prohibit" />Période bloquante sur ces dates : envoi impossible.</dd>
            <dd v-else-if="calendrier === 'sensible'" class="recap__res recap__res--warn"><Icon name="warning" />Période sensible sur ces dates : la demande sera signalée au validateur.</dd>
            <dd v-else-if="calendrier === 'ouvert'" class="recap__res recap__res--ok"><Icon name="check-circle" />Aucune période sensible sur ces dates.</dd>
        </div>
        <div class="recap__bloc">
            <dt>Objet de la mission <button type="button" class="recap__edit" @click="$emit('modifier', 'mission')">Modifier</button></dt>
            <dd class="recap__texte">{{ form.description || '—' }}</dd>
            <dd v-if="form.message_complement" class="recap__texte"><em>Message au validateur :</em> {{ form.message_complement }}</dd>
        </div>
        <div v-if="pieces.length" class="recap__bloc">
            <dt>Pièces jointes <button type="button" class="recap__edit" @click="$emit('modifier', 'pieces')">Modifier</button></dt>
            <dd>
                <ul class="recap__pieces">
                    <li v-for="p in pieces" :key="p.id" :class="{ 'is-missing': !p.fourni }">
                        <Icon :name="p.fourni ? 'check-circle' : 'file-dashed'" />
                        <span>
                            {{ p.libelle }}
                            <small>{{ p.fichier?.name ?? p.existant?.nom_fichier ?? (p.reprise ? `${p.reprise.nom_fichier} (reprise du dossier ${p.reprise.reference})` : 'Manquante') }}</small>
                        </span>
                    </li>
                </ul>
            </dd>
        </div>
    </dl>
</template>

<style scoped>
.hint { font-size: 13.5px; color: var(--ink-3); }

.recap { display: grid; gap: 0; margin: 0; }
.recap__bloc { display: grid; gap: 6px; padding: 14px 0; border-top: 1px solid var(--rule-soft); }
.recap__bloc:first-child { border-top: 0; padding-top: 0; }
.recap dt { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font: 600 10.5px/1.3 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.recap dd { margin: 0; font-size: 14px; color: var(--ink); overflow-wrap: anywhere; }
.recap__edit { padding: 0; background: none; border: 0; font: 600 12.5px var(--f-body); text-transform: none; letter-spacing: 0; color: var(--water); cursor: pointer; }
.recap__edit:hover { text-decoration: underline; }
.recap__texte { white-space: pre-line; color: var(--ink-2); max-width: 75ch; }
.recap__res { display: flex; align-items: flex-start; gap: 6px; font-size: 13px; }
.recap__res svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
.recap__res--ok svg { color: var(--forest); }
.recap__res--warn svg { color: var(--ochre); }
.recap__res--block { color: var(--signal); font-weight: 600; }
.recap__pieces { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.recap__pieces li { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 8px; align-items: start; }
.recap__pieces svg { width: 18px; height: 18px; color: var(--forest); }
.recap__pieces small { display: block; font-size: 12.5px; color: var(--ink-3); }
.recap__pieces .is-missing svg, .recap__pieces .is-missing small { color: var(--ochre); }
</style>
