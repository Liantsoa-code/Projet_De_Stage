<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Admin\PeriodeSensibleController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\TypeDemandeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Validateur\DemandeController as ValidateurDemandeController;
use Illuminate\Support\Facades\Route;

// Page d'accueil publique : présentation et carte des aires protégées
Route::get('/', AccueilController::class);

// Tableau de bord adapté au rôle (demandeur, validateur, admin)
Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Le demandeur consulte ses dossiers, modifie ses brouillons / compléments et supprime ses brouillons.
    // Création et modification (avec envoi de fichiers) limitées par le limiteur « demandes » (AppServiceProvider).
    Route::resource('demandes', DemandeController::class)->middlewareFor(['store', 'update'], 'throttle:demandes');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/demandes/{demande}/pdf/autorisation', [PdfController::class, 'autorisation'])->name('demandes.pdf.autorisation');
    Route::get('/demandes/{demande}/pdf/cahier-charges', [PdfController::class, 'cahierDesCharges'])->name('demandes.pdf.cahier-charges');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'can:is-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('sites', SiteController::class)->except(['show']);
    Route::resource('periode-sensibles', PeriodeSensibleController::class)->except(['show']);
    Route::resource('type-demandes', TypeDemandeController::class)->except(['show']);
    Route::resource('users', UserController::class)->only(['index', 'edit', 'update']);
});

Route::middleware(['auth', 'can:is-validateur'])->prefix('validateur')->name('validateur.')->group(function () {
    Route::get('demandes', [ValidateurDemandeController::class, 'index'])->name('demandes.index');
    Route::get('demandes/export', [ValidateurDemandeController::class, 'export'])->name('demandes.export');
    Route::get('demandes/{demande}', [ValidateurDemandeController::class, 'show'])->name('demandes.show');
    Route::post('demandes/{demande}/statut', [ValidateurDemandeController::class, 'updateStatut'])->name('demandes.update-statut');
});
