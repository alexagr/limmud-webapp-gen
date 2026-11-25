/*
 * × button inside the search boxes (Программа, Сетка, Презентеры): shown while there is text,
 * one tap empties the box and shows everything again (the page's own filter runs as if the user typed).
 */
(function () {
  function setup(input) {
    if (input.getAttribute('data-clear')) return;
    input.setAttribute('data-clear', '1');
    var holder = input.parentNode;
    holder.classList.add('search-clear-holder');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'search-clear';
    btn.setAttribute('aria-label', 'Clear');
    // drawn cross: a text "×" never sits exactly in the middle of the circle
    btn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8l8 8M16 8l-8 8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>';
    holder.appendChild(btn);

    function update() {
      btn.classList.toggle('is-visible', input.value !== '');
      place();
    }
    // inside the box at the end opposite the magnifier (right; left in Hebrew), measured from the box
    // itself - the box can be narrower than its container (Презентеры)
    function place() {
      var h = holder.getBoundingClientRect(), r = input.getBoundingClientRect();
      var rtl = getComputedStyle(input).direction === 'rtl';
      btn.style.top = (r.top - h.top + r.height / 2) + 'px';
      btn.style.right = rtl ? 'auto' : (h.right - r.right + 6) + 'px';
      btn.style.left = rtl ? (r.left - h.left + 6) + 'px' : 'auto';
    }
    window.addEventListener('resize', place);
    input.addEventListener('input', update);
    input.addEventListener('keyup', update);
    input.addEventListener('change', update);
    btn.addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep the keyboard open
    btn.addEventListener('click', function () {
      var anchor = topCard();
      input.value = '';
      ['input', 'keyup', 'change'].forEach(function (t) {
        input.dispatchEvent(new Event(t, {bubbles: true}));
      });
      update();
      input.focus({preventScroll: true});
      // the cards that come back push the one you were reading down: scroll so it stays where it was
      if (anchor) {
        var keep = function () {
          var shift = anchor.el.getBoundingClientRect().top - anchor.top;
          if (Math.abs(shift) > 1) window.scrollBy(0, shift);
        };
        keep();
        setTimeout(keep, 50);
        setTimeout(keep, 300);
      }
    });
    update();
    setTimeout(update, 800);   // the page may restore a previous search after loading
  }
  // the card you are reading: the one closest to the middle of the visible area (under the sticky search row)
  var CARDS = '.event, .speaker-inline, .calendar-view .session';
  function topCard() {
    var sticky = document.querySelector('.search-sticky');
    var line = sticky ? Math.max(0, sticky.getBoundingClientRect().bottom) : 0;
    var mid = (line + window.innerHeight) / 2;
    var best = null, bestDist = Infinity;
    var all = document.querySelectorAll(CARDS);
    for (var i = 0; i < all.length; i++) {
      var el = all[i];
      if (!el.offsetParent) continue;
      var r = el.getBoundingClientRect();
      if (r.bottom <= line || r.top >= window.innerHeight || r.height === 0) continue;
      var d = r.top <= mid && r.bottom >= mid ? 0 : Math.min(Math.abs(r.top - mid), Math.abs(r.bottom - mid));
      if (d < bestDist) { bestDist = d; best = {el: el, top: r.top}; }
    }
    return best;
  }
  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.search-filter-input'), setup);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
