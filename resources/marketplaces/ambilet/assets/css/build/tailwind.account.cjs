const shared = require('./_shared.cjs');

const sharedIncludes = [
  '../../includes/head.php',
  '../../includes/header.php',
  '../../includes/footer.php',
  '../../includes/scripts.php',
  '../../includes/cookie-consent.php',
  '../../includes/tracking.php',
  '../../includes/featured-carousel.php',
  '../../includes/nav-cache.php',
  '../../includes/auth-branding.php',
];

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
    ...sharedIncludes,
    '../../includes/user-sidebar.php',
    '../../includes/user-header.php',
    '../../includes/user-footer.php',
    '../../includes/user-wrap.php',
    '../../includes/user-wrap-end.php',
    ...sharedJS,
    '../../user/*.php',
    '../js/pages/user-*.js',
  ],
  theme: shared.theme,
  safelist: shared.safelist,
  plugins: shared.plugins,
};
