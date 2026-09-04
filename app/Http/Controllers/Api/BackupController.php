<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\AuditLogService;
use App\Services\BackupService;
use App\Services\GoogleDriveCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly GoogleDriveCredentialService $googleDriveCredentials,
        private readonly AuditLogService $auditLogService,
    ) {
        //
    }

    public function index(): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.view'), 403);

        $settings = $this->backupService->settingsPayload();
        $tiers = [
            'local' => [
                'disk' => BackupService::diskFor('local'),
                'items' => $this->backupService->list('local'),
            ],
            'cloud' => [
                'disk' => BackupService::diskFor('cloud'),
                'items' => $this->backupService->list('cloud'),
                'configured' => $this->backupService->isCloudConfigured(),
            ],
        ];

        return response()->json([
            'data' => [
                'settings' => $settings,
                'tiers' => $tiers,
                'recent_runs' => BackupRun::query()
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn (BackupRun $run) => [
                        'id' => $run->id,
                        'tier' => $run->tier,
                        'scope' => $run->scope,
                        'status' => $run->status,
                        'filename' => $run->filename,
                        'size' => $run->size,
                        'message' => $run->message,
                        'started_at' => $run->started_at?->toISOString(),
                        'finished_at' => $run->finished_at?->toISOString(),
                    ]),
            ],
        ]);
    }

    public function runs(): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.view'), 403);

        $runs = BackupRun::query()
            ->with('trigger:id,email')
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $runs->through(fn (BackupRun $run) => [
                'id' => $run->id,
                'tier' => $run->tier,
                'scope' => $run->scope,
                'status' => $run->status,
                'filename' => $run->filename,
                'size' => $run->size,
                'message' => $run->message,
                'started_at' => $run->started_at?->toISOString(),
                'finished_at' => $run->finished_at?->toISOString(),
                'triggered_by' => $run->trigger?->email,
            ]),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        $validated = $request->validate([
            'encryption_enabled' => 'boolean',
            'local' => 'required|array',
            'local.enabled' => 'boolean',
            'local.schedule' => 'required|string|max:100',
            'local.scope' => 'required|in:db,files,both',
            'local.retention_days' => 'required|integer|min:1|max:3650',
            'cloud' => 'required|array',
            'cloud.enabled' => 'boolean',
            'cloud.schedule' => 'required|string|max:100',
            'cloud.scope' => 'required|in:db,files,both',
            'cloud.retention_days' => 'required|integer|min:1|max:3650',
        ]);

        if (($validated['cloud']['enabled'] ?? false) && ! $this->backupService->isCloudConfigured()) {
            return response()->json([
                'message' => 'Google Drive is not configured. Save the client credentials from Settings → Backup & Restore first.',
                'code' => 'google_drive_unconfigured',
            ], 422);
        }

        $this->backupService->updateSettings($validated);

        return response()->json(['message' => 'Backup settings updated.']);
    }

    public function run(Request $request, string $tier): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        if (! BackupService::isValidTier($tier)) {
            return response()->json(['message' => 'Invalid tier.'], 422);
        }

        if ($tier === BackupService::TIER_CLOUD && ! $this->backupService->isCloudConfigured()) {
            return response()->json(['message' => 'Google Drive is not configured. Save the client credentials from Settings → Backup & Restore first.'], 422);
        }

        $scope = $request->input('scope', 'both');

        if (! BackupService::isValidScope($scope)) {
            return response()->json(['message' => 'Invalid scope.'], 422);
        }

        try {
            $run = $this->backupService->run($tier, $scope, Auth::user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => $run->status === 'success' ? 'Backup completed successfully.' : 'Backup failed.',
            'run' => [
                'id' => $run->id,
                'tier' => $run->tier,
                'scope' => $run->scope,
                'status' => $run->status,
                'filename' => $run->filename,
                'message' => $run->message,
                'started_at' => $run->started_at?->toISOString(),
                'finished_at' => $run->finished_at?->toISOString(),
            ],
        ], $run->status === 'success' ? 201 : 500);
    }

    public function cleanup(string $tier): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        if (! BackupService::isValidTier($tier)) {
            return response()->json(['message' => 'Invalid tier.'], 422);
        }

        try {
            $this->backupService->cleanup($tier, Auth::user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Cleanup completed.']);
    }

    public function download(Request $request, string $tier): StreamedResponse|JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        $path = (string) $request->query('file');

        try {
            return $this->backupService->download($tier, $path);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function destroy(Request $request, string $tier): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        $path = (string) $request->query('file');

        try {
            $this->backupService->delete($tier, $path, Auth::user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Backup deleted.']);
    }

    public function cloudCredentials(): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.view'), 403);

        return response()->json(['data' => $this->googleDriveCredentials->summary()]);
    }

    public function updateCloudCredentials(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        $validated = $request->validate([
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string|max:255',
            'refresh_token' => 'nullable|string|max:4096',
            'folder' => 'nullable|string|max:1024',
            'team_drive_id' => 'nullable|string|max:255',
        ]);

        $this->googleDriveCredentials->store($validated);

        $this->auditLogService->log(
            'backup.cloud_credentials_updated',
            'Google Drive backup credentials were updated.',
            'backup',
            null,
            Auth::user(),
            ['configured' => $this->googleDriveCredentials->isConfigured()],
        );

        return response()->json([
            'message' => $this->googleDriveCredentials->isConfigured()
                ? 'Google Drive credentials saved and ready to use.'
                : 'Google Drive credentials saved. The client ID, secret and refresh token are all required to connect.',
            'data' => $this->googleDriveCredentials->summary(),
        ]);
    }

    public function testCloudCredentials(): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        try {
            $this->googleDriveCredentials->testConnection();
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->auditLogService->log(
            'backup.cloud_credentials_tested',
            'Google Drive backup connection test succeeded.',
            'backup',
            null,
            Auth::user(),
        );

        return response()->json(['message' => 'Connected to Google Drive successfully.', 'data' => $this->googleDriveCredentials->summary()]);
    }

    public function clearCloudCredentials(): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        $this->googleDriveCredentials->clear();

        $this->auditLogService->log(
            'backup.cloud_credentials_cleared',
            'Google Drive backup credentials stored in the database were removed.',
            'backup',
            null,
            Auth::user(),
        );

        return response()->json(['message' => 'Stored Google Drive credentials removed.', 'data' => $this->googleDriveCredentials->summary()]);
    }

    public function restore(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.restore'), 403);

        $validated = $request->validate([
            'tier' => 'required|in:local,cloud',
            'path' => 'required|string|max:500',
            'scope' => 'required|in:db,files,both',
            'current_password' => ['required', 'current_password'],
            'confirmation' => 'required|string|in:RESTORE',
            'archive_password' => 'required|string',
        ]);

        try {
            $this->backupService->restore(
                $validated['tier'],
                $validated['path'],
                $validated['scope'],
                $validated['archive_password'],
                Auth::user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Restore completed. The application is back online.']);
    }
}
