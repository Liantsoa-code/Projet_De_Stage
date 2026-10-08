<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

// Paginateur Laravel sérialisé : { data, links, from, to, total, current_page, last_page, ... }
const props = defineProps({
    paginator: { type: Object, required: true },
    label: { type: String, default: 'résultats' },
});

// links[0] = précédent, links[n-1] = suivant ; entre les deux, les numéros de page (et « ... »)
const pages = computed(() => props.paginator.links.slice(1, -1));
const prev = computed(() => props.paginator.links[0]);
const next = computed(() => props.paginator.links[props.paginator.links.length - 1]);
</script>

<template>
    <div v-if="paginator.total > 0" class="pager">
        <span class="pager__info">
            {{ paginator.from }}–{{ paginator.to }} sur {{ paginator.total }} {{ label }}
        </span>

        <nav v-if="paginator.last_page > 1" class="pager__nav" aria-label="Pagination">
            <Link
                :href="prev.url ?? ''"
                class="pager__btn"
                :class="{ 'pager__btn--disabled': !prev.url }"
                preserve-scroll
                aria-label="Page précédente"
            >‹</Link>
            <template v-for="(link, i) in pages" :key="i">
                <span v-if="!link.url" class="pager__gap">…</span>
                <Link
                    v-else
                    :href="link.url"
                    class="pager__btn"
                    :class="{ 'pager__btn--active': link.active }"
                    :aria-current="link.active ? 'page' : undefined"
                    preserve-scroll
                >{{ link.label }}</Link>
            </template>
            <Link
                :href="next.url ?? ''"
                class="pager__btn"
                :class="{ 'pager__btn--disabled': !next.url }"
                preserve-scroll
                aria-label="Page suivante"
            >›</Link>
        </nav>
    </div>
</template>

<style scoped>
.pager {
    display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    margin-top: 16px; padding: 0 4px;
}
.pager__info { font-size: 12.5px; color: var(--c-muted); }
.pager__nav { display: flex; align-items: center; gap: 4px; }
.pager__btn {
    min-width: 34px; height: 34px; padding: 0 10px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 10px; border: 1px solid var(--c-line); background: var(--c-surface);
    font-size: 13px; font-weight: 600; color: var(--c-text); text-decoration: none;
    box-shadow: var(--shadow-xs);
    transition: background 0.2s, border-color 0.2s, color 0.2s;
}
.pager__btn:hover { background: var(--c-brand-soft); border-color: #a7f3d0; color: var(--c-brand-700); }
.pager__btn--active,
.pager__btn--active:hover { background: var(--c-ink); border-color: var(--c-ink); color: #fff; }
.pager__btn--disabled { opacity: 0.4; pointer-events: none; }
.pager__gap { padding: 0 4px; color: var(--c-subtle); }
</style>
