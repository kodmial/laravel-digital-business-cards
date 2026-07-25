# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- Contact links are restricted to the `http`, `https`, `tel` and `mailto`
  schemes. A stored `javascript:`, `data:` or `vbscript:` value previously
  reached the `href` of a rendered contact; such values are now rejected when
  saved and the contact is omitted from the public card.
- The lead export is authorised through a `digital-business-cards.export-leads`
  gate in addition to its route middleware. Previously a panel configured with
  permissive middleware exposed every stored contact to anonymous requests.
- The hero background escapes its media path for the CSS parser, so an uploaded
  file name containing a quote can no longer inject CSS declarations.
- `visitor_hash` is an HMAC keyed with the application key instead of a digest
  of a payload that merely contained it. **Existing event rows keep their old
  hashes and will not match newly recorded ones.**
- Lead and event endpoints use named rate limiters keyed by card and client
  address, with a wider per-address cap. A single busy card no longer consumes
  the budget of every other card in the installation.
- Export filters (`card_id`, `date_from`, `date_to`) are validated.

### Added

- Model factories for cards, blocks, leads and events, published under
  `DigitalCardKit\Laravel\Database\Factories`.
- A `published()` scope on the card model.
- `rate_limits` and `lead_export.ability` configuration keys.
- Russian and English translations for the public card, the contact exchange
  form and the Filament resources.

### Changed

- Public copy, CSV export headers and Filament labels follow the application
  locale instead of being hardcoded Russian, and `<html lang>` reflects it.
  **Applications relying on the previous Russian output should set the
  application locale to `ru` or publish the translations.**
- Lead and event submissions are validated by form requests. An invalid
  `block_id` now returns a validation error like every other field, so JSON
  clients receive `422` with an error body rather than a bare `422` abort, and
  form posts are redirected back with errors instead.
- Contact methods are handled by a dedicated Eloquent cast; media cleanup moved
  from model boot closures into observers attached with `#[ObservedBy]`, so a
  configured model subclass keeps its uploads cleaned up.
- The Filament contact editor rejects an unsupported link scheme with a
  validation error instead of saving the contact with an empty value.
- Configuration is read through a single accessor, so every setting has one
  fallback rather than a literal repeated at each call site.

## [1.0.0] - 2026-07-25

### Added

- Initial reusable Laravel package for digital business cards.
- Public card pages, vCard downloads, contact exchange, event tracking, mail
  notifications, themes, and Filament administration resources.

[Unreleased]: https://github.com/kodmial/laravel-digital-business-cards/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/kodmial/laravel-digital-business-cards/releases/tag/v1.0.0
