/*
 * Number of marked (★) sessions on the "Мой Лимуд" buttons (programme page and Сетка).
 * Favourites are stored in localStorage under the event name (data-favorites-key on the button).
 */
(function () {
  var buttons = document.querySelectorAll('[data-favorites-key]');
  if (!buttons.length) return;
  var KEY = buttons[0].getAttribute('data-favorites-key');

  function count() {
    var n = 0;
    try {
      var f = JSON.parse(localStorage.getItem(KEY) || '{}') || {};
      for (var k in f) if (f[k] == 1) n++;
    } catch (e) {}
    return n;
  }

  function update() {
    var n = count();
    for (var i = 0; i < buttons.length; i++) {
      var b = buttons[i];
      var badge = b.querySelector('.fav-count');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'fav-count';
        b.appendChild(badge);
      }
      badge.textContent = n;
      badge.style.display = n ? '' : 'none';
    }
    // "Сохранить закладки" row on the programme page: only when something is marked
    var save = document.getElementById('save-bookmarks');
    if (save) save.style.display = n ? '' : 'none';
  }

  // a ★ was tapped somewhere on the page - the page's own handler saves first, then we recount
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('.bookmark, .grid-fav')) setTimeout(update, 0);
  }, true);  // capture: also works if the page's own handler stops the click
  window.addEventListener('storage', function (e) { if (e.key === KEY) update(); });
  window.addEventListener('pageshow', update);
  update();
})();
