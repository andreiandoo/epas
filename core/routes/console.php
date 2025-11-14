<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic invoice generation for tenants
Schedule::command('invoices:generate-tenant')
    ->dailyAt('02:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Tenant invoices generated successfully');
    })
    ->onFailure(function () {
        \Log::error('Failed to generate tenant invoices');
    });

// Schedule overdue invoice reminders (daily at 9 AM)
Schedule::command('invoices:send-overdue-reminders')
    ->dailyAt('09:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Overdue invoice reminders sent successfully');
    })
    ->onFailure(function () {
        \Log::error('Failed to send overdue invoice reminders');
    });

// Schedule invoice status transition from 'new' to 'outstanding' (daily at 1 AM)
Schedule::command('invoices:transition-new --grace-days=3')
    ->dailyAt('01:00')
    ->timezone('Europe/Bucharest')
    ->onSuccess(function () {
        \Log::info('Invoice status transitions completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Failed to transition invoice statuses');
    });
