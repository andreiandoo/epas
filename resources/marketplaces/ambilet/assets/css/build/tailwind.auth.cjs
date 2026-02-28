const shared = require('./_shared.cjs');

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    '../../includes/head.php',
    '../../includes/scripts.php',
    '../../includes/cookie-consent.php',
    '../../includes/auth-branding.php',
    '../../login.php',
    '../../register.php',
    '../../forgot-password.php',
    '../../reset-password.php',
    '../../email-confirmed.php',
    '../js/pages/login.js',
    '../js/pages/register.js',
    '../js/pages/forgot-password.js',
    '../js/config.js',
    '../js/utils.js',
    '../js/api.js',
    '../js/auth.js',
    '../js/cookie-consent.js',
  ],
  theme: shared.theme,
  safelist: shared.safelist,
  plugins: shared.plugins,
};
