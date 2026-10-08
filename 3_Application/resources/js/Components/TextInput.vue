<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-lg border-gray-300 shadow-sm transition hover:border-gray-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20"
        v-model="model"
        ref="input"
    />
</template>
