<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
use App\Services\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PayMongoSettingsController extends Controller
{
    public function __construct(
        protected PayMongoService $payMongoService,
        protected AuditLogService $auditLogService
    ) {}

    public function index(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('settings.view') || $user->can('settings.update')), 403);

        return response()->json([
            'data' => $this->payMongoService->summary(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('settings.update')), 403);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'mode' => 'required|in:sandbox,live',
            'enabled_methods' => 'required|array',
            'enabled_methods.*' => 'string|in:gcash,paymaya,grab_pay,shopeepay,card,qrph,dob,billease',
            'pass_on_fees' => 'boolean',
            'send_email_receipt' => 'boolean',
        ]);

        // Requirement 8: Live mode must require explicit configuration.
        // Prevent enabling live mode if required live credentials are not present.
        if ($validated['mode'] === 'live' && ! $this->payMongoService->isLiveConfigured()) {
            return response()->json([
                'message' => 'Cannot switch to Live Mode: Live Secret Key and Live Public Key must be configured first.',
                'code' => 'live_credentials_missing',
            ], 422);
        }

        SystemSetting::set('paymongo_enabled', $validated['enabled']);
        SystemSetting::set('paymongo_mode', $validated['mode']);
        SystemSetting::set('paymongo_methods', $validated['enabled_methods']);
        SystemSetting::set('paymongo_pass_on_fees', $validated['pass_on_fees'] ?? false);
        SystemSetting::set('paymongo_send_email_receipt', $validated['send_email_receipt'] ?? true);

        $this->auditLogService->log(
            'settings.paymongo.updated',
            'PayMongo payment gateway configuration updated.',
            'settings',
            null,
            $user,
            [
                'enabled' => $validated['enabled'],
                'mode' => $validated['mode'],
                'methods_count' => count($validated['enabled_methods']),
            ]
        );

        return response()->json([
            'message' => 'PayMongo configuration updated successfully.',
            'data' => $this->payMongoService->summary(),
        ]);
    }

    public function updateCredentials(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('settings.update')), 403);

        $validated = $request->validate([
            'test_public_key' => 'nullable|string|max:255',
            'test_secret_key' => 'nullable|string|max:255',
            'test_webhook_secret' => 'nullable|string|max:255',
            'live_public_key' => 'nullable|string|max:255',
            'live_secret_key' => 'nullable|string|max:255',
            'live_webhook_secret' => 'nullable|string|max:255',
        ]);

        // Store encrypted credentials (do NOT log sensitive values)
        $this->payMongoService->storeCredentials($validated);

        $this->auditLogService->log(
            'settings.paymongo.credentials_updated',
            'PayMongo API credentials were updated.',
            'settings',
            null,
            $user,
            ['configured' => $this->payMongoService->isConfigured()]
        );

        return response()->json([
            'message' => 'PayMongo credentials saved securely.',
            'data' => $this->payMongoService->summary(),
        ]);
    }

    public function clearCredentials(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('settings.update')), 403);

        $this->payMongoService->clearCredentials();

        $this->auditLogService->log(
            'settings.paymongo.credentials_cleared',
            'Stored PayMongo credentials were removed.',
            'settings',
            null,
            $user
        );

        return response()->json([
            'message' => 'Stored PayMongo credentials removed.',
            'data' => $this->payMongoService->summary(),
        ]);
    }

    public function testConnection(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('settings.update')), 403);

        try {
            $result = $this->payMongoService->testConnection();

            $this->auditLogService->log(
                'settings.paymongo.tested',
                'PayMongo connection test succeeded.',
                'settings',
                null,
                $user
            );

            return response()->json([
                'message' => $result['message'],
                'data' => $this->payMongoService->summary(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'PayMongo connection test failed: '.$e->getMessage(),
            ], 422);
        }
    }
}
