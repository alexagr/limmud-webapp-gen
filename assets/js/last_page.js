/*
 * Installed app (PWA): reopen on the page the user was last on.
 * The phone always starts the app at index.html (manifest start_url). If the user was on another page
 * (Программа, Сетка, Презентеры, map...) less than 12 hours ago, we go straight back to that page.
 * Only the page is remembered - not the day tab, scroll position or filters.
 * START is remembered like any other page, so leaving the app on START reopens START.
 * Not in a normal browser tab, and not when START was opened from inside the app (menu «НАЧАЛО»).
 */
(function () {
  var KEY = 'webapp-last-page';
  var MAX_AGE = 12 * 60 * 60 * 1000;

  function get() { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } }
  function set(v) { try { localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) {} }

  var page = location.pathname.split('/').pop() || 'index.html';
  var isStart = /^index(_[a-z]+)?\.html$/.test(page);
  var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;

  // app launch = START opened with no referring page (menu «НАЧАЛО» always has the app page as referrer)
  if (isStart && standalone && !document.referrer) {
    var last = get();
    if (last && last.page && last.page !== page && !/^index(_[a-z]+)?\.html$/.test(last.page) &&
        /^[a-z_]+\.html$/.test(last.page) && Date.now() - last.t < MAX_AGE) {
      location.replace(last.page);
      return;
    }
  }

  function save() { set({page: page, t: Date.now()}); }
  save();
  window.addEventListener('pagehide', save);
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') save(); });
})();
