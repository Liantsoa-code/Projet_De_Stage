<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center gap-2 rounded border border-filet bg-white px-4 py-2.5 text-sm font-semibold text-encre transition-colors hover:border-encre-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-riviere focus-visible:ring-offset-2 disabled:opacity-45"
    >
        <slot />
    </button>
</template>
