<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->enum('base_type', ['group', 'business', 'special'])->default('business');
            $table->string('icon', 50)->default('wallet');
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('max_amount', 15, 2)->default(0);
            $table->unsignedInteger('max_term')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_no_existing_loan')->default(false);
            $table->decimal('minimum_share_capital', 15, 2)->default(0);
            $table->unsignedInteger('minimum_membership_years')->default(0);
            $table->unsignedTinyInteger('required_co_makers')->default(0);
            $table->boolean('requires_interview')->default(false);
            $table->boolean('requires_special_eligibility')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('base_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
