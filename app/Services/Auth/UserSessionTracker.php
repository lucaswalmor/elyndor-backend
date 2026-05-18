<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;

class UserSessionTracker
{
    /** Apaga registos antigos e cria sessão atual (login / registro). */
    public function beginSession(Request $request, User $user): void
    {
        UserSession::query()->where('user_id', $user->id)->delete();

        UserSession::query()->create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'device_id' => $request->filled('device_id') ? substr((string) $request->device_id, 0, 80) : null,
            'client_type' => substr((string) $request->input('client_type', 'web'), 0, 20),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Atualização leve durante uso da app / fila (evita escritas excessivas — no máximo a cada ~50s por utilizador).
     */
    public function touch(Request $request, User $user): void
    {
        /** @var UserSession|null $row */
        $row = UserSession::query()->where('user_id', $user->id)->orderByDesc('id')->first();

        if (! $row) {
            $this->beginSession($request, $user);

            return;
        }

        if ($row->last_seen_at && $row->last_seen_at->gt(now()->subSeconds(50))) {
            return;
        }

        $row->last_seen_at = now();
        $row->ip_address = $request->ip();
        $row->user_agent = substr((string) $request->userAgent(), 0, 2000);

        if ($request->filled('device_id')) {
            $row->device_id = substr((string) $request->device_id, 0, 80);
        }
        if ($request->filled('client_type')) {
            $row->client_type = substr((string) $request->client_type, 0, 20);
        }

        $row->save();
    }
}
