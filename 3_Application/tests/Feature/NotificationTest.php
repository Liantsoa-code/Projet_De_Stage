<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notifier(User $user, int $nombre): void
    {
        for ($i = 0; $i < $nombre; $i++) {
            Notification::create(['user_id' => $user->id, 'contenu' => "Message $i", 'lu' => false, 'date_envoi' => now()]);
        }
    }

    public function test_mark_all_as_read_only_affects_own_notifications(): void
    {
        $alice = User::factory()->create(['role' => 'demandeur']);
        $bob = User::factory()->create(['role' => 'demandeur']);
        $this->notifier($alice, 3);
        $this->notifier($bob, 2);

        $this->actingAs($alice)
            ->from(route('dashboard'))
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, Notification::where('user_id', $alice->id)->where('lu', false)->count());
        // Les notifications des autres utilisateurs restent non lues
        $this->assertSame(2, Notification::where('user_id', $bob->id)->where('lu', false)->count());
    }

    public function test_mark_all_as_read_requires_authentication(): void
    {
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }
}
