<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Concerns;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use DigitalCardKit\Laravel\Support\Config;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Renders the card "as it will look on the site" inside a Filament modal, built
 * entirely from the live form state — no record is persisted and the public
 * route is never opened. Shared by the create and edit pages so both expose the
 * same preview action.
 */
trait PreviewsCard
{
    /**
     * Build an in-memory card from the live form data so the preview reflects
     * the unsaved edits exactly as they will appear once saved.
     *
     * @param  array<string, mixed>  $data
     */
    protected function buildPreviewCard(array $data): Model
    {
        $cardClass = Config::cardModel();

        $attributes = collect($data)
            ->only(app($cardClass)->getFillable())
            ->all();

        // Filament's file-upload fields leave a non-empty array in the form
        // state for a cleared or pending upload. Those media columns are stored
        // as strings, so an array is never a valid path and would otherwise be
        // passed straight into storageUrl(), which is typed ?string and throws.
        // Treat any array as "no file" for the preview.
        foreach ($cardClass::MEDIA_ATTRIBUTES as $attribute) {
            if (isset($attributes[$attribute]) && is_array($attributes[$attribute])) {
                $attributes[$attribute] = null;
            }
        }

        /** @var Model $card */
        $card = app($cardClass)->newInstance($attributes);
        $card->exists = false;

        $blockClass = Config::model('block');

        $blocks = collect($data['blocks'] ?? [])
            ->filter(fn (array $block): bool => (bool) ($block['is_enabled'] ?? true))
            ->map(function (array $block) use ($blockClass): Model {
                // Same Filament upload-array problem as MEDIA_ATTRIBUTES, but
                // inside a block's data JSON: data.media (single upload) and
                // data.gallery (multiple) arrive as arrays in the form state.
                // storageUrl() is typed ?string, so coerce them to a string (the
                // one path) or an array of strings for the preview.
                $blockData = $block['data'] ?? [];
                if (is_array($blockData)) {
                    if (isset($blockData['media']) && is_array($blockData['media'])) {
                        $blockData['media'] = count($blockData['media']) === 1 ? reset($blockData['media']) : null;
                    }
                    if (isset($blockData['gallery']) && is_array($blockData['gallery'])) {
                        $blockData['gallery'] = array_values(array_filter(
                            $blockData['gallery'],
                            static fn ($item): bool => is_string($item) && $item !== '',
                        ));
                    }
                    $block['data'] = $blockData;
                }

                /** @var Model $instance */
                $instance = app($blockClass)->newInstance($block);
                $instance->exists = false;

                return $instance;
            });

        $card->setRelation('blocks', $blocks);

        return $card;
    }

    protected function previewAction(): Action
    {
        return Action::make('preview')
            ->label(DigitalBusinessCardResource::translate('actions.preview'))
            ->icon(Heroicon::OutlinedEye)
            ->modal()
            ->modalHeading(DigitalBusinessCardResource::translate('preview.heading'))
            ->modalDescription(DigitalBusinessCardResource::translate('preview.description'))
            ->modalWidth('7xl')
            ->modalContent(fn ($livewire) => view(
                'digital-business-cards::filament.components.card-preview',
                ['card' => $this->buildPreviewCard((array) ($livewire->data ?? []))],
            ));
    }
}
