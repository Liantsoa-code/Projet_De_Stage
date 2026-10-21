<?php

/*
|--------------------------------------------------------------------------
| Pages affichées
|--------------------------------------------------------------------------
|
| true : la page est visible. false : elle disparaît du menu et son adresse
| affiche « Page en préparation ». Le code de la page n'est pas touché :
| remettre true suffit pour la retrouver.
|
*/

return [

    // Tableau de bord complet de l'admin et du validateur.
    // false : seules les 3 statistiques principales sont affichées.
    'tableau_de_bord_complet' => true,

    // Mon espace
    'mes_demandes' => true,
    'nouvelle_demande' => true,
    'profil' => true,

    // Instruction (validateur et admin)
    'dossiers_a_valider' => true, // « Toutes les demandes » pour l'admin
    'references' => true,

    // Administration
    'aires_protegees' => true,
    'periodes_sensibles' => true,
    'types_demandes' => true,
    'utilisateurs' => true,

];
