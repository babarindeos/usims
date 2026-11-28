<?php

namespace Tests\Feature\Academic;

use App\Modules\Identity\Models\Person;

class StaffTest extends AcademicTestCase
{
    public function test_staff_record_creates_person_and_checks_duplicates(): void
    {
        $this->actingAs($this->userWith(['staff.manage', 'staff.view']));
        $payload = [
            'person' => ['surname' => 'Adeyemi', 'first_name' => 'Tola', 'email' => 'tola@example.test', 'date_of_birth' => '1985-04-02'],
            'staff_no' => 'sp/1001', 'org_unit_id' => $this->units['csc']->uuid, 'category' => 'academic', 'rank' => 'Lecturer I',
        ];
        $this->postJson('/api/v1/staff', $payload)->assertCreated()
            ->assertJsonPath('data.staff_no', 'SP/1001')->assertJsonPath('data.person.full_name', fn ($n) => str_contains($n, 'Adeyemi'))
            ->assertJsonPath('data.employment_status', 'active');

        $person = Person::where('email', 'tola@example.test')->first();
        $this->assertSame('SP/1001', $person->identifiers()->where('type', 'STAFF_NO')->value('value'));

        $this->postJson('/api/v1/staff', ['person_id' => $person->uuid, 'staff_no' => 'SP/1002'] + array_diff_key($payload, ['person' => 1]))->assertJsonValidationErrors('person_id');
        $this->postJson('/api/v1/staff', ['person' => ['surname' => 'Adeyemi', 'first_name' => 'Tola', 'date_of_birth' => '1985-04-02'], 'staff_no' => 'SP/1003'] + $payload)->assertStatus(409);
    }

    public function test_staff_lists_follow_scope(): void
    {
        $this->staff($this->units['csc'], 'SP001');
        $this->staff($this->units['mts'], 'SP002');

        $this->actingAs($this->userWith(['staff.view'], $this->units['csc']));
        $this->assertSame(['SP001'], array_column($this->getJson('/api/v1/staff')->json('data'), 'staff_no'));
        $this->actingAs($this->userWith(['staff.view'], $this->units['college']));
        $this->assertCount(2, $this->getJson('/api/v1/staff')->json('data'));
        $this->actingAs($this->userWith([]));
        $this->assertCount(0, $this->getJson('/api/v1/staff')->json('data'));
    }
}
