<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDigitalBusinessCards extends ListRecords
{
    protected static string $resource = DigitalBusinessCardResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Создать визитку')];
    }
}
