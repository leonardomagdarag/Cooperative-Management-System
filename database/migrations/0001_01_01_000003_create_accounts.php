<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('account_type', ['savings', 'share-capital'])->default('savings');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('loan_type', ['group', 'business', 'special'])->default('business');
            $table->decimal('loan_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('term_months')->nullable();
            $table->enum('payment_frequency', ['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'])->default('monthly');
            $table->enum('loan_status', ['pending', 'approved', 'active', 'paid_off'])->default('pending');
            $table->dateTime('applied_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->date('due_date')->nullable();

            // Business loan fields
            $table->unsignedBigInteger('employment_id')->nullable();
            $table->dateTime('interview_schedule')->nullable();
            $table->enum('interview_status', ['pending', 'scheduled', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('interview_officer_id')->nullable();
            $table->text('interview_notes')->nullable();

            $table->foreign('employment_id')->references('id')->on('employments')->onDelete('set null');
            $table->foreign('interview_officer_id')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });

        // Group loan members pivot table
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

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 20)->unique();
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'fee'])->default('deposit');
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->foreignId('loan_id')->nullable()->constrained('loans');
            $table->decimal('amount', 15, 2);
            $table->dateTime('transaction_date')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();

            // Indexes for common queries
            $table->index('transaction_type');
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('group_loan_members');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('accounts');
    }
};
