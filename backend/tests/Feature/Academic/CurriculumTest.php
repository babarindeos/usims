<?php

namespace Tests\Feature\Academic;

use App\Modules\Curriculum\Models\CurriculumVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CurriculumTest extends AcademicTestCase
{
    public function test_curriculum_goes_through_approval_then_is_locked(): void
    {
        $csc = $this->units['csc'];
        $programme = $this->programme($csc);
        $session = $this->academicSession();
        $c1 = $this->course($csc, 'CSC 101', 3);
        $c2 = $this->course($csc, 'CSC 102', 2, semester: 2);
        $officer = $this->departmentOfficer($csc);
        $hod = $this->userWith(['curricula.view', 'curricula.endorse'], $csc);
        $planning = $this->userWith(['curricula.view', 'curricula.approve', 'curricula.publish']);

        $this->actingAs($officer);
        $id = $this->postJson('/api/v1/curricula', [
            'programme_id' => $programme->uuid, 'effective_session_id' => $session->id, 'required_credit_units' => 5,
        ])->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.status', 'draft')->json('data.id');

        $this->postJson("/api/v1/curricula/{$id}/courses", ['course_id' => $c1->uuid, 'category' => 'core'])->assertCreated();
        $this->postJson("/api/v1/curricula/{$id}/submit")->assertUnprocessable()->assertJsonValidationErrors('curriculum');
        $this->postJson("/api/v1/curricula/{$id}/courses", ['course_id' => $c2->uuid, 'category' => 'elective'])->assertCreated()
            ->assertJsonPath('data.total_units', 5)->assertJsonPath('data.courses.1.semester_sequence', 2);

        $submitted = $this->postJson("/api/v1/curricula/{$id}/submit", ['remarks' => 'For review'])->assertOk()
            ->assertJsonPath('data.status', 'in_review')->assertJsonPath('data.workflow.current_stage', 'Head of Department endorsement');
        $workflow = $submitted->json('data.workflow.id');

        // Locked while in review.
        $this->postJson("/api/v1/curricula/{$id}/courses", ['course_id' => $this->course($csc, 'CSC 103')->uuid, 'category' => 'core'])->assertUnprocessable();
        $this->postJson("/api/v1/workflows/{$workflow}/actions", ['action' => 'approve'])->assertForbidden();

        $this->actingAs($hod)->postJson("/api/v1/workflows/{$workflow}/actions", ['action' => 'approve'])->assertOk();
        $this->actingAs($planning)->postJson("/api/v1/workflows/{$workflow}/actions", ['action' => 'approve'])->assertOk();
        $this->getJson("/api/v1/curricula/{$id}")->assertJsonPath('data.status', 'approved')->assertJsonPath('data.can.publish', true);

        $this->postJson("/api/v1/curricula/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'published');
        $this->actingAs($officer)->patchJson("/api/v1/curricula/{$id}", ['required_credit_units' => 4])->assertUnprocessable();

        // The database refuses edits to a published curriculum even if application checks are bypassed.
        $version = CurriculumVersion::where('uuid', $id)->first();
        $this->expectException(QueryException::class);
        DB::table('curriculum_courses')->where('curriculum_version_id', $version->id)->update(['credit_units' => 9]);
    }

    public function test_returned_curriculum_can_be_corrected_and_resubmitted(): void
    {
        $csc = $this->units['csc'];
        $version = $this->curriculum($this->programme($csc), $this->academicSession(), [$this->course($csc, 'CSC 101')]);
        $officer = $this->departmentOfficer($csc);
        $hod = $this->userWith(['curricula.view', 'curricula.endorse'], $csc);

        $workflow = $this->actingAs($officer)->postJson("/api/v1/curricula/{$version->uuid}/submit")->json('data.workflow.id');
        $this->actingAs($hod)->postJson("/api/v1/workflows/{$workflow}/actions", ['action' => 'return', 'remarks' => 'Add CSC 102'])->assertOk();
        $this->assertSame('returned', $version->refresh()->status);

        $this->actingAs($officer)->postJson("/api/v1/curricula/{$version->uuid}/courses", ['course_id' => $this->course($csc, 'CSC 102')->uuid, 'category' => 'core'])->assertCreated();
        $this->postJson("/api/v1/curricula/{$version->uuid}/submit")->assertOk()
            ->assertJsonPath('data.status', 'in_review')->assertJsonPath('data.workflow.id', $workflow);
    }

    public function test_new_version_copies_the_published_one_and_older_cohorts_keep_theirs(): void
    {
        $csc = $this->units['csc'];
        $programme = $this->programme($csc);
        $published = $this->curriculum($programme, $this->academicSession(), [$this->course($csc, 'CSC 101'), $this->course($csc, 'CSC 102')], 'published', 6);

        $this->actingAs($this->departmentOfficer($csc));
        $response = $this->postJson('/api/v1/curricula', [
            'programme_id' => $programme->uuid, 'based_on_id' => $published->uuid, 'effective_session_id' => $this->academicSession('2027/2028')->id,
        ])->assertCreated();

        $response->assertJsonPath('data.version', 2)->assertJsonPath('data.based_on.version', 1)
            ->assertJsonPath('data.required_credit_units', 6)->assertJsonCount(2, 'data.courses');
        $this->assertSame('published', $published->refresh()->status);
        $this->assertCount(2, $published->courses);
    }

    public function test_drafts_are_visible_only_within_scope(): void
    {
        $csc = $this->units['csc'];
        $programme = $this->programme($csc);
        $session = $this->academicSession();
        $draft = $this->curriculum($programme, $session, [$this->course($csc, 'CSC 101')]);
        $published = $this->curriculum($programme, $session, [], 'published');

        $this->actingAs($this->userWith(['curricula.view'], $this->units['mts']));
        $this->getJson("/api/v1/curricula/{$draft->uuid}")->assertForbidden();
        $this->getJson("/api/v1/curricula/{$published->uuid}")->assertOk();
        $this->assertSame([$published->uuid], array_column($this->getJson('/api/v1/curricula')->json('data'), 'id'));

        $this->actingAs($this->userWith(['curricula.view'], $this->units['college']));
        $this->assertCount(2, $this->getJson('/api/v1/curricula')->json('data'));
    }

    public function test_only_publishers_publish_and_retirement_needs_a_reason(): void
    {
        $csc = $this->units['csc'];
        $version = $this->curriculum($this->programme($csc), $this->academicSession(), [], 'approved');

        $this->actingAs($this->departmentOfficer($csc))->postJson("/api/v1/curricula/{$version->uuid}/publish")->assertForbidden();
        $this->actingAs($this->userWith(['curricula.publish']));
        $this->postJson("/api/v1/curricula/{$version->uuid}/publish")->assertOk();
        $this->postJson("/api/v1/curricula/{$version->uuid}/retire")->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/curricula/{$version->uuid}/retire", ['reason' => 'Replaced by 2027 review'])->assertOk()->assertJsonPath('data.status', 'retired');
    }
}
