<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SocialUserResource;
use App\Models\FriendRequest;
use App\Models\User;
use App\Services\Social\FriendshipService;
use App\Services\Social\SocialPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FriendshipController extends Controller
{
    public function __construct(
        private FriendshipService $friends,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $me = $request->user();
        $friends = $this->friends->listFriends($me);
        $presence = SocialPresence::recentlyOnlineIds($friends->pluck('id')->all());

        $items = [];
        foreach ($friends as $f) {
            $data = (new SocialUserResource($f))->resolve();
            $items[] = array_merge($data, [
                'online_agora' => isset($presence[(int) $data['id']]),
            ]);
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function incomingRequests(Request $request): JsonResponse
    {
        $me = $request->user();

        return response()->json([
            'data' => FriendRequest::query()
                ->where('addressee_id', $me->id)
                ->where('status', FriendRequest::STATUS_PENDING)
                ->with(['requester.avatar'])
                ->orderByDesc('id')
                ->get()
                ->map(fn ($r) => $this->mapRequest($r, 'incoming')),
        ]);
    }

    public function outgoingRequests(Request $request): JsonResponse
    {
        $me = $request->user();

        return response()->json([
            'data' => FriendRequest::query()
                ->where('requester_id', $me->id)
                ->where('status', FriendRequest::STATUS_PENDING)
                ->with(['addressee.avatar'])
                ->orderByDesc('id')
                ->get()
                ->map(fn ($r) => $this->mapRequest($r, 'outgoing')),
        ]);
    }

    public function sendRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:30'],
        ]);
        $req = $this->friends->sendRequestByNickname($request->user(), (string) $data['nickname']);

        return response()->json([
            'data' => $this->mapRequest($req->load(['requester.avatar', 'addressee.avatar']), 'outgoing'),
        ], JsonResponse::HTTP_CREATED);
    }

    public function accept(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        $this->friends->accept($request->user(), $friendRequest);

        return response()->json(['data' => ['accepted' => true]]);
    }

    public function decline(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        $this->friends->decline($request->user(), $friendRequest);

        return response()->json(['data' => ['declined' => true]]);
    }

    public function cancel(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        $this->friends->cancelOutgoing($request->user(), $friendRequest);

        return response()->json(['data' => ['cancelled' => true]]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            throw ValidationException::withMessages(['user' => ['Ação inválida.']]);
        }
        $this->friends->unfriend($request->user(), $user);

        return response()->json(['data' => ['removed' => true]]);
    }

    public function block(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:30'],
        ]);
        $this->friends->blockByNickname($request->user(), (string) $data['nickname']);

        return response()->json(['data' => ['blocked' => true]], JsonResponse::HTTP_CREATED);
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $this->friends->unblock($request->user(), $user);

        return response()->json(['data' => ['unblocked' => true]]);
    }

    private function mapRequest(FriendRequest $r, string $direction): array
    {
        $payload = [
            'id' => $r->id,
            'status' => $r->status,
            'direction' => $direction,
            'created_at' => $r->created_at?->toIso8601String(),
        ];

        $peerRelation = $direction === 'incoming' ? 'requester' : 'addressee';
        $peer = $r->$peerRelation ?? null;
        if ($peer && $direction === 'incoming') {
            $payload['peer'] = (new SocialUserResource($peer))->resolve();
        }
        if ($peer && $direction === 'outgoing') {
            $payload['peer'] = (new SocialUserResource($peer))->resolve();
        }

        return $payload;
    }
}
