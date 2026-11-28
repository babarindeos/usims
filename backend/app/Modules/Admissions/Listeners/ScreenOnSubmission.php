<?php

namespace App\Modules\Admissions\Listeners;

use App\Modules\Admissions\Events\ApplicationSubmitted;
use App\Modules\Admissions\Services\ScreeningEngine;
use App\Modules\Notifications\Services\NotificationService;

/** Screens every application as it is submitted and acknowledges it to the candidate. */
class ScreenOnSubmission
{
    public function __construct(private ScreeningEngine $screening, private NotificationService $notifications) {}

    public function handle(ApplicationSubmitted $event): void
    {
        $application = $event->application;
        $this->screening->screen($application, null);
        if ($user = $application->person->user) {
            $this->notifications->send('application.submitted', $user, ['application_no' => $application->application_no, 'exercise' => $application->exercise->name], '/applicant');
        }
    }
}
