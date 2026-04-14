const shared = require('./_shared.cjs');

const sharedIncludes = [
  '../../../includes/head.php',
  '../../../includes/header.php',
  '../../../includes/footer.php',
  '../../../includes/scripts.php',
  '../../../includes/cookie-consent.php',
  '../../../includes/tracking.php',
  '../../../includes/featured-carousel.php',
  '../../../includes/nav-cache.php',
  '../../../includes/auth-branding.php',
];

const sharedJS = [
  '../../js/config.js',
  '../../js/utils.js',
  '../../js/utils/**/*.js',
  '../../js/api.js',
  '../../js/auth.js',
  '../../js/cart.js',
  '../../js/tracking.js',
  '../../js/cookie-consent.js',
  '../../js/components/**/*.js',
];

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    ...sharedIncludes,
    ...sharedJS,
    './dynamic-classes.html',
    '../../../about.php',
    '../../../contact.php',
    '../../../privacy.php',
    '../../../terms.php',
    '../../../blog.php',
    '../../../blog-single.php',
    '../../../faq.php',
    '../../../help-center.php',
    '../../../cookies.php',
    '../../../gdpr.php',
    '../../../comisioane.php',
    '../../../gift-cards.php',
    '../../../press-kit.php',
    '../../../partners.php',
    '../../../mobile-app.php',
    '../../../accessibility.php',
    '../../../refund-policy.php',
    '../../../newsletter-unsubscribe.php',
  ],
  theme: shared.theme,
  safelist: shared.safelist,
  plugins: shared.plugins,
};
