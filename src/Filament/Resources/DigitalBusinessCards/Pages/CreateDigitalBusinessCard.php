<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\MessageBag;

class CreateDigitalBusinessCard extends CreateRecord
{
    use HasWizard;

    protected static string $resource = DigitalBusinessCardResource::class;

    /** Normalize an empty Livewire error bag before the initial wizard render. */
    public function getErrorBag(): MessageBag
    {
        $errorBag = parent::getErrorBag();

        if ($errorBag instanceof MessageBag) {
            return $errorBag;
        }

        $errorBag = new MessageBag;
        $this->setErrorBag($errorBag);

        return $errorBag;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(DigitalBusinessCardResource::translate('actions.create_card'));
    }

    protected function getFormMaxWidth(): ?string
    {
        return '7xl';
    }

    /**
     * The create flow is a guided five-step wizard rather than a single long
     * tabbed form: each step carries one tab's fields, so a new card is built
     * front-to-back without jumping between tabs.
     *
     * @return array<int, Step>
     */
    public function getSteps(): array
    {
        return [
            Step::make(self::tabLabel('profile'))
                ->icon('heroicon-o-user')
                ->schema(DigitalBusinessCardResource::profileTab()),
            Step::make(self::tabLabel('contacts'))
                ->icon('heroicon-o-phone')
                ->schema(DigitalBusinessCardResource::contactsTab()),
            Step::make(self::tabLabel('blocks'))
                ->icon('heroicon-o-rectangle-stack')
                ->schema(DigitalBusinessCardResource::blocksTab()),
            Step::make(self::tabLabel('design'))
                ->icon('heroicon-o-swatch')
                ->schema(DigitalBusinessCardResource::designTab()),
            Step::make(self::tabLabel('leads'))
                ->icon('heroicon-o-envelope')
                ->schema(DigitalBusinessCardResource::leadsTab()),
        ];
    }

    /**
     * Let an editor skip ahead when a step does not apply (optional blocks,
     * lead form off), mirroring the tabbed form where every section was
     * optional. This keeps the wizard from blocking on content that can be
     * empty.
     */
    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    private static function tabLabel(string $tab): string
    {
        return DigitalBusinessCardResource::translate('tabs.'.$tab);
    }
}
