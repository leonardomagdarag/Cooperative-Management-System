<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Officer review lifecycle — separate from final loan_status
            $table->enum('review_status', ['under_review', 'endorsed_for_approval', 'review_rejected'])
                ->nullable()
                ->after('loan_status');

            // Who performed the most recent review action
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('review_status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['review_status']);
            $table->dropColumn(['review_status', 'reviewed_by', 'reviewed_at', 'review_notes']);
        });
    }
};
