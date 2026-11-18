<?php

/*
 * Proxys de confiance, lus par le middleware TrustProxies de Laravel.
 *
 * Quand l'hébergeur, Cloudflare ou un nginx placé devant l'application gère le HTTPS, la requête
 * arrive en http jusqu'à Laravel. Sans proxy de confiance, Laravel croit le site en http : il refuse
 * les liens signés (« 403 Invalid signature » sur le lien de vérification de l'adresse email).
 *
 * TRUSTED_PROXIES=*                    faire confiance au proxy qui transmet la requête
 * TRUSTED_PROXIES=10.0.0.1,10.0.0.2    ou seulement à ces adresses IP
 * (vide)                               aucun proxy : le serveur web gère lui-même le HTTPS
 */
return [

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
