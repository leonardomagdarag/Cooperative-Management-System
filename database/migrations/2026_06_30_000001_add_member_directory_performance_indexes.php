<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at', 'users_created_at_index');
            $table->index('account_status', 'users_account_status_index');
            $table->index('is_officer', 'users_is_officer_index');
            $table->index('personal_data_id', 'users_personal_data_id_index');
        });

        Schema::table('personal_data', function (Blueprint $table) {
            $table->index('first_name', 'personal_data_first_name_index');
            $table->index('last_name', 'personal_data_last_name_index');
            $table->index('mid_name', 'personal_data_mid_name_index');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index('phone_no', 'contacts_phone_no_index');
            $table->index('facebook', 'contacts_facebook_index');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->index('city_municipality', 'addresses_city_municipality_index');
            $table->index('province', 'addresses_province_index');
            $table->index('barangay', 'addresses_barangay_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['status', 'transaction_type'], 'transactions_status_type_index');
            $table->index(['account_id', 'status', 'transaction_type'], 'transactions_account_status_type_index');
            $table->index(['status', 'created_at'], 'transactions_status_created_at_index');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->index(['loan_status', 'review_status'], 'loans_status_review_status_index');
            $table->index(['loan_status', 'applied_at'], 'loans_status_applied_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_barangay_index');
            $table->dropIndex('addresses_province_index');
            $table->dropIndex('addresses_city_municipality_index');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_status_applied_at_index');
            $table->dropIndex('loans_status_review_status_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_status_created_at_index');
            $table->dropIndex('transactions_account_status_type_index');
            $table->dropIndex('transactions_status_type_index');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_facebook_index');
            $table->dropIndex('contacts_phone_no_index');
        });

        Schema::table('personal_data', function (Blueprint $table) {
            $table->dropIndex('personal_data_mid_name_index');
            $table->dropIndex('personal_data_last_name_index');
            $table->dropIndex('personal_data_first_name_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_personal_data_id_index');
            $table->dropIndex('users_is_officer_index');
            $table->dropIndex('users_account_status_index');
            $table->dropIndex('users_created_at_index');
        });
    }
};
