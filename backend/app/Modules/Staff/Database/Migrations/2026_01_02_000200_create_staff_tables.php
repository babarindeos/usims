<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal staff master used by course allocation. The full HR module (appointments, promotions,
     * leave, exits) extends this table in M6, or an adapter keeps it in step with an external HR system.
     */
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('person_id')->unique()->constrained('persons');
            $table->string('staff_no', 30)->unique();
            $table->foreignId('org_unit_id')->constrained('org_units');
            $table->string('category', 30); // academic, administrative, technical, professional, management, contract, temporary
            $table->string('rank', 100)->nullable();
            $table->string('institutional_email')->nullable()->unique();
            $table->string('employment_status', 20)->default('active'); // active, on_leave, suspended, exited
            $table->string('source', 20)->default('usims'); // usims or an external HR system
            $table->timestamps();
            $table->index(['org_unit_id', 'employment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
