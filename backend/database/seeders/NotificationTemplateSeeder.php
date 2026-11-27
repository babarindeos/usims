<?php

namespace Database\Seeders;

use App\Modules\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['workflow.completed', 'in_app', '{{ process }} {{ outcome }}', 'Your submission {{ reference }} was {{ outcome }}.'],
            ['workflow.completed', 'mail', '{{ process }}: {{ outcome }}', "Your submission {{ reference }} was {{ outcome }}.\n\nSign in to USIMS for details."],
            ['account.created', 'mail', 'Your USIMS account', "An account has been created for you.\n\nSign in with {{ email }}."],
        ];
        foreach ($templates as [$code, $channel, $subject, $body]) {
            NotificationTemplate::firstOrCreate(['code' => $code, 'channel' => $channel], ['subject' => $subject, 'body' => $body]);
        }
    }
}
