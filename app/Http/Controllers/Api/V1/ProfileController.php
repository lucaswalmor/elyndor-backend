<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProfileResource;
use App\Http\Resources\UserResource;
use App\Models\Avatar;
use App\Models\PlayerCosmeticUnlock;
use App\Models\RankedMatchOutcome;
use App\Models\User;
use App\Services\Cosmetics\CosmeticLabelService;
use App\Services\Ranked\RankedService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(
        private CosmeticLabelService $cosmeticLabels,
        private RankedService $ranked,
    ) {}

    public function starters(): JsonResponse
    {
        $rows = Avatar::query()
            ->where('is_starter', true)
            ->whereNotNull('image_file')
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'label', 'image_file']);

        return response()->json(['data' => $rows]);
    }

    /** Lista pública das divisões (elo) para filtros no ranking — sem valores min/max ao cliente. */
    public function rankedDivisionOptions(): JsonResponse
    {
        $data = collect($this->ranked->divisions())->map(fn (array $d) => [
            'key' => $d['key'],
            'label' => $d['label'],
        ])->values();

        return response()->json(['data' => $data]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $tierKey = trim((string) $request->query('divisao', ''));

        $query = User::query()
            ->where('is_bot', false)
            ->with(['playerLevel', 'avatar']);

        if ($tierKey !== '') {
            $def = $this->ranked->divisionDefinitionByKey($tierKey);
            if ($def === null) {
                throw ValidationException::withMessages([
                    'divisao' => ['Divisão inválida.'],
                ]);
            }

            $min = (int) $def['min'];
            $max = $def['max'];
            if ($max === null) {
                $query->where('ranked_points', '>=', $min);
            } else {
                $query->whereBetween('ranked_points', [$min, (int) $max]);
            }
        }

        $users = $query
            ->orderByDesc('ranked_points')
            ->limit($limit)
            ->get([
                'id', 'nickname', 'ranked_points', 'ranked_wins', 'ranked_losses',
                'total_matches_played', 'match_mode_counts', 'playtime_seconds',
                'avatar_id', 'card_back_slug', 'profile_bg_slug', 'match_board_slug',
            ]);

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

    public function myRankedHistory(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        /** @var EloquentCollection<int, RankedMatchOutcome> $rows */
        $rows = RankedMatchOutcome::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $this->serializeRankedOutcomes($rows, true),
        ]);
    }

    public function publicRankedHistory(Request $request, string $nickname): JsonResponse
    {
        $limit = min(30, max(1, (int) $request->query('limit', 12)));
        $user = User::query()->where('nickname', $nickname)->firstOrFail();
        /** @var EloquentCollection<int, RankedMatchOutcome> $rows */
        $rows = RankedMatchOutcome::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $this->serializeRankedOutcomes($rows, false),
        ]);
    }

    /**
     * @param  EloquentCollection<int, RankedMatchOutcome>  $rows
     * @return list<array<string, mixed>>
     */
    private function serializeRankedOutcomes(EloquentCollection $rows, bool $withMatchId): array
    {
        $out = [];
        foreach ($rows as $row) {
            /** @var RankedMatchOutcome $row */
            $oppKey = $row->divisao_oponente;
            $oppLabel = $this->ranked->divisionLabelForKey($oppKey);
            if ($oppLabel === null) {
                $oppLabel = ($oppKey === null || $oppKey === '') ? 'Substituto' : (string) $oppKey;
            }

            $entry = [
                'venceu' => (bool) $row->venceu,
                'delta' => (int) $row->delta,
                'pontos_antes' => (int) $row->pontos_antes,
                'pontos_depois' => (int) $row->pontos_depois,
                'divisao_oponente' => $oppKey,
                'divisao_oponente_label' => $oppLabel,
                'ocorreu_em' => $row->created_at?->toIso8601String(),
            ];
            if ($withMatchId) {
                $entry['match_id'] = (int) $row->match_id;
            }
            $out[] = $entry;
        }

        return $out;
    }

    public function updateCosmetics(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'avatar_id' => ['sometimes', 'integer', Rule::exists('player_avatars', 'avatar_id')->where('user_id', $user->id)],
            'card_back_slug' => ['sometimes', 'string', 'max:50'],
            'profile_bg_slug' => ['sometimes', 'string', 'max:50'],
            'match_board_slug' => ['sometimes', 'string', 'max:80'],
        ]);

        if (isset($data['avatar_id'])) {
            $user->avatar_id = $data['avatar_id'];
        }
        if (isset($data['card_back_slug'])) {
            $key = $this->normalizedCardBackAssetKey($data['card_back_slug']);
            if (! $this->userOwnsCardBack($user, $key)) {
                throw ValidationException::withMessages([
                    'card_back_slug' => ['Este verso não está desbloqueado.'],
                ]);
            }
            $user->card_back_slug = $data['card_back_slug'];
        }
        if (isset($data['profile_bg_slug'])) {
            $key = $this->normalizedProfileBgAssetKey($data['profile_bg_slug']);
            if (! $this->userOwnsProfileBg($user, $key)) {
                throw ValidationException::withMessages([
                    'profile_bg_slug' => ['Este fundo não está desbloqueado.'],
                ]);
            }
            $user->profile_bg_slug = $data['profile_bg_slug'];
        }
        if (isset($data['match_board_slug'])) {
            $key = $this->normalizedMatchBoardAssetKey($data['match_board_slug']);
            if (! $this->userOwnsMatchBoard($user, $key)) {
                throw ValidationException::withMessages([
                    'match_board_slug' => ['Este tabuleiro não está desbloqueado.'],
                ]);
            }
            $user->match_board_slug = $data['match_board_slug'];
        }
        $user->save();
        $user->load(['playerLevel', 'avatar']);

        return response()->json(['data' => new UserResource($user)]);
    }

    /**
     * Desbloqueios de cosméticos (baús). O frontend junta opções grátis (padrão).
     *
     * @return array{card_backs: list<array{asset_key: string, label: string}>, profile_bgs: list<array{asset_key: string, label: string}>, match_boards: list<array{asset_key: string, label: string}>}
     */
    public function cosmeticUnlocks(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = PlayerCosmeticUnlock::query()
            ->where('user_id', $user->id)
            ->whereIn('asset_category', ['card_back', 'profile_bg', 'match_board'])
            ->orderBy('asset_category')
            ->orderBy('asset_key')
            ->get(['asset_category', 'asset_key']);

        $cardBacks = $rows->where('asset_category', 'card_back')->values()->map(fn ($r) => [
            'asset_key' => $r->asset_key,
            'label' => $this->cosmeticLabels->label('card_back', $r->asset_key),
        ])->all();
        $profileBgs = $rows->where('asset_category', 'profile_bg')->values()->map(fn ($r) => [
            'asset_key' => $r->asset_key,
            'label' => $this->cosmeticLabels->label('profile_bg', $r->asset_key),
        ])->all();
        $matchBoards = $rows->where('asset_category', 'match_board')->values()->map(fn ($r) => [
            'asset_key' => $r->asset_key,
            'label' => $this->cosmeticLabels->label('match_board', $r->asset_key),
        ])->all();

        return response()->json([
            'data' => [
                'card_backs' => $cardBacks,
                'profile_bgs' => $profileBgs,
                'match_boards' => $matchBoards,
            ],
        ]);
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

    private function normalizedCardBackAssetKey(string $input): string
    {
        $s = strtolower(trim($input));

        return match ($s) {
            'padrao' => 'verso_padrao',
            'comum' => 'verso_comum',
            default => $s,
        };
    }

    private function normalizedProfileBgAssetKey(string $input): string
    {
        $s = strtolower(trim($input));

        return match ($s) {
            'padrao' => 'ui_bg_profile_standard',
            default => $s,
        };
    }

    private function userOwnsCardBack(User $user, string $assetKey): bool
    {
        if (in_array($assetKey, ['verso_padrao', 'verso_comum'], true)) {
            return true;
        }

        return PlayerCosmeticUnlock::query()
            ->where('user_id', $user->id)
            ->where('asset_category', 'card_back')
            ->where('asset_key', $assetKey)
            ->exists();
    }

    private function userOwnsProfileBg(User $user, string $assetKey): bool
    {
        if ($assetKey === 'ui_bg_profile_standard') {
            return true;
        }

        return PlayerCosmeticUnlock::query()
            ->where('user_id', $user->id)
            ->where('asset_category', 'profile_bg')
            ->where('asset_key', $assetKey)
            ->exists();
    }

    private function normalizedMatchBoardAssetKey(string $input): string
    {
        $s = strtolower(trim($input));

        return match ($s) {
            'padrao' => 'tabuleiro_padrao_v2',
            default => $s,
        };
    }

    private function userOwnsMatchBoard(User $user, string $assetKey): bool
    {
        if ($assetKey === 'tabuleiro_padrao_v2') {
            return true;
        }

        return PlayerCosmeticUnlock::query()
            ->where('user_id', $user->id)
            ->where('asset_category', 'match_board')
            ->where('asset_key', $assetKey)
            ->exists();
    }
}
