/*
 * Top search (Программа, Сетка, Презентеры): besides hiding what doesn't match (the page's own filter),
 * highlight the matching text in what stays visible. Case-insensitive, «е» also finds «ё».
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
    if (q.length < 2) return;   // one letter would light up everything
    var re = pattern(q);
    var roots = document.querySelectorAll(SCOPES);
    for (var i = 0; i < roots.length; i++) mark(roots[i], re);
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
