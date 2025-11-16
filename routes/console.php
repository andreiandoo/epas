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

/*
|--------------------------------------------------------------------------
| Microservices Scheduled Tasks
|--------------------------------------------------------------------------
*/

// WhatsApp: Process scheduled reminders (every 10 minutes)
Schedule::call(function () {
    $service = app(\App\Services\WhatsApp\WhatsAppService::class);
    $result = $service->processScheduledReminders(50);
    \Log::info('WhatsApp reminders processed', $result);
})->everyTenMinutes();

// eFactura: Process queue (every 5 minutes)
Schedule::call(function () {
    $service = app(\App\Services\EFactura\EFacturaService::class);
    $result = $service->processQueue(10);
    \Log::info('eFactura queue processed', $result);
})->everyFiveMinutes();

// eFactura: Poll pending submissions (every 10 minutes)
Schedule::call(function () {
    $service = app(\App\Services\EFactura\EFacturaService::class);
    $result = $service->pollPending(20);
    \Log::info('eFactura polling completed', $result);
})->everyTenMinutes();

// Tenant microservices: Check expirations and send alerts (daily at 8 AM)
Schedule::call(function () {
    $expiringSoon = \DB::table('tenant_microservices')
        ->where('status', 'active')
        ->whereBetween('expires_at', [now(), now()->addDays(7)])
        ->get();

    foreach ($expiringSoon as $subscription) {
        \Log::info('Microservice expiring soon', [
            'tenant_id' => $subscription->tenant_id,
            'microservice_id' => $subscription->microservice_id,
            'expires_at' => $subscription->expires_at,
        ]);
        // TODO: Send notification to tenant
    }
})->dailyAt('08:00')->timezone('Europe/Bucharest');

// Tenant microservices: Auto-suspend expired subscriptions (daily at midnight)
Schedule::call(function () {
    $expired = \DB::table('tenant_microservices')
        ->where('status', 'active')
        ->where('expires_at', '<', now())
        ->get();

    foreach ($expired as $subscription) {
        \DB::table('tenant_microservices')
            ->where('id', $subscription->id)
            ->update(['status' => 'suspended']);

        \Log::info('Microservice auto-suspended', [
            'tenant_id' => $subscription->tenant_id,
            'microservice_id' => $subscription->microservice_id,
        ]);
        // TODO: Send notification to tenant
    }
})->daily()->timezone('Europe/Bucharest');
