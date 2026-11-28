<?php

namespace App\Modules\Registry\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'student_status_history';

    protected $fillable = ['student_id', 'from_status', 'to_status', 'effective_on', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['effective_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
