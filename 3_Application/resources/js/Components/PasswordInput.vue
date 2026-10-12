<script setup>
import Icon from '@/Components/Icon.vue';
import { ref } from 'vue';

defineProps({
    id: { type: String, required: true },
    autocomplete: { type: String, default: 'current-password' },
    autofocus: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
});

const model = defineModel({ type: String, default: '' });
const visible = ref(false);
</script>

<template>
    <div class="auth-field">
        <Icon name="lock-simple" class="auth-field__icon" />
        <input
            :id="id"
            v-model="model"
            :type="visible ? 'text' : 'password'"
            class="gf-input auth-field__input auth-field__input--toggle"
            :class="{ 'auth-field__input--invalid': invalid }"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            :aria-invalid="invalid || undefined"
            required
        />
        <button
            type="button"
            class="auth-field__toggle"
            :aria-label="visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
            :aria-pressed="visible"
            @click="visible = !visible"
        >
            <Icon :name="visible ? 'eye-slash' : 'eye'" />
        </button>
    </div>
</template>
