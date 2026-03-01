const shared = require('./_shared.cjs');

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    '../../../includes/head.php',
    '../../../includes/scripts.php',
    '../../../404.php',
    '../../../403.php',
    '../../../500.php',
    '../../../503.php',
    '../../js/config.js',
    '../../js/utils.js',
    '../../js/api.js',
  ],
  theme: shared.theme,
  safelist: shared.safelist,
  plugins: shared.plugins,
};
