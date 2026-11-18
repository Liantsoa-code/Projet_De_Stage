<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\PeriodeSensible;
use App\Models\Site;
use App\Models\TypeDemande;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlateformeSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Plateforme déployée avec de vrais emails (DEPLOIEMENT.md) : vérification de l'adresse, mots de passe,
 * limiteur des envois, messages en français, données de référence et création de l'administrateur.
 */
class MiseEnProductionTest extends TestCase
{
    use RefreshDatabase;

    private const MOT_DE_PASSE = 'Mot de passe (10 caractères minimum, avec au moins une lettre et un chiffre)';

    public function test_a_new_account_must_confirm_its_email_before_using_the_platform(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Rakotoarimanana Olivier',
            'email' => 'olivier@exemple.mg',
            'password' => 'Baobab-2026',
            'password_confirmation' => 'Baobab-2026',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'olivier@exemple.mg')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class, fn (VerifyEmail $n) => $n->toMail($user)->subject === 'Confirmez votre adresse e-mail');

        $this->get('/demandes')->assertRedirect(route('verification.notice'));
        $this->get('/demandes/create')->assertRedirect(route('verification.notice'));
        // Le profil reste ouvert pour corriger une adresse mal saisie
        $this->get('/profile')->assertOk();

        $user->markEmailAsVerified();
        $this->actingAs($user->fresh())->get('/demandes')->assertOk();
    }

    public function test_an_unverified_staff_account_cannot_reach_the_instruction(): void
    {
        $admin = User::factory()->unverified()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/validateur/demandes')->assertRedirect(route('verification.notice'));
        $this->actingAs($admin)->get('/admin/users')->assertRedirect(route('verification.notice'));
    }

    public function test_weak_passwords_are_refused_in_french(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Test',
            'email' => 'faible@exemple.mg',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
        ])->assertSessionHasErrors(['password' => 'Le mot de passe doit contenir au moins un chiffre.']);

        $this->assertDatabaseMissing('t_users', ['email' => 'faible@exemple.mg']);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->from('/register')->post('/register', ['email' => "robot{$i}@exemple.mg"])
                ->assertSessionDoesntHaveErrors(['email']);
        }

        $this->from('/register')->post('/register', ['email' => 'robot6@exemple.mg'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => 'Trop de tentatives depuis votre connexion : patientez quelques minutes avant de réessayer.']);
    }

    public function test_authentication_messages_and_reset_email_are_in_french(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'mauvais'])
            ->assertSessionHasErrors(['email' => 'Adresse e-mail ou mot de passe incorrect.']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', 'Un lien de réinitialisation vient de vous être envoyé par e-mail.');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $mail = $n->toMail($user);

            return $mail->subject === 'Réinitialisez votre mot de passe' && $mail->actionText === 'Choisir un nouveau mot de passe';
        });
    }

    public function test_reference_data_seeds_the_five_protected_areas_with_their_boundaries(): void
    {
        $this->seed(PlateformeSeeder::class);

        $this->assertSame(5, Site::count());
        $this->assertSame(12, TypeDemande::count());
        foreach (Site::all() as $site) {
            $this->assertGreaterThanOrEqual(3, count($site->polygone_coordonnees), "Contour de {$site->nom}");
            $this->assertArrayHasKey('lat', $site->polygone_coordonnees[0]);
            $this->assertArrayHasKey('lng', $site->polygone_coordonnees[0]);
        }
        $this->assertSame(0, User::count());

        // Relancé après des changements faits par l'admin : rien n'est dupliqué ni écrasé
        Site::where('nom', 'Menabe Antimena')->update(['description' => 'Description revue par l\'admin']);
        $this->seed(PlateformeSeeder::class);

        $this->assertSame(5, Site::count());
        $this->assertSame(12, TypeDemande::count());
        $this->assertSame('Description revue par l\'admin', Site::where('nom', 'Menabe Antimena')->value('description'));
    }

    public function test_the_default_seeder_creates_no_demo_account_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertSame(5, Site::count());
        $this->assertSame(0, User::count());
    }

    public function test_the_admin_command_creates_a_verified_administrator(): void
    {
        $this->artisan('plateforme:admin', ['email' => 'Direction@Fanamby.org'])
            ->expectsQuestion('Nom complet', 'Direction Fanamby')
            ->expectsQuestion(self::MOT_DE_PASSE, 'Baobab-2026')
            ->expectsQuestion('Confirmez le mot de passe', 'Baobab-2026')
            ->assertExitCode(0);

        $admin = User::where('email', 'direction@fanamby.org')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check('Baobab-2026', $admin->password));

        $this->actingAs($admin)->get('/admin/sites')->assertOk();
    }

    public function test_the_admin_command_promotes_an_existing_account(): void
    {
        $site = Site::create(['nom' => 'Menabe Antimena', 'description' => '', 'polygone_coordonnees' => []]);
        $user = User::factory()->unverified()->create(['role' => 'validateur']);
        $user->sites()->attach($site);
        $ancienHash = $user->password;

        $this->artisan('plateforme:admin', ['email' => $user->email])
            ->expectsConfirmation('Le nommer administrateur ?', 'yes')
            ->expectsConfirmation('Lui donner un nouveau mot de passe ?', 'no')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame($ancienHash, $user->password);
        $this->assertSame(0, $user->sites()->count());
    }

    public function test_the_admin_command_refuses_a_weak_password(): void
    {
        $this->artisan('plateforme:admin', ['email' => 'direction@fanamby.org'])
            ->expectsQuestion('Nom complet', 'Direction Fanamby')
            ->expectsQuestion(self::MOT_DE_PASSE, 'court')
            ->expectsQuestion('Confirmez le mot de passe', 'court')
            ->expectsQuestion(self::MOT_DE_PASSE, 'court')
            ->expectsQuestion('Confirmez le mot de passe', 'court')
            ->expectsQuestion(self::MOT_DE_PASSE, 'court')
            ->expectsQuestion('Confirmez le mot de passe', 'court')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('t_users', ['email' => 'direction@fanamby.org']);
    }

    public function test_dates_use_madagascar_time_and_keep_their_day(): void
    {
        // Heure des documents officiels et de l'historique : celle de Madagascar (UTC+3)
        $this->assertSame('Indian/Antananarivo', config('app.timezone'));

        // Une date sans heure part vers l'interface en AAAA-MM-JJ : elle ne glisse pas à la veille (21 h UTC)
        $demande = (new Demande)->setAppends([])
            ->forceFill(['date_arrivee' => '2026-11-17', 'date_debut' => '2026-11-18', 'date_fin' => '2026-11-30']);
        $this->assertSame(
            ['date_arrivee' => '2026-11-17', 'date_debut' => '2026-11-18', 'date_fin' => '2026-11-30'],
            $demande->toArray(),
        );

        $periode = (new PeriodeSensible)->forceFill(['date_debut' => '2026-12-01', 'date_fin' => '2027-02-28']);
        $this->assertSame(['date_debut' => '2026-12-01', 'date_fin' => '2027-02-28'], $periode->toArray());
    }
}
