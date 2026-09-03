# LeadMagnet

A self-hosted **lead capture and management** plugin for WordPress. Build forms
with a shortcode, store submissions in a **private** database (never in
`wp_posts`), log GDPR consents, optionally **route leads to partners**, bill
per partner, collect **customer reviews**, and send **automated follow-up
emails** — all from your own site, with no external SaaS.

The plugin is **country-agnostic and fully translatable**: it ships in English
and works in any market out of the box. Postal-code handling, currency, and the
city field all adapt to your country from the Settings screen.

- **Repository:** https://github.com/finland93/leadmagnet
- **License:** GPL-2.0-or-later
- **Requires:** WordPress 6.0+, PHP 7.4+

---

## Features

- **Form builder (JSON):** text, email, tel, number, textarea, select, radio,
  checkbox; required fields; per-field mapping to indexed columns.
- **Private lead store:** dedicated database tables, not exposed through the
  public REST API or the WordPress front end.
- **Spam protection:** honeypot + timing gate + per-IP rate limiting, plus
  optional Cloudflare Turnstile or Google reCAPTCHA v2/v3.
- **GDPR built in:** consent logging with proof (timestamp, text, policy
  version, hashed IP/UA), data export (JSON), automatic anonymization by
  retention window, and opt-in data deletion on uninstall.
- **Partner routing (optional):** match partners by postal-code prefix and
  service, with per-day/per-month caps and priority.
- **Per-partner billing:** configurable lead value/pricing rules, a billing
  screen grouped by partner, and "mark as billed".
- **Customer reviews:** a 1–5 star widget; high ratings are redirected to your
  public review page (Google/Trustpilot/…), low ratings are captured privately
  so you can follow up.
- **Automated emails:** transactional confirmations plus scheduled follow-ups,
  all editable, with per-field placeholders. **All automated emails are off by
  default** and individually switchable.

---

## Installation

The repository **is** the plugin (the main file `leadmagnet.php` is at the repo
root), so the ZIP you download from GitHub installs directly in WordPress — no
unzipping or moving files by hand.

**From WordPress admin (easiest):**

1. On the repo page, click **Code → Download ZIP** (you get `leadmagnet-main.zip`).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose that ZIP,
   click **Install Now**, then **Activate**.

**From a release:** download the ZIP attached to a
[release](https://github.com/finland93/leadmagnet/releases) and upload it the
same way.

**Manually / via git:**

```bash
cd wp-content/plugins
git clone https://github.com/finland93/leadmagnet.git leadmagnet
```

Then activate **LeadMagnet** under **Plugins**. On activation the plugin creates
its tables, seeds sensible defaults, and adds one starter form. A **LeadMagnet**
menu appears in the admin sidebar.

---

## Quick start

1. **Forms →** open the *Default lead form* (or add a new one) and edit its JSON.
2. Put the shortcode on any page or post:

   ```
   [leadmagnet id="1"]
   ```

3. **Settings →** set your notification email, currency, and (if needed) the
   postal-code format for your country.
4. **Emails →** review the confirmation templates; enable follow-ups/reviews if
   you want them (they are off by default).

The in-admin **Help** page documents every field, pricing rule, and feature.

---

## Making it fit your country

This is the part that makes LeadMagnet international. Everything below is on the
**Settings → Localization** and **Currency** screens — no code required.

### Postal codes

- **Postal code format:** `Any` (letters and numbers — the safe default, works
  for UK `SW1A 1AA`, Canada `K1A 0B1`, Netherlands `1234 AB`, …) or
  `Numeric only` (Finland, Germany, USA, …).
- **Postal code max length:** generous by default (12); tighten if you like.

### City auto-fill (optional)

By default the **city field is a normal editable text field**, which works
everywhere. If you want the city to fill in automatically from the postal code:

1. Tick **Auto-fill city from postal code**.
2. Provide a **Postal dataset URL** — a JSON object mapping codes to cities:

   ```json
   {
     "00100": "Helsinki",
     "10001": "New York",
     "SW1A 1AA": "London"
   }
   ```

   A Finnish dataset is bundled as an example at
   `public/data/examples/fi-postal-codes.json`. See
   [`public/data/examples/README.md`](leadmagnet/public/data/examples/README.md)
   for the exact format and how to add your own country.

### Currency

Set the **currency symbol** (`€`, `$`, `£`, `kr`, …), whether it goes **before
or after** the amount, and a **price note** (e.g. `excl. VAT`) shown on the
billing screen.

### Partner routing works with any postal format

A partner's postal-code list matches **by prefix**, so `SW1` matches
`SW1A 1AA`, and `10` matches `10001`. Leave a partner's list empty to match the
whole country.

---

## Translating

The plugin is fully internationalized (text domain `leadmagnet`).
A translation template is provided at
[`languages/leadmagnet.pot`](leadmagnet/languages/leadmagnet.pot).
Create a `.po`/`.mo` for your locale (e.g. `leadmagnet-fi.mo`) and
drop it in the `languages/` folder, or use a plugin like Loco Translate.

---

## Developer notes

- Prefix / text domain: `lmf93` / `leadmagnet`.
- Useful filters: `lmf93_route_lead`, `lmf93_lead_score`, `lmf93_lead_value`,
  `lmf93_email_placeholders`, `lmf93_followup_subject`, `lmf93_followup_body`,
  `lmf93_dedupe_minutes`, `lmf93_recaptcha_v3_threshold`.
- Every submitted field is exposed to email templates as `{field_key}` and, for
  choice fields, `{field_key_label}`.
- REST endpoints are write-only: `POST /lmf93/v1/lead`,
  `POST /lmf93/v1/preferences`, `POST /lmf93/v1/review`. There is **no** public
  read route for leads.

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
