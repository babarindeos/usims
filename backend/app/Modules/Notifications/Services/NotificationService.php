<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Jobs\SendMailNotification;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Notifications\Models\UserNotification;

/** Central notification service (PRD part 18): templates per event and channel, preferences, delivery log. */
class NotificationService
{
    public function send(string $code, User $user, array $data = [], ?string $link = null): void
    {
        $templates = NotificationTemplate::where('code', $code)->where('active', true)->get();

        foreach ($templates as $template) {
            if (! $template->mandatory && $this->optedOut($user, $code, $template->channel)) {
                continue;
            }
            $subject = NotificationTemplate::render($template->subject, $data);
            $body = NotificationTemplate::render($template->body, $data);

            match ($template->channel) {
                'in_app' => UserNotification::create([
                    'user_id' => $user->id, 'template_code' => $code, 'title' => $subject ?: $code,
                    'body' => $body, 'link' => $link,
                ]),
                'mail' => $this->queueMail($user, $code, $subject, $body),
                'sms' => $this->recordUnconfigured($user, $code, 'sms', $user->person?->phone),
                default => null,
            };
        }
    }

    private function queueMail(User $user, string $code, string $subject, string $body): void
    {
        $delivery = NotificationDelivery::create([
            'user_id' => $user->id, 'template_code' => $code, 'channel' => 'mail',
            'recipient' => $user->email, 'status' => 'queued',
        ]);
        SendMailNotification::dispatch($delivery->id, $subject, $body)->afterCommit();
    }

    /** No SMS provider is wired in yet; record that honestly rather than pretend it was sent. */
    private function recordUnconfigured(User $user, string $code, string $channel, ?string $recipient): void
    {
        NotificationDelivery::create([
            'user_id' => $user->id, 'template_code' => $code, 'channel' => $channel,
            'recipient' => $recipient ?? '', 'status' => 'skipped', 'error' => 'SMS provider is not configured.',
        ]);
    }

    private function optedOut(User $user, string $code, string $channel): bool
    {
        return NotificationPreference::where(['user_id' => $user->id, 'template_code' => $code, 'channel' => $channel, 'enabled' => false])->exists();
    }
}
