<?php

namespace DigitalCardKit\Laravel\Http\Controllers;

use DigitalCardKit\Laravel\Http\Requests\ExportLeadsRequest;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Routing\Controller;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DigitalBusinessCardLeadExportController extends Controller
{
    public function __invoke(ExportLeadsRequest $request): StreamedResponse
    {
        $filters = $request->filters();
        $query = Config::model('lead')::query()->with('card:id,first_name,middle_name,last_name');

        if (isset($filters['card_id'])) {
            $query->where('digital_business_card_id', $filters['card_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('submitted_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('submitted_at', '<=', $filters['date_to']);
        }

        $headers = [
            __('digital-business-cards::messages.export.card'),
            __('digital-business-cards::messages.export.name'),
            __('digital-business-cards::messages.export.phone'),
            __('digital-business-cards::messages.export.email'),
            __('digital-business-cards::messages.export.company'),
            __('digital-business-cards::messages.export.comment'),
            __('digital-business-cards::messages.export.custom_fields'),
            __('digital-business-cards::messages.export.consent'),
            __('digital-business-cards::messages.export.date'),
        ];

        return response()->streamDownload(function () use ($query, $headers): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            $csv = Writer::createFromStream($output);
            $csv->setDelimiter(';');
            $formatter = new EscapeFormula;
            $csv->addFormatter($formatter->escapeRecord(...));
            $csv->insertOne($headers);

            $query->orderByDesc('submitted_at')
                ->each(function (DigitalBusinessCardLead $lead) use ($csv): void {
                    $csv->insertOne([
                        (string) $lead->card?->full_name,
                        (string) $lead->name,
                        (string) $lead->phone,
                        (string) $lead->email,
                        (string) $lead->company,
                        (string) $lead->comment,
                        (string) collect($lead->custom_data ?: [])->map(
                            fn ($value, $key) => $key.': '.(is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE))
                        )->implode("\n"),
                        (string) __('digital-business-cards::messages.export.'.($lead->consent_given ? 'yes' : 'no')),
                        (string) $lead->submitted_at?->format('d.m.Y H:i'),
                    ]);
                });
        }, 'contacts-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
