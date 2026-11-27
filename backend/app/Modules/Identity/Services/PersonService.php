<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PersonService
{
    public function __construct(private IdentifierService $identifiers) {}

    /**
     * Records that may be the same human: same email, same phone, or same names and date of birth.
     * Callers show these for review instead of silently creating a duplicate person.
     */
    public function possibleDuplicates(array $data): Collection
    {
        if (empty($data['email']) && empty($data['phone']) && empty($data['date_of_birth'])) {
            return collect();
        }

        return Person::query()
            ->whereNull('merged_into_person_id')
            ->where(function ($q) use ($data) {
                if (! empty($data['email'])) {
                    $q->orWhereRaw('lower(email) = ?', [mb_strtolower($data['email'])]);
                }
                if (! empty($data['phone'])) {
                    $q->orWhere('phone', $data['phone']);
                }
                if (! empty($data['date_of_birth'])) {
                    $q->orWhere(fn ($q) => $q
                        ->whereRaw('lower(surname) = ?', [mb_strtolower($data['surname'])])
                        ->whereRaw('lower(first_name) = ?', [mb_strtolower($data['first_name'])])
                        ->whereDate('date_of_birth', $data['date_of_birth']));
                }
            })
            ->limit(10)
            ->get();
    }

    public function create(array $data): Person
    {
        return DB::transaction(function () use ($data) {
            $number = $this->identifiers->next('person');
            $person = Person::create($data + ['person_no' => sprintf('PER-%06d', $number)]);
            $this->identifiers->issue($person, 'PER', $person->person_no);

            return $person;
        });
    }
}
