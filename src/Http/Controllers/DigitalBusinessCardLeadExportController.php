<?php

namespace DigitalCardKit\Laravel\Http\Controllers;

use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DigitalBusinessCardLeadExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $leadModel = config('digital-business-cards.models.lead', DigitalBusinessCardLead::class);
        $query = $leadModel::query()->with('card:id,first_name,middle_name,last_name');

        if ($cardId = $request->integer('card_id')) {
            $query->where('digital_business_card_id', $cardId);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->where('submitted_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('submitted_at', '<=', $dateTo.' 23:59:59');
        }

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Визитка', 'Имя', 'Телефон', 'Email', 'Компания', 'Комментарий', 'Дополнительные поля', 'Согласие', 'Дата'], ';');

            $query->orderByDesc('submitted_at')
                ->each(function (DigitalBusinessCardLead $lead) use ($output): void {
                    fputcsv($output, array_map(self::escapeCsvCell(...), [
                        $lead->card?->full_name,
                        $lead->name,
                        $lead->phone,
                        $lead->email,
                        $lead->company,
                        $lead->comment,
                        collect($lead->custom_data ?: [])->map(
                            fn ($value, $key) => $key.': '.(is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE))
                        )->implode("\n"),
                        $lead->consent_given ? 'Да' : 'Нет',
                        $lead->submitted_at?->format('d.m.Y H:i'),
                    ]), ';');
                });

            fclose($output);
        }, 'contacts-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function escapeCsvCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/u', $value) ? "'".$value : $value;
    }
}
