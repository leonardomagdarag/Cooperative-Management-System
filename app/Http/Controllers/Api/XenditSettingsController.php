<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
use App\Services\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class XenditSettingsController extends Controller
{
    public function __construct(private XenditService $xendit, private AuditLogService $auditLog) {}

    private function authorizeAdmin(): mixed
    {
        $user = Auth::user();
        abort_unless($user?->hasRole('admin'), 403);

        return $user;
    }

    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json(['data' => $this->xendit->summary()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $user = $this->authorizeAdmin();
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'mode' => 'required|in:test,live',
        ]);

        if ($validated['mode'] === 'live' && ! $this->xendit->isLiveConfigured()) {
            return response()->json(['message' => 'Configure the live Xendit secret API key and webhook callback token before switching to Live Mode.', 'code' => 'live_credentials_missing'], 422);
        }

        SystemSetting::set('xendit_enabled', $validated['enabled']);
        SystemSetting::set('xendit_mode', $validated['mode']);
        $this->auditLog->log('settings.xendit.updated', 'Xendit payment gateway configuration updated.', 'settings', null, $user, $validated);

        return response()->json(['message' => 'Xendit settings updated.', 'data' => $this->xendit->summary()]);
    }

    public function updateCredentials(Request $request): JsonResponse
    {
        $user = $this->authorizeAdmin();
        $validated = $request->validate([
            'test_secret_key' => 'nullable|string|max:255',
            'test_webhook_token' => 'nullable|string|max:255',
            'live_secret_key' => 'nullable|string|max:255',
            'live_webhook_token' => 'nullable|string|max:255',
        ]);
        $this->xendit->storeCredentials($validated);
        $this->auditLog->log('settings.xendit.credentials_updated', 'Xendit credentials updated.', 'settings', null, $user);

        return response()->json(['message' => 'Xendit credentials saved securely.', 'data' => $this->xendit->summary()]);
    }

    public function clearCredentials(): JsonResponse
    {
        $user = $this->authorizeAdmin();
        $this->xendit->clearCredentials();
        $this->auditLog->log('settings.xendit.credentials_cleared', 'Xendit credentials cleared.', 'settings', null, $user);

        return response()->json(['message' => 'Stored Xendit credentials cleared.', 'data' => $this->xendit->summary()]);
    }

    public function testConnection(): JsonResponse
    {
        $this->authorizeAdmin();
        try {
            return response()->json(['message' => 'Connected to Xendit successfully.', 'data' => $this->xendit->testConnection()]);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
