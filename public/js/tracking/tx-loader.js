/**
 * TX Tracking SDK Loader
 *
 * Lightweight async loader for the TX tracking SDK.
 * Queues events before SDK is loaded, then replays them.
 *
 * Usage:
 * <script>
 *   window.txConfig = { tenantId: 'your-tenant-id', apiEndpoint: 'https://core.tixello.com/api/tx/events/batch' };
 * </script>
 * <script src="https://core.tixello.com/js/tracking/tx-loader.js" async></script>
 */
(function(w, d, s, l) {
  // Create queue for events before SDK loads
  w[l] = w[l] || [];

  // Stub tracker with queuing
  w.tx = w.tx || {
    _q: [],
    track: function() { this._q.push(['track', arguments]); },
    pageView: function() { this._q.push(['pageView', arguments]); },
    eventView: function() { this._q.push(['eventView', arguments]); },
    addToCart: function() { this._q.push(['addToCart', arguments]); },
    checkoutStarted: function() { this._q.push(['checkoutStarted', arguments]); },
    identify: function() { this._q.push(['identify', arguments]); },
    setConsent: function() { this._q.push(['setConsent', arguments]); }
  };

  // Load SDK
  var f = d.getElementsByTagName(s)[0];
  var j = d.createElement(s);
  j.async = true;
  j.src = (w.txConfig && w.txConfig.sdkUrl) || 'https://core.tixello.com/js/tracking/tx-sdk.js';

  j.onload = function() {
    // Initialize real tracker
    if (w.TxTracker && w.txConfig) {
      var realTx = new w.TxTracker(w.txConfig);

      // Replay queued events
      var q = w.tx._q || [];
      for (var i = 0; i < q.length; i++) {
        var method = q[i][0];
        var args = Array.prototype.slice.call(q[i][1]);
        if (typeof realTx[method] === 'function') {
          realTx[method].apply(realTx, args);
        }
      }

      // Replace stub with real tracker
      w.tx = realTx;
    }
  };

  f.parentNode.insertBefore(j, f);
})(window, document, 'script', 'txQueue');
