<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendMailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $deliveryId, public string $subject, public string $body) {}

    /** Exponential backoff in seconds between retries. */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $delivery = NotificationDelivery::findOrFail($this->deliveryId);
        if ($delivery->status === 'sent') {
            return; // idempotent on retry
        }
        $delivery->increment('attempts');
        Mail::raw($this->body, fn (Message $m) => $m->to($delivery->recipient)->subject($this->subject));
        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        NotificationDelivery::whereKey($this->deliveryId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
    }
}
