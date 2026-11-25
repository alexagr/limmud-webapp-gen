/*
 * START page: banner that invites phone users to install the app (PWA).
 * - Chrome / Edge / Samsung (Android): the browser says the app can be installed (beforeinstallprompt) ->
 *   banner with an "Install" button that opens the browser's own install dialog.
 * - iPhone / iPad (no install API on iOS): banner with the 2-step "Share -> Add to Home Screen" instruction.
 * - Not shown inside the installed app, not on desktop, and not for 1 day after the user closes it.
 * Language: data-lang on the script tag (ru, he, en, ukr).
 */
(function () {
  var KEY = 'install-banner-closed';
  var HIDE_FOR = 24 * 60 * 60 * 1000;
  var script = document.currentScript;
  var lang = (script && script.getAttribute('data-lang')) || 'ru';
  var icon = script && script.getAttribute('data-icon');

  var SHARE = '<svg class="install-share" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5M7 10H5v11h14V10h-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var T = {
    ru: {title: 'Установите приложение', text: 'Программа всегда под рукой — даже без интернета.', button: 'Установить',
         ios: 'Нажмите ' + SHARE + ' «Поделиться» (или ••• → «Поделиться»), затем «На экран "Домой"».', close: 'Закрыть'},
    he: {title: 'התקינו את האפליקציה', text: 'התוכנית תמיד בהישג יד — גם בלי אינטרנט.', button: 'התקנה',
         ios: 'לחצו על ' + SHARE + ' "שיתוף" (או ••• ← "שיתוף"), ואז "הוספה למסך הבית".', close: 'סגירה'},
    en: {title: 'Install the app', text: 'The programme always at hand — even offline.', button: 'Install',
         ios: 'Tap ' + SHARE + ' "Share" (or ••• → "Share"), then "Add to Home Screen".', close: 'Close'},
    ukr: {title: 'Встановіть застосунок', text: 'Програма завжди під рукою — навіть без інтернету.', button: 'Встановити',
          ios: 'Натисніть ' + SHARE + ' «Поділитися» (або ••• → «Поділитися»), потім «На початковий екран».', close: 'Закрити'}
  }[lang] || null;
  if (!T) return;

  var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
  if (standalone) return;
  try {
    var closed = Number(localStorage.getItem(KEY) || 0);
    if (Date.now() - closed < HIDE_FOR) return;
  } catch (e) {}

  var ua = navigator.userAgent;
  var isIOS = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isPhone = isIOS || /Android|Mobi/.test(ua);
  if (!isPhone) return;

  var banner = null;
  function show(withButton, onInstall) {
    if (banner) return;
    banner = document.createElement('div');
    banner.className = 'install-banner';
    banner.setAttribute('role', 'dialog');
    banner.innerHTML =
      (icon ? '<img class="install-icon" src="' + icon + '" alt="">' : '') +
      '<div class="install-body"><b>' + T.title + '</b><span>' + (withButton ? T.text : T.ios) + '</span>' +
      (withButton ? '<button type="button" class="install-go">' + T.button + '</button>' : '') + '</div>' +
      '<button type="button" class="install-close" aria-label="' + T.close + '">&times;</button>';
    banner.querySelector('.install-close').addEventListener('click', function () {
      try { localStorage.setItem(KEY, String(Date.now())); } catch (e) {}
      hide();
    });
    if (withButton) banner.querySelector('.install-go').addEventListener('click', onInstall);
    var img = banner.querySelector('.install-icon');
    if (img) img.onerror = function () { img.remove(); };
    document.body.appendChild(banner);
    requestAnimationFrame(function () { banner.classList.add('is-visible'); });
  }
  function hide() {
    if (!banner) return;
    banner.classList.remove('is-visible');
    var b = banner;
    banner = null;
    setTimeout(function () { b.remove(); }, 300);
  }

  if (isIOS) {
    // only Safari (and iOS 16.4+ Chrome/Edge, which also have "Add to Home Screen" in the share menu)
    if (/FxiOS|OPiOS|YaBrowser/.test(ua)) return;
    setTimeout(function () { show(false); }, 1500);
    return;
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();   // our banner instead of the browser's mini-infobar
    var deferred = e;
    show(true, function () {
      deferred.prompt();
      deferred.userChoice.then(function () { hide(); });
    });
  });
  window.addEventListener('appinstalled', hide);
})();
