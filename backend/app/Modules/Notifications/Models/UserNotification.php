<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Core\Support\HasUuid;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use HasUuid;

    protected $fillable = ['user_id', 'template_code', 'title', 'body', 'link', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
