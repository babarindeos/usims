<?php

use App\Modules\Admissions\Services\OfferService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Offers not answered by their acceptance deadline lapse each night.
Schedule::call(fn () => app(OfferService::class)->expireOverdue())->dailyAt('00:30')->name('admissions:expire-offers');
