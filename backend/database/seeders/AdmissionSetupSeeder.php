<?php

namespace Database\Seeders;

use App\Modules\Admissions\Models\AdmissionCategory;
use App\Modules\Admissions\Models\ClearanceUnit;
use App\Modules\Admissions\Models\DocumentType;
use App\Modules\Admissions\Models\ExamBody;
use App\Modules\Admissions\Models\OlevelSubject;
use App\Modules\Admissions\Services\AdmissionListService;
use App\Modules\Institution\Models\Setting;
use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Registry\Models\MatricFormat;
use App\Modules\Registry\Services\MatricNumberService;
use App\Modules\Workflow\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

/**
 * Starting reference data for admissions. Every row is editable from the admissions configuration
 * screens; existing rows are never changed by re-running this seeder.
 */
class AdmissionSetupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['UTME', 'UTME', true], ['DE', 'Direct Entry', false], ['PG', 'Postgraduate', false], ['TRANSFER', 'Inter-university transfer', false]] as [$code, $name, $jamb]) {
            AdmissionCategory::firstOrCreate(['code' => $code], ['name' => $name, 'requires_jamb' => $jamb]);
        }

        // West African school certificate scale; points rank grades, the credit flag drives screening.
        $scale = [['A1', 9, true], ['B2', 8, true], ['B3', 7, true], ['C4', 6, true], ['C5', 5, true], ['C6', 4, true], ['D7', 3, false], ['E8', 2, false], ['F9', 1, false]];
        foreach ([['WAEC', 'West African Examinations Council (WASSCE)'], ['NECO', 'National Examinations Council (SSCE)'], ['NABTEB', 'National Business and Technical Examinations Board']] as [$code, $name]) {
            $body = ExamBody::firstOrCreate(['code' => $code], ['name' => $name]);
            if ($body->wasRecentlyCreated) {
                foreach ($scale as $i => [$grade, $points, $credit]) {
                    $body->grades()->create(['grade' => $grade, 'points' => $points, 'is_credit' => $credit, 'sort_order' => $i]);
                }
            }
        }

        $subjects = [
            'ENG' => 'English Language', 'MTH' => 'Mathematics', 'PHY' => 'Physics', 'CHM' => 'Chemistry', 'BIO' => 'Biology',
            'AGR' => 'Agricultural Science', 'ECO' => 'Economics', 'GEO' => 'Geography', 'FMT' => 'Further Mathematics',
            'CIV' => 'Civic Education', 'LIT' => 'Literature in English', 'GOV' => 'Government', 'CRS' => 'Christian Religious Studies',
            'IRS' => 'Islamic Religious Studies', 'COM' => 'Commerce', 'ACC' => 'Financial Accounting', 'TDR' => 'Technical Drawing',
            'CMP' => 'Computer Studies', 'YOR' => 'Yoruba', 'IGB' => 'Igbo', 'HAU' => 'Hausa',
        ];
        foreach ($subjects as $code => $name) {
            OlevelSubject::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $documents = [
            ['PASSPORT', 'Passport photograph', true, 512], ['OLEVEL', "O'Level result", true, 2048], ['JAMB_RESULT', 'JAMB result slip', true, 2048],
            ['BIRTH_CERT', 'Birth certificate or declaration of age', true, 2048], ['LGA_ID', 'Local government identification', true, 2048],
            ['ACADEMIC_CERT', 'Academic certificate', true, 4096], ['TRANSCRIPT', 'Transcript', true, 4096],
            ['PAYMENT_EVIDENCE', 'Payment evidence (bank teller or receipt)', false, 2048], ['MEDICAL', 'Medical report', true, 4096],
        ];
        foreach ($documents as [$code, $name, $verify, $kb]) {
            DocumentType::firstOrCreate(['code' => $code], ['name' => $name, 'needs_verification' => $verify, 'max_kb' => $kb]);
        }

        if (! ClearanceUnit::exists()) {
            foreach ([['REGISTRY', 'Registry', 'fixed'], ['BURSARY', 'Bursary', 'fixed'], ['DEPARTMENT', 'Department', 'department'], ['MEDICAL', 'Medical Centre', 'fixed']] as $i => [$code, $name, $target]) {
                ClearanceUnit::create(['code' => $code, 'name' => $name, 'target' => $target, 'sort_order' => $i]);
            }
        }
        if (! MatricFormat::exists()) {
            MatricFormat::create(['name' => 'Default', 'format' => MatricNumberService::DEFAULT_FORMAT]);
        }
        Setting::firstOrCreate(['key' => 'auto_matriculate'], ['value' => false]);

        if (! WorkflowDefinition::where('process', AdmissionListService::PROCESS)->exists()) {
            $definition = WorkflowDefinition::create(['process' => AdmissionListService::PROCESS, 'code' => 'admission_approval_default', 'name' => 'Admission list approval', 'status' => 'active']);
            $definition->stages()->createMany([
                ['sequence' => 1, 'code' => 'committee', 'name' => 'Admissions Committee recommendation', 'permission_code' => 'admissions.recommend', 'due_in_hours' => 72],
                ['sequence' => 2, 'code' => 'approval', 'name' => 'Admission approval', 'permission_code' => 'admissions.approve', 'due_in_hours' => 72],
            ]);
        }

        $templates = [
            ['applicant.verify_email', 'mail', 'Verify your email address', "Hello {{ name }},\n\nConfirm your email address to continue your application:\n{{ link }}\n\nThe link expires in 24 hours."],
            ['application.submitted', 'in_app', 'Application {{ application_no }} received', 'Your application to {{ exercise }} was submitted.'],
            ['application.submitted', 'mail', 'Application {{ application_no }} received', "Your application to {{ exercise }} was submitted. Your application number is {{ application_no }}.\n\nKeep it for all enquiries."],
            ['application.decision', 'in_app', 'Application {{ application_no }}', 'Your application was {{ outcome }}.'],
            ['application.decision', 'mail', 'Application {{ application_no }}', "Your application {{ application_no }} was {{ outcome }}.\n\nSign in to the admission portal for details."],
            ['admission.payment_reviewed', 'in_app', 'Payment {{ outcome }}', 'Your {{ purpose }} payment was {{ outcome }}. {{ remarks }}'],
            ['admission.offered', 'in_app', 'Admission offered', 'You have been offered admission into {{ programme }}. Accept by {{ accept_by }}.'],
            ['admission.offered', 'mail', 'Offer of admission {{ admission_no }}', "Dear {{ name }},\n\nYou have been offered admission into {{ programme }}.\nAdmission number: {{ admission_no }}\n\nSign in to the admission portal to read your letter and accept by {{ accept_by }}."],
            ['clearance.updated', 'in_app', '{{ unit }} clearance {{ status }}', '{{ remarks }}'],
            ['student.matriculated', 'in_app', 'Welcome, {{ name }}', 'You are now a student. Your matriculation number is {{ matric_no }}.'],
            ['student.matriculated', 'mail', 'Your matriculation number', "Dear {{ name }},\n\nYou have been matriculated. Your matriculation number is {{ matric_no }}."],
        ];
        foreach ($templates as [$code, $channel, $subject, $body]) {
            NotificationTemplate::firstOrCreate(['code' => $code, 'channel' => $channel], ['subject' => $subject, 'body' => $body, 'mandatory' => in_array($code, ['applicant.verify_email', 'admission.offered'], true)]);
        }
    }
}
