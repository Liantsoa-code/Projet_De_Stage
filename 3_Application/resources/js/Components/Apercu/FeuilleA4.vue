<script setup>
// Une page A4 d'aperçu (794 × 1123 px : 210 × 297 mm à 96 dpi, comme DomPDF). Les marges reprennent
// celles de la vue PDF ; le pied de page reste en bas de la feuille, ou après le texte s'il déborde.
defineProps({
    // Marge @page par défaut de DomPDF (1,2 cm), plus la place du pied de page
    marges: { type: String, default: '45px 45px 75px' },
});
</script>

<template>
    <div class="feuille-a4" :style="{ padding: marges }">
        <div class="feuille-a4__corps"><slot /></div>
        <div v-if="$slots.pied" class="feuille-a4__pied"><slot name="pied" /></div>
    </div>
</template>

<style scoped>
/* overflow-wrap: anywhere, comme les vues PDF : un mot trop long pour la ligne est coupé, y compris dans un tableau */
.feuille-a4 { display: flex; flex-direction: column; min-height: 1123px; background: #fff; color: #15201A; overflow-wrap: anywhere; box-shadow: 0 1px 2px rgb(21 32 26 / 0.08), 0 10px 28px -14px rgb(21 32 26 / 0.3); }
.feuille-a4__corps { padding-bottom: 24px; }
.feuille-a4__pied { margin-top: auto; padding-top: 10px; border-top: 1px solid #D3DAD1; text-align: center; color: #6E7A72; }
</style>
