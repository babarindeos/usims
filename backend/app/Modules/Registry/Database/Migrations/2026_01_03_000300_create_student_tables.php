<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Student master record, status history and matric number formats (PRD 6.4, 6.5, 7.2, 7.3). */
return new class extends Migration
{
    public function up(): void
    {
        // Most specific matching format wins; tokens are documented in MatricNumberService.
        Schema::create('matric_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('format', 100); // e.g. {YEAR}/{DEPT}/{SEQ:4}
            $table->foreignId('admission_category_id')->nullable()->constrained('admission_categories');
            $table->foreignId('programme_category_id')->nullable()->constrained('programme_categories');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('student_no', 20)->unique(); // STU-000123, internal and permanent
            $table->string('matric_no', 40)->unique();
            $table->foreignId('person_id')->constrained('persons');
            $table->foreignId('admission_offer_id')->unique()->constrained('admission_offers');
            $table->foreignId('programme_id')->constrained('programmes');
            $table->foreignId('admission_category_id')->constrained('admission_categories');
            $table->foreignId('admission_session_id')->constrained('academic_sessions');
            $table->foreignId('curriculum_version_id')->nullable()->constrained('curriculum_versions');
            $table->unsignedSmallInteger('entry_level');
            $table->unsignedSmallInteger('current_level');
            $table->string('status', 20); // matriculated, active, deferred, suspended, withdrawn, expelled, graduated, deceased
            $table->timestamp('matriculated_at');
            $table->foreignId('matriculated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['programme_id', 'current_level', 'status']);
        });

        Schema::create('student_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->date('effective_on');
            $table->text('reason');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
        // History rows are never edited or removed.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION student_status_history_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'student_status_history is append-only';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER student_status_history_no_change BEFORE UPDATE OR DELETE ON student_status_history
                FOR EACH ROW EXECUTE FUNCTION student_status_history_append_only();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS student_status_history_no_change ON student_status_history; DROP FUNCTION IF EXISTS student_status_history_append_only();');
        foreach (['student_status_history', 'students', 'matric_formats'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
