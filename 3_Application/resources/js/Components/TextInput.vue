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
        class="rounded border-filet bg-white px-3 py-2.5 text-[14.5px] text-encre shadow-none transition hover:border-encre-3 focus:border-foret focus:ring-[3px] focus:ring-foret/20"
        v-model="model"
        ref="input"
    />
</template>
