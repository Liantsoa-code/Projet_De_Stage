<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import MapComponent from '@/Components/MapComponent.vue';

const props = defineProps({ site: Object });
const form = useForm({
    nom: props.site.nom,
    description: props.site.description || '',
    polygone_coordonnees: props.site.polygone_coordonnees || [],
});
const submit = () => form.put(route('admin.sites.update', props.site.id));

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const data = JSON.parse(e.target.result);
            let ring = null;

            if (data.type === 'FeatureCollection' && data.features && data.features.length > 0) {
                const geom = data.features[0].geometry;
                if (geom.type === 'Polygon') ring = geom.coordinates[0];
                else if (geom.type === 'MultiPolygon') ring = geom.coordinates[0][0];
            } else if (data.type === 'Polygon') {
                ring = data.coordinates[0];
            } else if (data.type === 'MultiPolygon') {
                ring = data.coordinates[0][0];
            }

            if (ring) {
                const isValidLonLat = ring.every(pt => Math.abs(pt[0]) <= 180 && Math.abs(pt[1]) <= 90);
                if (!isValidLonLat) {
                    alert("Attention : Le fichier GeoJSON semble utiliser un système de projection spécifique (ex: Laborde, UTM). Les coordonnées GPS standards (EPSG:4326) sont requises.");
                    return;
                }
                form.polygone_coordonnees = ring.map(pt => ({ lat: pt[1], lng: pt[0] }));
            } else {
                alert("Impossible de trouver un polygone valide dans ce fichier GeoJSON.");
            }
        } catch (error) {
            alert("Erreur lors de la lecture du fichier GeoJSON.");
        }
        event.target.value = ''; // Reset input
    };
    reader.readAsText(file);
};
</script>

<template>
    <Head title="Modifier le Site" />
    <AuthenticatedLayout>
        <template #header>
            <div style="display:flex;align-items:center;gap:12px">
                <Link :href="route('admin.sites.index')" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Retour
                </Link>
                <span class="ph-title">Modifier : {{ site.nom }}</span>
            </div>
        </template>
        <div style="max-width:720px">
            <div class="glass-form-wrap">
                <form @submit.prevent="submit" class="gf-body">
                    <div>
                        <label class="gf-label">Nom du site *</label>
                        <input type="text" class="gf-input" v-model="form.nom" required />
                        <InputError class="gf-error" :message="form.errors.nom" />
                    </div>
                    <div>
                        <label class="gf-label">Description</label>
                        <textarea class="gf-textarea" v-model="form.description" rows="3"></textarea>
                        <InputError class="gf-error" :message="form.errors.description" />
                    </div>
                    <div>
                        <div style="display:flex; justify-content: space-between; align-items:flex-end; margin-bottom: 8px;">
                            <label class="gf-label" style="margin-bottom:0;">Délimitation géographique</label>
                            <label class="btn-ghost" style="cursor:pointer; padding: 4px 12px; font-size:12px; display:inline-flex; align-items:center; gap:6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                                Importer GeoJSON
                                <input type="file" accept=".geojson,.json" @change="handleFileUpload" style="display:none;" />
                            </label>
                        </div>
                        <div class="map-box">
                            <MapComponent v-model="form.polygone_coordonnees" :editable="true" />
                        </div>
                        <InputError class="gf-error" :message="form.errors.polygone_coordonnees" />
                    </div>
                    <div class="gf-footer">
                        <Link :href="route('admin.sites.index')" class="btn-ghost">Annuler</Link>
                        <button type="submit" :disabled="form.processing" class="btn-accent">
                            {{ form.processing ? 'Mise à jour...' : 'Mettre à jour' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.map-box { border: 1px solid var(--c-line); border-radius: var(--radius-md); overflow: hidden; min-height: 280px; box-shadow: var(--shadow-xs); }
.gf-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 16px; border-top: 1px solid var(--c-line-soft); }
</style>
