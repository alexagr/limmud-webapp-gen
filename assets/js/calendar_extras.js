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

  // The session start times stay visible; the current time goes between the two start times around it,
  // without covering either, at the matching point of the gap between them (18:31 between 18:30 and 19:40
  // sits right under 18:30, 19:05 in the middle, 19:35 right above 19:40).
  function festMinutes(t) {   // "18:30" -> minutes; after midnight (00:xx-04:xx) belongs to the same day
    var m = /(\d{1,2}):(\d{2})/.exec(t || '');
    if (!m) return null;
    var v = parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
    return v < 5 * 60 ? v + 24 * 60 : v;
  }
  function placeLabel(times, rooms, line, label, nowMin) {
    var tr = times.getBoundingClientRect();
    // the printed time inside its slot (the slot is taller than the text)
    function textBox(el) {
      var r = document.createRange();
      r.selectNodeContents(el);
      var b = r.getBoundingClientRect();
      return b.height ? b : el.getBoundingClientRect();
    }
    var prev = null, next = null;
    var kids = times.querySelectorAll('.time');
    for (var k = 0; k < kids.length; k++) {
      var v = festMinutes(kids[k].textContent);
      if (v === null) continue;
      if (v <= nowMin) prev = kids[k];
      else if (!next) next = kids[k];
    }
    var h = label.offsetHeight, gap = 3;
    var y = line.getBoundingClientRect().top - tr.top;   // the exact minute on the time scale
    var lo = prev ? textBox(prev).bottom - tr.top + gap + h / 2 : null;   // just under the earlier time
    var hi = next ? textBox(next).top - tr.top - gap - h / 2 : null;      // just above the later time
    if (lo !== null && hi !== null) {
      if (hi < lo) return (lo + hi) / 2;   // no room between them
      var t0 = festMinutes(prev.textContent), t1 = festMinutes(next.textContent);
      var f = t1 > t0 ? (nowMin - t0) / (t1 - t0) : 0;
      return lo + Math.max(0, Math.min(1, f)) * (hi - lo);
    }
    if (lo !== null) return Math.max(lo, y);   // after the last start time
    if (hi !== null) return Math.min(hi, y);   // before the first one
    return line.getBoundingClientRect().top - tr.top;
  }

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
        label.innerHTML = '<i class="fa fa-clock" aria-hidden="true"></i>' + pad(Math.floor(now.minutes / 60) % 24) + ':' + pad(now.minutes % 60);
        label.style.top = placeLabel(times, rooms, line, label, festMinutes(pad(Math.floor(now.minutes / 60) % 24) + ':' + pad(now.minutes % 60))) + 'px';
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
    var bar = document.querySelector('.search-sticky');
    var offset = (nav ? nav.getBoundingClientRect().bottom : 0) + (bar ? bar.offsetHeight : 0) + 120;
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

/*
 * Session details on the Сетка: tapping a session opens a card with the same details as on the
 * programme page (time, room, language, description, presenters) instead of jumping there.
 * The details are read from the programme page itself (schedule.html / schedule_he.html ...),
 * which the service worker has saved, so this also works offline.
 */
(function () {
  var view = document.querySelector('.calendar-view');
  if (!view) return;

  var cache = {};   // programme page URL -> parsed document (promise)

  function programmeDoc(url) {
    if (!cache[url]) {
      cache[url] = fetch(url).then(function (r) { return r.text(); }).then(function (html) {
        return new DOMParser().parseFromString(html, 'text/html');
      });
    }
    return cache[url];
  }

  // build the card once
  var overlay = document.createElement('div');
  overlay.className = 'session-modal';
  overlay.innerHTML =
    '<div class="session-modal-card" role="dialog" aria-modal="true">' +
    '  <button type="button" class="session-modal-close" aria-label="×">&times;</button>' +
    '  <h4 class="session-modal-title"></h4>' +
    '  <div class="session-modal-body"></div>' +
    '</div>';
  document.body.appendChild(overlay);

  var current = null;   // session element whose details are shown

  function close() {
    overlay.classList.remove('is-open');
    document.body.classList.remove('session-modal-open');
    current = null;
    // the tapped session link keeps focus otherwise (underlined, other colour after Esc)
    var a = document.activeElement;
    if (a && a.closest && a.closest('.calendar .session')) a.blur();
  }

  function open(session) {
    var link = session.querySelector('.session-name a');
    if (!link) return;
    var href = link.getAttribute('href');                    // e.g. schedule.html#42
    var page = href.split('#')[0];
    var id = session.id.replace(/^session-/, '');
    current = session;

    overlay.querySelector('.session-modal-title').textContent = link.textContent.trim();
    overlay.querySelector('.session-modal-body').innerHTML = '<p class="session-modal-loading">…</p>';
    overlay.classList.add('is-open');
    document.body.classList.add('session-modal-open');

    programmeDoc(page).then(function (doc) {
      if (current !== session) return;
      var desc = doc.getElementById('desc-' + id);
      var body = overlay.querySelector('.session-modal-body');
      if (!desc) { body.innerHTML = ''; return; }
      body.innerHTML = desc.innerHTML;
      // presenter links lead to the presenters page - keep them working as normal links
    }).catch(function () {
      overlay.querySelector('.session-modal-body').innerHTML = '';
    });
  }

  // tap on a session (but not on its ★) opens the card instead of jumping to the programme
  view.addEventListener('click', function (e) {
    if (e.target.closest('.grid-fav')) return;
    var s = e.target.closest('.session[id^="session-"]');
    if (!s) return;
    e.preventDefault();
    open(s);
  });

  overlay.addEventListener('click', function (e) {
    if (e.target === overlay || e.target.closest('.session-modal-close')) close();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();

/*
 * Search on the Сетка: sessions whose title / presenters don't match fade out
 * (they stay in place, so the grid keeps its shape and you keep your place in time).
 */
(function () {
  var input = document.querySelector('.grid-search');
  var view = document.querySelector('.calendar-view');
  if (!input || !view) return;

  function norm(s) { return String(s || '').toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim(); }

  function apply() {
    var q = norm(input.value);
    var sessions = view.querySelectorAll('.session[id^="session-"]');
    var hits = 0;
    for (var i = 0; i < sessions.length; i++) {
      var s = sessions[i];
      var text = '';
      var parts = s.querySelectorAll('.find-target');
      for (var j = 0; j < parts.length; j++) text += ' ' + parts[j].textContent;
      var match = !q || norm(text).indexOf(q) !== -1;
      s.classList.toggle('search-dim', !match);
      if (q && match) hits++;
    }
    view.classList.toggle('is-searching', !!q);
  }

  input.addEventListener('input', apply);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { input.value = ''; apply(); }
  });
})();
