<script setup>
defineProps({
    cols: { type: Number, required: true },
    rows: { type: Number, default: 6 },
});

// Largeurs variées pour que les lignes ne ressemblent pas à une grille uniforme
const LARGEURS = ['70%', '85%', '55%', '90%', '60%', '75%'];
</script>

<template>
    <tbody aria-busy="true">
        <tr v-for="r in rows" :key="r" class="tsk">
            <td v-for="c in cols" :key="c">
                <span class="sk" :style="{ width: LARGEURS[(r + c) % LARGEURS.length], height: '13px' }"></span>
            </td>
        </tr>
    </tbody>
</template>

<style scoped>
.tsk td { padding-top: 17px; padding-bottom: 17px; }
.tsk:hover td { background: transparent !important; box-shadow: none !important; }
</style>
