<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Console\Command;

class BootstrapChairmanCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'oversight:bootstrap-chairman {--email= : The email address of the user to become chairman}';

    /**
     * The console command description.
     */
    protected $description = 'Bootstrap the first chairman to enable countersign.';

    /**
     * Execute the console command.
     */
    public function handle(AuditLogService $auditLog): int
    {
        $email = $this->option('email');

        if (! $email) {
            $this->error('Please provide an email address using --email=...');

            return 1;
        }

        if (User::role('chairman')->exists()) {
            $this->error('A chairman already exists. Cannot bootstrap a second one.');

            return 1;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found.");

            return 1;
        }

        if ($user->hasRole('admin') || $user->can('officers.manage-permissions')) {
            $this->error('The chairman account cannot also hold role-management authority. Choose a separate account.');

            return 1;
        }

        if ($user->account_status !== 'active') {
            $this->error('The chairman account must be active before provisioning.');

            return 1;
        }

        $user->syncRoles(['chairman']);
        $user->forceFill(['role' => 'chairman', 'is_officer' => true])->save();

        $auditLog->log(
            'role.assigned',
            'Chairman role provisioned via CLI.',
            'officer',
            $user
        );

        $this->info("User {$email} is now the chairman.");

        return 0;
    }
}
