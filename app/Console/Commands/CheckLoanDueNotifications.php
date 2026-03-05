<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckLoanDueNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:check-loan-due';

    /**
     * The console command description.
     */
    protected $description = 'Check for loans due soon and send notifications';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for loans due soon...');

        // Get approved loans that are not yet paid
        $loans = Loan::where('loan_status', 'approved')
            ->whereNotNull('due_date')
            ->get();

        $notificationsSent = 0;

        foreach ($loans as $loan) {
            $dueDate = Carbon::parse($loan->due_date);
            $today = Carbon::today();
            $daysUntilDue = $today->diffInDays($dueDate, false);

            // Check if loan is overdue
            if ($daysUntilDue < 0) {
                // Check if we haven't already sent an overdue notification today
                $alreadyNotified = $loan->user->notifications()
                    ->where('type', 'loan_due')
                    ->where('related_id', $loan->id)
                    ->where('message', 'like', '%overdue%')
                    ->where('created_at', '>=', Carbon::today())
                    ->exists();

                if (!$alreadyNotified) {
                    $this->notificationService->notifyLoanOverdue($loan);
                    $this->line("Sent overdue notification for loan #{$loan->loan_number}");
                    $notificationsSent++;
                }
                continue;
            }

            // Check for 7, 3, or 1 day warnings
            $warningDays = [7, 3, 1];

            foreach ($warningDays as $days) {
                if ($daysUntilDue == $days) {
                    // Check if we haven't already sent this notification
                    $alreadyNotified = $loan->user->notifications()
                        ->where('type', 'loan_due')
                        ->where('related_id', $loan->id)
                        ->where('message', 'like', "%{$days} day%")
                        ->where('created_at', '>=', Carbon::today())
                        ->exists();

                    if (!$alreadyNotified) {
                        $this->notificationService->notifyLoanDue($loan, $days);
                        $this->line("Sent {$days}-day warning for loan #{$loan->loan_number}");
                        $notificationsSent++;
                    }
                }
            }
        }

        $this->info("Sent {$notificationsSent} loan due notifications.");

        return Command::SUCCESS;
    }
}
