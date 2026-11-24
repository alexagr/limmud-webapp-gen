/*
 * Сетка (calendar) helpers:
 *  - zoom (60%..130%): pinch with two fingers on the grid, Ctrl + mouse wheel, or the -/+ buttons.
 *    Scales the CSS variables --zx (column width) and --zy (time axis) on .calendar-view;
 *    every position in the grid is calc(<px> * var(--zx|--zy)). The level is remembered per device.
 *  - room names stay visible: the header row of each day slides down under the navbar
 *    while that day's grid is scrolled past.
 */
(function () {
  var MIN = 0.6, MAX = 1.3, KEY = 'calendar-zoom';
  var LEVELS = [0.6, 0.7, 0.8, 0.9, 1, 1.1, 1.2, 1.3];   // -/+ buttons step through these
  var view = document.querySelector('.calendar-view');
  if (!view) return;

  function clamp(z) { return Math.min(MAX, Math.max(MIN, z)); }
  function snap(z) { return clamp(Math.round(z * 20) / 20); }   // nearest 5%

  var zoom = 1;
  try { zoom = snap(parseFloat(localStorage.getItem(KEY)) || 1); } catch (e) {}

  function nextLevel(dir) {
    if (dir > 0) {
      for (var i = 0; i < LEVELS.length; i++) if (LEVELS[i] > zoom + 0.001) return LEVELS[i];
      return MAX;
    }
    for (var j = LEVELS.length - 1; j >= 0; j--) if (LEVELS[j] < zoom - 0.001) return LEVELS[j];
    return MIN;
  }

  function apply(z) {
    zoom = clamp(z);
    view.style.setProperty('--zx', zoom);
    view.style.setProperty('--zy', zoom);
    try { localStorage.setItem(KEY, String(zoom)); } catch (e) {}
    var label = document.querySelector('.zoom-level');
    if (label) label.textContent = Math.round(zoom * 100) + '%';
    updateHeaders();
  }

  // zoom around a point on screen (clientX/clientY), keeping the content under it in place
  function zoomAt(newZoom, clientX, clientY, rooms) {
    newZoom = clamp(newZoom);
    if (!rooms || newZoom === zoom) { apply(newZoom); return; }
    var r = rooms.getBoundingClientRect();
    var ratio = newZoom / zoom;
    var cx = rooms.scrollLeft + (clientX - r.left);   // content x under the point
    var cy = clientY - r.top;                          // content y under the point
    apply(newZoom);
    rooms.scrollLeft = cx * ratio - (clientX - r.left);
    window.scrollBy(0, cy * ratio - cy);
    // the page just scrolled: put the room names back exactly under the navbar
    updateHeaders();
    window.requestAnimationFrame(updateHeaders);
  }

  function roomsAt(target) {
    return target && target.closest ? target.closest('.rooms') : null;
  }

  // --- pinch (two fingers) on the grid; one finger keeps normal scrolling ---
  var pinch = null;
  function dist(t) { return Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY); }
  function mid(t) { return {x: (t[0].clientX + t[1].clientX) / 2, y: (t[0].clientY + t[1].clientY) / 2}; }

  view.addEventListener('touchstart', function (e) {
    var rooms = roomsAt(e.target);
    if (e.touches.length === 2 && rooms) {
      pinch = {d: dist(e.touches), z: zoom, rooms: rooms};
      e.preventDefault();
    }
  }, {passive: false});

  view.addEventListener('touchmove', function (e) {
    if (!pinch || e.touches.length !== 2) return;
    e.preventDefault();  // stop the browser from zooming the whole page
    var m = mid(e.touches);
    var target = pinch.z * dist(e.touches) / pinch.d;
    if (Math.abs(target - zoom) > 0.01) zoomAt(target, m.x, m.y, pinch.rooms);
  }, {passive: false});

  view.addEventListener('touchend', function (e) {
    if (pinch && e.touches.length < 2) {
      var p = pinch;
      pinch = null;
      var r = p.rooms.getBoundingClientRect();
      zoomAt(snap(zoom), r.left + r.width / 2, Math.max(r.top, 0) + window.innerHeight / 2, p.rooms);  // settle on a round 5%
    }
  });

  // --- Ctrl + mouse wheel (also trackpad pinch on desktop) ---
  view.addEventListener('wheel', function (e) {
    var rooms = roomsAt(e.target);
    if (!e.ctrlKey || !rooms) return;
    e.preventDefault();
    zoomAt(zoom * Math.exp(-e.deltaY * 0.002), e.clientX, e.clientY, rooms);
    clearTimeout(wheelTimer);
    wheelTimer = setTimeout(function () { zoomAt(snap(zoom), e.clientX, e.clientY, rooms); }, 250);  // settle on a round 5%
  }, {passive: false});
  var wheelTimer;

  // --- -/+ buttons and reset ---
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-zoom]');
    if (!b) return;
    e.preventDefault();
    var z = b.getAttribute('data-zoom');
    apply(z === 'in' ? nextLevel(1) : z === 'out' ? nextLevel(-1) : 1);
  });

  // --- room names stay visible while scrolling down a day ---
  // the room names stick below the menu and the search row that stays visible under it
  function navbarHeight() {
    var nav = document.querySelector('.navbar-fixed-top');
    var bar = document.querySelector('.search-sticky');
    return Math.max(nav ? nav.getBoundingClientRect().bottom : 0, bar ? bar.getBoundingClientRect().bottom : 0);
  }

  function updateHeaders() {
    var top = navbarHeight();
    var all = document.querySelectorAll('.calendar .rooms');
    for (var i = 0; i < all.length; i++) {
      var rooms = all[i];
      var headers = rooms.querySelectorAll('.room-header');
      if (!headers.length || !rooms.offsetParent) continue;   // hidden day
      var r = rooms.getBoundingClientRect();
      var h = headers[0].offsetHeight;
      // where the header row sits without any shift (measured from the row itself, not from the grid box)
      var current = parseFloat((headers[0].style.transform.match(/translateY\(([-\d.]+)px\)/) || [0, 0])[1]);
      var naturalTop = headers[0].getBoundingClientRect().top - current;
      var shift = Math.max(0, Math.min(top - naturalTop, r.bottom - naturalTop - h * 2));
      for (var j = 0; j < headers.length; j++) {
        headers[j].style.transform = shift ? 'translateY(' + shift + 'px)' : '';
        headers[j].classList.toggle('is-stuck', shift > 0);
      }
    }
  }

  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(function () { ticking = false; updateHeaders(); });
  }
  window.addEventListener('scroll', onScroll, {passive: true});
  window.addEventListener('resize', onScroll);
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('.date-button')) setTimeout(updateHeaders, 0);
  });

  // the PNG export should show the grid without shifted headers
  document.addEventListener('click', function (e) {
    if (!(e.target.closest && e.target.closest('.export-png'))) return;
    var headers = document.querySelectorAll('.room-header');
    for (var i = 0; i < headers.length; i++) { headers[i].style.transform = ''; headers[i].classList.remove('is-stuck'); }
    window.removeEventListener('scroll', onScroll);
    setTimeout(function () { window.addEventListener('scroll', onScroll, {passive: true}); updateHeaders(); }, 4000);
  }, true);

  apply(zoom);
})();
