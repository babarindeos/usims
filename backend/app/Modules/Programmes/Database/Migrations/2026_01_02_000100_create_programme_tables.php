<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reference lists every institution configures (PRD 4.2).
        Schema::create('award_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // BSC, MSC, PHD, PGD, ND ...
            $table->string('name');
            $table->string('level', 30); // sub_degree, undergraduate, postgraduate
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('study_modes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // full_time, part_time, distance ...
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('programme_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // undergraduate, postgraduate, diploma ...
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('org_unit_id')->constrained('org_units'); // owning department or unit
            $table->foreignId('award_type_id')->constrained('award_types');
            $table->foreignId('programme_category_id')->constrained('programme_categories');
            $table->unsignedTinyInteger('duration_years');
            $table->unsignedTinyInteger('min_duration_years');
            $table->unsignedTinyInteger('max_duration_years');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('accreditation_status', 30)->nullable(); // full, interim, denied, pending
            $table->date('accreditation_expires_on')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active, suspended, phased_out
            $table->timestamps();
            $table->index('org_unit_id');
        });
        DB::statement('ALTER TABLE programmes ADD CONSTRAINT programmes_duration_order CHECK (min_duration_years <= duration_years AND duration_years <= max_duration_years)');

        Schema::create('programme_study_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->cascadeOnDelete();
            $table->foreignId('study_mode_id')->constrained('study_modes');
            $table->unsignedTinyInteger('duration_years')->nullable(); // when the mode differs from the default
            $table->unique(['programme_id', 'study_mode_id']);
        });

        // Levels at which students may join, e.g. 100 for UTME and 200 for direct entry.
        Schema::create('programme_entry_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->unique(['programme_id', 'level']);
        });
    }

    public function down(): void
    {
        foreach (['programme_entry_levels', 'programme_study_modes', 'programmes', 'programme_categories', 'study_modes', 'award_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
