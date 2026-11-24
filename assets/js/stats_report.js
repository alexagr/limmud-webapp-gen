/*
 * Anonymous bookmark statistics.
 * Each device gets a random id (no personal data). Whenever the set of starred sessions changes
 * (or on start, if it was never reported), the full current list is sent to stats_report.php,
 * which keeps only the latest list per device - so un-starring and re-sending are counted correctly.
 * Works for every way a star is set (programme, Сетка, presenters page): we simply watch the stored value.
 * Offline: the send fails silently and is retried on the next change / start / return to the app.
 */
(function () {
  var me = document.currentScript;
  var KEY = me && me.getAttribute('data-key');
  var APP = me && me.getAttribute('data-app');
  if (!KEY || !APP || !window.fetch) return;

  var ID_KEY = 'stats-device-id';
  var SENT_KEY = 'stats-sent:' + KEY;

  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  function deviceId() {
    var id = get(ID_KEY);
    if (!id) {
      id = '';
      var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
      var rnd = (window.crypto && crypto.getRandomValues) ? crypto.getRandomValues(new Uint8Array(20)) : null;
      for (var i = 0; i < 20; i++) id += chars[(rnd ? rnd[i] : Math.floor(Math.random() * 256)) % chars.length];
      set(ID_KEY, id);
    }
    return id;
  }

  function starred() {
    var f = {};
    try { f = JSON.parse(get(KEY) || '{}') || {}; } catch (e) {}
    var ids = [];
    for (var k in f) if (f[k] == 1 && /^\d+$/.test(k)) ids.push(Number(k));
    ids.sort(function (a, b) { return a - b; });
    return ids;
  }

  var sending = false;
  function report() {
    if (sending || (navigator.onLine === false)) return;
    var ids = starred();
    var snapshot = ids.join(',');
    if (snapshot === get(SENT_KEY)) return;       // nothing new since the last successful report
    var id = deviceId();
    sending = true;
    fetch('../stats_report.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({app: APP, device: id, sessions: ids}),
      keepalive: true
    }).then(function (r) {
      if (r.ok) set(SENT_KEY, snapshot);
    }).catch(function () {}).then(function () { sending = false; });
  }

  // stars are toggled by clicks/taps; check shortly after each one
  document.addEventListener('click', function () { setTimeout(report, 800); }, true);
  window.addEventListener('online', report);
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') report(); });
  window.addEventListener('pagehide', report);
  setTimeout(report, 3000);
})();
