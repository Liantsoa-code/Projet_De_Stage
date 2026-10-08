<script setup>
// GuestLayout — formulaire à gauche, visuel plein cadre à droite
</script>

<template>
    <div class="split-layout">
        <div class="split-form-side">
            <div class="split-form-inner">
                <!-- Logo -->
                <div class="guest-logo">
                    <img src="/assets/logo-fanamby-vertical-transparent.png" alt="Logo Fanamby">
                </div>

                <div class="guest-form-container">
                    <slot />
                </div>
            </div>
        </div>

        <!-- Image de fond sur la moitié droite -->
        <div class="split-image-side" style="background-image: url('/assets/bg-login.jpg');"></div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
body {
    font-family: 'Inter', sans-serif;
    margin: 0;
    background: #f5f7f6;
    color: #111827;
    -webkit-font-smoothing: antialiased;
}
</style>

<style scoped>
.split-layout {
    display: flex;
    min-height: 100vh;
    background: #f5f7f6;
}

.split-form-side {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 24px;
    background:
        radial-gradient(700px 420px at 0% 0%, rgba(16, 185, 129, 0.10), transparent 60%),
        radial-gradient(600px 400px at 100% 100%, rgba(52, 211, 153, 0.08), transparent 60%),
        #f7faf8;
    position: relative;
}

.split-form-inner {
    width: 100%;
    max-width: 420px;
    padding: 36px 32px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid #e7eae9;
    border-radius: 24px;
    box-shadow: 0 2px 6px rgba(16, 24, 40, 0.04), 0 24px 56px -20px rgba(16, 24, 40, 0.18);
    animation: guest-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}

.guest-logo {
    display: flex;
    justify-content: center;
    margin-bottom: 28px;
}

.guest-logo img {
    height: 100px;
    width: auto;
    object-fit: contain;
    background-color: white;
    padding: 6px;
    border-radius: 18px;
    box-shadow: 0 0 0 1px #eef1f0, 0 8px 20px -10px rgba(16, 24, 40, 0.25);
}

.guest-form-container {
    width: 100%;
}

.split-image-side {
    flex: 1.2;
    background-size: cover;
    background-position: center;
    display: none;
    margin: 16px 16px 16px 0;
    border-radius: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 24px 56px -24px rgba(6, 78, 59, 0.55);
}

.split-image-side::after {
    content: '';
    position: absolute;
    inset: 0;
    background:
        linear-gradient(180deg, rgba(6, 78, 59, 0) 45%, rgba(6, 78, 59, 0.55) 100%),
        linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, transparent 50%);
}

@media (min-width: 900px) {
    .split-image-side {
        display: block;
    }
}

@media (max-width: 480px) {
    .split-form-inner { padding: 28px 20px; border-radius: 20px; }
}

@keyframes guest-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
    .split-form-inner { animation: none; }
}
</style>
