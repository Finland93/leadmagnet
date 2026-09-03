# Changelog

All notable changes to this project are documented here. This project adheres
to [Semantic Versioning](https://semver.org/).

## [2.0.0] - 2026-09-03

### Changed — internationalization (breaking for translations only)
- Translated the entire plugin from Finnish to **English** (admin UI, emails,
  review widget, help page, code comments). The English strings are now the
  source; existing Finnish translations should be moved into a `-fi` language
  file.
- Made the plugin **country-agnostic**:
  - Postal-code input is now configurable (`Any` vs `Numeric only`) with a
    configurable max length, instead of being hard-coded to 5 numeric digits.
  - The **city field is a normal editable field by default**; automatic
    postal → city fill is now **opt-in** and driven by a configurable dataset
    URL.
  - The bundled Finnish postal dataset moved to
    `public/data/examples/fi-postal-codes.json` and is now an *example*, with a
    documented JSON format so any country can be added.
  - **Currency** is configurable (symbol, position, price note) instead of a
    hard-coded `€` / `alv 0 %`.
- Default email templates are now **generic** (industry-agnostic) instead of
  heat-pump / `ilppihuolto.fi` specific. Every submitted field is exposed to
  templates as `{field_key}` and `{field_key_label}`.

### Fixed
- Removed the **dead follow-up toggles** on the Settings page that appeared to
  enable/disable the review request and reminder but did nothing. The Emails
  page is now the single source of truth for enabling/disabling, editing, and
  timing these messages.
- Added an on/off switch for the **low-rating notification email**.
- Uninstall now also removes the `lmf93_fixed_followups` option.

### Security / privacy
- **All automated emails (review request, service reminder) are now disabled by
  default** and must be explicitly enabled — so nothing is sent to customers
  unexpectedly on a fresh install.

### Added
- `LMF93_Helpers::currency_symbol()`, `format_price()`, `price_note()`.
- `lmf93_email_placeholders` filter.
- Repository packaging: README, LICENSE, CHANGELOG, `.gitignore`, and a
  regenerated translation template (`.pot`).

## [1.1.1] - prior
- Original Finnish release (lead capture, partner routing, billing, reviews,
  follow-ups).
