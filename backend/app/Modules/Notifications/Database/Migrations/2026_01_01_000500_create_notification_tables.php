<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80);
            $table->string('channel', 20); // in_app, mail, sms
            $table->string('subject')->nullable();
            $table->text('body'); // {{ placeholders }}
            $table->boolean('mandatory')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['code', 'channel']);
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('template_code', 80);
            $table->string('title');
            $table->text('body');
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('template_code', 80);
            $table->string('channel', 20);
            $table->string('recipient');
            $table->string('status', 20); // queued, sent, failed, skipped
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('template_code', 80);
            $table->string('channel', 20);
            $table->boolean('enabled');
            $table->timestamps();
            $table->unique(['user_id', 'template_code', 'channel']);
        });
    }

    public function down(): void
    {
        foreach (['notification_preferences', 'notification_deliveries', 'user_notifications', 'notification_templates'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
