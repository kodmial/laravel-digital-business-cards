<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditDigitalBusinessCard extends EditRecord
{
    protected static string $resource = DigitalBusinessCardResource::class;

    /**
     * Incremented after every save so the published-version iframe re-mounts
     * and picks up the just-saved changes, even when the card URL is unchanged.
     */
    public int $previewVersion = 0;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make()
                ->columns(['default' => 1, 'lg' => 3])
                ->schema([
                    // The full tabbed form lives in the wider column so an
                    // editor sees the same fields as on the resource form,
                    // while the published-version preview sits alongside.
                    DigitalBusinessCardResource::cardTabs()
                        ->columnSpan(['lg' => 2]),
                    View::make('digital-business-cards::filament.components.live-preview')
                        ->columnSpan(['lg' => 1])
                        ->viewData(fn (): array => [
                            'card' => $this->getRecord(),
                            'previewVersion' => $this->previewVersion,
                        ]),
                ]),
        ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $updated = parent::handleRecordUpdate($record, $data);
        $this->previewVersion++;

        return $updated;
    }

    protected function getHeaderActions(): array
    {
        return [
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
