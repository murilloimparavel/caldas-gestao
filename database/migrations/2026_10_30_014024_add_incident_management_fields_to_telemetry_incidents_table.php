<?php

use App\Support\Telemetry\IncidentClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry_incidents', function (Blueprint $table): void {
            $table->string('title', 180)->default('Incidente capturado');
            $table->string('classification', 40)->default('unclassified')->index();
            $table->string('module', 40)->default('platform')->index();
            $table->string('severity', 16)->default('medium')->index();
            $table->unsignedSmallInteger('confidence')->default(0);
            $table->string('impact_summary', 400)->default('Sinal aguardando avaliação técnica.');
            $table->string('workflow_status', 24)->default('new')->index();
            $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('detection_source', 32)->default('server');
            $table->unsignedInteger('lock_version')->default(0);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('auto_diagnosis_eligible')->default(false);
            $table->unsignedSmallInteger('diagnosis_threshold')->default(3);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('duration_bucket', 24)->nullable();
            $table->timestampTz('resolved_at')->nullable();
        });
        Schema::create('telemetry_incident_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('incident_id')->constrained('telemetry_incidents')->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->string('reason_code', 32)->nullable();
            $table->timestampTz('created_at');
        });
        Schema::create('telemetry_runtime_health', function (Blueprint $table): void {
            $table->string('component', 32)->primary();
            $table->string('status', 16);
            $table->json('checks');
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('last_healthy_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
        });
        DB::table('telemetry_incidents')->orderBy('id')->chunkById(100, function ($incidents): void {
            foreach ($incidents as $incident) {
                $classification = IncidentClassifier::classify($incident->kind, $incident->category, $incident->exception_class, $incident->route_name, hasFrames: json_decode($incident->frames, true) !== []);
                DB::table('telemetry_incidents')->where('id', $incident->id)->update($classification);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_runtime_health');
        Schema::dropIfExists('telemetry_incident_events');
        Schema::table('telemetry_incidents', function (Blueprint $table): void {
            $table->dropForeign(['owner_user_id']);
            $table->dropColumn(['title', 'classification', 'module', 'severity', 'confidence', 'impact_summary', 'workflow_status', 'owner_user_id', 'detection_source', 'lock_version', 'priority', 'auto_diagnosis_eligible', 'diagnosis_threshold', 'http_status', 'duration_bucket', 'resolved_at']);
        });
    }
};
