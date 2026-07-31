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

class EditDigitalBusinessCard extends EditRecord
{
    protected static string $resource = DigitalBusinessCardResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make()
                ->columns(['default' => 1, 'lg' => 3])
                ->schema([
                    // The full tabbed form lives in the wider column so an
                    // editor sees the same fields as on the resource form,
                    // while the live preview stays visible alongside.
                    DigitalBusinessCardResource::cardTabs()
                        ->columnSpan(['lg' => 2]),
                    View::make('digital-business-cards::filament.components.live-preview')
                        ->columnSpan(['lg' => 1])
                        ->viewData(fn (): array => [
                            'card' => $this->getRecord(),
                        ]),
                ]),
        ]);
    }

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
