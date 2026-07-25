# Laravel Digital Business Cards

[![Tests](https://github.com/kodmial/laravel-digital-business-cards/actions/workflows/tests.yml/badge.svg)](https://github.com/kodmial/laravel-digital-business-cards/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

An independent Laravel package for publishing digital business cards, exporting
vCards, collecting contact leads with configurable consent, and recording
interaction events. It includes a public responsive interface and Filament
administration resources.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13
- Filament 5.6 or newer

Filament is currently a required dependency because the package includes its
administration resources and uses Filament authentication for the default lead
export route.

## Installation

Until the package is listed on Packagist, register its GitHub repository once
in the host application's `composer.json`:

```bash
composer config repositories.laravel-digital-business-cards vcs https://github.com/kodmial/laravel-digital-business-cards
composer require digital-card-kit/laravel-digital-business-cards
php artisan migrate
php artisan storage:link
```

Laravel discovers the service provider automatically. The package registers:

- `GET /card/{card}`
- `GET /card/{card}/contact.vcf`
- `POST /card/{card}/contacts`
- `POST /card/{card}/events`

The public card view, CSS, JavaScript, contact exchange forms, emails, database
migrations, and translations are bundled. The default asset controller requires
no Vite integration in the host application.

## Filament

Add the plugin to the Filament panel where the card and lead resources should
appear:

```php
use DigitalCardKit\Laravel\DigitalBusinessCardsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            DigitalBusinessCardsPlugin::make(),
        ]);
}
```

Open the panel, create a card under **Digital business cards**, add its contact
methods and appearance, then enable **Published**. New cards are drafts by
default. Their public URL uses the configured `route_prefix` and the card slug.

## Configuration

Publish the configuration when you need custom routes, middleware, models,
storage, mail delivery, or package theme colors:

```bash
php artisan vendor:publish --tag=digital-business-cards-config
```

Important settings in `config/digital-business-cards.php`:

| Setting | Purpose |
| --- | --- |
| `route_prefix` and `route_name_prefix` | Public URL and route-name prefixes |
| `route_middleware` | Middleware for public card routes |
| `lead_middleware` and `event_middleware` | Middleware for the write endpoints |
| `rate_limits` | Attempts per minute, per card and per client address |
| `storage_disk` and `media_directories` | Local, S3, or CDN-backed media storage |
| `privacy_url` | Optional global privacy-policy URL used by consent forms |
| `default_theme` | Neutral default background, accent, and text colors |
| `models` | Application-specific model subclasses |
| `notification_sender` | Replaceable contact notification implementation |
| `notifications` | Default listener, queue connection, and queue name |
| `mail` | Mailer, subjects, and overridable email views |
| `lead_export` | Export path, route name, middleware, and authorization ability |

Cards are drafts by default. Publish a card explicitly after its content,
privacy URL, recipients, and appearance have been reviewed. A card-specific
privacy URL takes precedence over the global `privacy_url` setting. When both
are empty, the consent control remains visible as plain text and does not link
to an unrelated host-application page.

### Assets and views

For static assets, publish the files and set `assets_url` to
`/vendor/digital-business-cards`:

```bash
php artisan vendor:publish --tag=digital-business-cards-assets --force
```

Override the packaged templates without modifying vendor code:

```bash
php artisan vendor:publish --tag=digital-business-cards-views
```

The package migrations run without publishing. Publish them only when the host
application needs to review or customize them before the first migration:

```bash
php artisan vendor:publish --tag=digital-business-cards-migrations
```

Translations can be published for application-specific wording:

```bash
php artisan vendor:publish --tag=digital-business-cards-translations
```

### Mail

Notifications use Laravel's configured mailer. Owner notifications are sent only
to valid addresses configured on a card. Visitor confirmation is sent only when
the card enables `lead_send_confirmation` and the submitted email is valid.
The confirmation template uses the card's safely normalized palette and its logo
or identity; it does not carry package or host-application branding.

The package dispatches `ContactExchangeCompleted` after the database transaction
commits. Its default listener sends through the configured `NotificationSender`.
Choose one of these extension points:

- Set `notifications.queued` to `true` to queue the default listener. Optionally
  set `queue_connection` and `queue_name`; a Laravel queue worker must be running.
- Set `notification_sender` to an implementation of `NotificationSender` to
  replace delivery while retaining the package listener.
- Bind `NotificationSender` in the host service container. An existing binding
  is respected by the package service provider.
- Set `notifications.register_default_listener` to `false` and register any host
  listener for `ContactExchangeCompleted`.
- Change `mail.owner_view` or `mail.confirmation_view`, or publish the package
  views, to replace the templates.

Example custom listener registration:

```php
use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(ContactExchangeCompleted::class, SendExchangeToCrm::class);
```

The event serializes only the lead model identifier, without arbitrary
eager-loaded relations, so it is safe to use with queued listeners. Custom lead
models must extend the package lead model as described below.

Configure a Laravel mailer before enabling email delivery. When
`notifications.queued` is enabled, configure a queue connection and keep a
worker running:

```bash
php artisan queue:work
```

## Lead export authorization

The export streams every stored contact, so it is gated in addition to its route
middleware. The package registers a default ability that requires an
authenticated user. Define the ability yourself to apply your own rules, and the
packaged default steps aside:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('digital-business-cards.export-leads', fn ($user) => $user->isAdmin());
```

Point `lead_export.ability` at a different name to reuse an existing ability.

`Gate` resolves the user from the default auth guard, so the packaged default
also accepts a user authenticated on a Filament panel's own guard. An ability you
define yourself is responsible for whichever guard your panel uses.

## Rate limits

Lead and event submissions use named limiters registered by the package. Each is
keyed by card and client address, with a wider per-address cap so spreading
requests across many cards does not lift the ceiling. Tune the numbers under
`rate_limits`, or replace `lead_middleware` and `event_middleware` outright.

## Localization

Public pages, contact exchange emails, CSV export headers, and the Filament
resources follow the application locale. English and Russian are bundled; publish
the translations to add a language or reword the defaults:

```bash
php artisan vendor:publish --tag=digital-business-cards-translations
```

## Factories

The package ships factories for host applications and its own tests:

```php
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;

DigitalBusinessCard::factory()->published()->create();
DigitalBusinessCard::factory()->withoutLeadForm()->count(3)->create();
```

They resolve the configured model classes, so a custom subclass is honored.

## Extending models

Set custom model classes under `models` in the published configuration. Custom
card, block, lead, and event classes should extend the corresponding package
models so relationships and route behavior remain compatible.

## Development

The package has an autonomous Testbench suite:

```bash
composer install
composer test
npm ci
npm test
```

The PHP suite uses Orchestra Testbench. The JavaScript suite uses the package's
own Vitest configuration and a jsdom environment; neither suite depends on a
host Laravel application.

## Upgrading

Update the package and run outstanding migrations:

```bash
composer update digital-card-kit/laravel-digital-business-cards
php artisan migrate
```

If assets or views were published, review package changes before replacing local
overrides. Republish static assets after an upgrade:

```bash
php artisan vendor:publish --tag=digital-business-cards-assets --force
```

Published configuration, views, migrations, and translations are application
overrides and are not replaced automatically.

The initial migration checks the cards, blocks, leads, and events tables
independently. If an application already has the cards table, missing related
tables are still created and existing card data is preserved. The reconciliation
migration adds missing package columns without dropping data. Applications with
custom schemas should still review published migrations before running them.
Released migrations are not rewritten during upgrades.

## Troubleshooting

- If uploaded images return `404`, verify that the configured disk is public and
  run `php artisan storage:link` when using Laravel's default `public` disk.
- If the admin resources are missing, confirm that
  `DigitalBusinessCardsPlugin::make()` is registered on the intended panel.
- If notification emails are not delivered, check the configured mailer,
  recipient addresses, and the queue worker when queued delivery is enabled.
- If published CSS or JavaScript appears stale after an update, republish assets
  with `--force` and clear the browser or proxy cache.

## Security notes

- Public write routes are rate-limited per card and per client address.
- Unpublished cards return `404`.
- The lead export requires the configured authorization ability.
- Contact links are limited to the `http`, `https`, `tel` and `mailto` schemes.
- Lead source referers are length-bounded.
- Visitors are pseudonymized with an HMAC keyed by the application key.
- CSV exports neutralize spreadsheet formulas.
- Replaced and deleted managed media is removed from the configured disk.

## License

MIT
