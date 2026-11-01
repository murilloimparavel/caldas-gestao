<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table): void {
            $table->uuid('notification_membership_id')->nullable();
            $table->foreign(['tenant_id', 'notification_membership_id'], 'professionals_notification_membership_fk')->references(['tenant_id', 'id'])->on('memberships')->restrictOnDelete();
            $table->unique(['tenant_id', 'unit_id', 'notification_membership_id'], 'professional_notification_membership_unique');
        });
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('new_appointments')->default(true);
            $table->boolean('changes')->default(true);
            $table->boolean('cancellations')->default(true);
            $table->boolean('reminders')->default(true);
            $table->unsignedSmallInteger('reminder_minutes')->default(15);
            $table->string('quiet_start', 5)->nullable();
            $table->string('quiet_end', 5)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });
        Schema::create('agenda_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('unit_id');
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('professional_id')->nullable();
            $table->foreign(['tenant_id', 'unit_id', 'professional_id'], 'agenda_notification_professional_fk')->references(['tenant_id', 'unit_id', 'id'])->on('professionals')->restrictOnDelete();
            $table->uuid('appointment_id')->nullable();
            $table->foreign(['tenant_id', 'unit_id', 'appointment_id'], 'agenda_notification_appointment_fk')->references(['tenant_id', 'unit_id', 'id'])->on('appointments')->restrictOnDelete();
            $table->string('category', 40);
            $table->string('deduplication_key', 191)->unique();
            $table->string('title');
            $table->text('body');
            $table->timestamp('appointment_starts_at')->nullable();
            $table->unsignedInteger('appointment_version')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('push_processed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'read_at', 'created_at'], 'agenda_notification_inbox_index');
            $table->index(['push_processed_at', 'expires_at']);
        });
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64);
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('origin');
            $table->char('session_hash', 64)->index();
            $table->string('device_name', 80);
            $table->timestamps();
            $table->unique(['tenant_id', 'endpoint_hash']);
            $table->index(['tenant_id', 'user_id']);
        });
        Schema::create('push_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('agenda_notification_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('push_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->unique(['agenda_notification_id', 'push_subscription_id'], 'push_delivery_notification_subscription_unique');
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('agenda_notifications');
        Schema::dropIfExists('notification_preferences');
        Schema::table('professionals', function (Blueprint $table): void {
            $table->dropForeign('professionals_notification_membership_fk');
            $table->dropUnique('professional_notification_membership_unique');
            $table->dropColumn('notification_membership_id');
        });
    }
};
