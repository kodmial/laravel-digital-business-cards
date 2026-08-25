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
storage, email templates, or package theme colors:

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
| `spam_protection` | Honeypot and minimum form-fill time for Livewire leads |
| `storage_disk` and `media_directories` | Local, S3, or CDN-backed media storage |
| `privacy_url` | Optional global privacy-policy URL used by consent forms |
| `default_theme` | Neutral default background, accent, and text colors |
| `models` | Application-specific model subclasses |
| `mail` | Subjects and overridable views for the optional Mailable helpers |
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

The package never sends email itself and never configures your mailer. After a
contact exchange is stored, it dispatches a single event:

```php
DigitalCardKit\Laravel\Events\ContactExchangeCompleted // -> $event->leadId
```

The event fires only after the database transaction commits (it implements
`ShouldDispatchAfterCommit`), and it carries just the lead identifier, so it is
safe for queued listeners. Your application owns delivery: register a listener,
decide who gets notified, and send whatever you like.

Register a listener in a service provider:

```php
use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(ContactExchangeCompleted::class, SendExchangeNotifications::class);
```

A minimal listener that notifies the card owner and thanks the visitor:

```php
use DigitalCardKit\Laravel\Events\ContactExchangeCompleted;
use DigitalCardKit\Laravel\Mail\ContactExchangeConfirmation;
use DigitalCardKit\Laravel\Mail\ContactExchangeReceived;
use DigitalCardKit\Laravel\Models\DigitalBusinessCardLead;
use DigitalCardKit\Laravel\Support\Config;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendExchangeNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(ContactExchangeCompleted $event): void
    {
        /** @var DigitalBusinessCardLead $lead */
        $lead = Config::model('lead')::findOrFail($event->leadId);
        $lead->loadMissing('card');

        foreach ($lead->card->lead_notification_emails ?: [] as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($email)->send(new ContactExchangeReceived($lead));
            }
        }

        if ($lead->card->lead_send_confirmation && $lead->consent_given
            && filter_var($lead->email, FILTER_VALIDATE_EMAIL)) {
            Mail::to($lead->email)->send(new ContactExchangeConfirmation($lead));
        }
    }
}
```

The packaged Mailables are optional helpers — plain Laravel Mailables you can
use, queue, or replace with your own. Their subjects and views are configurable
through the `mail.owner_subject`, `mail.confirmation_subject`,
`mail.owner_view`, and `mail.confirmation_view` settings; publish the package
views to customize the templates. The confirmation template uses the card's
safely normalized palette and its logo or identity; it does not carry package
or host-application branding.

If the listener implements `ShouldQueue` it runs on your configured queue
connection, keeping a queue worker running:

```bash
php artisan queue:work
```

Custom lead models must extend the package lead model as described below.

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
`rate_limits`. Custom middleware added through `lead_middleware` and
`event_middleware` is additive and must not replace the configured throttle
middleware—the named `Support\RateLimits` limiters keyed by card and client
address are required for security.

Livewire lead forms add a server-side honeypot and a protected minimum fill
time under `spam_protection`. The atomic update of per-card and per-address
budgets requires a Laravel cache store that supports atomic locks. On
multi-server deployments, every server must use the same central lock-capable
cache backend, such as Redis.

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

## AI Code Review

[CodeRabbit](https://coderabbit.com) is configured on this repository with an
**assertive** review profile. It runs PHPStan (level 5) and ESLint, and applies
path-specific instructions for `src/`, `tests/`, `routes/`, `resources/views/`,
and `resources/lang/`.

Commits on the `main` branch are reviewed automatically. You can also trigger
a review by posting `@coderabbitai review` in any pull request comment.

See [`.coderabbit.yaml`](.coderabbit.yaml) for the full configuration.

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
- If notification emails are not delivered, check your own listener for
  `ContactExchangeCompleted`, the configured mailer, recipient addresses, and
  the queue worker when delivery is queued.
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
