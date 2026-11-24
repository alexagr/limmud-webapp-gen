/*
 * Сетка (calendar) extras:
 *  - "now" line: a thin orange line across the grid at the current Israel time (festival days only),
 *    with the time shown in the time column. The clock button scrolls to it.
 *    For testing, add ?now=2026-12-17T19:40 to the address.
 *  - favourites: the same ★ selection as on the programme page (shared localStorage).
 *    A ★ on each session toggles it; the ★ button above the grid shows only favourites
 *    (and the PNG download then contains only them).
 */
(function () {
  var view = document.querySelector('.calendar-view');
  if (!view) return;

  var PX_PER_15 = 35;  // generate.php: $timeToPixel (15 minutes = 35px at zoom 1)
  var TZ = 'Asia/Jerusalem';

  // ---------- helpers ----------
  function zoomY() {
    return parseFloat(getComputedStyle(view).getPropertyValue('--zy')) || 1;
  }

  function toMinutes(hhmm) {
    var p = String(hhmm || '').split(':');
    return parseInt(p[0], 10) * 60 + parseInt(p[1] || '0', 10);
  }

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  // current date + minutes in Israel (or the ?now= override)
  function israelNow() {
    var m = /[?&]now=(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/.exec(window.location.search);
    if (m) return {date: m[1], minutes: parseInt(m[2], 10) * 60 + parseInt(m[3], 10)};
    var parts = {};
    new Intl.DateTimeFormat('en-CA', {timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}).formatToParts(new Date())
      .forEach(function (p) { parts[p.type] = p.value; });
    return {date: parts.year + '-' + parts.month + '-' + parts.day,
            minutes: parseInt(parts.hour, 10) * 60 + parseInt(parts.minute, 10)};
  }

  function previousDate(iso) {
    var d = new Date(iso + 'T12:00:00Z');
    d.setUTCDate(d.getUTCDate() - 1);
    return d.toISOString().slice(0, 10);
  }

  // ---------- "now" line ----------
  var nowTarget = null;  // the line element currently shown (for the clock button)

  function drawNowLine() {
    var now = israelNow();
    var z = zoomY();
    nowTarget = null;
    var days = document.querySelectorAll('.calendar[data-date]');
    for (var i = 0; i < days.length; i++) {
      var day = days[i];
      var rooms = day.querySelector('.rooms');
      var times = day.querySelector('.times');
      if (!rooms) continue;
      var line = rooms.querySelector('.now-line');
      var label = times && times.querySelector('.now-label');

      // minutes since the start of this day's grid (the grid may continue after midnight)
      var start = toMinutes(day.getAttribute('data-start'));
      var mins = null;
      if (day.getAttribute('data-date') === now.date) mins = now.minutes - start;
      else if (previousDate(now.date) === day.getAttribute('data-date')) mins = now.minutes + 24 * 60 - start;

      var top = mins === null ? -1 : (mins * PX_PER_15 / 15 + PX_PER_15) * z;  // same formula as sessions
      var visible = mins !== null && mins >= 0 && top <= rooms.scrollHeight - 2;
      if (!visible) {
        if (line) line.remove();
        if (label) label.remove();
        continue;
      }
      if (!line) {
        line = document.createElement('div');
        line.className = 'now-line';
        rooms.appendChild(line);
      }
      line.style.top = top + 'px';
      line.style.width = rooms.scrollWidth + 'px';
      if (times) {
        if (!label) {
          label = document.createElement('div');
          label.className = 'now-label';
          times.appendChild(label);
        }
        label.style.top = top + 'px';
        label.textContent = pad(Math.floor(now.minutes / 60) % 24) + ':' + pad(now.minutes % 60);
      }
      if (day.offsetParent) nowTarget = line;  // only if that day is shown
    }
  }

  drawNowLine();
  setInterval(drawNowLine, 30 * 1000);
  window.addEventListener('resize', drawNowLine);
  // redraw when the zoom changes (calendar_zoom.js sets --zx/--zy on .calendar-view)
  new MutationObserver(drawNowLine).observe(view, {attributes: true, attributeFilter: ['style']});
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('.date-button')) setTimeout(drawNowLine, 0);
  });

  // clock button: during the festival jump to the "now" line (otherwise keep its old behaviour)
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('#now-button');
    if (!btn) return;
    drawNowLine();
    if (!nowTarget) return;
    e.preventDefault();
    e.stopPropagation();
    var nav = document.querySelector('.navbar-fixed-top');
    var offset = (nav ? nav.getBoundingClientRect().bottom : 0) + 120;
    window.scrollTo({top: nowTarget.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth'});
  }, true);

  // ---------- favourites ----------
  var KEY = view.getAttribute('data-favorites-key') || '';

  function readFavorites() {
    try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; }
  }
  function writeFavorites(f) {
    try { localStorage.setItem(KEY, JSON.stringify(f)); } catch (e) {}
  }
  function sessionId(el) { return el.id.replace(/^session-/, ''); }

  function markFavorites() {
    var f = readFavorites();
    var sessions = document.querySelectorAll('.calendar .session[id^="session-"]');
    for (var i = 0; i < sessions.length; i++) {
      var s = sessions[i];
      if (!s.querySelector('.grid-fav')) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'grid-fav';
        b.setAttribute('aria-label', '★');
        b.innerHTML = '<i class="fa fa-star" aria-hidden="true"></i>';
        s.appendChild(b);
      }
      s.classList.toggle('is-fav', f[sessionId(s)] == 1);
    }
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.grid-fav');
    if (!b) return;
    e.preventDefault();
    e.stopPropagation();
    var s = b.closest('.session');
    var f = readFavorites();
    var id = sessionId(s);
    if (f[id] == 1) delete f[id]; else f[id] = 1;
    writeFavorites(f);
    markFavorites();
  }, true);

  // ★ filter: show only favourites (the PNG export then shows only them too)
  document.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('.grid-favorites-toggle');
    if (!t) return;
    e.preventDefault();
    var on = !view.classList.contains('only-favorites');
    view.classList.toggle('only-favorites', on);
    t.classList.toggle('is-active', on);
    drawNowLine();
  });

  // favourites changed on another page/tab
  window.addEventListener('storage', function (e) { if (e.key === KEY) markFavorites(); });
  window.addEventListener('pageshow', markFavorites);
  markFavorites();
})();
