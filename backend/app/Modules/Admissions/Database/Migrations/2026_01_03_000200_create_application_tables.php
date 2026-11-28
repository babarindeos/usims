<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Applications through offers, acceptance, onboarding and clearance (PRD 5.3 to 6.3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('application_no', 30)->nullable()->unique(); // issued on submission
            $table->foreignId('person_id')->constrained('persons');
            $table->foreignId('admission_exercise_id')->constrained('admission_exercises');
            $table->foreignId('exercise_programme_id')->nullable()->constrained('exercise_programmes'); // first choice, or the programme Registry moved it to
            $table->foreignId('second_choice_id')->nullable()->constrained('exercise_programmes');
            $table->string('status', 20)->default('draft'); // draft, submitted, under_review, recommended, on_hold, rejected, offered, withdrawn
            $table->json('next_of_kin')->nullable(); // {name, relationship, phone, address}
            $table->string('jamb_reg_no', 20)->nullable();
            $table->unsignedSmallInteger('jamb_year')->nullable();
            $table->unsignedSmallInteger('utme_score')->nullable();
            $table->json('utme_subjects')->nullable(); // olevel subject codes
            $table->string('screening_outcome', 30)->nullable(); // eligible, ineligible, pending_verification, manual_review
            $table->timestamp('screened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('corrections_open')->default(false);
            $table->timestamps();
            $table->unique(['person_id', 'admission_exercise_id']);
            $table->index(['admission_exercise_id', 'status']);
            $table->index('jamb_reg_no');
        });

        Schema::create('application_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications');
            $table->foreignId('document_type_id')->constrained('document_types');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('status', 20)->default('pending'); // pending, verified, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('superseded_at')->nullable(); // a replacement upload supersedes, never deletes
            $table->timestamps();
            $table->index(['application_id', 'document_type_id']);
        });

        Schema::create('olevel_sittings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('exam_body_id')->constrained('exam_bodies');
            $table->unsignedSmallInteger('exam_year');
            $table->string('exam_number', 30);
            $table->timestamps();
            $table->unique(['application_id', 'exam_body_id', 'exam_year', 'exam_number']);
        });

        Schema::create('olevel_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olevel_sitting_id')->constrained('olevel_sittings')->cascadeOnDelete();
            $table->foreignId('olevel_subject_id')->constrained('olevel_subjects');
            $table->foreignId('exam_grade_id')->constrained('exam_grades');
            $table->unique(['olevel_sitting_id', 'olevel_subject_id']);
        });

        Schema::create('education_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('institution');
            $table->string('qualification', 100);
            $table->string('course_of_study')->nullable();
            $table->string('grade', 50)->nullable();
            $table->unsignedSmallInteger('year_obtained');
            $table->timestamps();
        });

        // Every screening run is kept, with the rules it used and the reason for each check.
        Schema::create('screening_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications');
            $table->foreignId('exercise_programme_id')->constrained('exercise_programmes');
            $table->string('outcome', 30);
            $table->json('checks');
            $table->json('rules');
            $table->foreignId('screened_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        // Fees until the payment gateway module (M4) takes over: the candidate records a bank
        // payment, Bursary verifies it against the bank. Nothing here is marked paid automatically.
        Schema::create('admission_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications');
            $table->string('purpose', 30); // application_fee, acceptance_fee
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('awaiting_payment'); // awaiting_payment, submitted, verified, rejected
            $table->string('method', 20)->nullable(); // bank (gateway methods arrive in M4)
            $table->string('reference', 60)->nullable();
            $table->date('paid_on')->nullable();
            $table->foreignId('evidence_document_id')->nullable()->constrained('application_documents');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'purpose']);
        });
        DB::statement("CREATE UNIQUE INDEX admission_payments_reference_unique ON admission_payments (method, reference) WHERE reference IS NOT NULL AND status <> 'rejected'");

        Schema::create('admission_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admission_exercise_id')->constrained('admission_exercises');
            $table->string('name');
            $table->string('status', 20)->default('draft'); // draft, in_review, returned, approved, rejected
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_list_id')->constrained('admission_lists')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications');
            $table->foreignId('exercise_programme_id')->constrained('exercise_programmes');
            $table->timestamps();
            $table->unique(['admission_list_id', 'application_id']);
        });

        Schema::create('admission_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('admission_no', 30)->unique();
            $table->foreignId('application_id')->unique()->constrained('applications');
            $table->foreignId('admission_list_id')->nullable()->constrained('admission_lists');
            $table->foreignId('programme_id')->constrained('programmes');
            $table->unsignedSmallInteger('entry_level');
            $table->timestamp('offered_at');
            $table->date('accept_by');
            $table->string('status', 20)->default('offered'); // offered, accepted, declined, expired, withdrawn
            $table->timestamp('responded_at')->nullable();
            $table->boolean('declaration_accepted')->default(false);
            $table->text('decline_reason')->nullable();
            $table->longText('letter'); // rendered at issue and kept as issued
            $table->json('onboarding')->nullable(); // sponsor, residence, medical, declarations
            $table->timestamp('onboarding_submitted_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('clearance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_offer_id')->constrained('admission_offers');
            $table->foreignId('clearance_unit_id')->constrained('clearance_units');
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units'); // the unit that reviews it
            $table->string('status', 20)->default('pending'); // pending, submitted, under_review, approved, rejected, requires_correction
            $table->timestamps();
            $table->unique(['admission_offer_id', 'clearance_unit_id']);
            $table->index(['org_unit_id', 'status']);
        });

        Schema::create('clearance_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_item_id')->constrained('clearance_items');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('remarks')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['clearance_actions', 'clearance_items', 'admission_offers', 'admission_list_entries', 'admission_lists', 'admission_payments', 'screening_results', 'education_records', 'olevel_results', 'olevel_sittings', 'application_documents', 'application_status_history', 'applications'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
