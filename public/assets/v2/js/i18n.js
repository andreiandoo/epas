/* Languages, for every script of the site (the counterpart of includes/v2/i18n.php):
 *
 *   VQ.t('Loading…')                          the text in the visitor's language; the English text is the key
 *   VQ.t('Hello, {name}', { name: n })        {placeholders} are filled after translation
 *   VQ.n(5, 'city', 'cities')                 "5 cities": the count formatted, the noun in the right plural form
 *   VQ.plural(5, 'city', 'cities')            the noun alone
 *   VQ.url('/rome')                           an internal address with the language prefix, for links built in a script
 *
 * In English nothing is loaded and every call returns its own text. For another language head.php prints
 * window.VQ_I18N = { locale, strings, plurals } from the 'js' and 'plurals' parts of lang/<code>.php.
 */
(function () {
  'use strict';
  var I = window.VQ_I18N || { locale: 'en', strings: {}, plurals: {} };
  var VQ = window.VQ = window.VQ || {};
  VQ.locale = I.locale || 'en';
  function fill(text, vars) {
    if (!vars) return text;
    return text.replace(/\{(\w+)\}/g, function (m, k) { return vars[k] !== undefined && vars[k] !== null ? String(vars[k]) : m; });
  }
  VQ.t = function (text, vars) {
    var out = (I.strings && I.strings[text]) || text;
    return fill(out, vars);
  };
  function index(n) {
    n = Math.abs(n);
    switch (VQ.locale) {
      case 'fr': return n <= 1 ? 0 : 1;
      case 'ro': return n === 1 ? 0 : ((n === 0 || (n % 100 >= 1 && n % 100 <= 19)) ? 1 : 2);
      case 'pl': return n === 1 ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);
      default: return n === 1 ? 0 : 1;
    }
  }
  VQ.plural = function (n, one, many) {
    var forms = I.plurals && I.plurals[one + '|' + many];
    if (forms && forms.length) return forms[Math.min(index(n), forms.length - 1)];
    return n === 1 ? one : many;
  };
  VQ.n = function (n, one, many) {
    var shown;
    try { shown = new Intl.NumberFormat(VQ.locale === 'en' ? 'en-GB' : VQ.locale).format(n); } catch (e) { shown = String(n); }
    return shown + ' ' + VQ.plural(n, one, many);
  };
  VQ.url = function (path) {
    if (VQ.locale === 'en' || !path || path.charAt(0) !== '/' || path.charAt(1) === '/') return path;
    if (/^\/(assets|api|storage)\//.test(path)) return path;
    return '/' + VQ.locale + (path === '/' ? '' : path);
  };
})();
