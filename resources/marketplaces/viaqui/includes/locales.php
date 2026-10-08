<?php
/**
 * The languages of viaqui.com. Read by the router (before config.php is loaded) and by includes/v2/i18n.php.
 *
 *   default   the language at the root of the site (/rome). Its texts are the ones written in the pages themselves.
 *   enabled   every language the site answers in. Each one other than the default lives under its own prefix
 *             (/de/rome) and needs a catalogue in lang/<code>.php. A language listed in `names` but not here is
 *             known to the code and closed to visitors: add it here when its catalogue is ready.
 *   names     how each language is called in the language menu, in its own words.
 *
 * To open a language: translate lang/<code>.php (see lang/README.md), then add the code to `enabled`.
 */
return [
    'default' => 'en',
    'enabled' => ['en'],
    'names' => [
        'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano',
        'ro' => 'Română', 'nl' => 'Nederlands', 'pl' => 'Polski', 'pt' => 'Português',
    ],
];
