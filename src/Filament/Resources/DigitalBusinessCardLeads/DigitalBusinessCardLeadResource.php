<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCardLeads;

use BackedEnum;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCardLeads\Pages\ListDigitalBusinessCardLeads;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCardLeads\Pages\ViewDigitalBusinessCardLead;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Support\Config;
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

    public static function getModel(): string
    {
        return Config::model('lead');
    }

    public static function getModelLabel(): string
    {
        return self::translate('label');
    }

    public static function getPluralModelLabel(): string
    {
        return self::translate('plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return self::translate('navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('digital-business-cards::admin.navigation_group');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(self::translate('section'))->columns(2)->schema([
                TextEntry::make('card.full_name')->label(self::translate('fields.card')),
                TextEntry::make('submitted_at')->label(self::translate('fields.submitted_at'))->dateTime(),
                TextEntry::make('name')->label(self::translate('fields.name'))->placeholder('—'),
                TextEntry::make('phone')->label(self::translate('fields.phone'))->placeholder('—'),
                TextEntry::make('email')->label(self::translate('fields.email'))->placeholder('—'),
                TextEntry::make('company')->label(self::translate('fields.company'))->placeholder('—'),
                TextEntry::make('comment')->label(self::translate('fields.comment'))->placeholder('—')->columnSpanFull(),
                TextEntry::make('custom_data')
                    ->label(self::translate('fields.custom_data'))
                    ->formatStateUsing(fn (mixed $state): string => is_array($state)
                        ? collect($state)->map(fn ($value, $key) => $key.': '.$value)->implode("\n")
                        : (string) $state)
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('card.full_name')->label(self::translate('fields.card'))->searchable(['first_name', 'last_name']),
                TextColumn::make('name')->label(self::translate('fields.name'))->searchable()->placeholder('—'),
                TextColumn::make('phone')->label(self::translate('fields.phone'))->searchable()->placeholder('—'),
                TextColumn::make('email')->label(self::translate('fields.email'))->searchable()->placeholder('—'),
                TextColumn::make('company')->label(self::translate('fields.company'))->searchable()->placeholder('—'),
                IconColumn::make('consent_given')->label(self::translate('fields.consent'))->boolean(),
                TextColumn::make('submitted_at')->label(self::translate('fields.submitted_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('digital_business_card_id')
                    ->label(self::translate('fields.card'))
                    ->options(fn (): array => Config::model('card')::query()
                        ->orderBy('first_name')
                        ->select('id', 'first_name', 'middle_name', 'last_name', 'company_name')
                        ->get()
                        ->mapWithKeys(fn (DigitalBusinessCard $card) => [$card->id => $card->full_name])
                        ->all()),
            ])
            ->headerActions([
                Action::make('export')
                    ->label(self::translate('actions.export'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn ($livewire): string => route(Config::leadExportRouteName(), array_filter([
                        'card_id' => data_get($livewire->getTableFilterState('digital_business_card_id'), 'value'),
                    ]))),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(self::translate('actions.view'))
                    ->url(fn (DigitalBusinessCardLead $record) => static::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDigitalBusinessCardLeads::route('/'),
            'view' => ViewDigitalBusinessCardLead::route('/{record}'),
        ];
    }

    private static function translate(string $key): string
    {
        return __('digital-business-cards::admin.leads.'.$key);
    }
}
