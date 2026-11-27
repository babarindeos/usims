<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('process', 60);  // e.g. course_registration, fee_structure_approval
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            // Selection conditions such as {"programme_id": 4, "level": 300}. Most specific match wins.
            $table->json('conditions')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('allow_self_approval')->default(false);
            $table->string('status', 20)->default('active'); // draft, active, retired
            $table->timestamps();
            $table->index(['process', 'status']);
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('code', 60);
            $table->string('name');
            // Who may act: a permission checked against the subject's org unit.
            $table->string('permission_code', 100);
            $table->unsignedSmallInteger('due_in_hours')->nullable();
            $table->boolean('can_return')->default(true);
            $table->timestamps();
            $table->unique(['workflow_definition_id', 'sequence']);
            $table->unique(['workflow_definition_id', 'code']);
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 40)->unique();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions');
            $table->string('subject_type', 150);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units');
            $table->foreignId('current_stage_id')->nullable()->constrained('workflow_stages');
            $table->string('status', 20); // in_progress, returned, approved, rejected, cancelled
            $table->foreignId('initiated_by')->nullable()->constrained('users');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'current_stage_id']);
        });

        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances');
            $table->foreignId('workflow_stage_id')->nullable()->constrained('workflow_stages');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('role_code', 60)->nullable();
            $table->string('action', 20); // submit, approve, reject, return, resubmit, cancel, escalate
            $table->text('remarks')->nullable();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('reference', 50)->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['workflow_actions', 'workflow_instances', 'workflow_stages', 'workflow_definitions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
