<?php

namespace App\Services;

use App\Models\LoginHandoff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginHandoffService
{
    public function requestApproval(User $user, string $previousSessionId, Request $request): ?LoginHandoff
    {
        return DB::transaction(function () use ($user, $previousSessionId, $request) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $activeSessionId = $lockedUser->current_session_id;

            if (! $activeSessionId || $activeSessionId === $previousSessionId) {
                return null;
            }

            LoginHandoff::query()->where('user_id', $lockedUser->id)->whereIn('status', ['pending', 'approved'])
                ->update(['status' => 'expired', 'decided_at' => now()]);

            return LoginHandoff::query()->create([
                'user_id' => $lockedUser->id,
                'current_session_id' => $activeSessionId,
                'pending_session_id' => $request->session()->getId(),
                'device_label' => app(SecurityOtpService::class)->deviceLabel($request->userAgent()),
                'ip_address' => $request->ip(),
                'expires_at' => now()->addMinutes(5),
            ]);
        });
    }
}
