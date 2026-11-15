<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

App\Models\User::query()->update(['current_session_id' => null]);
App\Models\LoginHandoff::query()->delete();
App\Models\Transaction::whereIn('loan_id', [16, 18])->where('status', 'pending')->delete();

$user = App\Models\User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->first();
if ($user) {
    $accountIds = $user->accounts()->pluck('id')->toArray();
    if (!empty($accountIds)) {
        App\Models\Transaction::whereIn('account_id', $accountIds)->where('status', 'pending')->delete();
    }
}
echo "Sessions, handoffs, and pending transactions cleared successfully.\n";
