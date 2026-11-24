/*
 * Keeps the search / "Мой Лимуд" row visible under the menu while scrolling (programme page).
 * Sets --nav-h to the real menu height (it differs between phone and desktop) and marks the row
 * as .is-stuck once it sticks, so CSS can add a shadow.
 */
(function () {
  var bar = document.querySelector('.search-sticky');
  if (!bar) return;
  var root = document.documentElement;

  function navBottom() {
    var nav = document.querySelector('.navbar-fixed-top');
    return nav ? Math.round(nav.getBoundingClientRect().bottom) : 0;
  }

  function update() {
    var top = navBottom();
    root.style.setProperty('--nav-h', top + 'px');
    bar.classList.toggle('is-stuck', bar.getBoundingClientRect().top <= top + 1 && window.scrollY > 0);
  }

  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(function () { ticking = false; update(); });
  }, {passive: true});
  window.addEventListener('resize', update);
  update();
})();
