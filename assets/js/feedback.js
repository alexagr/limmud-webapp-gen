/*
 * "Report a problem" (every page): an item in the ☰ menu and a link in the footer open a short form -
 * type, what happened, optional e-mail (for a reply) and phone. Sent to feedback_report.php together with
 * the page / open session, the anonymous device profile and the app version. Without internet the report
 * waits on the phone and is sent when the connection is back.
 */
(function () {
  var me = document.currentScript;
  var APP = me && me.getAttribute('data-app');
  var LANG = (me && me.getAttribute('data-lang')) || 'ru';
  var VERSION = (me && me.getAttribute('data-version')) || '';
  if (!APP || !window.fetch || !document.querySelector) return;

  var T = {
    ru: {link: 'Сообщить о проблеме', title: 'Сообщить о проблеме', type: 'Тема',
         types: {info: 'Ошибка в программе', app: 'Проблема с приложением', registration: 'Запись на сессию', other: 'Другое'},
         text: 'Что случилось?', email: 'E-mail для ответа (не обязательно)', phone: 'Телефон (не обязательно)', send: 'Отправить',
         thanks: 'Спасибо! Сообщение отправлено.', queued: 'Нет связи с интернетом - сообщение отправится, когда связь появится.',
         errText: 'Пожалуйста, опишите проблему.', errEmail: 'Пожалуйста, проверьте e-mail.', errPhone: 'Пожалуйста, проверьте номер телефона.',
         errBusy: 'Слишком много сообщений. Попробуйте позже.', errOther: 'Не удалось отправить. Попробуйте ещё раз.', close: 'Закрыть'},
    ukr: {link: 'Повідомити про проблему', title: 'Повідомити про проблему', type: 'Тема',
          types: {info: 'Помилка в програмі', app: 'Проблема із застосунком', registration: 'Запис на сесію', other: 'Інше'},
          text: 'Що сталося?', email: "E-mail для відповіді (не обов'язково)", phone: "Телефон (не обов'язково)", send: 'Надіслати',
          thanks: 'Дякуємо! Повідомлення надіслано.', queued: "Немає зв'язку з інтернетом - повідомлення буде надіслано, коли зв'язок з'явиться.",
          errText: 'Будь ласка, опишіть проблему.', errEmail: 'Будь ласка, перевірте e-mail.', errPhone: 'Будь ласка, перевірте номер телефону.',
          errBusy: 'Забагато повідомлень. Спробуйте пізніше.', errOther: 'Не вдалося надіслати. Спробуйте ще раз.', close: 'Закрити'},
    en: {link: 'Report a problem', title: 'Report a problem', type: 'Topic',
         types: {info: 'Wrong programme info', app: 'App problem', registration: 'Session registration', other: 'Other'},
         text: 'What happened?', email: 'E-mail for a reply (optional)', phone: 'Phone (optional)', send: 'Send',
         thanks: 'Thank you! Your message was sent.', queued: 'No internet connection - the message will be sent when you are back online.',
         errText: 'Please describe the problem.', errEmail: 'Please check the e-mail.', errPhone: 'Please check the phone number.',
         errBusy: 'Too many messages. Please try later.', errOther: 'Could not send. Please try again.', close: 'Close'},
    he: {link: 'דיווח על בעיה', title: 'דיווח על בעיה', type: 'נושא',
         types: {info: 'טעות בתוכנית', app: 'בעיה באפליקציה', registration: 'הרשמה למפגש', other: 'אחר'},
         text: 'מה קרה?', email: 'דוא״ל לתשובה (לא חובה)', phone: 'טלפון (לא חובה)', send: 'שליחה',
         thanks: 'תודה! ההודעה נשלחה.', queued: 'אין חיבור לאינטרנט - ההודעה תישלח כשהחיבור יחזור.',
         errText: 'נא לתאר את הבעיה.', errEmail: 'נא לבדוק את כתובת הדוא״ל.', errPhone: 'נא לבדוק את מספר הטלפון.',
         errBusy: 'יותר מדי הודעות. נסו שוב מאוחר יותר.', errOther: 'השליחה נכשלה. נסו שוב.', close: 'סגירה'}
  }[LANG];
  if (!T) return;

  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; });
  }
  // the same anonymous id as the usage stats and registration
  function deviceId() {
    var id = get('stats-device-id');
    if (!id) {
      id = '';
      var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
      var rnd = (window.crypto && crypto.getRandomValues) ? crypto.getRandomValues(new Uint8Array(20)) : null;
      for (var i = 0; i < 20; i++) id += chars[(rnd ? rnd[i] : Math.floor(Math.random() * 256)) % chars.length];
      set('stats-device-id', id);
    }
    return id;
  }
  // rough device profile (as in stats_report.js)
  function info() {
    var ua = navigator.userAgent;
    var iPad = /iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var os = iPad ? 'ipad' : /iPhone|iPod/.test(ua) ? 'iphone' : /Android/.test(ua) ? 'android'
           : /Windows/.test(ua) ? 'windows' : /Macintosh/.test(ua) ? 'mac' : /Linux|CrOS/.test(ua) ? 'linux' : 'other';
    var br = /SamsungBrowser/.test(ua) ? 'samsung' : /Edg\//.test(ua) ? 'edge' : /Firefox|FxiOS/.test(ua) ? 'firefox'
           : /YaBrowser/.test(ua) ? 'yandex' : /OPR\/|Opera/.test(ua) ? 'opera' : /Chrome|CriOS/.test(ua) ? 'chrome'
           : /Safari/.test(ua) ? 'safari' : 'other';
    var app = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
    return {os: os, br: br, mode: app ? 'app' : 'browser', lang: LANG};
  }
  // what the person was looking at: an open session card (Сетка) or an expanded session (Программа)
  function currentSession() {
    var m = document.querySelector('.session-modal.is-open .session-modal-title');
    if (m) return m.textContent.trim();
    var open = document.querySelector('.session-desc.in');
    if (open) {
      var t = document.getElementById('title-' + open.id.replace(/^desc-/, ''));
      var h = t && t.querySelector('h4');
      if (h) return h.textContent.trim();
    }
    return '';
  }

  function post(body) {
    body.app = APP;
    body.device = deviceId();
    return fetch('../feedback_report.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body), cache: 'no-store'
    }).then(function (r) { if (!r.ok && r.status !== 500) throw new Error('http'); return r.json(); });
  }
  function send(report) {
    return post({action: 'token'}).then(function (t) {
      var b = JSON.parse(JSON.stringify(report));
      b.action = 'send';
      b.token = t.token;
      return post(b);
    });
  }

  // reports written offline: sent when the connection is back (oldest first)
  function queue() { try { return JSON.parse(get('feedback-queue') || '[]') || []; } catch (e) { return []; } }
  function flush() {
    var q = queue();
    if (!q.length || navigator.onLine === false) return;
    send(q[0]).then(function (r) {
      if (r && (r.ok || r.error === 'text' || r.error === 'email' || r.error === 'phone')) {   // done, or will never pass
        q.shift();
        set('feedback-queue', JSON.stringify(q));
        if (q.length) setTimeout(flush, 1000);
      }
    }).catch(function () {});
  }

  // ---- dialog (styles shared with registration: .reg-dialog) ----
  var dlg = null;
  function close() {
    if (dlg) { dlg.remove(); dlg = null; }
    document.body.classList.remove('reg-dialog-open');
  }
  function dialog(html) {
    close();
    dlg = document.createElement('div');
    dlg.className = 'reg-dialog';
    dlg.innerHTML = '<div class="reg-dialog-card" role="dialog" aria-modal="true">' +
      '<button type="button" class="reg-dialog-close" aria-label="' + T.close + '">&times;</button>' + html + '</div>';
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg || e.target.closest('.reg-dialog-close') || e.target.closest('.reg-dialog-ok')) close();
    });
    document.body.appendChild(dlg);
    document.body.classList.add('reg-dialog-open');
    return dlg;
  }
  function done(text) {
    dialog('<div class="reg-dialog-text"><p class="reg-ok"><i class="fa fa-check-circle"></i> ' + esc(text) + '</p></div>' +
           '<div class="reg-dialog-buttons"><button type="button" class="reg-dialog-ok reg-primary">OK</button></div>');
  }

  function open(e) {
    if (e) e.preventDefault();
    var session = currentSession();
    var page = (location.pathname.split('/').pop() || 'index.html') + location.hash;
    var saved = {};
    try { saved = JSON.parse(get('feedback-contact') || '{}') || {}; } catch (err) {}
    var opts = '';
    for (var k in T.types) opts += '<option value="' + k + '">' + esc(T.types[k]) + '</option>';
    var d = dialog(
      '<h4>' + esc(T.title) + '</h4>' +
      (session ? '<div class="reg-dialog-session">' + esc(session) + '</div>' : '') +
      '<form class="reg-form" novalidate>' +
      '<label>' + esc(T.type) + '<select name="type" class="feedback-type">' + opts + '</select></label>' +
      '<label>' + esc(T.text) + '<textarea name="text" rows="5" maxlength="3000" class="feedback-text"></textarea></label>' +
      '<label>' + esc(T.email) + '<input name="email" type="email" autocomplete="email" maxlength="120" dir="ltr" value="' + esc(saved.email || '') + '"></label>' +
      '<label>' + esc(T.phone) + '<input name="phone" type="tel" autocomplete="tel" maxlength="20" dir="ltr" value="' + esc(saved.phone || '') + '"></label>' +
      '<div class="reg-form-error" aria-live="polite"></div>' +
      '<div class="reg-dialog-buttons"><button type="submit" class="reg-primary">' + esc(T.send) + '</button></div>' +
      '</form>');
    var form = d.querySelector('form');
    var F = function (n) { return form.querySelector('[name=' + n + ']'); };
    F('text').focus();
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var err = form.querySelector('.reg-form-error');
      var r = {type: F('type').value, text: F('text').value.trim(), email: F('email').value.trim(), phone: F('phone').value.trim(),
               page: page, session: session, info: info(), version: VERSION, lang: LANG};
      err.textContent = '';
      if (r.text.length < 3) { err.textContent = T.errText; F('text').focus(); return; }
      if (r.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(r.email)) { err.textContent = T.errEmail; F('email').focus(); return; }
      if (r.phone && r.phone.replace(/\D/g, '').length < 9) { err.textContent = T.errPhone; F('phone').focus(); return; }
      set('feedback-contact', JSON.stringify({email: r.email, phone: r.phone}));
      var btn = form.querySelector('button[type=submit]');
      btn.disabled = true;
      var later = function () {
        var q = queue();
        q.push(r);
        set('feedback-queue', JSON.stringify(q.slice(-10)));
        done(T.queued);
      };
      if (navigator.onLine === false) { later(); return; }
      send(r).then(function (res) {
        if (res && res.ok) { done(T.thanks); return; }
        btn.disabled = false;
        err.textContent = res && res.error === 'busy' ? T.errBusy : res && res.error === 'email' ? T.errEmail
                        : res && res.error === 'phone' ? T.errPhone : res && res.error === 'text' ? T.errText : T.errOther;
      }).catch(later);
    });
  }

  function init() {
    // ☰ menu (phones): right below the "update" button at the bottom of the menu
    var reload = document.getElementById('version-refresh');
    if (reload && !document.getElementById('feedbacklink')) {
      var m = document.createElement('div');
      m.className = 'feedback-menu';
      m.innerHTML = '<a id="feedbacklink" href="#"><i class="fa fa-comment-dots" aria-hidden="true"></i>&nbsp;' + esc(T.link) + '</a>';
      reload.parentNode.insertBefore(m, reload.nextSibling);
      m.querySelector('a').addEventListener('click', open);
    }
    // footer link
    var foot = document.querySelector('.footer-container footer .container');
    if (foot && !document.querySelector('.feedback-footer')) {
      var p = document.createElement('div');
      p.className = 'feedback-footer';
      p.innerHTML = '<a href="#"><i class="fa fa-comment-dots" aria-hidden="true"></i> ' + esc(T.link) + '</a>';
      foot.appendChild(p);
      p.querySelector('a').addEventListener('click', open);
    }
    flush();
  }
  window.addEventListener('online', flush);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dlg) close(); });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
