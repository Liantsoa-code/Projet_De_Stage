<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Page d'accueil publique : présentation du service et carte des aires protégées.
 */
class AccueilController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'sites' => Site::pourAccueil(),
        ]);
    }
}
