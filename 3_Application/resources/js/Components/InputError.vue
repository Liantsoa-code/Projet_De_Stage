<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-[12.5px] font-semibold text-signal">
            {{ message }}
        </p>
    </div>
</template>
