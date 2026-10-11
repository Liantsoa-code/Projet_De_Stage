<script setup>
defineProps({
    variant: { type: String, default: 'list' },
});
</script>

<template>
    <div class="psk" role="status" aria-live="polite" aria-busy="true">
        <span class="sr-only">Chargement de la page…</span>

        <template v-if="variant === 'dashboard'">
            <div class="psk__kpis">
                <div v-for="i in 4" :key="i" class="psk__card">
                    <span class="sk" style="width: 56px; height: 32px"></span>
                    <span class="sk" style="width: 60%; height: 13px"></span>
                    <span class="sk" style="width: 80%; height: 11px"></span>
                </div>
            </div>
            <div class="psk__cols psk__cols--dash">
                <div class="psk__panel">
                    <span class="sk psk__head"></span>
                    <span v-for="i in 4" :key="i" class="sk psk__row"></span>
                </div>
                <div class="psk__panel">
                    <span class="sk psk__head"></span>
                    <span class="sk psk__block" style="height: 380px"></span>
                </div>
            </div>
        </template>

        <template v-else-if="variant === 'form'">
            <div class="psk__cols psk__cols--side">
                <div class="psk__stack">
                    <div v-for="i in 4" :key="i" class="psk__panel">
                        <span class="sk" style="width: 200px; height: 18px"></span>
                        <div class="psk__fields">
                            <span class="sk psk__field"></span>
                            <span class="sk psk__field"></span>
                        </div>
                    </div>
                </div>
                <div class="psk__panel">
                    <span class="sk psk__head"></span>
                    <span class="sk psk__block" style="height: 220px"></span>
                    <span class="sk psk__row"></span>
                </div>
            </div>
        </template>

        <template v-else-if="variant === 'detail'">
            <span class="sk" style="width: min(520px, 90%); height: 26px"></span>
            <div class="psk__steps"><span v-for="i in 3" :key="i" class="sk"></span></div>
            <div class="psk__cols psk__cols--side">
                <div class="psk__stack">
                    <span class="sk psk__block" style="height: 110px"></span>
                    <div class="psk__panel">
                        <span class="sk psk__head"></span>
                        <span v-for="i in 3" :key="i" class="sk psk__row"></span>
                    </div>
                </div>
                <div class="psk__panel">
                    <span class="sk psk__head"></span>
                    <span v-for="i in 3" :key="i" class="sk psk__row"></span>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="psk__toolbar">
                <span class="sk" style="flex: 1; height: 38px"></span>
                <span class="sk" style="width: 160px; height: 38px"></span>
            </div>
            <div class="psk__panel psk__panel--flush">
                <span class="sk psk__thead"></span>
                <div v-for="i in 7" :key="i" class="psk__trow">
                    <span class="sk" style="width: 110px"></span>
                    <span class="sk" style="width: 28%"></span>
                    <span class="sk" style="width: 18%"></span>
                    <span class="sk" style="width: 80px"></span>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.psk { display: grid; gap: 18px; }
.psk__kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
.psk__card, .psk__panel { display: grid; gap: 12px; align-content: start; padding: 16px 18px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); }
.psk__panel--flush { gap: 0; padding: 0; overflow: hidden; }
.psk__cols { display: grid; gap: 18px; align-items: start; }
.psk__cols--dash { grid-template-columns: minmax(0, 1fr) minmax(0, 1.45fr); }
.psk__cols--side { grid-template-columns: minmax(0, 1fr) 340px; }
.psk__stack { display: grid; gap: 16px; }
.psk__head { width: 180px; height: 16px; margin-bottom: 4px; }
.psk__row { width: 100%; height: 44px; }
.psk__block { width: 100%; }
.psk__fields { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.psk__field { height: 40px; }
.psk__steps { display: grid; grid-template-columns: repeat(3, minmax(0, 180px)); gap: 4px; }
.psk__steps .sk { height: 6px; }
.psk__toolbar { display: flex; gap: 10px; padding: 10px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); }
.psk__thead { height: 38px; border-radius: 0; }
.psk__trow { display: flex; align-items: center; gap: 24px; padding: 16px 18px; }
.psk__trow + .psk__trow { border-top: 1px solid var(--rule-soft); }
.psk__trow .sk { height: 13px; }

@media (max-width: 960px) {
    .psk__cols--dash, .psk__cols--side { grid-template-columns: minmax(0, 1fr); }
}
</style>
