<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCardLeads;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCardLeads\Pages\ListDigitalBusinessCardLeads;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCardLeads\Pages\ViewDigitalBusinessCardLead;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DigitalBusinessCardLeadResource extends Resource
{
    protected static ?string $model = DigitalBusinessCardLead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $modelLabel = 'собранный контакт';

    protected static ?string $pluralModelLabel = 'собранные контакты';

    protected static ?string $navigationLabel = 'Собранные контакты';

    protected static string|\UnitEnum|null $navigationGroup = 'Визитки';

    public static function getModel(): string
    {
        return config('digital-business-cards.models.lead', DigitalBusinessCardLead::class);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Контакт')->columns(2)->schema([
                TextEntry::make('card.full_name')->label('Визитка'),
                TextEntry::make('submitted_at')->label('Получен')->dateTime(),
                TextEntry::make('name')->label('Имя')->placeholder('—'),
                TextEntry::make('phone')->label('Телефон')->placeholder('—'),
                TextEntry::make('email')->label('Email')->placeholder('—'),
                TextEntry::make('company')->label('Компания')->placeholder('—'),
                TextEntry::make('comment')->label('Комментарий')->placeholder('—')->columnSpanFull(),
                TextEntry::make('custom_data')->label('Дополнительные поля')->formatStateUsing(fn (mixed $state): string => is_array($state) ? collect($state)->map(fn ($value, $key) => $key.': '.$value)->implode("\n") : (string) $state)->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('card.full_name')->label('Визитка')->searchable(['first_name', 'last_name']),
                TextColumn::make('name')->label('Имя')->searchable()->placeholder('—'),
                TextColumn::make('phone')->label('Телефон')->searchable()->placeholder('—'),
                TextColumn::make('email')->label('Email')->searchable()->placeholder('—'),
                TextColumn::make('company')->label('Компания')->searchable()->placeholder('—'),
                IconColumn::make('consent_given')->label('Согласие')->boolean(),
                TextColumn::make('submitted_at')->label('Получен')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('digital_business_card_id')->label('Визитка')->options(function (): array {
                    $cardModel = config('digital-business-cards.models.card', DigitalBusinessCard::class);

                    return $cardModel::query()
                        ->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (DigitalBusinessCard $card) => [$card->id => $card->full_name])
                        ->all();
                }),
            ])
            ->headerActions([
                Action::make('export')->label('Скачать CSV')->icon(Heroicon::OutlinedArrowDownTray)->url(fn ($livewire): string => route(config('digital-business-cards.lead_export.route_name', 'admin.cards.leads.export'), array_filter([
                    'card_id' => data_get($livewire->getTableFilterState('digital_business_card_id'), 'value'),
                ]))),
            ])
            ->recordActions([Action::make('view')->label('Открыть')->url(fn (DigitalBusinessCardLead $record) => static::getUrl('view', ['record' => $record]))])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDigitalBusinessCardLeads::route('/'),
            'view' => ViewDigitalBusinessCardLead::route('/{record}'),
        ];
    }
}
