<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateDigitalBusinessCard extends CreateRecord
{
    protected static string $resource = DigitalBusinessCardResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Создать визитку');
    }

    protected function getFormMaxWidth(): ?string
    {
        return '7xl';
    }
}
