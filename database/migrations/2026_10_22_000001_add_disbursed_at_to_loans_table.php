<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dateTime('disbursed_at')->nullable()->after('approved_at');
        });

        // Preserve the original repayment start for loans already released.
        DB::table('loans')->whereIn('loan_status', ['active', 'paid_off'])->orderBy('id')->chunkById(100, function ($loans) {
            foreach ($loans as $loan) {
                $disbursement = DB::table('transactions')
                    ->where('loan_id', $loan->id)
                    ->where('transaction_type', 'loan_disbursement')
                    ->where('status', 'completed')
                    ->orderBy('transaction_date')
                    ->orderBy('id')
                    ->first(['transaction_date', 'created_at']);

                $date = $disbursement?->transaction_date
                    ?? $disbursement?->created_at
                    ?? ($loan->due_date && $loan->term_months
                        ? Carbon::parse($loan->due_date)->subMonths((int) $loan->term_months)->toDateTimeString()
                        : null)
                    ?? $loan->approved_at
                    ?? $loan->created_at;

                DB::table('loans')->where('id', $loan->id)->update(['disbursed_at' => $date]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('disbursed_at');
        });
    }
};
