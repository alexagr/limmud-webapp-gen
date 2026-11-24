/*
 * Programme page: "now" divider - a thin orange line with the current Israel time, placed after the
 * last time group that has already started (festival days only). The clock button scrolls to it.
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

  var divider = null;

  function place() {
    var now = israelNow();
    if (divider) { divider.remove(); divider = null; }

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
      var last = null, lastStart = null;
      for (var j = 0; j < groups.length; j++) {
        var start = groupMinutes(groups[j]);
        if (start === null) continue;
        if (start <= mins) { last = groups[j]; lastStart = start; }
      }
      if (!last || mins - lastStart > LATEST_AFTER_LAST_START) continue;

      divider = document.createElement('div');
      divider.className = 'now-divider';
      divider.innerHTML = '<span class="now-divider-time">' + pad(Math.floor(now.minutes / 60) % 24) + ':' + pad(now.minutes % 60) + '</span>';
      last.parentNode.insertBefore(divider, last.nextSibling);
      return;
    }
  }

  place();
  setInterval(place, 30 * 1000);
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('.date-button')) setTimeout(place, 0);
  });

  // clock button: during the festival scroll to the divider (otherwise keep its old behaviour)
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('#now-button');
    if (!btn) return;
    place();
    if (!divider || !divider.offsetParent) return;
    e.preventDefault();
    e.stopPropagation();
    btn.classList.add('is-flash');
    setTimeout(function () { btn.classList.remove('is-flash'); }, 2000);
    var nav = document.querySelector('.navbar-fixed-top');
    var offset = (nav ? nav.getBoundingClientRect().bottom : 0) + 120;
    window.scrollTo({top: divider.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth'});
  }, true);
})();
