<?php

use DigitalCardKit\Laravel\Http\Controllers\AssetController;
use DigitalCardKit\Laravel\Http\Controllers\DigitalBusinessCardController;
use DigitalCardKit\Laravel\Http\Controllers\DigitalBusinessCardLeadExportController;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\RateLimits;
use Illuminate\Support\Facades\Route;

$middleware = Config::middleware('route_middleware');

Route::middleware($middleware)
    ->get(Config::assetRoutePrefix().'/{file}', AssetController::class)
    ->whereIn('file', AssetController::FILES)
    ->name(Config::routeName('assets'));

Route::middleware($middleware)
    ->prefix(Config::routePrefix())
    ->name(Config::routeName())
    ->group(function (): void {
        Route::get('/{card}', [DigitalBusinessCardController::class, 'show'])->name('show');
        Route::get('/{card}/contact.vcf', [DigitalBusinessCardController::class, 'download'])->name('download');
        Route::post('/{card}/contacts', [DigitalBusinessCardController::class, 'submitLead'])
            ->middleware(Config::middleware('lead_middleware', ['throttle:'.RateLimits::LEADS]))
            ->name('leads.store');
        Route::post('/{card}/events', [DigitalBusinessCardController::class, 'event'])
            ->middleware(Config::middleware('event_middleware', ['throttle:'.RateLimits::EVENTS]))
            ->name('events.store');
    });

Route::middleware(Config::middleware('lead_export.middleware'))
    ->get(
        (string) Config::get('lead_export.path', 'admin/digital-business-card-leads-export'),
        DigitalBusinessCardLeadExportController::class,
    )
    ->name(Config::leadExportRouteName());
