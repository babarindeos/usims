<?php

namespace Tests\Feature\Academic;

class CourseTest extends AcademicTestCase
{
    public function test_course_codes_are_normalised_and_unique(): void
    {
        $this->actingAs($this->departmentOfficer($this->units['csc']));
        $payload = ['code' => 'csc-301', 'title' => 'Operating Systems', 'credit_units' => 3, 'level' => 300, 'org_unit_id' => $this->units['csc']->uuid];

        $this->postJson('/api/v1/courses', $payload)->assertCreated()->assertJsonPath('data.code', 'CSC 301');
        $this->postJson('/api/v1/courses', ['code' => 'CSC301'] + $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/courses', ['code' => 'C3'] + $payload)->assertJsonValidationErrors('code');
    }

    public function test_departments_maintain_only_their_own_courses(): void
    {
        $mtsCourse = $this->course($this->units['mts'], 'MTS 101');
        $this->actingAs($this->departmentOfficer($this->units['csc']));

        $this->postJson('/api/v1/courses', ['code' => 'MTS 102', 'title' => 'Algebra', 'credit_units' => 3, 'level' => 100, 'org_unit_id' => $this->units['mts']->uuid])->assertForbidden();
        $this->patchJson("/api/v1/courses/{$mtsCourse->uuid}", ['title' => 'Changed'])->assertForbidden();
        $this->getJson('/api/v1/courses?q=mts')->assertOk()->assertJsonPath('data.0.code', 'MTS 101');
    }

    public function test_prerequisite_loops_are_refused(): void
    {
        $a = $this->course($this->units['csc'], 'CSC 101');
        $b = $this->course($this->units['csc'], 'CSC 201', level: 200);
        $c = $this->course($this->units['csc'], 'CSC 301', level: 300);
        $this->actingAs($this->departmentOfficer($this->units['csc']));

        $this->postJson("/api/v1/courses/{$b->uuid}/requisites", ['course_id' => $a->uuid, 'type' => 'prerequisite'])->assertCreated();
        $this->postJson("/api/v1/courses/{$c->uuid}/requisites", ['course_id' => $b->uuid, 'type' => 'prerequisite'])->assertCreated();

        $this->postJson("/api/v1/courses/{$a->uuid}/requisites", ['course_id' => $c->uuid, 'type' => 'prerequisite'])
            ->assertUnprocessable()->assertJsonValidationErrors('required_course_id');
        $this->postJson("/api/v1/courses/{$a->uuid}/requisites", ['course_id' => $a->uuid, 'type' => 'prerequisite'])->assertUnprocessable();
        $this->postJson("/api/v1/courses/{$c->uuid}/requisites", ['course_id' => $b->uuid, 'type' => 'prerequisite'])->assertUnprocessable();

        // Co-requisites are taken together, so a pair may name each other.
        $this->postJson("/api/v1/courses/{$a->uuid}/requisites", ['course_id' => $b->uuid, 'type' => 'corequisite', 'requirement' => 'registered'])->assertCreated();

        $show = $this->getJson("/api/v1/courses/{$c->uuid}")->assertOk();
        $this->assertSame('CSC 201', $show->json('data.requisites.0.course.code'));
    }
}
