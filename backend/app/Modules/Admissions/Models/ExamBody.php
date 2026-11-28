<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamBody extends Model
{
    use Auditable;

    protected $table = 'exam_bodies';

    protected $fillable = ['code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function grades(): HasMany
    {
        return $this->hasMany(ExamGrade::class)->orderBy('sort_order');
    }
}
