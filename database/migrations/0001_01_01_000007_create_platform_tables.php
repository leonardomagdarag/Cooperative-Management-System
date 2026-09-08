<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', [
                'announcement',
                'loan_due',
                'loan_approved',
                'loan_rejected',
                'loan_payment',
                'security_alert',
                'email_verification',
                'otp',
                'general',
            ]);
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('related_type')->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['priority', 'created_at']);
        });

        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('section');
            $table->string('key');
            $table->text('value');
            $table->string('type')->default('text');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['section', 'key']);
            $table->index('section');
        });

        Schema::create('board_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position');
            $table->string('title')->nullable();
            $table->text('motto')->nullable();
            $table->string('photo')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('bio')->nullable();
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('position');
            $table->index('is_active');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('type')->default('text');
            $table->string('group')->default('general');
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('options')->nullable();
            $table->boolean('is_editable')->default(true);
            $table->boolean('requires_restart')->default(false);
            $table->timestamps();

            $table->index('group');
            $table->index('key');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('type', ['news', 'announcement', 'event'])->default('announcement');
            $table->text('visibility');
            $table->string('category', 100)->nullable();
            $table->string('image_path')->nullable();
            $table->date('event_date')->nullable();
            $table->string('event_time', 50)->nullable();
            $table->string('event_location', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100);
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->dateTime('expense_date');
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('expense_date');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('board_members');
        Schema::dropIfExists('site_contents');
        Schema::dropIfExists('notifications');
    }
};
