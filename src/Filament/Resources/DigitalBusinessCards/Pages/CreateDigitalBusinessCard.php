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

    /** Submit button labelled with the translated "create card" string. */
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(DigitalBusinessCardResource::translate('actions.create_card'));
    }

    /** Header actions: preview first, then the parent's defaults. */
    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            ...parent::getHeaderActions(),
        ];
    }

    /** Widen the create form to the 7xl max width. */
    public function getFormMaxWidth(): ?string
    {
        return '7xl';
    }
}
