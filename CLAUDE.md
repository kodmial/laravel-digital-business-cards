# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A standalone Laravel **package** (`digital-card-kit/laravel-digital-business-cards`) — not an application. It ships public digital business card pages, vCard export, a lead/contact-exchange form, event tracking, and Filament 5 admin resources. There is no `app/`, no `.env`, and no host application in the repo; everything runs under Orchestra Testbench.

PSR-4: `DigitalCardKit\Laravel\` → `src/`, `DigitalCardKit\Laravel\Tests\` → `tests/`.

## Commands

```bash
composer test                       # PHPUnit (Testbench, in-memory SQLite)
vendor/bin/phpunit --filter test_cards_are_private_by_default_and_public_when_published
vendor/bin/phpunit tests/LeadsEventsAndMailTest.php
composer lint                       # pint --test (composer format to fix)
composer analyse                    # phpstan level 5 over src/ + tests/ (excl. tests/Fixtures)
composer validate --strict --no-check-publish

npm test                            # vitest run (jsdom)
npm run test:coverage               # enforces 80% thresholds; CI runs this form
npx vitest run -t "closes on Escape"
```

CI (`.github/workflows/tests.yml`) runs lint+phpstan on PHP 8.4, the PHP suite across PHP 8.3/8.4 × Testbench 10/11, and the JS suite on Node 22 — always with `composer update`, never a committed lock.

`phpstan-baseline.neon` holds ~120 entries, almost all Eloquent dynamic property/method noise that larastan would resolve (it was deliberately dropped in `2d17c4c`). Don't regenerate it wholesale to silence new findings; check the error *identifiers* first and fix anything outside that known category.

## Architecture

### Model indirection is mandatory

Every model class is resolved through `config('digital-business-cards.models.{card,block,lead,event}')` so host apps can substitute subclasses. Concrete classes are only referenced as config defaults and type hints. Follow the existing patterns:

- Everything reads config through `Support\Config` — one accessor with one fallback per key. Never call `config('digital-business-cards.…')` directly; add a method to `Config` instead.
- Controllers and form requests use the `Support\ResolvesModels` trait. `resolvePublishedCard()` applies the `published()` scope in the query, which is why no endpoint carries its own `abort_unless($card->is_published, 404)`.
- Relations pass `Config::model(…)`, not `::class` (see `DigitalBusinessCard::blocks()`).
- Filament resources override `getModel()` to read config; `protected static ?string $model` is only the fallback.

`tests/PackageTest.php` asserts this end to end with custom subclasses — a hardcoded model class will fail there.

### Config-driven routing

`routes/web.php` reads config at provider boot (`route_prefix`, `route_name_prefix`, per-endpoint middleware). Never hardcode a URL or route name; build them as `config('digital-business-cards.route_name_prefix', 'cards.').'show'`, as `DigitalBusinessCard::publicUrl()` does. Routes: card page, `contact.vcf`, `POST contacts` (throttled 10/min), `POST events` (throttled 120/min), plus a Filament-authenticated CSV lead export.

Cards default to unpublished (`$attributes = ['is_published' => false]`); visibility is enforced by the `published()` scope inside `resolvePublishedCard()`, so an unpublished card 404s before a controller ever sees it.

Write endpoints are throttled by named limiters declared in `Support\RateLimits` and registered in the provider's `boot()`. They key on card **and** client address, with a wider per-address cap; the counts live under the `rate_limits` config key.

### Contact channels

`Support\ContactChannelRegistry` is the single source of truth for contact types. Values are normalized **on write** by the `Casts\ContactMethods` cast, and rendered via `href()` / `label()` / `displayValue()` / `isMessenger()` / `group()`. Adding a channel means touching the registry, `resources/lang/*/messages.php`, and the vCard mapping in `Services\VCardGenerator`.

`href()` only ever emits `http`, `https`, `tel` or `mailto`; anything else yields an empty string and `publicContactMethods()` drops that contact so no dead link renders. Do not weaken this — a `javascript:` value reaching an `href` is the stored-XSS case it exists to prevent.

Both the scheme check and `normalize()` match the scheme on the raw string rather than with `parse_url()`, which reads `tel:112` as host:port and reports no scheme at all. Using `parse_url` there silently deletes short numbers (emergency lines, extensions); a test pins that.

### Theming

`Support\CardTheme::tokens()` derives a full palette (surface, muted text, border, page background, shadow, accent RGB) from just background/accent/text, sanitizing each to a `#rrggbb` hex with a fallback. Card page, notification emails, and the Filament preview must all render from these tokens so they stay consistent. `theme_mode = 'custom'` uses the card's colors; anything else uses `config('digital-business-cards.default_theme')`.

### Contact exchange / notification pipeline

`Http\Requests\StoreCardLeadRequest` (validation + `leadAttributes()`) → `DigitalBusinessCardController::submitLead()` → `Events\ContactExchangeCompleted` (serializes only the lead ID, queue-safe, dispatched after commit) — and that is where the package's responsibility ends. The package ships no listener, sends no mail, and owns no mail delivery configuration; the host application registers its own listener and implements delivery (including mailer/queue setup). The packaged `Mail\*` Mailables are optional helpers a host may send from its own listener; the `mail.*` config keys exist only for those Mailables' subjects and views and must not be removed while they ship. Do not reintroduce automatic listeners, sender bindings, or notification config keys.

Lead form fields are per-card JSON (`leadFields()` supplies defaults); `validatableLeadFields()` filters out keys that fail `DigitalBusinessCard::LEAD_FIELD_KEY_PATTERN`, and `StoreCardLeadRequest` builds its rules from what survives. Keys outside the lead table's own columns land in `custom_data`.

The lead export is gated by `Config::leadExportAbility()`, defaulting to "any authenticated user" and registered only when the host has not already defined that ability. The route middleware is host-configurable, so this gate is the endpoint's real guarantee. `Gate` resolves its user from the *default* guard, so the default ability also checks each Filament panel's own guard — otherwise a panel on `->authGuard('admin')` would 403 the admin its own middleware just admitted.

### Media lifecycle

Uploads live on `Config::disk()` under `media_directories.*`. Deletion is handled by the classes in `src/Observers/`, attached with `#[ObservedBy]` **on the model classes**.

That attribute placement is load-bearing. `Model::observe()` in the provider would register the listener under `eloquent.updated: DigitalBusinessCard`, while `fireModelEvent()` resolves `static::class` on the *runtime* instance — so a host subclass would fire `eloquent.updated: CustomCard`, match nothing, and orphan every replaced upload. `resolveObserveAttributes()` merges the parent's attributes, so the attribute inherits where `observe()` does not. `ModelsMediaAndAssetsTest` pins this with a configured subclass.

The card observer diffs `DigitalBusinessCard::MEDIA_ATTRIBUTES`; the block observer diffs media paths inside the block's `data` JSON. New media attributes need matching cleanup there.

### Assets

`Http\Controllers\AssetController` serves `card.css` / `card.js` straight from `resources/` with ETag + revalidation, so hosts need no Vite. Publishing to `public/vendor/digital-business-cards` and setting `assets_url` is the alternative. `resources/js/card.js` exports `initDigitalBusinessCard(root)` and self-bootstraps; all configuration comes from `data-*` attributes so `resources/js/card.test.js` can drive it in jsdom.

### Migrations

Both migrations are idempotent (`Schema::hasTable` / `hasColumn` guards) so they can complete a partial install without touching existing data. `PackageTest::test_migration_basenames_and_table_names_are_stable` pins the filenames and table names — released migrations are never renamed or rewritten; add a new one instead.

## Conventions

- Every user-visible string is translated. Public copy and CSV headers use `digital-business-cards::messages.*`; Filament labels use `digital-business-cards::admin.*` via the resources' private `translate()` helpers (Filament's `$navigationLabel`-style static properties cannot hold a `__()` call, so the `getNavigationLabel()` / `getModelLabel()` overrides are used instead). `en` and `ru` are both maintained.
- Tests are grouped by concern into a handful of files (not one per class). Records come from the shipped factories in `database/factories/`; `tests/Concerns/CreatesCards` and `CreatesAdminRecords` are thin fixed-value presets on top of them, because the assertions check exact rendered output. Filament's providers are registered explicitly in `tests/TestCase.php`.
- Update `README.md` and `CHANGELOG.md` when behavior or public API changes (per `CONTRIBUTING.md`).
- Security invariants covered by tests: per-card throttling, 404 for unpublished cards, the lead-export gate, the `href` scheme allowlist, CSS escaping of the hero background path, `Str::limit` on lead `source` and event metadata, HMAC visitor pseudonymization, CSV formula neutralization.
