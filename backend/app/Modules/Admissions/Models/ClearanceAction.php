<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearanceAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['clearance_item_id', 'from_status', 'to_status', 'remarks', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
