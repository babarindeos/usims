<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Institution-defined lists and admission exercises (PRD 4.3, 5.1, 5.2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // UTME, DE, PG, TRANSFER …
            $table->string('name');
            $table->boolean('requires_jamb')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('exam_bodies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // WAEC, NECO, NABTEB …
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Each body has its own grade scale; screening uses points and the credit flag, never letters.
        Schema::create('exam_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_body_id')->constrained('exam_bodies')->cascadeOnDelete();
            $table->string('grade', 5);
            $table->unsignedTinyInteger('points');
            $table->boolean('is_credit')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['exam_body_id', 'grade']);
        });

        Schema::create('olevel_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // ENG, MTH, PHY …
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('needs_verification')->default(true);
            $table->unsignedInteger('max_kb')->default(2048);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('admission_exercises', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('academic_session_id')->constrained('academic_sessions');
            $table->foreignId('admission_category_id')->constrained('admission_categories');
            $table->string('name');
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->decimal('application_fee', 12, 2)->default(0);
            $table->decimal('acceptance_fee', 12, 2)->default(0);
            $table->unsignedInteger('capacity')->nullable(); // applications accepted in total
            $table->unsignedSmallInteger('acceptance_days')->default(14); // offer must be accepted within
            $table->text('instructions')->nullable();
            $table->string('status', 20)->default('draft'); // draft, scheduled, open, suspended, closed
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['status', 'opens_at']);
        });
        DB::statement('ALTER TABLE admission_exercises ADD CONSTRAINT admission_exercises_dates CHECK (opens_at < closes_at)');

        // What can be applied for in an exercise, with its own capacity and screening rules.
        Schema::create('exercise_programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_exercise_id')->constrained('admission_exercises')->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained('programmes');
            $table->unsignedSmallInteger('entry_level')->default(100);
            $table->unsignedInteger('capacity')->nullable();
            $table->json('rules')->nullable(); // see ScreeningEngine for the keys
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['admission_exercise_id', 'programme_id', 'entry_level']);
        });

        Schema::create('exercise_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_exercise_id')->constrained('admission_exercises')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types');
            $table->string('stage', 20)->default('application'); // application, onboarding
            $table->boolean('required')->default(true);
            $table->timestamps();
            $table->unique(['admission_exercise_id', 'document_type_id', 'stage']);
        });

        // Units that clear admitted candidates; target "department"/"faculty" resolve from the programme.
        Schema::create('clearance_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('target', 20)->default('fixed'); // fixed, department, faculty
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units'); // for fixed
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['clearance_units', 'exercise_documents', 'exercise_programmes', 'admission_exercises', 'document_types', 'olevel_subjects', 'exam_grades', 'exam_bodies', 'admission_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
