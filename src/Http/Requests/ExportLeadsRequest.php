<?php

namespace DigitalCardKit\Laravel\Http\Requests;

use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ExportLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Config::leadExportAbility());
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'card_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * The validated filters, with the date range expanded to whole inclusive
     * days so a lead submitted late on the closing date is still exported.
     *
     * @return array{card_id?: int, date_from?: Carbon, date_to?: Carbon}
     */
    public function filters(): array
    {
        $validated = array_filter(
            $this->validated(),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $filters = [];

        if (isset($validated['card_id'])) {
            $filters['card_id'] = (int) $validated['card_id'];
        }

        if (isset($validated['date_from'])) {
            $filters['date_from'] = Carbon::parse($validated['date_from'])->startOfDay();
        }

        if (isset($validated['date_to'])) {
            $filters['date_to'] = Carbon::parse($validated['date_to'])->endOfDay();
        }

        return $filters;
    }
}
