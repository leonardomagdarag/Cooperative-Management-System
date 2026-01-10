<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update any existing loan types to 'business' as default
        DB::table('loans')->whereNotIn('loan_type', ['group', 'business'])->update(['loan_type' => 'business']);
        
        Schema::table('loans', function (Blueprint $table) {
            // Modify loan_type to only allow 'group' and 'business'
            $table->enum('loan_type', ['group', 'business'])->change();
            
            // Add fields for business loan
            $table->unsignedBigInteger('employment_id')->nullable()->after('loan_type');
            $table->dateTime('interview_schedule')->nullable()->after('employment_id');
            $table->enum('interview_status', ['pending', 'scheduled', 'completed', 'cancelled'])->default('pending')->after('interview_schedule');
            $table->unsignedBigInteger('interview_officer_id')->nullable()->after('interview_status');
            $table->text('interview_notes')->nullable()->after('interview_officer_id');
            
            // Add payment frequency if not exists
            if (!Schema::hasColumn('loans', 'payment_frequency')) {
                $table->enum('payment_frequency', ['weekly', 'monthly'])->default('monthly')->after('term_months');
            }
            
            // Foreign keys
            $table->foreign('employment_id')->references('id')->on('employments')->onDelete('set null');
            $table->foreign('interview_officer_id')->references('id')->on('users')->onDelete('set null');
        });
        
        // Create group_loan_members pivot table
        Schema::create('group_loan_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('has_approved')->default(false);
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Ensure unique combination
            $table->unique(['loan_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_loan_members');
        
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['employment_id']);
            $table->dropForeign(['interview_officer_id']);
            
            $table->dropColumn([
                'employment_id',
                'interview_schedule',
                'interview_status',
                'interview_officer_id',
                'interview_notes'
            ]);
        });
    }
};
