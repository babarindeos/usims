<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use Auditable;

    protected $fillable = ['code', 'channel', 'subject', 'body', 'mandatory', 'active'];

    protected function casts(): array
    {
        return ['mandatory' => 'boolean', 'active' => 'boolean'];
    }

    /** Replace {{ key }} placeholders; values are plain text. */
    public static function render(?string $text, array $data): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            fn ($m) => (string) data_get($data, $m[1], ''), (string) $text);
    }
}
