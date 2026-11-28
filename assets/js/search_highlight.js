/*
 * Top search (Программа, Сетка, Презентеры): besides hiding what doesn't match (the page's own filter),
 * highlight the matching text in what stays visible. Case-insensitive, «е» also finds «ё».
 * A box with data-find (Программа, Сетка) does not hide anything (Сетка only dims, calendar_extras.js): it highlights and jumps from match to match
 * like the bottom-left search - counter "2/5" and a "next" button at the end of the box, Enter = next.
 */
(function () {
  var SCOPES = '.event, .speaker-name, .calendar-view .session .find-target';
  var input = null;
  var timer = null;

  function inputs() { return document.querySelectorAll('.search-filter-input'); }
  function current() {
    // the page uses the visible box (it may have several copies)
    var all = inputs();
    for (var i = 0; i < all.length; i++) if (all[i].offsetParent) return all[i].value;
    return all.length ? all[0].value : '';
  }

  function clear() {
    var marks = document.querySelectorAll('mark.search-hit');
    for (var i = 0; i < marks.length; i++) {
      var m = marks[i], p = m.parentNode;
      p.replaceChild(document.createTextNode(m.textContent), m);
      p.normalize();
    }
  }

  function pattern(q) {
    var out = '';
    for (var i = 0; i < q.length; i++) {
      var c = q[i].toLowerCase();
      if (c === 'е' || c === 'ё') out += '[её]';
      else if (/\s/.test(c)) out += '\\s+';
      else out += c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    return new RegExp(out, 'gi');
  }

  function mark(root, re) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (n) {
        var p = n.parentNode;
        if (!n.nodeValue.trim() || /^(SCRIPT|STYLE|TEXTAREA|INPUT|MARK)$/.test(p.nodeName)) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      }
    });
    var nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(function (n) {
      var text = n.nodeValue;
      re.lastIndex = 0;
      if (!re.test(text)) return;
      re.lastIndex = 0;
      var frag = document.createDocumentFragment(), last = 0, m;
      while ((m = re.exec(text)) !== null) {
        if (!m[0]) { re.lastIndex++; continue; }
        frag.appendChild(document.createTextNode(text.slice(last, m.index)));
        var mk = document.createElement('mark');
        mk.className = 'search-hit';
        mk.textContent = m[0];
        frag.appendChild(mk);
        last = m.index + m[0].length;
      }
      frag.appendChild(document.createTextNode(text.slice(last)));
      n.parentNode.replaceChild(frag, n);
    });
  }

  function run() {
    clear();
    var q = current().trim();
    if (q.length >= 2) {   // one letter would light up everything
      var re = pattern(q);
      var roots = document.querySelectorAll(SCOPES);
      for (var i = 0; i < roots.length; i++) mark(roots[i], re);
    }
    findUpdate(q);
  }

  // ---- find mode (data-find): matches = visible cards with highlighted text, in page order
  var hits = [], pos = -1, lastQ = null;
  function findInput() {
    var all = inputs();
    for (var i = 0; i < all.length; i++) if (all[i].hasAttribute('data-find') && all[i].offsetParent) return all[i];
    return null;
  }
  function findUpdate(q) {
    var inp = findInput();
    if (!inp) return;
    var marks = document.querySelectorAll('mark.search-hit');
    hits = [];
    for (var i = 0; i < marks.length; i++) {
      if (!marks[i].offsetParent) continue;   // other day / not in My Limmud
      var card = marks[i].closest('.event, .calendar-view .session') || marks[i];
      if (!hits.length || hits[hits.length - 1].card !== card) hits.push({card: card, mark: marks[i]});
    }
    var changed = q !== lastQ;
    lastQ = q;
    if (changed || pos >= hits.length) pos = hits.length ? 0 : -1;
    show(inp, q, changed);
  }
  function show(inp, q, jump) {
    var ui = findUi(inp);
    var cur = document.querySelectorAll('mark.search-hit.is-current');
    for (var i = 0; i < cur.length; i++) cur[i].classList.remove('is-current');
    ui.box.classList.toggle('is-visible', q.length >= 2);
    inp.parentNode.classList.toggle('has-find', q.length >= 2);
    ui.count.textContent = hits.length ? (pos + 1) + '/' + hits.length : '0';
    ui.next.disabled = hits.length < 2;
    ui.place();
    if (pos < 0) return;
    var h = hits[pos];
    var ms = h.card.querySelectorAll('mark.search-hit');
    for (var j = 0; j < ms.length; j++) ms[j].classList.add('is-current');
    if (jump) scrollTo(h.mark);
  }
  function next() {
    if (!hits.length) return;
    pos = (pos + 1) % hits.length;
    show(findInput(), current().trim(), true);
  }
  // the match goes into the really visible part: below the menu / sticky search row, above the keyboard
  function scrollTo(target) {
    var cover = 0;
    var els = document.querySelectorAll('.navbar, .search-sticky');
    for (var i = 0; i < els.length; i++) {
      var cs = getComputedStyle(els[i]);
      if (cs.position === 'fixed' || cs.position === 'sticky') cover = Math.max(cover, (parseFloat(cs.top) || 0) + els[i].offsetHeight);
    }
    // Сетка: the grid also scrolls sideways - bring the match into view horizontally first
    // (by hand: scrollIntoView on a phone with the keyboard open also pans the visible area and the
    // sticky search row goes off screen)
    for (var sc = target.parentElement; sc && sc !== document.body; sc = sc.parentElement) {
      if (sc.scrollWidth > sc.clientWidth + 1 && /auto|scroll/.test(getComputedStyle(sc).overflowX)) {
        var sr = sc.getBoundingClientRect(), tr = target.getBoundingClientRect();
        if (tr.left < sr.left || tr.right > sr.right) sc.scrollLeft += tr.left - sr.left - (sr.width - tr.width) / 2;
        break;
      }
    }
    var vh = window.visualViewport ? window.visualViewport.height : window.innerHeight;
    var y = target.getBoundingClientRect().top + window.pageYOffset - cover - Math.max(20, (vh - cover) * 0.3);
    y = Math.max(0, y);
    // fixed short animation even for far jumps (native smooth scrolling is slow over a long page)
    if (window.jQuery) jQuery('html, body').stop().animate({scrollTop: y}, 400);
    else window.scrollTo(0, y);
  }
  function findUi(inp) {
    if (inp._find) return inp._find;
    var holder = inp.parentNode;
    holder.style.position = 'relative';
    var box = document.createElement('span');
    box.className = 'search-find';
    // drawn arrow (like the ×): a font icon never sits exactly in the middle of the circle
    box.innerHTML = '<span class="search-find-count"></span><button type="button" class="search-find-next" aria-label="Next"><svg viewBox="0 0 24 24" aria-hidden="true">' +
      '<path d="M12 6.5v10M7.5 12.5l4.5 4.5 4.5-4.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
    holder.appendChild(box);
    var ui = {box: box, count: box.firstChild, next: box.lastChild};
    // keep the keyboard open: "next" is pressed while typing
    ui.next.addEventListener('mousedown', function (e) { e.preventDefault(); });
    ui.next.addEventListener('click', next);
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); next(); }
    });
    ui.place = function () {   // at the end of the box (the × is where the magnifier was)
      var h = holder.getBoundingClientRect(), r = inp.getBoundingClientRect();
      var rtl = getComputedStyle(inp).direction === 'rtl';
      box.style.top = (r.top - h.top + r.height / 2) + 'px';
      box.style.right = rtl ? 'auto' : (h.right - r.right + 6) + 'px';
      box.style.left = rtl ? (r.left - h.left + 6) + 'px' : 'auto';
    };
    window.addEventListener('resize', ui.place);
    inp._find = ui;
    return ui;
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(run, 150);   // after the page's own filter, and not on every key while typing fast
  }

  function init() {
    var all = inputs();
    for (var i = 0; i < all.length; i++) {
      ['input', 'keyup', 'change'].forEach(function (t) { all[i].addEventListener(t, schedule); });
    }
    if (current()) schedule();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
