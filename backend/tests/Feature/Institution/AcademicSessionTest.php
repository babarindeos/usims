<?php

namespace Tests\Feature\Institution;

use App\Modules\Institution\Models\AcademicSession;
use Tests\TestCase;

class AcademicSessionTest extends TestCase
{
    private function makeSession(string $name = '2026/2027'): AcademicSession
    {
        [$y] = explode('/', $name);

        return AcademicSession::create(['name' => $name, 'starts_on' => "{$y}-09-01", 'ends_on' => ($y + 1).'-08-31']);
    }

    public function test_planning_officer_creates_session_with_independent_processes(): void
    {
        $officer = $this->userWith(['sessions.manage']);

        $this->actingAs($officer)->postJson('/api/v1/academic-sessions', [
            'name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31',
            'processes' => ['admission' => 'open', 'course_registration' => 'closed'],
        ])->assertCreated()
            ->assertJsonPath('data.processes.admission', 'open')
            ->assertJsonPath('data.processes.course_registration', 'closed');
    }

    public function test_only_authorised_users_change_sessions(): void
    {
        $this->actingAs($this->userWith([]))->postJson('/api/v1/academic-sessions', [
            'name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31',
        ])->assertForbidden();
    }

    public function test_only_one_current_session(): void
    {
        $officer = $this->userWith(['sessions.manage']);
        $a = $this->makeSession('2025/2026');
        $b = $this->makeSession('2026/2027');

        $this->actingAs($officer)->postJson("/api/v1/academic-sessions/{$a->id}/make-current")->assertOk();
        $this->actingAs($officer)->postJson("/api/v1/academic-sessions/{$b->id}/make-current")->assertOk();

        $this->assertFalse($a->fresh()->is_current);
        $this->assertTrue($b->fresh()->is_current);
    }

    public function test_semesters_must_sit_inside_the_session(): void
    {
        $officer = $this->userWith(['sessions.manage']);
        $s = $this->makeSession();

        $this->actingAs($officer)->postJson("/api/v1/academic-sessions/{$s->id}/semesters", [
            'name' => 'First Semester', 'code' => 'FIRST', 'sequence' => 1, 'starts_on' => '2026-09-15', 'ends_on' => '2027-01-31',
        ])->assertCreated();
        $this->actingAs($officer)->postJson("/api/v1/academic-sessions/{$s->id}/semesters", [
            'name' => 'Second Semester', 'code' => 'SECOND', 'sequence' => 2, 'starts_on' => '2027-02-01', 'ends_on' => '2027-10-01',
        ])->assertStatus(422);
        $this->actingAs($officer)->postJson("/api/v1/academic-sessions/{$s->id}/semesters", [
            'name' => 'Again', 'code' => 'FIRST', 'sequence' => 3, 'starts_on' => '2027-02-01', 'ends_on' => '2027-05-01',
        ])->assertStatus(422);
    }

    public function test_closed_session_is_locked(): void
    {
        $officer = $this->userWith(['sessions.manage']);
        $s = $this->makeSession();

        $this->actingAs($officer)->postJson("/api/v1/academic-sessions/{$s->id}/close", ['reason' => 'Session ended'])
            ->assertOk()->assertJsonPath('data.processes.admission', 'closed');
        $this->actingAs($officer)->patchJson("/api/v1/academic-sessions/{$s->id}", ['processes' => ['admission' => 'open']])
            ->assertStatus(422);
    }

    public function test_unit_calendar_events_need_scope(): void
    {
        ['csc' => $csc, 'mts' => $mts] = $this->structure();
        $s = $this->makeSession();
        $officer = $this->userWith(['calendar.manage'], $csc);
        $event = ['academic_session_id' => $s->id, 'category' => 'department_board', 'title' => 'Board meeting', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-01'];

        $this->actingAs($officer)->postJson('/api/v1/calendar-events', $event + ['org_unit_id' => $csc->uuid])->assertCreated();
        $this->actingAs($officer)->postJson('/api/v1/calendar-events', $event + ['org_unit_id' => $mts->uuid])->assertForbidden();
        $this->actingAs($officer)->postJson('/api/v1/calendar-events', $event)->assertForbidden();
    }
}
