<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_remove_sessao_de_presenca(): void
    {
        $usuario = User::factory()->create(['nickname' => 'jogador_teste']);

        UserSession::query()->create([
            'user_id' => $usuario->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'last_seen_at' => now(),
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseMissing('user_sessions', [
            'user_id' => $usuario->id,
        ]);
    }

    public function test_presence_leave_remove_sessao(): void
    {
        $usuario = User::factory()->create(['nickname' => 'jogador_teste']);

        UserSession::query()->create([
            'user_id' => $usuario->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'last_seen_at' => now(),
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/v1/presence/leave')->assertOk();

        $this->assertDatabaseMissing('user_sessions', [
            'user_id' => $usuario->id,
        ]);
    }
}
