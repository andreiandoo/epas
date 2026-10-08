# Languages of viaqui.com

The pages are written in English. Every text a visitor reads goes through a function that takes the English text as
its key, so adding a language means translating one file, not touching the pages.

## In a page (PHP)

```php
<?= v2_te('Sign in') ?>                                        // escaped, for HTML
<?= v2_te('Hello, {name}', ['name' => $name]) ?>                // {placeholders}; values are escaped too
<?= v2_t('Accept the <a href="{url}">terms</a>', ['url' => '/terms']) ?>   // not escaped: text that carries markup
<?= v2_e(v2_num($n, 'city', 'cities')) ?>                       // "5 cities", with the plural form of the language
$pageTitle = v2_t('Trip planner');                              // titles and descriptions too
```

Rules that keep the catalogue usable:

- The argument is a **literal string**. `v2_t($label)` cannot be collected; if the text is in a list, wrap each item
  where the list is written.
- One whole sentence per call. Do not build a sentence from pieces: word order differs between languages. Put what
  varies in a `{placeholder}`.
- Names (places, attractions, brands, people) are not translated: they come from the data.
- Internal links stay as they are (`/rome`): the language prefix is added to every link when the page is sent.

## In a script (JS)

`assets/v2/js/base.js` gives every script:

```js
VQ.t('Loading…')                       // text
VQ.t('{n} more from this area', { n: 12 })
VQ.n(5, 'city', 'cities')              // "5 cities"
VQ.url('/rome')                        // an address built in a script, with the language prefix
```

## A catalogue: `lang/<code>.php`

```php
<?php
return [
    'strings' => ['Sign in' => 'Anmelden'],                    // texts of PHP pages
    'plurals' => ['city|cities' => ['Stadt', 'Städte']],       // as many forms as the language needs (ro and pl: three)
    'js'      => ['Loading…' => 'Wird geladen…'],               // texts of the scripts
];
```

A text with no translation is shown in English, so a catalogue can be filled a page at a time.

`python extract_strings.py` (in `plans/viaqui-data/`) reads the site and writes `lang/_template.php`: every text
found, grouped the same way, with empty translations. Copy it to `lang/<code>.php` and fill it; run it again later to
see what is new (`--check <code>` lists what a catalogue is missing and what it no longer needs).

## Opening a language

1. Translate `lang/<code>.php`.
2. Add the code to `enabled` in `includes/locales.php`.

From then on `/<code>/…` answers in that language, pages name each other with `hreflang`, and the language menu
lists it. The default language (English) has no prefix. Until step 2 the address `/<code>/…` is a 404.

## What is not in the catalogue

- Long documents (terms, privacy policy): one file per language, not a catalogue of sentences.
- Content that comes from the core API (names, descriptions, guides): core returns it in the language asked for.
- Emails: they are sent by core.
