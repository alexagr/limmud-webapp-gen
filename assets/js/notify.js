/*
 * In-app message from the admin page (data/notify.json, written by notify.php).
 * Shown as a popup while the app is open: ~10s after start, every 3 minutes, and when the app comes back
 * to the foreground. Each message (its timestamp) is shown once per device - it is marked as seen only
 * when the popup is closed, so a page reload (e.g. the "new version" auto-reload) shows it again.
 * Not shown while a new-version reload is pending (window.appUpdating, set in the footer).
 */
(function () {
  var SEEN_KEY = 'webapp_notify_seen';
  var showing = false;

  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  function texts() {
    var lang = document.documentElement.lang;
    if (lang === 'he') return {msg: 'msg_he', button: 'סגור'};
    if (lang === 'en') return {msg: 'msg', button: 'Close'};
    if (/_ukr\b/.test(location.pathname)) return {msg: 'msg', button: 'ЗАКРИТИ'};
    return {msg: 'msg', button: 'ЗАКРЫТЬ'};
  }

  function active(data) {
    var now = Date.now();
    var from = data.not_before ? Date.parse(data.not_before) : NaN;
    var to = data.not_after ? Date.parse(data.not_after) : NaN;
    if (!isNaN(from) && now < from) return false;
    if (!isNaN(to) && now > to) return false;
    return true;
  }

  function check() {
    if (showing || window.appUpdating || !window.fetch || !window.Swal) return;
    if (document.visibilityState === 'hidden') return;
    fetch('data/notify.json', {cache: 'no-store'}).then(function (r) {
      return r.ok ? r.json() : null;
    }).then(function (data) {
      if (!data || showing || window.appUpdating) return;
      var t = texts();
      var msg = data[t.msg];
      if (!msg || !data.timestamp || get(SEEN_KEY) === String(data.timestamp) || !active(data)) return;
      showing = true;
      Swal.fire({
        icon: 'info',
        text: msg,
        confirmButtonText: t.button
      }).then(function () {
        set(SEEN_KEY, String(data.timestamp));   // seen only once actually closed
        showing = false;
      });
    }).catch(function () {});
  }

  setTimeout(check, 10 * 1000);
  setInterval(check, 3 * 60 * 1000);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') setTimeout(check, 2000);
  });
})();
