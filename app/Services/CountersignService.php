<?php

namespace App\Services;

use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\SettingsController;
use App\Jobs\DisburseInterestJob;
use App\Models\Expense;
use App\Models\Notification;
use App\Models\OfficerPermissionOverride;
use App\Models\PrivilegedActionRequest;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinancialSettings;
use App\Support\MaintenanceState;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CountersignService
{
    public const EXECUTION_TIMEOUT_MINUTES = 15;

    /**
     * Create a pending privileged action request.
     */
    public function request(string $actionType, array $payload, User $requester): PrivilegedActionRequest
    {
        $request = PrivilegedActionRequest::create([
            'action_type' => $actionType,
            'status' => 'pending',
            'payload' => $payload,
            'requested_by' => $requester->id,
            'idempotency_key' => Str::uuid()->toString(),
            'expires_at' => now()->addHours(24),
        ]);

        $approvers = User::role('chairman')
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', 'oversight.approve'))
            ->where('account_status', 'active')
            ->get();
        foreach ($approvers as $approver) {
            Notification::query()->create([
                'user_id' => $approver->id,
                'type' => 'security_alert',
                'title' => 'Privileged action needs approval',
                'message' => "A {$actionType} request requires your review.",
                'related_id' => $request->id,
                'related_type' => PrivilegedActionRequest::class,
                'action_url' => '/dashboard/members?oversight=1',
                'priority' => 'urgent',
                'expires_at' => $request->expires_at,
            ]);

            if ($approver->email) {
                try {
                    Mail::raw("A privileged action ({$actionType}) requires your review. Sign in and open Members > Oversight approvals. Request #{$request->id}.", function ($message) use ($approver) {
                        $message->to($approver->email)->subject('Privileged action requires approval');
                    });
                } catch (\Throwable $e) {
                    Log::error("Countersign notice email failed for user {$approver->id}: {$e->getMessage()}");
                }
            }
        }

        $target = User::find($payload['user_id'] ?? null);
        if ($target) {
            if ($actionType === 'officer.permissions.update') {
                $this->notifyAccountTarget($target, 'Permission changes awaiting approval', 'An officer permission override change was requested for your account. Your current access remains unchanged until the chairman approves it.');
            } else {
                $this->notifyAccountTarget(
                    $target,
                    'Privileged role awaiting approval',
                    'A privileged role has been requested for your account. It will not take effect until the chairman approves it.'
                );
            }
        }

        return $request;
    }

    /**
     * Approve a pending request.
     */
    public function approve(PrivilegedActionRequest $request, User $decider): void
    {
        if ($request->requested_by === $decider->id) {
            abort(403, 'Self-approval is forbidden.');
        }

        abort_unless($this->canDecide($decider), 403, 'Only the chairman can approve privileged actions.');

        $claimed = PrivilegedActionRequest::query()->whereKey($request->id)
            ->where('status', 'pending')->where('expires_at', '>', now())
            ->update([
                'status' => 'approved',
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'otp_verified_at' => now(),
            ]);
        abort_unless($claimed, 409, 'This request is no longer pending.');
        $request->refresh();

        $this->appendLog($request, 'approved');

        // Execute immediately (as per user approval of plan, synchronous)
        $this->execute($request);
    }

    /**
     * Reject a pending request.
     */
    public function reject(PrivilegedActionRequest $request, User $decider, string $note): void
    {
        if ($request->requested_by === $decider->id) {
            abort(403, 'Self-rejection is forbidden.'); // For consistency, though less critical
        }

        abort_unless($this->canDecide($decider), 403, 'Only the chairman can reject privileged actions.');

        $claimed = PrivilegedActionRequest::query()->whereKey($request->id)
            ->where('status', 'pending')->where('expires_at', '>', now())
            ->update([
                'status' => 'rejected',
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);
        abort_unless($claimed, 409, 'This request is no longer pending.');
        $request->refresh();

        $newAccount = (bool) ($request->payload['new_account'] ?? false);
        if (in_array($request->action_type, ['admin.create', 'admin.update', 'role.assign'], true)) {
            $user = User::find($request->payload['user_id'] ?? null);
            if ($user && $newAccount && $user->account_status === 'inactive') {
                $user->update(['account_status' => 'terminated']);
                $this->notifyAccountTarget($user, 'Privileged account request rejected', 'Your privileged account activation request was rejected. Contact a cooperative administrator for assistance.');
            }
        }

        if (in_array($request->action_type, ['role.assign', 'admin.create', 'admin.update', 'officer.permissions.update'], true) && ! $newAccount) {
            $user = User::find($request->payload['user_id'] ?? null);
            if ($user) {
                if ($request->action_type === 'officer.permissions.update') {
                    $this->notifyAccountTarget($user, 'Permission changes not approved', 'The requested officer permission overrides were not approved. Your current access remains unchanged.');
                } else {
                    $this->notifyAccountTarget($user, 'Role change not approved', 'The requested privileged role change was not approved. Your current role remains unchanged.');
                }
            }
        }

        $this->appendLog($request, 'rejected');
        $this->notifyRequester($request, 'Privileged action rejected', "Your {$request->action_type} request was rejected.");
    }

    /**
     * Execute an approved request.
     */
    public function execute(PrivilegedActionRequest $request): void
    {
        $updated = PrivilegedActionRequest::where('id', $request->id)
            ->where('status', 'approved')
            ->update([
                'status' => 'executing',
                'started_at' => now(),
            ]);

        if (! $updated) {
            // Another worker claimed it or it is not approved.
            return;
        }

        $request->refresh();

        try {
            DB::beginTransaction();

            $result = $this->performAction($request->action_type, $request->payload);

            $request->update([
                'status' => 'executed',
                'executed_at' => now(),
                'execution_result' => $result,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            $request->update([
                'status' => 'failed',
                'execution_result' => ['error' => $e->getMessage()],
            ]);

            $this->appendLog($request, 'failed');

            throw $e;
        }

        $this->appendLog($request, 'executed');
        $this->notifyExecutionTarget($request);
        $this->notifyRequester($request, 'Privileged action completed', "Your {$request->action_type} request was approved and executed.");
    }

    public function reconcile(PrivilegedActionRequest $request, User $decider, string $outcome, string $note): void
    {
        abort_unless($this->canDecide($decider), 403, 'Only the chairman can reconcile privileged actions.');
        abort_if((int) $request->requested_by === (int) $decider->id, 403, 'Self-reconciliation is forbidden.');
        abort_unless(in_array($outcome, ['executed', 'failed'], true), 422, 'Invalid reconciliation outcome.');
        abort_unless(
            $request->status === 'executing'
                && $request->started_at?->lte(now()->subMinutes(self::EXECUTION_TIMEOUT_MINUTES)),
            409,
            'This request is not eligible for reconciliation.'
        );

        $updated = PrivilegedActionRequest::query()->whereKey($request->id)
            ->where('status', 'executing')
            ->where('started_at', '<=', now()->subMinutes(self::EXECUTION_TIMEOUT_MINUTES))
            ->update([
                'status' => $outcome,
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'execution_result' => ['reconciled' => true, 'outcome' => $outcome, 'note' => $note],
                'executed_at' => $outcome === 'executed' ? now() : null,
            ]);
        abort_unless($updated, 409, 'This request was already reconciled.');

        $request->refresh();
        $this->appendLog($request, 'reconciled_'.$outcome);
        $this->notifyRequester(
            $request,
            'Privileged action reconciled',
            "Your {$request->action_type} request was manually marked {$outcome}. Reconciliation note: {$note}"
        );
    }

    public function canDecide(User $user): bool
    {
        return $user->hasRole('chairman') && $user->can('oversight.approve');
    }

    /**
     * Route the action to the appropriate handler.
     */
    private function performAction(string $actionType, array $payload): array
    {
        switch ($actionType) {
            case 'admin.create':
            case 'admin.update':
                $user = User::findOrFail($payload['user_id']);
                if (isset($payload['role']) && in_array($payload['role'], ['admin', 'chairman'], true)) {
                    $user->syncRoles([$payload['role']]);
                    $user->update([
                        'role' => $payload['role'],
                        'is_officer' => true,
                        'account_status' => 'active',
                    ]);
                    app(PermissionRegistrar::class)->forgetCachedPermissions();
                } else {
                    $user->update(['account_status' => 'active']);
                }

                return ['status' => 'success', 'user_id' => $user->id];

            case 'admin.delete':
                $user = User::findOrFail($payload['user_id']);
                app(MemberController::class)->destroy($user, true);

                return ['status' => 'success', 'user_id' => $user->id];

            case 'admin.force_delete':
                $user = User::withTrashed()->findOrFail($payload['user_id']);
                app(MemberController::class)->forceDestroy($user->id, true);

                return ['status' => 'success', 'user_id' => $user->id];

            case 'admin.restore':
                $user = User::withTrashed()->findOrFail($payload['user_id']);
                app(MemberController::class)->restore($user->id, true);

                return ['status' => 'success', 'user_id' => $user->id];

            case 'role.assign':
                $user = User::findOrFail($payload['user_id']);
                $roles = $payload['roles'] ?? [];
                abort_unless(count($roles) === 1 && is_string($roles[0]), 422, 'Exactly one role must be assigned.');
                $role = Role::query()->where('name', $roles[0])->where('guard_name', 'web')->firstOrFail();
                $roleName = $role->name;
                $user->syncRoles($roles);
                $user->update([
                    'role' => $payload['legacy_role'] ?? $roleName,
                    'is_officer' => ! in_array($roleName, ['member', 'inactive', 'terminated', 'developer'], true),
                    'account_status' => ($payload['activate'] ?? false) ? 'active' : $user->account_status,
                ]);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return ['status' => 'success', 'roles' => $payload['roles']];

            case 'permissions.update':
                $role = Role::query()->where('name', $payload['role'])->where('guard_name', 'web')->firstOrFail();
                $role->syncPermissions($payload['permissions']);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return ['status' => 'success', 'permissions' => $payload['permissions']];

            case 'officer.permissions.update':
                $user = User::findOrFail($payload['user_id']);
                abort_unless($user->is_officer, 409, 'Permission overrides can only be applied to an officer account.');
                $overrides = $payload['overrides'] ?? [];
                abort_unless(is_array($overrides), 422, 'Permission overrides are invalid.');
                foreach ($overrides as $permission => $effect) {
                    abort_unless(
                        in_array($permission, PermissionCatalog::allPermissions(), true)
                            && in_array($effect, [OfficerPermissionOverride::EFFECT_ALLOW, OfficerPermissionOverride::EFFECT_DISALLOW], true),
                        422,
                        'An override contains an invalid permission or effect.'
                    );
                }

                OfficerPermissionOverride::query()->where('user_id', $user->id)->delete();
                foreach ($overrides as $permission => $effect) {
                    OfficerPermissionOverride::query()->create([
                        'user_id' => $user->id,
                        'permission' => $permission,
                        'effect' => $effect,
                    ]);
                }
                OfficerPermissionOverride::forgetAllCachedEffects($user->id);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return ['status' => 'success', 'user_id' => $user->id, 'override_count' => count($overrides)];

            case 'role.create':
                abort_unless(is_string($payload['role'] ?? null) && ! in_array($payload['role'], ['admin', 'chairman', 'developer', 'member', 'inactive', 'terminated'], true), 422, 'Invalid custom role name.');
                abort_unless(is_array($payload['permissions'] ?? null), 422, 'Role permissions are invalid.');
                abort_if(Role::query()->where('name', $payload['role'])->where('guard_name', 'web')->exists(), 409, 'Role already exists.');
                $role = Role::create(['name' => $payload['role'], 'guard_name' => 'web']);
                $role->syncPermissions($payload['permissions']);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return ['status' => 'success', 'role' => $role->name];

            case 'role.rename':
                $role = Role::query()->where('name', $payload['role'] ?? null)->where('guard_name', 'web')->firstOrFail();
                abort_unless(is_string($payload['new_name'] ?? null) && $payload['new_name'] !== '', 422, 'Invalid role name.');
                abort_if(Role::query()->where('name', $payload['new_name'])->where('guard_name', 'web')->exists(), 409, 'Role name already exists.');
                abort_if(User::withTrashed()->role($role->name)->exists(), 409, 'Role is assigned to a user and cannot be renamed.');
                abort_if(in_array($role->name, array_keys(PermissionCatalog::rolePresets()), true), 422, 'Base roles cannot be renamed.');
                abort_if(in_array($payload['new_name'], ['admin', 'chairman', 'developer', 'member', 'inactive', 'terminated'], true), 422, 'Reserved role name.');
                $role->update(['name' => $payload['new_name']]);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return ['status' => 'success', 'role' => $payload['new_name']];

            case 'role.delete':
                $role = Role::query()->where('name', $payload['role'] ?? null)->where('guard_name', 'web')->firstOrFail();
                abort_if(in_array($role->name, array_keys(PermissionCatalog::rolePresets()), true), 422, 'Base roles cannot be deleted.');
                abort_if(User::withTrashed()->role($role->name)->exists(), 409, 'Role is assigned to a user and cannot be deleted.');
                $role->delete();
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return ['status' => 'success', 'role' => $payload['role']];

            case 'backup.restore':
                $run = app(BackupService::class)->queueRestore(
                    $payload['tier'],
                    $payload['path'],
                    $payload['scope'],
                    $payload['archive_password'] ?? '',
                    User::find($payload['requested_by'] ?? 1) ?? User::first()
                );

                return ['status' => 'queued', 'run_id' => $run->id];

            case 'backup.hourly.update':
                abort_unless(is_bool($payload['enabled'] ?? null), 422, 'Hourly backup state is invalid.');
                app(BackupService::class)->updateHourlyEnabled($payload['enabled']);

                return ['status' => 'success', 'enabled' => $payload['enabled']];

            case 'backup.delete':
                app(BackupService::class)->delete(
                    $payload['tier'],
                    $payload['path'],
                    User::find($payload['requested_by'] ?? 1) ?? User::first()
                );

                return ['status' => 'success'];

            case 'settings.update':
                foreach ($payload['settings'] as $key => $value) {
                    SystemSetting::set($key, $value);
                }
                if ($payload['sync_login'] ?? false) {
                    MaintenanceState::syncLoginAvailability();
                }
                if (($payload['notify_affected'] ?? false) && isset($payload['services'])) {
                    app(SettingsController::class)->notifyAffectedUsers($payload['services']);
                }

                return ['status' => 'success', 'updated_keys' => array_keys($payload['settings'])];

            case 'financial.settings.update':
                $allowedKeys = FinancialSettings::EDITABLE_KEYS;
                $updated = [];

                foreach ($payload['settings'] ?? [] as $key => $value) {
                    if (! in_array($key, $allowedKeys, true)) {
                        throw new \RuntimeException("Disallowed financial setting key: {$key}");
                    }

                    SystemSetting::set($key, $value);
                    $updated[] = $key;
                }

                if (($payload['notify_affected'] ?? false) && isset($payload['services'])) {
                    app(SettingsController::class)->notifyAffectedUsers($payload['services']);
                }

                return ['status' => 'success', 'updated_keys' => $updated];

            case 'financial.interest.run':
                $accountType = $payload['account_type'] ?? null;
                if (! in_array($accountType, FinancialSettings::ACCOUNT_TYPES, true)) {
                    throw new \RuntimeException("Unknown interest account type: {$accountType}");
                }

                DisburseInterestJob::dispatch($accountType, $payload['requested_by'] ?? null, 'manual');

                return [
                    'status' => 'queued',
                    'account_type' => $accountType,
                ];

            case 'expense.update':
                $expense = Expense::find($payload['expense_id'] ?? null);
                abort_unless($expense, 404, 'The target expense no longer exists.');

                $changes = $payload['changes'] ?? [];
                abort_unless(is_array($changes), 422, 'Expense changes are invalid.');
                abort_unless(is_string($changes['category'] ?? null) && $changes['category'] !== '', 422, 'Expense category is required.');
                abort_unless(is_numeric($changes['amount'] ?? null) && (float) $changes['amount'] >= 0.01, 422, 'Invalid expense amount.');
                abort_unless(is_string($changes['expense_date'] ?? null) && strtotime($changes['expense_date']) !== false, 422, 'Invalid expense date.');

                $expense->update([
                    'category' => $changes['category'],
                    'description' => $changes['description'] ?? null,
                    'amount' => $changes['amount'],
                    'expense_date' => $changes['expense_date'],
                    'branch_id' => $changes['branch_id'] ?? null,
                ]);

                return ['status' => 'success', 'expense_id' => $expense->id];

            case 'expense.delete':
                $expense = Expense::find($payload['expense_id'] ?? null);
                abort_unless($expense, 404, 'The target expense no longer exists.');

                app(AuditLogService::class)->log(
                    'expense.deleted',
                    'Expense deleted.',
                    'expense',
                    $expense,
                    User::find($payload['requested_by'] ?? null) ?? Auth::user()
                );

                $expense->delete();

                return ['status' => 'success', 'expense_id' => $payload['expense_id']];

            case 'transaction.update':
                $transaction = Transaction::find($payload['transaction_id'] ?? null);
                abort_unless($transaction, 404, 'The target transaction no longer exists.');
                abort_unless($transaction->status === 'pending', 409, 'Only pending transactions can be edited.');

                $changes = $payload['changes'] ?? [];
                abort_unless(is_array($changes), 422, 'Transaction changes are invalid.');
                abort_unless(
                    in_array($changes['transaction_type'] ?? null, ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'fee'], true),
                    422,
                    'Invalid transaction type.'
                );
                abort_unless(is_numeric($changes['amount'] ?? null) && (float) $changes['amount'] >= 0.01, 422, 'Invalid transaction amount.');

                $transaction->update([
                    'transaction_type' => $changes['transaction_type'],
                    'amount' => $changes['amount'],
                    'note' => $changes['note'] ?? null,
                    'account_id' => $changes['account_id'] ?? null,
                    'loan_id' => $changes['loan_id'] ?? null,
                    'branch_id' => $changes['branch_id'] ?? null,
                ]);

                return ['status' => 'success', 'transaction_id' => $transaction->id];

            default:
                throw new \RuntimeException("Unknown privileged action type: {$actionType}");
        }
    }

    private function notifyAccountTarget(User $user, string $title, string $message): void
    {
        app(NotificationService::class)->notifyAccountActivity($user, $title, $message, 'high');

        if ($user->email) {
            try {
                Mail::raw($message, fn ($mail) => $mail->to($user->email)->subject($title));
            } catch (\Throwable $e) {
                Log::error("Countersign notice email failed for user {$user->id}: {$e->getMessage()}");
            }
        }
    }

    private function notifyRequester(PrivilegedActionRequest $request, string $title, string $message): void
    {
        $requester = $request->requestedBy;
        if ($requester) {
            $this->notifyAccountTarget($requester, $title, $message);
        }
    }

    private function notifyExecutionTarget(PrivilegedActionRequest $request): void
    {
        if (! in_array($request->action_type, ['admin.create', 'admin.update', 'role.assign', 'officer.permissions.update'], true)) {
            return;
        }

        $user = User::find($request->payload['user_id'] ?? null);
        if ($user) {
            if ($request->action_type === 'officer.permissions.update') {
                $this->notifyAccountTarget($user, 'Permission changes approved', 'The chairman approved the requested permission overrides. Your account access has been updated.');
            } else {
                $this->notifyAccountTarget($user, 'Privileged role approved', 'The requested privileged role is now active on your account.');
            }
        }
    }

    /**
     * Append to the out-of-db hash-chained log.
     */
    private function appendLog(PrivilegedActionRequest $request, string $event): void
    {
        $logPath = 'privileged-actions.jsonl';
        $prevHash = '0000000000000000000000000000000000000000000000000000000000000000'; // Default for first
        $seq = 1;

        if (Storage::disk('local')->exists($logPath)) {
            $contents = Storage::disk('local')->get($logPath);
            $lines = array_filter(explode("\n", $contents));
            if (count($lines) > 0) {
                $lastLine = end($lines);
                $lastEntry = json_decode($lastLine, true);
                if ($lastEntry) {
                    $seq = ($lastEntry['seq'] ?? 0) + 1;
                    $prevHash = hash('sha256', $lastLine);
                }
            }
        }

        $entry = [
            'seq' => $seq,
            'ts' => now()->toIso8601String(),
            'request_id' => $request->id,
            'action' => $request->action_type,
            'event' => $event,
            'payload_hash' => hash('sha256', json_encode($request->payload)),
            'prev' => $prevHash,
        ];

        if ($event === 'approved') {
            $entry['approved_by'] = $request->decided_by;
        }

        $line = json_encode($entry)."\n";
        Storage::disk('local')->append($logPath, trim($line));

        if (class_exists(AuditLogService::class)) {
            $actorId = match ($event) {
                'requested' => $request->requested_by,
                'approved', 'rejected', 'reconciled_executed', 'reconciled_failed' => $request->decided_by,
                default => null,
            };
            $actor = $actorId ? User::find($actorId) : null;

            $description = match ($event) {
                'requested' => "Privileged action '{$request->action_type}' requested.",
                'approved' => "Privileged action '{$request->action_type}' approved.",
                'rejected' => "Privileged action '{$request->action_type}' rejected.",
                'executed' => "Privileged action '{$request->action_type}' executed successfully.",
                'failed' => "Privileged action '{$request->action_type}' failed to execute.",
                'reconciled_executed' => "Privileged action '{$request->action_type}' reconciled as executed.",
                'reconciled_failed' => "Privileged action '{$request->action_type}' reconciled as failed.",
                default => "Privileged action '{$request->action_type}' state changed to {$event}.",
            };

            app(AuditLogService::class)->log(
                "oversight.{$event}",
                $description,
                'oversight',
                $request,
                $actor ?? Auth::user(),
                ['action_type' => $request->action_type, 'payload' => $request->payload]
            );
        }

        if ($request->action_type === 'backup.restore' && $event === 'approved') {
            // Best effort B2 sync
            try {
                if (Storage::disk('b2')->exists($logPath)) {
                    // This is naive, it might overwrite with local or append.
                    // Better approach is just put the local file to B2
                    Storage::disk('b2')->put($logPath, Storage::disk('local')->get($logPath));
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to sync privileged-actions.jsonl to B2', ['error' => $e->getMessage()]);
            }
        }
    }
}
