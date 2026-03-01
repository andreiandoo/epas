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
    '../../../events.php',
    '../../../artists.php',
    '../../../venues.php',
    '../../../cities.php',
    '../../../city.php',
    '../../../category.php',
    '../../../genre.php',
    '../../../region.php',
    '../../../venue-type.php',
    '../../../past-events.php',
    '../../../events-calendar.php',
    '../../../cauta.php',
    '../../../ajutor-*.php',
    '../../../organizers.php',
    '../../../organizer-public.php',
    '../../js/pages/events-page.js',
    '../../js/pages/artists-page.js',
    '../../js/pages/category-page.js',
    '../../js/pages/city-page.js',
    '../../js/pages/genre-page.js',
    '../../js/pages/search-page.js',
  ],
  theme: shared.theme,
  safelist: shared.safelist,
  plugins: shared.plugins,
};
