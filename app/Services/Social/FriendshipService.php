<?php

namespace App\Services\Social;

use App\Events\FriendRequestReceived;
use App\Events\FriendshipAccepted;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FriendshipService
{
    public function blockedBetween(?User $a, ?User $b): bool
    {
        if (!$a || !$b || $a->id === $b->id) {
            return false;
        }

        return UserBlock::query()
            ->where(function ($q) use ($a, $b) {
                $q->where('blocker_id', $a->id)->where('blocked_id', $b->id);
            })
            ->orWhere(function ($q) use ($a, $b) {
                $q->where('blocker_id', $b->id)->where('blocked_id', $a->id);
            })
            ->exists();
    }

    public function areFriends(User $a, User $b): bool
    {
        if ($a->id === $b->id) {
            return false;
        }

        [$one, $two] = Friendship::orderedIds((int) $a->id, (int) $b->id);

        return Friendship::query()
            ->where('user_one_id', $one)
            ->where('user_two_id', $two)
            ->exists();
    }

    /**
     * Estado da relação para perfil público (visitante autenticado).
     *
     * @return array{status: string, friend_request_id: int|null}
     */
    public function relationshipForViewer(?User $viewer, User $target): array
    {
        if (! $viewer || $viewer->id === $target->id) {
            return ['status' => 'self', 'friend_request_id' => null];
        }

        if ($this->blockedBetween($viewer, $target)) {
            return ['status' => 'blocked', 'friend_request_id' => null];
        }

        if ($this->areFriends($viewer, $target)) {
            return ['status' => 'friends', 'friend_request_id' => null];
        }

        $pending = FriendRequest::query()
            ->where('status', FriendRequest::STATUS_PENDING)
            ->where(function ($q) use ($viewer, $target) {
                $q->where(function ($inner) use ($viewer, $target) {
                    $inner->where('requester_id', $viewer->id)->where('addressee_id', $target->id);
                })->orWhere(function ($inner) use ($viewer, $target) {
                    $inner->where('requester_id', $target->id)->where('addressee_id', $viewer->id);
                });
            })
            ->first();

        if ($pending) {
            $status = (int) $pending->requester_id === (int) $viewer->id
                ? 'pending_outgoing'
                : 'pending_incoming';

            return [
                'status' => $status,
                'friend_request_id' => (int) $pending->id,
            ];
        }

        return ['status' => 'none', 'friend_request_id' => null];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    public function listFriends(User $me)
    {
        $uid = (int) $me->id;
        $ids = Friendship::query()
            ->where('user_one_id', $uid)
            ->orWhere('user_two_id', $uid)
            ->get()
            ->map(function (Friendship $f) use ($uid) {
                return (int) $f->user_one_id === $uid ? (int) $f->user_two_id : (int) $f->user_one_id;
            })
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return User::query()
            ->whereIn('id', $ids)
            ->with('avatar')
            ->orderBy('nickname')
            ->get();
    }

    public function sendRequestByNickname(User $me, string $nickname): FriendRequest
    {
        $nick = trim($nickname);
        if ($nick === '') {
            throw ValidationException::withMessages(['nickname' => ['Informe um apelido.']]);
        }

        $target = User::query()->where('nickname', $nick)->with('avatar')->first();
        if (!$target) {
            throw ValidationException::withMessages(['nickname' => ['Jogador não encontrado.']]);
        }

        if ($target->id === $me->id) {
            throw ValidationException::withMessages(['nickname' => ['Você não pode adicionar a si mesmo.']]);
        }

        if ($this->blockedBetween($me, $target)) {
            throw ValidationException::withMessages(['nickname' => ['Não é possível enviar pedido.']]);
        }

        if ($this->areFriends($me, $target)) {
            throw ValidationException::withMessages(['nickname' => ['Vocês já são amigos.']]);
        }

        $inversePending = FriendRequest::query()
            ->where('requester_id', $target->id)
            ->where('addressee_id', $me->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->first();

        if ($inversePending) {
            throw ValidationException::withMessages([
                'nickname' => ['Esse jogador já te enviou um pedido — aceite em «Pedidos recebidos».'],
            ]);
        }

        UserNotification::query()
            ->where('user_id', $target->id)
            ->where('type', UserNotification::TYPE_FRIEND_REQUEST)
            ->whereNull('read_at')
            ->where('payload->requester_id', $me->id)
            ->delete();

        $request = FriendRequest::query()->updateOrCreate(
            [
                'requester_id' => $me->id,
                'addressee_id' => $target->id,
            ],
            ['status' => FriendRequest::STATUS_PENDING],
        );

        $notification = UserNotification::query()->create([
            'user_id' => $target->id,
            'type' => UserNotification::TYPE_FRIEND_REQUEST,
            'payload' => [
                'request_id' => $request->id,
                'requester_id' => $me->id,
                'requester_nickname' => $me->nickname,
            ],
        ]);

        event(new FriendRequestReceived($request->fresh(['requester.avatar']), $notification));

        return $request->fresh(['requester.avatar', 'addressee.avatar']);
    }

    public function cancelOutgoing(User $me, FriendRequest $req): void
    {
        if ($req->requester_id !== $me->id) {
            abort(403);
        }
        if ($req->status !== FriendRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['request' => ['Este pedido não está pendente.']]);
        }
        $req->update(['status' => FriendRequest::STATUS_CANCELLED]);
    }

    public function decline(User $me, FriendRequest $req): void
    {
        if ($req->addressee_id !== $me->id) {
            abort(403);
        }
        if ($req->status !== FriendRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['request' => ['Este pedido não está pendente.']]);
        }
        $req->update(['status' => FriendRequest::STATUS_DECLINED]);
    }

    public function accept(User $me, FriendRequest $req): void
    {
        if ($req->addressee_id !== $me->id) {
            abort(403);
        }
        if ($req->status !== FriendRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['request' => ['Este pedido não está pendente.']]);
        }

        $requester = User::query()->findOrFail($req->requester_id);

        if ($this->blockedBetween($me, $requester)) {
            throw ValidationException::withMessages(['request' => ['Não é possível aceitar este pedido.']]);
        }

        DB::transaction(function () use ($req, $me, $requester) {
            [$one, $two] = Friendship::orderedIds((int) $req->requester_id, (int) $req->addressee_id);
            Friendship::query()->firstOrCreate(
                ['user_one_id' => $one, 'user_two_id' => $two],
                [],
            );
            $req->update(['status' => FriendRequest::STATUS_ACCEPTED]);
        });

        $notification = UserNotification::query()->create([
            'user_id' => $requester->id,
            'type' => UserNotification::TYPE_FRIEND_ACCEPTED,
            'payload' => [
                'friend_id' => $me->id,
                'friend_nickname' => $me->nickname,
            ],
        ]);

        $me->load('avatar');
        event(new FriendshipAccepted($me, $requester, $notification));
    }

    public function unfriend(User $me, User $other): void
    {
        if ($other->id === $me->id) {
            abort(422);
        }
        [$one, $two] = Friendship::orderedIds((int) $me->id, (int) $other->id);
        Friendship::query()
            ->where('user_one_id', $one)
            ->where('user_two_id', $two)
            ->delete();
    }

    public function blockByNickname(User $me, string $nickname): UserBlock
    {
        $nick = trim($nickname);
        if ($nick === '') {
            throw ValidationException::withMessages(['nickname' => ['Informe um apelido.']]);
        }

        $target = User::query()->where('nickname', $nick)->first();
        if (!$target) {
            throw ValidationException::withMessages(['nickname' => ['Jogador não encontrado.']]);
        }

        if ($target->id === $me->id) {
            throw ValidationException::withMessages(['nickname' => ['Ação inválida.']]);
        }

        return DB::transaction(function () use ($me, $target) {
            $this->unfriend($me, $target);

            FriendRequest::query()
                ->where(
                    fn ($q) => $q->where('requester_id', $me->id)->where('addressee_id', $target->id)
                        ->orWhere('requester_id', $target->id)->where('addressee_id', $me->id),
                )
                ->where('status', FriendRequest::STATUS_PENDING)
                ->update(['status' => FriendRequest::STATUS_CANCELLED]);

            return UserBlock::query()->firstOrCreate(
                ['blocker_id' => $me->id, 'blocked_id' => $target->id],
                [],
            );
        });
    }

    public function unblock(User $me, User $blocked): void
    {
        UserBlock::query()
            ->where('blocker_id', $me->id)
            ->where('blocked_id', $blocked->id)
            ->delete();
    }
}
