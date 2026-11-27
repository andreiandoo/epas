{{-- Client Reverb minimal (protocol Pusher peste WebSocket), comun pentru panoul marketplace.
     Același abordaj ca în seating/embed: fără pusher-js. Expune window.epRealtime. --}}
@php
    $epRt = [
        'key' => config('reverb-client.app_key'),
        'host' => config('reverb-client.host'),
        'port' => (int) config('reverb-client.port'),
        'scheme' => config('reverb-client.scheme'),
        'path' => config('reverb-client.path') ?? '',
        'client' => (int) (auth('marketplace_admin')->user()?->marketplace_client_id ?? 0),
    ];
@endphp
<script>
(function () {
    if (window.epRealtime) return;
    var CFG = @json($epRt);

    var ws = null, socketId = null, connected = false, delay = 1000;
    var reconnectTimer = null, pingTimer = null;
    var subs = {};          // channel -> { event -> [handlers] }
    var statusHandlers = [];

    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
    function url() {
        var scheme = CFG.scheme === 'https' ? 'wss' : 'ws';
        var port = (CFG.scheme === 'https' && CFG.port === 443) || (CFG.scheme !== 'https' && CFG.port === 80) ? '' : ':' + CFG.port;
        return scheme + '://' + CFG.host + port + (CFG.path || '') + '/app/' + CFG.key + '?protocol=7&client=js&version=8.4.0&flash=false';
    }
    function setConnected(v) {
        if (connected === v) return;
        connected = v;
        statusHandlers.forEach(function (fn) { try { fn(v); } catch (e) {} });
    }
    function send(obj) { if (ws && ws.readyState === 1) ws.send(JSON.stringify(obj)); }

    function subscribe(channel) {
        if (!socketId) return;
        if (channel.indexOf('private-') !== 0) { send({ event: 'pusher:subscribe', data: { channel: channel } }); return; }
        var body = 'socket_id=' + encodeURIComponent(socketId) + '&channel_name=' + encodeURIComponent(channel);
        fetch('/broadcasting/auth', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (res) { if (res && res.auth) send({ event: 'pusher:subscribe', data: { channel: channel, auth: res.auth } }); })
          .catch(function () {});
    }

    function connect() {
        if (!CFG.key || !CFG.host || typeof WebSocket === 'undefined') return;
        try { ws = new WebSocket(url()); } catch (e) { schedule(); return; }
        ws.onmessage = function (evt) {
            var frame; try { frame = JSON.parse(evt.data); } catch (e) { return; }
            var data = frame.data;
            if (typeof data === 'string') { try { data = data ? JSON.parse(data) : null; } catch (e) {} }
            if (frame.event === 'pusher:connection_established') {
                socketId = data && data.socket_id;
                delay = 1000;
                Object.keys(subs).forEach(subscribe);
                clearInterval(pingTimer);
                pingTimer = setInterval(function () { send({ event: 'pusher:ping', data: {} }); }, 30000);
                return;
            }
            if (frame.event === 'pusher_internal:subscription_succeeded') { setConnected(true); return; }
            if (frame.event && frame.channel && subs[frame.channel] && subs[frame.channel][frame.event]) {
                subs[frame.channel][frame.event].forEach(function (fn) { try { fn(data); } catch (e) {} });
            }
        };
        ws.onclose = function () {
            clearInterval(pingTimer);
            socketId = null;
            setConnected(false);
            schedule();
        };
    }
    function schedule() {
        clearTimeout(reconnectTimer);
        reconnectTimer = setTimeout(connect, delay);
        delay = Math.min(delay * 2, 30000);
    }

    window.epRealtime = {
        clientId: CFG.client || 0,
        // channel: nume Laravel fără prefix (ex. 'cockpit.1'); privat = true pentru canale private.
        on: function (channel, isPrivate, event, handler) {
            var name = (isPrivate ? 'private-' : '') + channel;
            var isNew = !subs[name];
            subs[name] = subs[name] || {};
            (subs[name][event] = subs[name][event] || []).push(handler);
            if (isNew) subscribe(name);
        },
        onStatus: function (fn) { statusHandlers.push(fn); },
        isConnected: function () { return connected; }
    };

    connect();
})();
</script>
