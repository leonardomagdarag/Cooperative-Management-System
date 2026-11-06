<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\AuditLogService;
use App\Services\BackupService;
use App\Services\CountersignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly AuditLogService $auditLogService,
    ) {
        //
    }

    public function index(): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.view'), 403);

        $settings = $this->backupService->settingsPayload();

        $tiers = [];
        foreach (BackupService::VALID_TIERS as $tier) {
            $tiers[$tier] = [
                'disk' => BackupService::diskFor($tier),
                'items' => $this->backupService->list($tier),
                'offsite' => $tier === BackupService::TIER_OFFSITE,
            ];
        }

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
            'daily_time' => ['required_without:archive_password', 'nullable', 'date_format:H:i'],
            'archive_password' => ['required_without:daily_time', 'nullable', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/', 'confirmed'],
        ]);

        $requests = [];
        if ($request->filled('daily_time')) {
            $requests[] = app(CountersignService::class)->request('backup.daily.schedule.update', [
                'time' => $validated['daily_time'],
            ], Auth::user());
        }
        if ($request->filled('archive_password')) {
            $requests[] = app(CountersignService::class)->request('backup.password.update', [
                'password' => Crypt::encryptString($validated['archive_password']),
            ], Auth::user());
        }

        return response()->json([
            'message' => 'Backup settings changes are awaiting chairman countersign approval.',
            'request_id' => $requests[0]->id,
            'status' => 'pending',
        ], 202);
    }

    public function run(Request $request, string $tier): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        if (! BackupService::isValidTier($tier)) {
            return response()->json(['message' => 'Invalid tier.'], 422);
        }

        if ($tier === BackupService::TIER_OFFSITE && ! $this->backupService->isOffsiteConfigured()) {
            return response()->json(['message' => 'Backblaze B2 is not configured. Set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET and AWS_ENDPOINT values in .env first.'], 422);
        }

        $scope = $request->input('scope', 'both');

        if (! BackupService::isValidScope($scope)) {
            return response()->json(['message' => 'Invalid scope.'], 422);
        }

        try {
            $run = $this->backupService->queueRun($tier, $scope, Auth::user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], $e instanceof \RuntimeException ? 409 : 500);
        }

        return response()->json([
            'message' => 'Backup queued.',
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
        ], 202);
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
        abort(403, 'Deleting backups is permanently disabled for security reasons.');
    }

    /**
     * Manually re-check connectivity to the off-site (Backblaze B2) storage.
     */
    public function testOffsite(): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.manage'), 403);

        $status = $this->backupService->offsiteStatus();

        $this->auditLogService->log(
            'backup.offsite_connection_tested',
            $status['reachable']
                ? 'Off-site backup connection test succeeded.'
                : 'Off-site backup connection test failed.',
            'backup',
            null,
            Auth::user(),
            ['reachable' => $status['reachable']],
        );

        if (! $status['configured']) {
            return response()->json([
                'message' => 'Backblaze B2 is not configured. Set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET and AWS_ENDPOINT values in .env.',
                'data' => $status,
            ], 422);
        }

        if (! $status['reachable']) {
            return response()->json([
                'message' => 'Could not reach Backblaze B2. Check the .env credentials, bucket and endpoint.',
                'data' => $status,
            ], 422);
        }

        return response()->json([
            'message' => 'Connected to Backblaze B2 successfully.',
            'data' => $status,
        ]);
    }

    public function restore(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('backups.restore'), 403);

        $validated = $request->validate([
            'tier' => 'required_without:archive|nullable|in:daily,offsite',
            'path' => 'required_without:archive|nullable|string|max:500',
            'archive' => 'required_without:tier|nullable|file|mimes:zip|max:512000',
            'scope' => 'required|in:db,files,both',
            'current_password' => ['required', 'current_password'],
            'confirmation' => 'required|string|in:RESTORE',
        ]);

        abort_if(
            $request->hasFile('archive') && ($request->filled('tier') || $request->filled('path')),
            422,
            'Choose either a stored backup or an uploaded archive.'
        );

        $tier = $validated['tier'] ?? BackupService::TIER_DAILY;
        $path = $request->file('archive')
            ? $this->backupService->importRestoreArchive($request->file('archive'), Auth::user())
            : $validated['path'];

        $countersign = app(CountersignService::class);
        $pending = $countersign->request('backup.restore', [
            'tier' => $tier,
            'path' => $path,
            'scope' => $validated['scope'],
        ], Auth::user());

        return response()->json([
            'message' => 'Restore requested and requires countersign approval.',
            'request_id' => $pending->id,
            'status' => 'pending',
        ], 202);
    }
}
