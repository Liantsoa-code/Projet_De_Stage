<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages désactivées dans config/pages.php : leur adresse affiche « Page en préparation ».
 * Seules les pages (requêtes GET) sont concernées ; les actions des pages visibles restent permises.
 */
class PagesVisibles
{
    // Routes de chaque page de config/pages.php
    private const ROUTES = [
        'mes_demandes' => ['demandes.index', 'demandes.show', 'demandes.edit'],
        'nouvelle_demande' => ['demandes.create'],
        'profil' => ['profile.*'],
        'dossiers_a_valider' => ['validateur.demandes.*'],
        'references' => ['references.*'],
        'aires_protegees' => ['admin.sites.*'],
        'periodes_sensibles' => ['admin.periode-sensibles.*'],
        'types_demandes' => ['admin.type-demandes.*'],
        'utilisateurs' => ['admin.users.*'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Visiteur non connecté : le middleware auth le redirige vers la connexion
        if (! $request->user() || ! $request->isMethod('GET')) {
            return $next($request);
        }

        foreach (self::ROUTES as $page => $routes) {
            if (! config("pages.$page", true) && $request->routeIs(...$routes)) {
                return Inertia::render('EnPreparation')->toResponse($request);
            }
        }

        return $next($request);
    }
}
