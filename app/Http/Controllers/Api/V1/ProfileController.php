<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProfileResource;
use App\Http\Resources\UserResource;
use App\Models\Avatar;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function starters(): JsonResponse
    {
        $rows = Avatar::query()
            ->where('is_starter', true)
            ->whereNotNull('image_file')
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'label', 'image_file']);

        return response()->json(['data' => $rows]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $users = User::query()
            ->with(['playerLevel', 'avatar'])
            ->orderByDesc('ranked_points')
            ->limit($limit)
            ->get(['id', 'nickname', 'ranked_points', 'ranked_wins', 'ranked_losses', 'avatar_id']);

        return response()->json([
            'data' => PublicProfileResource::collection($users),
        ]);
    }

    public function show(string $nickname): JsonResponse
    {
        $user = User::query()
            ->with(['playerLevel', 'avatar'])
            ->where('nickname', $nickname)
            ->firstOrFail();

        return response()->json(['data' => new PublicProfileResource($user)]);
    }

    public function updateCosmetics(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'avatar_id' => ['sometimes', 'integer', Rule::exists('player_avatars', 'avatar_id')->where('user_id', $user->id)],
            'card_back_slug' => ['sometimes', 'string', 'max:50'],
            'profile_bg_slug' => ['sometimes', 'string', 'max:50'],
        ]);

        if (isset($data['avatar_id'])) {
            $user->avatar_id = $data['avatar_id'];
        }
        if (isset($data['card_back_slug'])) {
            $user->card_back_slug = $data['card_back_slug'];
        }
        if (isset($data['profile_bg_slug'])) {
            $user->profile_bg_slug = $data['profile_bg_slug'];
        }
        $user->save();
        $user->load(['playerLevel', 'avatar']);

        return response()->json(['data' => new UserResource($user)]);
    }

    public function unlockSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $avatars = $user->unlockedAvatars()
            ->whereNotNull('avatars.image_file')
            ->orderBy('sort_order')
            ->get([
                'avatars.id', 'avatars.slug', 'avatars.label', 'avatars.image_file',
            ]);

        return response()->json([
            'data' => $avatars,
            'equipado_id' => $user->avatar_id,
        ]);
    }
}
