<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards;

use BackedEnum;
use Closure;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\CreateDigitalBusinessCard;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\EditDigitalBusinessCard;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\ListDigitalBusinessCards;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Support\ContactChannelRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DigitalBusinessCardResource extends Resource
{
    /** Colour presets offered in the appearance tab, as background/accent/text. */
    private const PRESETS = [
        'dark' => ['#0f172a', '#6366f1', '#f1f5f9'],
        'dark-blue' => ['#0c1a2d', '#3b82f6', '#f8fafc'],
        'dark-green' => ['#0f1e17', '#22c55e', '#f0fdf4'],
        'light' => ['#faf5ff', '#7c3aed', '#1e1b2e'],
        'light-blue' => ['#f0f9ff', '#2563eb', '#172554'],
        'corp' => ['#0a0d14', '#c084fc', '#e2e8f0'],
        'minimal' => ['#ffffff', '#18181b', '#09090b'],
        'warm' => ['#1c1917', '#f59e0b', '#fefce8'],
    ];

    protected static ?string $model = DigitalBusinessCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function getModel(): string
    {
        return Config::model('card');
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('card')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make(self::translate('tabs.profile'))->schema(self::profileTab()),
                    Tab::make(self::translate('tabs.contacts'))->schema(self::contactsTab()),
                    Tab::make(self::translate('tabs.blocks'))->schema(self::blocksTab()),
                    Tab::make(self::translate('tabs.design'))->schema(self::designTab()),
                    Tab::make(self::translate('tabs.leads'))->schema(self::leadsTab()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(self::translate('fields.owner'))
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn (DigitalBusinessCard $record): ?string => $record->job_title),
                TextColumn::make('slug')
                    ->label(self::translate('fields.address'))
                    ->prefix('/'.Config::routePrefix().'/')
                    ->copyable(),
                IconColumn::make('is_published')->label(self::translate('fields.is_published'))->boolean(),
                IconColumn::make('lead_form_enabled')->label(self::translate('fields.lead_form_column'))->boolean(),
                TextColumn::make('events_count')
                    ->label(self::translate('fields.events_count'))
                    ->counts('events')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('leads_count')->label(self::translate('fields.leads_count'))->counts('leads')->sortable(),
                TextColumn::make('updated_at')->label(self::translate('fields.updated_at'))->since()->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(self::translate('actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (DigitalBusinessCard $record) => $record->publicUrl())
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDigitalBusinessCards::route('/'),
            'create' => CreateDigitalBusinessCard::route('/create'),
            'edit' => EditDigitalBusinessCard::route('/{record}/edit'),
        ];
    }

    /** @return array<int, mixed> */
    private static function profileTab(): array
    {
        return [
            Section::make(self::translate('sections.address'))
                ->description(fn (): string => self::translate('sections.address_hint', ['prefix' => Config::routePrefix()]))
                ->columns(2)
                ->schema([
                    TextInput::make('slug')
                        ->label(self::translate('fields.slug'))
                        ->prefix(fn (): string => '/'.Config::routePrefix().'/')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->helperText(self::translate('fields.slug_hint'))
                        ->afterStateUpdated(function ($record): void {
                            if ($record && $record->is_published) {
                                Notification::make()
                                    ->warning()
                                    ->title(self::translate('notifications.published_slug_changed'))
                                    ->body(self::translate('notifications.published_slug_changed_body'))
                                    ->send();
                            }
                        }),
                    Toggle::make('is_published')->label(self::translate('fields.is_published'))->default(false),
                ]),
            Section::make(self::translate('sections.hero'))
                ->columns(3)
                ->schema([
                    self::upload('avatar', 'avatars')->image()->avatar()->imageEditor()->imageEditorAspectRatios(['1:1']),
                    self::upload('logo', 'logos')->image(),
                    self::upload('cover_image', 'covers')->image()->columnSpanFull(),
                    TextInput::make('first_name')->label(self::translate('fields.first_name'))->maxLength(100)->required(),
                    TextInput::make('last_name')->label(self::translate('fields.last_name'))->maxLength(100),
                    TextInput::make('middle_name')->label(self::translate('fields.middle_name'))->maxLength(100),
                    TextInput::make('job_title')->label(self::translate('fields.job_title'))->maxLength(255),
                    TextInput::make('company_name')->label(self::translate('fields.company_name'))->maxLength(255),
                    Textarea::make('headline')->label(self::translate('fields.headline'))->rows(4)->maxLength(500)->columnSpanFull(),
                    Textarea::make('about')->label(self::translate('fields.about'))->rows(8)->columnSpanFull(),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private static function contactsTab(): array
    {
        return [
            Section::make(self::translate('sections.contacts'))
                ->description(self::translate('sections.contacts_hint'))
                ->schema([
                    Repeater::make('contact_methods')
                        ->label('')
                        ->defaultItems(0)
                        ->addActionLabel(self::translate('actions.add_contact'))
                        ->schema([
                            Select::make('type')
                                ->label(self::translate('fields.contact_type'))
                                ->options(ContactChannelRegistry::options())
                                ->required()
                                ->live()
                                ->default('phone'),
                            TextInput::make('label')->label(self::translate('fields.contact_label'))->maxLength(100),
                            TextInput::make('value')
                                ->label(self::translate('fields.contact_value'))
                                ->required()
                                ->maxLength(2048)
                                // Unsupported schemes are refused when stored,
                                // so reject them here too rather than saving
                                // the contact away as an empty value.
                                ->rule(fn ($get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    if (ContactChannelRegistry::normalize((string) $get('type'), (string) $value) === '' && trim((string) $value) !== '') {
                                        $fail(self::translate('validation.unsupported_scheme'));
                                    }
                                })
                                ->helperText(fn ($get): ?string => match ($get('type')) {
                                    'telegram' => self::translate('hints.telegram'),
                                    'max' => self::translate('hints.max'),
                                    'phone' => self::translate('hints.phone'),
                                    'website' => self::translate('hints.website'),
                                    default => null,
                                })
                                ->columnSpan(2),
                        ])
                        ->columns(4),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private static function blocksTab(): array
    {
        return [
            Section::make(self::translate('sections.blocks'))
                ->description(self::translate('sections.blocks_hint'))
                ->schema([
                    Repeater::make('blocks')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->defaultItems(0)
                        ->addActionLabel(self::translate('actions.add_block'))
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->collapsed()
                        ->schema([
                            Select::make('type')
                                ->label(self::translate('fields.block_type'))
                                ->options(self::options('block', ['link', 'social', 'text', 'gallery', 'video', 'file', 'banner']))
                                ->required()
                                ->default('link'),
                            TextInput::make('title')->label(self::translate('fields.block_title'))->maxLength(255),
                            Textarea::make('content')->label(self::translate('fields.block_content'))->rows(3)->columnSpanFull(),
                            TextInput::make('url')->label(self::translate('fields.block_url'))->url()->maxLength(2048)->columnSpanFull(),
                            TextInput::make('button_label')->label(self::translate('fields.block_button_label'))->maxLength(100),
                            self::upload('data.media', 'content')->columnSpanFull(),
                            self::upload('data.gallery', 'galleries')->image()->multiple()->reorderable()->columnSpanFull(),
                            Toggle::make('data.open_in_new_tab')->label(self::translate('fields.block_new_tab'))->default(true),
                            Toggle::make('is_enabled')->label(self::translate('fields.block_enabled'))->default(true),
                        ])
                        ->columns(3),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private static function designTab(): array
    {
        return [
            Section::make(self::translate('sections.appearance'))
                ->description(self::translate('sections.appearance_hint'))
                ->columns(4)
                ->schema([
                    Select::make('theme_mode')
                        ->label(self::translate('fields.theme_mode'))
                        ->options([
                            'default' => self::translate('options.theme_default'),
                            'custom' => self::translate('options.theme_custom'),
                        ])
                        ->default('default')
                        ->live(),
                    Select::make('preset_theme')
                        ->label(self::translate('fields.preset_theme'))
                        ->placeholder(self::translate('actions.choose'))
                        ->live()
                        ->dehydrated(false)
                        ->options(self::options('preset', array_keys(self::PRESETS)))
                        ->afterStateUpdated(function ($set, $state): void {
                            if (! isset(self::PRESETS[$state])) {
                                return;
                            }

                            [$background, $accent, $text] = self::PRESETS[$state];
                            $set('background_color', $background);
                            $set('accent_color', $accent);
                            $set('text_color', $text);
                        })
                        ->helperText(self::translate('fields.preset_theme_hint'))
                        ->columnSpan(2),
                    ColorPicker::make('background_color')
                        ->label(self::translate('fields.background_color'))
                        ->live()
                        ->default('#101827')
                        ->visible(fn ($get): bool => $get('theme_mode') === 'custom'),
                    ColorPicker::make('accent_color')
                        ->label(self::translate('fields.accent_color'))
                        ->live()
                        ->default('#7357ff')
                        ->visible(fn ($get): bool => $get('theme_mode') === 'custom'),
                    ColorPicker::make('text_color')
                        ->label(self::translate('fields.text_color'))
                        ->live()
                        ->default('#ffffff')
                        ->visible(fn ($get): bool => $get('theme_mode') === 'custom'),
                    Select::make('button_style')
                        ->label(self::translate('fields.button_style'))
                        ->options(self::options('button', ['rounded', 'pill', 'square']))
                        ->default('rounded'),
                    Select::make('font_family')
                        ->label(self::translate('fields.font_family'))
                        ->options(self::options('font', ['system', 'serif', 'mono']))
                        ->default('system'),
                    ViewField::make('theme_preview')
                        ->label(self::translate('fields.theme_preview'))
                        ->columnSpanFull()
                        ->view('digital-business-cards::filament.components.theme-preview')
                        ->visible(fn ($get): bool => $get('background_color') !== null)
                        ->viewData(fn ($get): array => [
                            'bg' => $get('background_color') ?: '#101827',
                            'accent' => $get('accent_color') ?: '#7357ff',
                            'text' => $get('text_color') ?: '#ffffff',
                        ]),
                ]),
            Section::make(self::translate('sections.seo'))
                ->columns(2)
                ->schema([
                    TextInput::make('meta_title')->label(self::translate('fields.meta_title'))->maxLength(255),
                    Textarea::make('meta_description')->label(self::translate('fields.meta_description'))->rows(3)->maxLength(500),
                ]),
        ];
    }

    /** @return array<int, mixed> */
    private static function leadsTab(): array
    {
        return [
            Section::make(self::translate('sections.lead_form'))
                ->description(self::translate('sections.lead_form_hint'))
                ->columns(3)
                ->schema([
                    Toggle::make('lead_form_enabled')->label(self::translate('fields.lead_form_enabled'))->default(true),
                    Toggle::make('lead_consent_required')->label(self::translate('fields.lead_consent_required'))->default(true),
                    Toggle::make('lead_send_confirmation')->label(self::translate('fields.lead_send_confirmation')),
                    TextInput::make('lead_form_title')
                        ->label(self::translate('fields.lead_form_title'))
                        ->default(fn (): string => __('digital-business-cards::messages.lead.title'))
                        ->required(),
                    TextInput::make('lead_confirmation_subject')
                        ->label(self::translate('fields.lead_confirmation_subject'))
                        ->maxLength(255),
                    Textarea::make('lead_form_description')->label(self::translate('fields.lead_form_description'))->rows(3),
                    TagsInput::make('lead_notification_emails')
                        ->label(self::translate('fields.lead_notification_emails'))
                        ->placeholder('name@example.com')
                        ->nestedRecursiveRules(['email:rfc'])
                        ->columnSpanFull(),
                    TextInput::make('privacy_url')->label(self::translate('fields.privacy_url'))->url()->maxLength(2048)->columnSpanFull(),
                ]),
            Section::make(self::translate('sections.lead_fields'))
                ->schema([
                    Repeater::make('lead_form_fields')
                        ->label('')
                        ->default(fn () => (new DigitalBusinessCard)->leadFields())
                        ->addActionLabel(self::translate('actions.add_field'))
                        ->schema([
                            TextInput::make('key')
                                ->label(self::translate('fields.field_key'))
                                ->required()
                                ->regex(DigitalBusinessCard::LEAD_FIELD_KEY_PATTERN)
                                ->helperText(self::translate('fields.field_key_hint')),
                            TextInput::make('label')->label(self::translate('fields.field_label'))->required()->maxLength(100),
                            Select::make('type')
                                ->label(self::translate('fields.field_type'))
                                ->options(self::options('field', ['text', 'tel', 'email', 'textarea']))
                                ->required()
                                ->default('text'),
                            Toggle::make('required')->label(self::translate('fields.field_required'))->default(false),
                        ])
                        ->columns(4),
                ]),
        ];
    }

    private static function upload(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->label(self::translate('fields.'.str_replace('data.', 'block_', $name)))
            ->disk(Config::disk())
            ->directory(Config::mediaDirectory($directory))
            ->visibility('public');
    }

    /**
     * Build a Filament option list from translation keys named "<group>_<value>",
     * keeping the stored values and their labels in step.
     *
     * @param  array<int, string>  $values
     * @return array<string, string>
     */
    private static function options(string $group, array $values): array
    {
        return array_combine(
            $values,
            array_map(
                static fn (string $value): string => self::translate('options.'.$group.'_'.str_replace('-', '_', $value)),
                $values,
            ),
        );
    }

    /** @param  array<string, mixed>  $replace */
    private static function translate(string $key, array $replace = []): string
    {
        return __('digital-business-cards::admin.cards.'.$key, $replace);
    }
}
