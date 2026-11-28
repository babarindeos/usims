<?php

namespace Tests\Feature\Academic;

use App\Modules\Institution\Models\Setting;
use App\Modules\Offerings\Events\OfferingPublished;
use App\Modules\Offerings\Models\CourseOffering;
use Illuminate\Support\Facades\Event;

class OfferingTest extends AcademicTestCase
{
    public function test_offerings_are_generated_from_published_curricula_within_scope(): void
    {
        $csc = $this->units['csc'];
        $mts = $this->units['mts'];
        $session = $this->academicSession();
        $first = $this->semester($session, 1);
        $this->curriculum($this->programme($csc), $session, [
            $this->course($csc, 'CSC 101'), $this->course($csc, 'CSC 102', semester: 2), $this->course($mts, 'MTS 101'),
        ], 'published');
        $this->curriculum($this->programme($mts, 'BSC-MTS'), $session, [$this->course($mts, 'MTS 103')]); // draft: ignored

        $this->actingAs($this->departmentOfficer($csc));
        $this->postJson("/api/v1/semesters/{$first->id}/offerings/generate")->assertOk()
            ->assertJsonPath('data', ['created' => 1, 'linked' => 1, 'skipped_out_of_scope' => 1]);
        $this->postJson("/api/v1/semesters/{$first->id}/offerings/generate")->assertJsonPath('data.created', 0);

        $offering = CourseOffering::with('course', 'programmes')->sole();
        $this->assertSame('CSC 101', $offering->course->code);
        $this->assertSame(100, $offering->programmes->first()->level);

        $this->actingAs($this->userWith([]))->postJson("/api/v1/semesters/{$first->id}/offerings/generate")->assertForbidden();
    }

    public function test_one_offering_per_course_semester_and_group(): void
    {
        $csc = $this->units['csc'];
        $semester = $this->semester($this->academicSession());
        $course = $this->course($csc, 'CSC 101');
        $this->actingAs($this->departmentOfficer($csc));

        $payload = ['course_id' => $course->uuid, 'semester_id' => $semester->id];
        $this->postJson('/api/v1/offerings', $payload)->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->postJson('/api/v1/offerings', $payload)->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->postJson('/api/v1/offerings', $payload + ['group_code' => 'b'])->assertCreated()->assertJsonPath('data.group_code', 'B');
    }

    public function test_publishing_sets_the_lms_short_name_from_policy(): void
    {
        Event::fake([OfferingPublished::class]);
        $csc = $this->units['csc'];
        $semester = $this->semester($this->academicSession());
        $this->actingAs($this->departmentOfficer($csc));
        $id = $this->postJson('/api/v1/offerings', ['course_id' => $this->course($csc, 'CSC 301', level: 300)->uuid, 'semester_id' => $semester->id])->json('data.id');

        $this->postJson("/api/v1/offerings/{$id}/publish")->assertOk()
            ->assertJsonPath('data.status', 'published')->assertJsonPath('data.lms_shortname', 'CSC301-2026-2027-1');
        Event::assertDispatched(OfferingPublished::class);
        $this->postJson("/api/v1/offerings/{$id}/publish")->assertUnprocessable();

        // Published offerings are visible to everyone signed in; drafts are not.
        $draft = $this->postJson('/api/v1/offerings', ['course_id' => $this->course($csc, 'CSC 302', level: 300)->uuid, 'semester_id' => $semester->id])->json('data.id');
        $this->actingAs($this->userWith([]));
        $this->getJson("/api/v1/offerings/{$id}")->assertOk();
        $this->getJson("/api/v1/offerings/{$draft}")->assertForbidden();
        $this->assertCount(1, $this->getJson('/api/v1/offerings')->json('data'));
    }

    public function test_staff_allocation_follows_eligibility_policy(): void
    {
        $csc = $this->units['csc'];
        $semester = $this->semester($this->academicSession());
        $this->actingAs($this->departmentOfficer($csc));
        $id = $this->postJson('/api/v1/offerings', ['course_id' => $this->course($csc, 'CSC 101')->uuid, 'semester_id' => $semester->id])->json('data.id');

        $lecturer = $this->staff($csc, 'SP001');
        $clerk = $this->staff($csc, 'SP002', 'administrative');
        $gone = $this->staff($csc, 'SP003', status: 'exited');

        $this->postJson("/api/v1/offerings/{$id}/staff", ['staff_id' => $lecturer->uuid, 'role' => 'lecturer'])->assertCreated()
            ->assertJsonPath('data.staff.0.staff.staff_no', 'SP001');
        $this->postJson("/api/v1/offerings/{$id}/staff", ['staff_id' => $lecturer->uuid, 'role' => 'lecturer'])->assertUnprocessable();
        $this->postJson("/api/v1/offerings/{$id}/staff", ['staff_id' => $lecturer->uuid, 'role' => 'coordinator'])->assertCreated();
        $this->postJson("/api/v1/offerings/{$id}/staff", ['staff_id' => $clerk->uuid, 'role' => 'lecturer'])->assertUnprocessable()->assertJsonValidationErrors('staff_id');
        $this->postJson("/api/v1/offerings/{$id}/staff", ['staff_id' => $gone->uuid, 'role' => 'lecturer'])->assertUnprocessable();

        // Policy is data: allowing technical staff to teach practicals is a settings change.
        Setting::where('key', 'teaching_eligible_categories')->update(['value' => json_encode(['academic', 'administrative'])]);
        $this->postJson("/api/v1/offerings/{$id}/staff", ['staff_id' => $clerk->uuid, 'role' => 'teaching_assistant'])->assertCreated();

        $this->actingAs($this->userWith(['offerings.allocate'], $this->units['mts']))
            ->postJson("/api/v1/offerings/{$id}/staff", ['staff_id' => $lecturer->uuid, 'role' => 'examiner'])->assertForbidden();
    }

    public function test_staff_leaving_flags_assignments_and_cancellation_ends_them(): void
    {
        $csc = $this->units['csc'];
        $semester = $this->semester($this->academicSession());
        $officer = $this->departmentOfficer($csc);
        $this->actingAs($officer);
        $id = $this->postJson('/api/v1/offerings', ['course_id' => $this->course($csc, 'CSC 101')->uuid, 'semester_id' => $semester->id])->json('data.id');
        $lecturer = $this->staff($csc, 'SP001');
        $this->postJson("/api/v1/offerings/{$id}/staff", ['staff_id' => $lecturer->uuid, 'role' => 'lecturer'])->assertCreated();

        $hr = $this->userWith(['staff.manage', 'staff.view']);
        $this->actingAs($hr)->patchJson("/api/v1/staff/{$lecturer->uuid}", ['employment_status' => 'exited'])->assertJsonValidationErrors('reason');
        $this->patchJson("/api/v1/staff/{$lecturer->uuid}", ['employment_status' => 'exited', 'reason' => 'Resigned'])->assertOk();

        $this->actingAs($officer);
        $this->getJson('/api/v1/offerings?flagged=1')->assertJsonPath('data.0.flagged_count', 1);
        $this->assertNotNull($this->getJson("/api/v1/offerings/{$id}")->json('data.staff.0.flagged_at'));

        $this->actingAs($hr)->patchJson("/api/v1/staff/{$lecturer->uuid}", ['employment_status' => 'active', 'reason' => 'Rehired'])->assertOk();
        $this->actingAs($officer);
        $this->assertNull($this->getJson("/api/v1/offerings/{$id}")->json('data.staff.0.flagged_at'));

        $this->postJson("/api/v1/offerings/{$id}/cancel")->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/offerings/{$id}/cancel", ['reason' => 'Merged with group B'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.staff.0.status', 'ended')
            ->assertJsonPath('data.staff.0.end_reason', 'Offering cancelled: Merged with group B');
        $this->postJson("/api/v1/offerings/{$id}/staff", ['staff_id' => $lecturer->uuid, 'role' => 'examiner'])->assertUnprocessable();
    }
}
