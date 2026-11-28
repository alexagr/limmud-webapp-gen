/*
 * Programme page: "now" marker - the time group that is running now (last one that has already started)
 * gets an orange time label and a small pill with the current Israel time under it (festival days only).
 * The clock button scrolls to that group.
 * For testing, add ?now=2026-12-17T19:40 to the address.
 */
(function () {
  var TZ = 'Asia/Jerusalem';
  var LATEST_AFTER_LAST_START = 3 * 60;   // stop showing it 3 hours after the day's last session started

  function pad(n) { return (n < 10 ? '0' : '') + n; }

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

  // time group caption "19:30" -> minutes; times after midnight (00:xx, 01:xx) belong to the same festival day
  function groupMinutes(group) {
    var h4 = group.querySelector('.eventtime h4');
    var m = h4 && /(\d{1,2}):(\d{2})/.exec(h4.textContent);
    if (!m) return null;
    var mins = parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
    return mins < 5 * 60 ? mins + 24 * 60 : mins;
  }

  // when the group's sessions end: the latest "17:15 - 18:15" in its cards' details
  function groupEnd(group, start) {
    var end = null;
    var clocks = group.querySelectorAll('.fa-clock');
    for (var i = 0; i < clocks.length; i++) {
      var m = /\d{1,2}:\d{2}\s*-\s*(\d{1,2}):(\d{2})/.exec(clocks[i].parentNode.textContent);
      if (!m) continue;
      var e = parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
      while (e < start) e += 24 * 60;   // ends after midnight
      if (end === null || e > end) end = e;
    }
    return end;
  }

  var current = null;   // the time group the clock button scrolls to
  var currentPill = null;

  function clear() {
    if (current) current.classList.remove('is-now');
    if (currentPill) currentPill.remove();
    current = currentPill = null;
  }

  // Where "now" is in the programme: {group, running, mins} or null when "now" is not on a festival day.
  //   running: the group that started last is still on (its sessions end later) -> that group
  //   otherwise (a break): the next group
  function locate(now) {
    var days = document.querySelectorAll('.day-filter');
    for (var i = 0; i < days.length; i++) {
      var day = days[i];
      var date = (day.className.match(/\d{4}-\d{2}-\d{2}/) || [])[0];
      if (!date) continue;
      var mins = null;
      if (date === now.date) mins = now.minutes < 5 * 60 ? null : now.minutes;      // early morning belongs to yesterday
      if (previousDate(now.date) === date && now.minutes < 5 * 60) mins = now.minutes + 24 * 60;
      if (mins === null) continue;

      var groups = day.querySelectorAll('.time-filter');
      var last = null, lastStart = null, next = null, first = null;
      for (var j = 0; j < groups.length; j++) {
        var start = groupMinutes(groups[j]);
        if (start === null) continue;
        if (!first) first = groups[j];
        if (start <= mins) { last = groups[j]; lastStart = start; }
        else if (!next) next = groups[j];
      }
      if (!last) return first ? {group: first, running: false, mins: mins, before: true} : null;   // before the day starts
      if (mins - lastStart > LATEST_AFTER_LAST_START) return null;
      var end = groupEnd(last, lastStart);
      if (next && end !== null && mins >= end) return {group: next, running: false, mins: mins};
      return {group: last, running: true, mins: mins};
    }
    return null;
  }

  // the marker: drawn only when it is really a festival day now
  function place() {
    var now = israelNow();
    clear();
    var at = locate(now);
    if (!at || at.before) return;
    var pill = document.createElement('div');
    pill.className = 'now-pill';
    pill.innerHTML = '<i class="fa fa-clock" aria-hidden="true"></i>' + pad(Math.floor(now.minutes / 60) % 24) + ':' + pad(now.minutes % 60);
    current = at.group;
    var label = current.querySelector('.eventtime');
    if (at.running) {
      current.classList.add('is-now');
      if (label) label.appendChild(pill);
    } else {
      // break between groups: the time goes above the next group's time, nothing is marked as running
      pill.className += ' is-between';
      if (label) label.insertBefore(pill, label.firstChild);
    }
    currentPill = pill;
  }

  place();
  setInterval(place, 30 * 1000);
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('.date-button')) setTimeout(place, 0);
  });

  // clock button: scroll to where the marker is and say whether that session is running or comes next.
  // Not a festival day now: the same, as if it were this time of day on the first festival day (no marker).
  var TEXT = {
    ru: ['Текущая сессия началась в ', 'Ближайшая сессия начинается в '],
    uk: ['Поточна сесія почалася о ', 'Найближча сесія починається о '],
    he: ['ההרצאה הנוכחית התחילה ב- ', 'ההרצאה הקרובה מתחילה ב- '],
    en: ['Current session started at ', 'Next session starts at ']
  };
  function lang() {
    // the Ukrainian pages say lang="ru" and are also called schedule.html: recognise them by their own label
    var my = document.querySelector('.bookmark-toggle-label');
    if (my && /Мій/.test(my.textContent)) return 'uk';
    var l = document.documentElement.lang;
    return TEXT[l] ? l : 'en';
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('#now-button');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();   // instead of the old scroll_now.js behaviour
    var now = israelNow();
    var at = locate(now);
    if (!at) {
      var firstDay = document.querySelector('.day-filter');
      var date = firstDay && (firstDay.className.match(/\d{4}-\d{2}-\d{2}/) || [])[0];
      if (date) at = locate({date: date, minutes: now.minutes});
      // night / after the first day's programme: its first session is the next one
      if (!at && firstDay) {
        var g0 = firstDay.querySelector('.time-filter');
        if (g0) at = {group: g0, running: false};
      }
    }
    if (!at) return;
    var group = at.group;
    if (!group.offsetParent) {   // another day tab / My Limmud hides it: show all days
      var all = document.querySelector('.date-button.all');
      if (all) all.click();
    }
    if (!group.offsetParent) return;
    btn.classList.add('is-flash');
    setTimeout(function () { btn.classList.remove('is-flash'); }, 2000);
    var h4 = group.querySelector('.eventtime h4');
    var time = h4 ? h4.textContent.trim() : '';
    if (window.Snackbar && time) {
      Snackbar.show({text: TEXT[lang()][at.running ? 0 : 1] + time, pos: 'bottom-center', showAction: false, duration: 2000});
    }
    // land just below the menu and the search row that stays visible under it
    var nav = document.querySelector('.navbar-fixed-top');
    var bar = document.querySelector('.search-sticky');
    var navH = nav ? nav.getBoundingClientRect().bottom : 0;
    var offset = navH + (bar ? bar.offsetHeight : 0) + 10;
    window.scrollTo({top: group.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth'});
  }, true);
})();
