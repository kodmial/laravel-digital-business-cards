<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Concerns\PreviewsCard;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditDigitalBusinessCard extends EditRecord
{
    use PreviewsCard;

    protected static string $resource = DigitalBusinessCardResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components(DigitalBusinessCardResource::cardForm());
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            Action::make('open')
                ->label(DigitalBusinessCardResource::translate('actions.open_card'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn () => $this->record->publicUrl())
                ->visible(fn (): bool => $this->canOpenCard())
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    /**
     * The public route serves published cards only, so the "open" header action
     * is shown only once the card is published.
     */
    private function canOpenCard(): bool
    {
        return (bool) $this->getRecord()->getAttribute('is_published');
    }

    protected function getFormMaxWidth(): ?string
    {
        return '7xl';
    }
}
