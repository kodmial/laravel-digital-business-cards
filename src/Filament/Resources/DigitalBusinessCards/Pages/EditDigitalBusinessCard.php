<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDigitalBusinessCard extends EditRecord
{
    protected static string $resource = DigitalBusinessCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')->label('Открыть визитку')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn () => $this->record->publicUrl())->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    protected function getFormMaxWidth(): ?string
    {
        return '7xl';
    }
}
