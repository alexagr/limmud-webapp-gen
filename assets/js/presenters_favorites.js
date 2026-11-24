/*
 * Presenters page: a ★ on each of a presenter's session cards to add it to "Мой Лимуд"
 * (same favourites as the programme page and the Сетка - localStorage under the event name).
 */
(function () {
  var holder = document.querySelector('[data-favorites-key]');
  if (!holder) return;
  var KEY = holder.getAttribute('data-favorites-key');

  function read() {
    try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; }
  }
  function write(f) {
    try { localStorage.setItem(KEY, JSON.stringify(f)); } catch (e) {}
  }

  function cards() {
    return document.querySelectorAll('a[href^="schedule"] > .speaker-event');
  }
  function idOf(card) {
    return (card.parentNode.getAttribute('href').split('#')[1] || '');
  }

  function render() {
    var f = read();
    var list = cards();
    for (var i = 0; i < list.length; i++) {
      var card = list[i];
      if (!card.querySelector('.event-fav')) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'event-fav';
        b.setAttribute('aria-label', '★');
        b.innerHTML = '<i class="fa fa-star" aria-hidden="true"></i>';
        card.appendChild(b);
      }
      card.classList.toggle('is-fav', f[idOf(card)] == 1);
    }
  }

  // tap on ★ toggles the favourite and does not open the programme page
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.event-fav');
    if (!b) return;
    e.preventDefault();
    e.stopPropagation();
    var id = idOf(b.closest('.speaker-event'));
    if (!id) return;
    var f = read();
    if (f[id] == 1) delete f[id]; else f[id] = 1;
    write(f);
    render();
  }, true);

  window.addEventListener('storage', function (e) { if (e.key === KEY) render(); });
  window.addEventListener('pageshow', render);
  render();
})();
