<script setup>
import CompteurAnime from '@/Components/CompteurAnime.vue';
import Icon from '@/Components/Icon.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Statistiques principales (admin et validateur) : demandes reçues, en attente et validées.
// Reçues = en attente + validées + rejetées, d'où la barre de répartition.
const p = computed(() => usePage().props.principales || {});
const total = computed(() => p.value.recues || 0);
const part = (n) => (total.value ? `${(n / total.value) * 100}%` : '0%');
</script>

<template>
    <section class="principales" aria-label="Statistiques principales">
        <div class="principales__cartes">
            <Link :href="route('validateur.demandes.index')" class="carte carte--recues">
                <span class="carte__icone"><Icon name="files" /></span>
                <CompteurAnime class="carte__n" :valeur="p.recues ?? 0" />
                <span class="carte__l">Demandes reçues</span>
                <span class="carte__s">dont {{ p.recues_mois ?? 0 }} ce mois-ci</span>
            </Link>
            <div class="carte carte--attente">
                <span class="carte__icone"><Icon name="hourglass-medium" /></span>
                <CompteurAnime class="carte__n" :valeur="p.en_attente ?? 0" />
                <span class="carte__l">En attente</span>
                <span class="carte__s">sans décision finale<template v-if="p.chez_demandeur"> · dont {{ p.chez_demandeur }} chez le demandeur</template></span>
            </div>
            <Link :href="route('validateur.demandes.index', { statut: 'validee' })" class="carte carte--validees">
                <span class="carte__icone"><Icon name="seal-check" /></span>
                <CompteurAnime class="carte__n" :valeur="p.validees ?? 0" />
                <span class="carte__l">Validées</span>
                <span class="carte__s">
                    <template v-if="p.taux_validation != null">{{ p.taux_validation }} % des dossiers décidés</template>
                    <template v-else>aucune décision pour le moment</template>
                </span>
            </Link>
        </div>

        <div v-if="total" class="repartition">
            <div class="repartition__barre" role="img"
                :aria-label="`Sur ${total} demandes reçues : ${p.en_attente} en attente, ${p.validees} validées, ${p.rejetees} rejetées`">
                <i class="seg seg--attente" :style="{ width: part(p.en_attente) }"></i>
                <i class="seg seg--validees" :style="{ width: part(p.validees) }"></i>
                <i class="seg seg--rejetees" :style="{ width: part(p.rejetees) }"></i>
            </div>
            <ul class="repartition__legende">
                <li><i class="pastille seg--attente"></i>{{ p.en_attente }} en attente</li>
                <li><i class="pastille seg--validees"></i>{{ p.validees }} validées</li>
                <li><i class="pastille seg--rejetees"></i>{{ p.rejetees }} rejetées</li>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.principales { display: grid; gap: 12px; }
.principales__cartes { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 12px; }

.carte {
    position: relative; display: grid; align-content: start; gap: 6px;
    padding: 20px 24px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r);
    border-top: 4px solid var(--accent-carte); color: inherit; text-decoration: none;
}
a.carte:hover { border-color: var(--accent-carte); }
a.carte:focus-visible { outline: 2px solid var(--accent-carte); outline-offset: 2px; }
.carte--recues { --accent-carte: var(--ink-2); }
.carte--attente { --accent-carte: var(--water); }
.carte--validees { --accent-carte: var(--forest); }

.carte__icone { position: absolute; top: 18px; right: 20px; display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; background: var(--sunk); color: var(--accent-carte); }
.carte__icone svg { width: 20px; height: 20px; }
.carte__n { font: 700 40px/1 var(--f-display); font-variant-numeric: tabular-nums; color: var(--ink); }
.carte__l { font-size: 14.5px; font-weight: 700; }
.carte__s { font-size: 12.5px; color: var(--ink-3); }

.repartition { display: grid; gap: 8px; padding: 12px 24px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); }
.repartition__barre { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: var(--sunk); }
.seg { display: block; height: 100%; transition: width 0.6s ease; }
.seg--attente { background: var(--water); }
.seg--validees { background: var(--forest); }
.seg--rejetees { background: var(--signal); }
.repartition__legende { display: flex; flex-wrap: wrap; gap: 6px 18px; margin: 0; padding: 0; list-style: none; font-size: 12.5px; color: var(--ink-2); }
.repartition__legende li { display: inline-flex; align-items: center; gap: 6px; }
.pastille { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
</style>
