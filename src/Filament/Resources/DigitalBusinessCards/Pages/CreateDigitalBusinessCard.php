<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Concerns\PreviewsCard;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateDigitalBusinessCard extends CreateRecord
{
    use PreviewsCard;

    protected static string $resource = DigitalBusinessCardResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(DigitalBusinessCardResource::translate('actions.create_card'));
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            ...parent::getHeaderActions(),
        ];
    }

    public function getFormMaxWidth(): ?string
    {
        return '7xl';
    }
}
