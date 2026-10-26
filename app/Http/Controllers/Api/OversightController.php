<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivilegedActionRequest;
use App\Services\BackupService;
use App\Services\CountersignService;
use App\Services\SecurityOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OversightController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(Auth::user()?->can('oversight.view'), 403);

        $requests = PrivilegedActionRequest::query()
            ->with(['requestedBy.personalData'])
            ->where(function ($query) {
                $query->where(fn ($pending) => $pending->where('status', 'pending')->where('expires_at', '>', now()))
                    ->orWhere(fn ($inDoubt) => $inDoubt->where('status', 'executing')->where('started_at', '<=', now()->subMinutes(CountersignService::EXECUTION_TIMEOUT_MINUTES)));
            })
            ->latest()
            ->get()
            ->map(fn (PrivilegedActionRequest $request) => [
                'id' => $request->id,
                'action_type' => $request->action_type,
                'status' => $request->status,
                'in_doubt_since' => $request->status === 'executing' ? $request->started_at : null,
                'created_at' => $request->created_at,
                'requested_by' => $request->requestedBy?->name ?: $request->requestedBy?->email,
                'requested_by_id' => $request->requested_by,
                'payload' => collect($request->payload)->except(['password', 'password_confirmation', 'otp_code', 'archive_password'])->all(),
            ]);

        return response()->json(['data' => $requests]);
    }

    public function sendOtp(Request $request, PrivilegedActionRequest $privilegedActionRequest, CountersignService $countersign, SecurityOtpService $otp): JsonResponse
    {
        abort_unless($countersign->canDecide(Auth::user()), 403);
        abort_if((int) $privilegedActionRequest->requested_by === (int) Auth::id(), 403, 'Self-approval is forbidden.');
        $actor = Auth::user();
        $validated = $request->validate(['decision' => ['required', 'in:approve,reject,reconcile_executed,reconcile_failed']]);
        $isReconciliation = str_starts_with($validated['decision'], 'reconcile_');
        if ($isReconciliation) {
            abort_unless(
                $privilegedActionRequest->status === 'executing'
                    && $privilegedActionRequest->started_at?->lte(now()->subMinutes(CountersignService::EXECUTION_TIMEOUT_MINUTES)),
                409,
                'This request is not eligible for reconciliation.'
            );
        } else {
            abort_unless($privilegedActionRequest->status === 'pending' && $privilegedActionRequest->expires_at?->isFuture(), 409, 'This request is no longer pending.');
        }

        $context = ['request_id' => $privilegedActionRequest->id, 'decision' => $validated['decision']];
        $challenge = $otp->issueChallenge($actor, 'privileged_action_approval', $context);
        $otp->sendOtpMail($actor, 'privileged_action_approval', $challenge['otp_code']);

        return response()->json(['message' => 'Verification code sent to your email.', 'expires_at' => $challenge['challenge']->expires_at->toISOString()]);
    }

    public function decide(Request $request, PrivilegedActionRequest $privilegedActionRequest, CountersignService $countersign, SecurityOtpService $otp): JsonResponse
    {
        abort_unless($countersign->canDecide(Auth::user()), 403);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject,reconcile_executed,reconcile_failed'],
            'otp_code' => ['required', 'digits:6'],
            'note' => ['required_if:decision,reconcile_executed,reconcile_failed', 'nullable', 'string', 'max:1000'],
        ]);

        $actor = Auth::user();
        abort_if((int) $privilegedActionRequest->requested_by === (int) $actor->id, 403, 'Self-approval is forbidden.');
        abort_unless($otp->verifyChallenge($actor, 'privileged_action_approval', $validated['otp_code'], ['request_id' => $privilegedActionRequest->id, 'decision' => $validated['decision']]), 422, 'Invalid or expired verification code.');

        if ($validated['decision'] === 'approve') {
            $countersign->approve($privilegedActionRequest, $actor);
        } elseif ($validated['decision'] === 'reject') {
            $countersign->reject($privilegedActionRequest, $actor, $validated['note'] ?? '');
        } else {
            $countersign->reconcile(
                $privilegedActionRequest,
                $actor,
                $validated['decision'] === 'reconcile_executed' ? 'executed' : 'failed',
                $validated['note'],
            );
        }

        $response = ['message' => 'Decision recorded.'];
        if ($validated['decision'] === 'approve' && $privilegedActionRequest->action_type === 'backup.restore') {
            $delay = BackupService::RESTORE_START_DELAY_SECONDS;
            $response = [
                'message' => "Restore approved. The system will enter maintenance mode in about {$delay} seconds.",
                'restore_start_delay_seconds' => $delay,
            ];
        }

        return response()->json($response);
    }
}
