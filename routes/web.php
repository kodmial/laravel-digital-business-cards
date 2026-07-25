<?php

use DigitalCardKit\Laravel\Http\Controllers\AssetController;
use DigitalCardKit\Laravel\Http\Controllers\DigitalBusinessCardController;
use DigitalCardKit\Laravel\Http\Controllers\DigitalBusinessCardLeadExportController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('digital-business-cards.route_prefix', 'card'), '/');
$namePrefix = (string) config('digital-business-cards.route_name_prefix', 'cards.');
$assetPrefix = trim((string) config('digital-business-cards.asset_route_prefix', 'digital-business-cards/assets'), '/');
$middleware = config('digital-business-cards.route_middleware', ['web']);

Route::middleware($middleware)
    ->get($assetPrefix.'/{file}', AssetController::class)
    ->whereIn('file', ['card.css', 'card.js'])
    ->name($namePrefix.'assets');

Route::middleware($middleware)
    ->prefix($prefix)
    ->name($namePrefix)
    ->group(function (): void {
        Route::get('/{card}', [DigitalBusinessCardController::class, 'show'])->name('show');
        Route::get('/{card}/contact.vcf', [DigitalBusinessCardController::class, 'download'])->name('download');
        Route::post('/{card}/contacts', [DigitalBusinessCardController::class, 'submitLead'])
            ->middleware(config('digital-business-cards.lead_middleware', ['throttle:10,1']))
            ->name('leads.store');
        Route::post('/{card}/events', [DigitalBusinessCardController::class, 'event'])
            ->middleware(config('digital-business-cards.event_middleware', ['throttle:120,1']))
            ->name('events.store');
    });

Route::middleware(config('digital-business-cards.lead_export.middleware', ['web']))
    ->get(
        config('digital-business-cards.lead_export.path', 'admin/digital-business-card-leads-export'),
        DigitalBusinessCardLeadExportController::class,
    )
    ->name(config('digital-business-cards.lead_export.route_name', 'admin.cards.leads.export'));
