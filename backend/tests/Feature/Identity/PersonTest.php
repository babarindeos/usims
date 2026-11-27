<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Services\IdentifierService;
use Tests\TestCase;

class PersonTest extends TestCase
{
    public function test_person_gets_permanent_number(): void
    {
        $officer = $this->userWith(['persons.manage', 'persons.view']);

        $res = $this->actingAs($officer)->postJson('/api/v1/persons', [
            'surname' => 'Adeyemi', 'first_name' => 'Tola', 'email' => 'tola@example.com',
        ])->assertCreated();

        $this->assertMatchesRegularExpression('/^PER-\d{6}$/', $res->json('data.person_no'));
        $person = Person::where('uuid', $res->json('data.id'))->first();
        $this->assertSame($person->person_no, $person->activeIdentifier('PER')->value);
    }

    public function test_possible_duplicate_needs_confirmation(): void
    {
        $officer = $this->userWith(['persons.manage']);
        Person::factory()->create(['surname' => 'Okafor', 'first_name' => 'Ada', 'date_of_birth' => '2005-03-01', 'email' => null, 'phone' => null]);
        $payload = ['surname' => 'okafor', 'first_name' => 'ADA', 'date_of_birth' => '2005-03-01'];

        $this->actingAs($officer)->postJson('/api/v1/persons', $payload)
            ->assertStatus(409)->assertJsonCount(1, 'matches');
        $this->actingAs($officer)->postJson('/api/v1/persons', $payload + ['confirm_not_duplicate' => true])->assertCreated();
    }

    public function test_reissued_identifier_supersedes_but_keeps_history(): void
    {
        $person = Person::factory()->create();
        $ids = app(IdentifierService::class);
        $ids->issue($person, 'MATRIC', '2026/CSC/0001');
        $ids->issue($person, 'MATRIC', '2026/CSC/0002', 'Correction approved');

        $this->assertSame('2026/CSC/0002', $person->activeIdentifier('MATRIC')->value);
        $this->assertSame(2, $person->identifiers()->where('type', 'MATRIC')->count());
    }

    public function test_sequences_never_repeat(): void
    {
        $ids = app(IdentifierService::class);
        $values = collect(range(1, 20))->map(fn () => $ids->next('demo'));

        $this->assertSame(range(1, 20), $values->all());
    }

    public function test_person_records_need_permission(): void
    {
        $user = $this->userWith([]);
        $this->actingAs($user)->getJson('/api/v1/persons')->assertForbidden();
    }
}
