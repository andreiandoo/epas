const shared = require('./_shared.cjs');

const sharedJS = [
  '../js/config.js',
  '../js/utils.js',
  '../js/utils/**/*.js',
  '../js/api.js',
  '../js/auth.js',
  '../js/cart.js',
  '../js/tracking.js',
  '../js/cookie-consent.js',
  '../js/components/**/*.js',
];

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    '../../includes/head.php',
    '../../includes/scripts.php',
    '../../includes/cookie-consent.php',
    '../../includes/organizer-sidebar.php',
    '../../includes/organizer-topbar.php',
    '../../includes/organizer-footer.php',
    ...sharedJS,
    '../../organizer/*.php',
  ],
  theme: shared.theme,
  safelist: shared.safelist,
  plugins: shared.plugins,
};
