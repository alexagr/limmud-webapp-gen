/*
 * Anonymous bookmark statistics.
 * Each device gets a random id (no personal data). Whenever the set of starred sessions changes
 * (or on start, if it was never reported), the full current list is sent to stats_report.php,
 * which keeps only the latest list per device - so un-starring and re-sending are counted correctly.
 * Works for every way a star is set (programme, Сетка, presenters page): we simply watch the stored value.
 * While the app is used it also reports at most every 6 hours, so the server knows when the device was last seen.
 * Also sends a rough, anonymous device profile: platform, browser, app or browser tab, language.
 * Offline: the send fails silently and is retried on the next change / start / return to the app.
 */
(function () {
  var me = document.currentScript;
  var KEY = me && me.getAttribute('data-key');
  var APP = me && me.getAttribute('data-app');
  if (!KEY || !APP || !window.fetch) return;

  var ID_KEY = 'stats-device-id';
  var SENT_KEY = 'stats-sent:' + KEY;
  var SENT_AT_KEY = 'stats-sent-at:' + KEY;

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

  // anonymous device profile: rough categories only (no model, version or screen size)
  function info() {
    var ua = navigator.userAgent;
    var iPad = /iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var os = iPad ? 'ipad' : /iPhone|iPod/.test(ua) ? 'iphone' : /Android/.test(ua) ? 'android'
           : /Windows/.test(ua) ? 'windows' : /Macintosh/.test(ua) ? 'mac' : /Linux|CrOS/.test(ua) ? 'linux' : 'other';
    var br = /SamsungBrowser/.test(ua) ? 'samsung' : /Edg\//.test(ua) ? 'edge' : /Firefox|FxiOS/.test(ua) ? 'firefox'
           : /YaBrowser/.test(ua) ? 'yandex' : /OPR\/|Opera/.test(ua) ? 'opera' : /Chrome|CriOS/.test(ua) ? 'chrome'
           : /Safari/.test(ua) ? 'safari' : 'other';
    var app = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
    // on Android the app and the browser share this storage: once the installed app was used, count the device as "app"
    if (app) set('stats-used-app', '1');
    app = app || get('stats-used-app') === '1';
    var page = location.pathname.split('/').pop() || '';
    var m = page.match(/_(he|en|ukr)\.html$/);
    return {os: os, br: br, mode: app ? 'app' : 'browser', lang: m ? m[1] : 'ru'};
  }

  var sending = false;
  // team device (this browser opened admin.php): not counted - remove it from the stats once, then stay silent
  function team() {
    if (get('stats-team') !== '1') return false;
    if (get('stats-team-removed:' + KEY) === '1' || !get(ID_KEY) || sending) return true;
    sending = true;
    fetch('../stats_report.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({app: APP, device: get(ID_KEY), remove: true}),
      keepalive: true
    }).then(function (r) {
      if (r.ok) set('stats-team-removed:' + KEY, '1');
    }).catch(function () {}).then(function () { sending = false; });
    return true;
  }

  function report() {
    if (team()) return;
    if (sending || (navigator.onLine === false)) return;
    var ids = starred();
    var inf = info();
    // also re-send when the profile changes (e.g. the app got installed, or the language was switched)
    var snapshot = ids.join(',') + '|' + inf.os + ',' + inf.br + ',' + inf.mode + ',' + inf.lang;
    // nothing new since the last successful report, and it was less than 6 hours ago (keeps "last seen" current)
    if (snapshot === get(SENT_KEY) && Date.now() - Number(get(SENT_AT_KEY) || 0) < 6 * 60 * 60 * 1000) return;
    var id = deviceId();
    sending = true;
    fetch('../stats_report.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({app: APP, device: id, sessions: ids, info: inf}),
      keepalive: true
    }).then(function (r) {
      if (r.ok) { set(SENT_KEY, snapshot); set(SENT_AT_KEY, String(Date.now())); }
    }).catch(function () {}).then(function () { sending = false; });
  }

  // stars are toggled by clicks/taps; check shortly after each one
  document.addEventListener('click', function () { setTimeout(report, 800); }, true);
  window.addEventListener('online', report);
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') report(); });
  window.addEventListener('pagehide', report);
  setTimeout(report, 3000);
})();
