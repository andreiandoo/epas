/* =============================================================================
 * Scan App — API client
 * -----------------------------------------------------------------------------
 * Wraps the dedicated /api/scan-proxy.php so the scan app doesn't depend on
 * the main panel's action-based proxy + 30-endpoint mapping table in
 * /assets/js/api.js. Lets us add arbitrary new endpoints by just calling
 * them — no api.js / proxy.php edits required.
 *
 * Exposes window.ScanAPI with .get / .post / .put / .patch / .delete that
 * mirror the AmbiletAPI surface the scan-app pages already use, so we can
 * search-replace AmbiletAPI → ScanAPI in pages with no other changes.
 * ============================================================================= */
(function () {
  'use strict';

  var BASE = '/api/scan-proxy.php';

  function buildUrl(path) {
    return BASE + '?path=' + encodeURIComponent(path);
  }

  function isVenueOwner() {
    return typeof ScanAuth !== 'undefined' && ScanAuth.isVenueOwner && ScanAuth.isVenueOwner();
  }

  // Venue owners call the organizer-shaped paths; the backend mirrors them
  // under /venue-owner/ (same rules as the mobile app's client.js).
  function rewritePath(path) {
    if (!path || !isVenueOwner()) return path;
    if (path.indexOf('/venue-owner/') === 0) return path;
    if (path.indexOf('/organizer/') === 0) return '/venue-owner/' + path.slice('/organizer/'.length);
    if (path === '/orders' || path.indexOf('/orders/') === 0 || path.indexOf('/orders?') === 0) return '/venue-owner' + path;
    if (/^\/events\/\d+\/sales-breakdown(\?|$)/.test(path)) return '/venue-owner' + path;
    return path;
  }

  function getToken() {
    if (isVenueOwner()) return ScanAuth.getVenueToken();
    if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.getToken) {
      try { return AmbiletAuth.getToken(); } catch (e) {}
    }
    try { return localStorage.getItem('ambilet_organizer_token'); } catch (e) { return null; }
  }

  function request(path, method, body) {
    var headers = {
      'Accept':       'application/json',
      'Content-Type': 'application/json'
    };
    var token = getToken();
    if (token) headers['Authorization'] = 'Bearer ' + token;
    path = rewritePath(path);

    var init = { method: method, headers: headers, credentials: 'same-origin' };
    if (body !== undefined && body !== null && method !== 'GET' && method !== 'DELETE') {
      init.body = typeof body === 'string' ? body : JSON.stringify(body);
    }

    return fetch(buildUrl(path), init).then(function (resp) {
      var ct = resp.headers.get('content-type') || '';
      var p = ct.indexOf('application/json') !== -1 ? resp.json() : resp.text();
      return p.then(function (data) {
        if (!resp.ok) {
          var err = new Error((data && data.message) || ('HTTP ' + resp.status));
          err.status = resp.status;
          err.data = data;
          throw err;
        }
        return data;
      });
    });
  }

  function appendParams(path, params) {
    if (!params) return path;
    var keys = Object.keys(params);
    if (!keys.length) return path;
    var sep = path.indexOf('?') === -1 ? '?' : '&';
    var qs = keys
      .filter(function (k) { return params[k] !== undefined && params[k] !== null; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
      .join('&');
    return qs ? (path + sep + qs) : path;
  }

  window.ScanAPI = {
    get:    function (path, params) { return request(appendParams(path, params), 'GET'); },
    post:   function (path, body)   { return request(path, 'POST',  body || {}); },
    put:    function (path, body)   { return request(path, 'PUT',   body || {}); },
    patch:  function (path, body)   { return request(path, 'PATCH', body || {}); },
    delete: function (path)         { return request(path, 'DELETE'); }
  };
})();
