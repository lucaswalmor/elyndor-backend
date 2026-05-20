<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Social\FriendshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicProfileFriendshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_publico_inclui_friendship_quando_autenticado(): void
    {
        $visitante = User::factory()->create(['nickname' => 'VisitantePub']);
        $alvo = User::factory()->create(['nickname' => 'AlvoPub']);

        Sanctum::actingAs($visitante);

        $resposta = $this->getJson('/api/v1/profile/AlvoPub');

        $resposta->assertOk()
            ->assertJsonPath('data.friendship.status', 'none')
            ->assertJsonPath('data.friendship.friend_request_id', null);
    }

    public function test_perfil_publico_sem_friendship_para_anonimo(): void
    {
        $alvo = User::factory()->create(['nickname' => 'AlvoAnon']);

        $resposta = $this->getJson('/api/v1/profile/AlvoAnon');

        $resposta->assertOk()
            ->assertJsonMissingPath('data.friendship');
    }

    public function test_relationship_pending_outgoing(): void
    {
        $visitante = User::factory()->create(['nickname' => 'ReqPub']);
        $alvo = User::factory()->create(['nickname' => 'AddPub']);

        app(FriendshipService::class)->sendRequestByNickname($visitante, 'AddPub');

        Sanctum::actingAs($visitante);

        $this->getJson('/api/v1/profile/AddPub')
            ->assertOk()
            ->assertJsonPath('data.friendship.status', 'pending_outgoing');
    }
}
