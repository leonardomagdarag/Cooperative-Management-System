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
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->enum('loan_type', ['group', 'business', 'special'])->default('business');
            $table->decimal('loan_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('term_months')->nullable();
            $table->enum('payment_frequency', ['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'])->default('monthly');
            $table->enum('loan_status', ['pending', 'approved', 'active', 'paid_off'])->default('pending');
            $table->enum('review_status', ['under_review', 'endorsed_for_approval', 'review_rejected'])->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->date('due_date')->nullable();

            $table->unsignedBigInteger('employment_id')->nullable();
            $table->dateTime('interview_schedule')->nullable();
            $table->enum('interview_status', ['pending', 'scheduled', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('interview_officer_id')->nullable();
            $table->text('interview_notes')->nullable();

            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('employment_id')->references('id')->on('employments')->onDelete('set null');
            $table->foreign('interview_officer_id')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index('review_status');
            $table->index(['loan_status', 'review_status'], 'loans_status_review_status_index');
            $table->index(['loan_status', 'applied_at'], 'loans_status_applied_at_index');
        });

        Schema::create('group_loan_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('has_approved')->default(false);
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['loan_id', 'user_id']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 20)->unique();
            $table->string('client_request_id', 100)->nullable()->unique();
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'fee'])->default('deposit');
            $table->string('transaction_mode')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->foreignId('loan_id')->nullable()->constrained('loans');
            $table->decimal('amount', 15, 2);
            $table->dateTime('transaction_date')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('proof_of_payment_path')->nullable();
            $table->timestamp('proof_of_payment_uploaded_at')->nullable();
            $table->timestamps();

            $table->index('transaction_type');
            $table->index('transaction_date');
            $table->index('status');
            $table->index(['status', 'transaction_type'], 'transactions_status_type_index');
            $table->index(['account_id', 'status', 'transaction_type'], 'transactions_account_status_type_index');
            $table->index(['status', 'created_at'], 'transactions_status_created_at_index');
        });

        Schema::create('archived_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->string('transaction_number', 20)->index();
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'interest', 'fee']);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->dateTime('transaction_date')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->useCurrent();

            $table->index('transaction_type');
            $table->index('transaction_date');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_transactions');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('group_loan_members');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('accounts');
    }
};
