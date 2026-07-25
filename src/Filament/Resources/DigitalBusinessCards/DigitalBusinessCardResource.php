<?php

namespace DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards;

use BackedEnum;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\CreateDigitalBusinessCard;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\EditDigitalBusinessCard;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\ListDigitalBusinessCards;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
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
    protected static ?string $model = DigitalBusinessCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $modelLabel = 'электронная визитка';

    protected static ?string $pluralModelLabel = 'электронные визитки';

    protected static ?string $navigationLabel = 'Электронные визитки';

    protected static string|\UnitEnum|null $navigationGroup = 'Визитки';

    public static function getModel(): string
    {
        return config('digital-business-cards.models.card', DigitalBusinessCard::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Визитка')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Профиль')
                        ->schema([
                            Section::make('Адрес и публикация')
                                ->description(fn (): string => 'Визитка будет доступна по адресу /'.trim((string) config('digital-business-cards.route_prefix', 'card'), '/').'/{slug}.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('slug')
                                        ->label('Адрес визитки')
                                        ->prefix(fn (): string => '/'.trim((string) config('digital-business-cards.route_prefix', 'card'), '/').'/')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                        ->helperText('Латиница, цифры и дефис. Например: alex-smith')
                                        ->afterStateUpdated(function ($set, $get, $record): void {
                                            if ($record && $record->is_published) {
                                                Notification::make()
                                                    ->warning()
                                                    ->title('Визитка уже опубликована')
                                                    ->body('Старый адрес перестанет работать. Не забудьте обновить ссылки на визитку.')
                                                    ->send();
                                            }
                                        }),
                                    Toggle::make('is_published')
                                        ->label('Опубликовать визитку')
                                        ->default(false),
                                ]),
                            Section::make('Первый экран')
                                ->columns(3)
                                ->schema([
                                    FileUpload::make('avatar')
                                        ->label('Фото владельца')
                                        ->image()
                                        ->avatar()
                                        ->disk(config('digital-business-cards.storage_disk', 'public'))
                                        ->directory(config('digital-business-cards.media_directories.avatars', 'cards/avatars'))
                                        ->imageEditor()
                                        ->imageEditorAspectRatios(['1:1'])
                                        ->visibility('public'),
                                    FileUpload::make('logo')
                                        ->label('Логотип')
                                        ->image()
                                        ->disk(config('digital-business-cards.storage_disk', 'public'))
                                        ->directory(config('digital-business-cards.media_directories.logos', 'cards/logos'))
                                        ->visibility('public'),
                                    FileUpload::make('cover_image')
                                        ->label('Фоновое изображение')
                                        ->image()
                                        ->disk(config('digital-business-cards.storage_disk', 'public'))
                                        ->directory(config('digital-business-cards.media_directories.covers', 'cards/covers'))
                                        ->visibility('public')
                                        ->columnSpanFull(),
                                    TextInput::make('first_name')->label('Имя')->maxLength(100)->required(),
                                    TextInput::make('last_name')->label('Фамилия')->maxLength(100),
                                    TextInput::make('middle_name')->label('Отчество')->maxLength(100),
                                    TextInput::make('job_title')->label('Должность')->maxLength(255),
                                    TextInput::make('company_name')->label('Компания')->maxLength(255),
                                    Textarea::make('headline')->label('Короткое описание')->rows(4)->maxLength(500)->columnSpanFull(),
                                    Textarea::make('about')->label('О владельце / компании')->rows(8)->columnSpanFull(),
                                ]),
                        ]),
                    Tab::make('Контакты')
                        ->schema([
                            Section::make('Контактные данные')
                                ->description('Порядок контактов сохраняется. Мессенджеры, расположенные подряд, объединяются в один ряд; одиночный мессенджер остаётся полноразмерной строкой.')
                                ->schema([
                                    Repeater::make('contact_methods')
                                        ->label('')
                                        ->defaultItems(0)
                                        ->addActionLabel('Добавить контакт')
                                        ->schema([
                                            Select::make('type')->label('Тип')->options(ContactChannelRegistry::options())->required()->live()->default('phone'),
                                            TextInput::make('label')->label('Подпись')->maxLength(100),
                                            TextInput::make('value')->label('Значение или ссылка')->required()->maxLength(2048)
                                                ->helperText(fn ($get): ?string => match ($get('type')) {
                                                    'telegram' => 'Username: @username, username или ссылка t.me/username.',
                                                    'max' => 'Вставьте полную ссылку на профиль MAX (https://max.ru/...).',
                                                    'phone' => 'Российский номер отобразится в привычном формате; исходное значение останется в ссылке и VCF.',
                                                    'website' => 'На визитке будет показан только домен без длинного пути, а полная ссылка сохранится для перехода.',
                                                    default => null,
                                                })->columnSpan(2),
                                        ])->columns(4),
                                ]),
                        ]),
                    Tab::make('Контентные блоки')
                        ->schema([
                            Section::make('Контентные блоки')
                                ->description('Порядок блоков можно менять перетаскиванием. Каждый блок необязателен и редактируется отдельно.')
                                ->schema([
                                    Repeater::make('blocks')
                                        ->relationship()
                                        ->orderColumn('sort_order')
                                        ->defaultItems(0)
                                        ->addActionLabel('Добавить блок')
                                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                        ->collapsed()
                                        ->schema([
                                            Select::make('type')->label('Тип блока')->options([
                                                'link' => 'Кнопка / ссылка', 'social' => 'Соцсеть или мессенджер',
                                                'text' => 'Текстовый блок', 'gallery' => 'Галерея изображений',
                                                'video' => 'Видео', 'file' => 'Вложенный файл', 'banner' => 'Кликабельный баннер',
                                            ])->required()->default('link'),
                                            TextInput::make('title')->label('Заголовок')->maxLength(255),
                                            Textarea::make('content')->label('Текст / описание')->rows(3)->columnSpanFull(),
                                            TextInput::make('url')->label('Ссылка')->url()->maxLength(2048)->columnSpanFull(),
                                            TextInput::make('button_label')->label('Подпись кнопки')->maxLength(100),
                                            FileUpload::make('data.media')->label('Изображение или файл')->disk(config('digital-business-cards.storage_disk', 'public'))->directory(config('digital-business-cards.media_directories.content', 'cards/content'))->columnSpanFull(),
                                            FileUpload::make('data.gallery')->label('Изображения галереи')->image()->multiple()->reorderable()->disk(config('digital-business-cards.storage_disk', 'public'))->directory(config('digital-business-cards.media_directories.galleries', 'cards/galleries'))->columnSpanFull(),
                                            Toggle::make('data.open_in_new_tab')->label('Открывать ссылку в новой вкладке')->default(true),
                                            Toggle::make('is_enabled')->label('Показывать блок')->default(true),
                                        ])->columns(3),
                                ]),
                        ]),
                    Tab::make('Дизайн и SEO')
                        ->schema([
                            Section::make('Оформление')
                                ->description('Готовая тема заполняет три цвета ниже. Их можно затем тонко настроить вручную — визитка и предпросмотр используют одни и те же цвета.')
                                ->columns(4)
                                ->schema([
                                    Select::make('theme_mode')->label('Источник цветов')->options(['default' => 'Тема пакета', 'custom' => 'Свои цвета'])->default('default')->live(),
                                    Select::make('preset_theme')
                                        ->label('Готовая тема')
                                        ->placeholder('Выбрать...')
                                        ->live()
                                        ->dehydrated(false)
                                        ->options([
                                            'dark' => 'Тёмная (indigo)',
                                            'dark-blue' => 'Тёмная (синяя)',
                                            'dark-green' => 'Тёмная (зелёная)',
                                            'light' => 'Светлая (фиолетовая)',
                                            'light-blue' => 'Светлая (синяя)',
                                            'corp' => 'Корпоративная',
                                            'minimal' => 'Минималистичная',
                                            'warm' => 'Тёплая',
                                        ])
                                        ->afterStateUpdated(function ($set, $state): void {
                                            $map = [
                                                'dark' => ['bg' => '#0f172a', 'accent' => '#6366f1', 'text' => '#f1f5f9'],
                                                'dark-blue' => ['bg' => '#0c1a2d', 'accent' => '#3b82f6', 'text' => '#f8fafc'],
                                                'dark-green' => ['bg' => '#0f1e17', 'accent' => '#22c55e', 'text' => '#f0fdf4'],
                                                'light' => ['bg' => '#faf5ff', 'accent' => '#7c3aed', 'text' => '#1e1b2e'],
                                                'light-blue' => ['bg' => '#f0f9ff', 'accent' => '#2563eb', 'text' => '#172554'],
                                                'corp' => ['bg' => '#0a0d14', 'accent' => '#c084fc', 'text' => '#e2e8f0'],
                                                'minimal' => ['bg' => '#ffffff', 'accent' => '#18181b', 'text' => '#09090b'],
                                                'warm' => ['bg' => '#1c1917', 'accent' => '#f59e0b', 'text' => '#fefce8'],
                                            ];
                                            if (isset($map[$state])) {
                                                $set('background_color', $map[$state]['bg']);
                                                $set('accent_color', $map[$state]['accent']);
                                                $set('text_color', $map[$state]['text']);
                                            }
                                        })->helperText('Выбор темы не блокирует ручную настройку цветов ниже.')->columnSpan(2),
                                    ColorPicker::make('background_color')->label('Цвет фона')->live()->default('#101827')->visible(fn ($get): bool => $get('theme_mode') === 'custom'),
                                    ColorPicker::make('accent_color')->label('Акцентный цвет')->live()->default('#7357ff')->visible(fn ($get): bool => $get('theme_mode') === 'custom'),
                                    ColorPicker::make('text_color')->label('Цвет текста')->live()->default('#ffffff')->visible(fn ($get): bool => $get('theme_mode') === 'custom'),
                                    Select::make('button_style')->label('Форма кнопок')->options(['rounded' => 'Скруглённые', 'pill' => 'Капсула', 'square' => 'Прямые'])->default('rounded'),
                                    Select::make('font_family')->label('Шрифт')->options(['system' => 'Системный', 'serif' => 'С засечками', 'mono' => 'Моноширинный'])->default('system'),
                                    ViewField::make('theme_preview')
                                        ->label('Предпросмотр')
                                        ->columnSpanFull()
                                        ->view('digital-business-cards::filament.components.theme-preview')
                                        ->visible(fn ($get): bool => $get('background_color') !== null)
                                        ->viewData(fn ($get): array => [
                                            'bg' => $get('background_color') ?: '#101827',
                                            'accent' => $get('accent_color') ?: '#7357ff',
                                            'text' => $get('text_color') ?: '#ffffff',
                                        ]),
                                ]),
                            Section::make('Поисковая выдача и превью')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('meta_title')->label('Заголовок страницы')->maxLength(255),
                                    Textarea::make('meta_description')->label('Описание страницы')->rows(3)->maxLength(500),
                                ]),
                        ]),
                    Tab::make('Сбор контактов')
                        ->schema([
                            Section::make('Форма обмена контактами')
                                ->description('Все поля, адреса уведомлений и текст согласия настраиваются для каждой визитки.')
                                ->columns(3)
                                ->schema([
                                    Toggle::make('lead_form_enabled')->label('Показывать форму')->default(true),
                                    Toggle::make('lead_consent_required')->label('Требовать согласие')->default(true),
                                    Toggle::make('lead_send_confirmation')->label('Отправлять подтверждение человеку, оставившему email'),
                                    TextInput::make('lead_form_title')->label('Заголовок формы')->default('Обменяться контактами')->required(),
                                    TextInput::make('lead_confirmation_subject')->label('Тема подтверждающего письма')->maxLength(255)->placeholder('Например: Спасибо, контакты отправлены'),
                                    Textarea::make('lead_form_description')->label('Описание формы')->rows(3),
                                    TagsInput::make('lead_notification_emails')->label('Email для уведомлений')->placeholder('name@example.com')->columnSpanFull(),
                                    TextInput::make('privacy_url')->label('Ссылка на политику конфиденциальности')->url()->maxLength(2048)->columnSpanFull(),
                                ]),
                            Section::make('Поля формы')
                                ->schema([
                                    Repeater::make('lead_form_fields')
                                        ->label('')
                                        ->default(fn () => (new DigitalBusinessCard)->leadFields())
                                        ->addActionLabel('Добавить поле')
                                        ->schema([
                                            TextInput::make('key')->label('Системное имя')->required()->regex('/^[a-z][a-z0-9_]{0,63}$/')->helperText('Латиница и нижнее подчёркивание, например: telegram'),
                                            TextInput::make('label')->label('Подпись')->required()->maxLength(100),
                                            Select::make('type')->label('Тип')->options(['text' => 'Строка', 'tel' => 'Телефон', 'email' => 'Email', 'textarea' => 'Многострочный текст'])->required()->default('text'),
                                            Toggle::make('required')->label('Обязательное')->default(false),
                                        ])->columns(4),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')->label('Владелец')->searchable(['first_name', 'last_name'])->description(fn (DigitalBusinessCard $record): ?string => $record->job_title),
                TextColumn::make('slug')->label('Адрес')->prefix('/'.trim((string) config('digital-business-cards.route_prefix', 'card'), '/').'/')->copyable(),
                IconColumn::make('is_published')->label('Опубликована')->boolean(),
                IconColumn::make('lead_form_enabled')->label('Сбор контактов')->boolean(),
                TextColumn::make('events_count')->label('События')->counts('events')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('leads_count')->label('Контакты')->counts('leads')->sortable(),
                TextColumn::make('updated_at')->label('Изменена')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('open')->label('Открыть')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (DigitalBusinessCard $record) => $record->publicUrl())->openUrlInNewTab(),
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
}
