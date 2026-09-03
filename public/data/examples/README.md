# Postal code datasets (optional)

These files are **optional**. LeadMagnet works in every country without them:
by default the postal code accepts any format and the city field is a normal
editable text field.

You only need a dataset here if you enable **Settings → Localization →
"Auto-fill city from postal code"**.

## Format

A dataset is a single JSON object mapping a postal code to a city / locality
name:

```json
{
  "00100": "Helsinki",
  "10001": "New York",
  "SW1A 1AA": "London"
}
```

- Keys are postal codes exactly as a visitor would type them (for numeric
  countries, digits only; for alphanumeric countries, the plugin also tries a
  space-free variant, so both `SW1A 1AA` and `SW1A1AA` resolve).
- Values are the city / locality names shown to the visitor.

## Using a dataset

1. Save your file here (or anywhere public, e.g. the Media Library).
2. Copy its URL.
3. Paste it into **Settings → Localization → Postal dataset URL**.

If you enable auto-fill but leave the URL empty, the bundled
`fi-postal-codes.json` (Finland) example is used.

## Bundled example

- `fi-postal-codes.json` — Finnish postal codes (source: Finnish postal code
  data). Provided as a working example of the format; replace it with your own
  country's data.
