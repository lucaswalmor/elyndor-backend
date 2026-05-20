<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileMeRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranked_history_me_nao_confunde_com_perfil_publico(): void
    {
        $user = User::factory()->create(['nickname' => 'jogador_real']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile/me/ranked-history')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
