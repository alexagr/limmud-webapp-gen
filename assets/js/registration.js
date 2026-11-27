/*
 * Registration in advance for sessions with limited seats (reg-seats / reg-start in the sheet; server: registration.php).
 * Each .reg-block (session details on Программа, and the Сетка session card that copies them) gets:
 *   - phones: the device's own registrations (with "cancel" until 1 hour before the start) and a button:
 *     "Записаться" (before the opening it only tells when registration opens), "... в лист ожидания", or "Мест нет";
 *   - computers: nothing more than the "(20 мест, по предварительной записи)" line - registration is from phones only.
 * A device can register up to 3 people per session (e.g. parents). The device is the anonymous id of the usage stats.
 */
(function () {
  var me = document.currentScript;
  var APP = me && me.getAttribute('data-app');
  var LANG = (me && me.getAttribute('data-lang')) || 'ru';
  if (!APP || !window.fetch || !document.querySelector) return;

  var ua = navigator.userAgent;
  var isPhone = /iPhone|iPad|iPod|Android|Mobi/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (!isPhone) return;

  var T = {
    ru: {
      register: 'Записаться', another: 'Записать ещё одного человека', waitlist: 'Записаться в лист ожидания',
      anotherWait: 'Ещё одного - в лист ожидания', full: 'Мест нет', closed: 'Запись закрыта',
      you: 'записан(а)', waitPos: 'лист ожидания, №', cancel: 'Отменить', late: 'Отменить запись теперь можно только на стойке HelpDesk',
      soon: 'Запись откроется %d в %t.', title: 'Запись на сессию', titleWait: 'Запись в лист ожидания',
      waitNote: 'Все места заняты. Вы попадёте в лист ожидания - если кто-то отменит запись или не придёт вовремя, вы сможете участвовать.',
      name: 'Имя и фамилия', phone: 'Телефон', email: 'E-mail', send: 'Записаться', close: 'Закрыть',
      okConfirmed: 'Ваша запись принята!', okWaiting: 'Вы в листе ожидания, №%n.', gone: 'Пока вы заполняли форму, свободные места закончились.',
      okWaitingMore: 'Если кто-то отменит запись или не придёт вовремя, вы сможете участвовать. Мы сообщим, когда для вас освободится место.',
      mailSent: 'Подтверждение отправлено на %e.',
      cancelQ: 'Отменить запись: %n?', cancelYes: 'Да, отменить', cancelNo: 'Нет', cancelled: 'Запись отменена.',
      promoted: 'Для вас освободилось место! Запись подтверждена:',
      soonStart: 'Через %m мин. начинается:', started: 'Уже началось:',
      offline: 'Нет связи с интернетом. Попробуйте ещё раз, когда связь появится.',
      err: {
        invalid_name: 'Пожалуйста, укажите имя и фамилию.', invalid_phone: 'Пожалуйста, проверьте номер телефона.',
        invalid_email: 'Пожалуйста, проверьте e-mail.', already: 'Этот человек уже записан на эту сессию.',
        overlap: 'Нельзя записаться на две сессии в одно время. Уже есть запись на «%x».',
        category: 'Можно записаться не более чем на %l сессии категории «%c».',
        full: 'К сожалению, мест больше нет.', device: 'С одного телефона можно записать не более %x человек на сессию.',
        soon: 'Запись ещё не открылась.', closed: 'Запись на эту сессию закрыта.', late: 'Отменить запись теперь можно только на стойке HelpDesk.',
        desktop: 'Запись - только с мобильного телефона.', other: 'Что-то пошло не так. Попробуйте ещё раз или обратитесь на стойку HelpDesk.'
      }
    },
    ukr: {
      register: 'Записатися', another: 'Записати ще одну людину', waitlist: 'Записатися до списку очікування',
      anotherWait: 'Ще одну - до списку очікування', full: 'Місць немає', closed: 'Запис закрито',
      you: 'записано', waitPos: 'список очікування, №', cancel: 'Скасувати', late: 'Скасувати запис тепер можна лише на стійці HelpDesk',
      soon: 'Запис відкриється %d о %t.', title: 'Запис на сесію', titleWait: 'Запис до списку очікування',
      waitNote: 'Усі місця зайняті. Ви потрапите до списку очікування - якщо хтось скасує запис або не прийде вчасно, ви зможете взяти участь.',
      name: "Ім'я та прізвище", phone: 'Телефон', email: 'E-mail', send: 'Записатися', close: 'Закрити',
      okConfirmed: 'Ваш запис прийнято!', okWaiting: 'Ви у списку очікування, №%n.', gone: 'Поки ви заповнювали форму, вільні місця закінчилися.',
      okWaitingMore: 'Якщо хтось скасує запис або не прийде вчасно, ви зможете взяти участь. Ми повідомимо, коли для вас звільниться місце.',
      mailSent: 'Підтвердження надіслано на %e.',
      cancelQ: 'Скасувати запис: %n?', cancelYes: 'Так, скасувати', cancelNo: 'Ні', cancelled: 'Запис скасовано.',
      promoted: 'Для вас звільнилося місце! Запис підтверджено:',
      soonStart: 'За %m хв. починається:', started: 'Вже почалося:',
      offline: "Немає зв'язку з інтернетом. Спробуйте ще раз, коли зв'язок з'явиться.",
      err: {
        invalid_name: "Будь ласка, вкажіть ім'я та прізвище.", invalid_phone: 'Будь ласка, перевірте номер телефону.',
        invalid_email: 'Будь ласка, перевірте e-mail.', already: 'Цю людину вже записано на цю сесію.',
        overlap: 'Не можна записатися на дві сесії одночасно. Вже є запис на «%x».',
        category: 'Можна записатися не більше ніж на %l сесії категорії «%c».',
        full: 'На жаль, місць більше немає.', device: 'З одного телефону можна записати не більше %x людей на сесію.',
        soon: 'Запис ще не відкрився.', closed: 'Запис на цю сесію закрито.', late: 'Скасувати запис тепер можна лише на стійці HelpDesk.',
        desktop: 'Запис - лише з мобільного телефону.', other: 'Щось пішло не так. Спробуйте ще раз або зверніться на стійку HelpDesk.'
      }
    },
    en: {
      register: 'Register', another: 'Register one more person', waitlist: 'Join the waiting list',
      anotherWait: 'One more - to the waiting list', full: 'No seats left', closed: 'Registration closed',
      you: 'registered', waitPos: 'waiting list, #', cancel: 'Cancel', late: 'Registration can now be cancelled only at the HelpDesk',
      soon: 'Registration opens on %d at %t.', title: 'Registration', titleWait: 'Waiting list',
      waitNote: 'All seats are taken. You will be on the waiting list - if someone cancels or does not arrive in time, you will be able to take part.',
      name: 'First and last name', phone: 'Phone', email: 'E-mail', send: 'Register', close: 'Close',
      okConfirmed: 'You are registered!', okWaiting: 'You are on the waiting list, #%n.', gone: 'While you were filling in the form, the last seats were taken.',
      okWaitingMore: 'If someone cancels or does not arrive in time, you will be able to take part. We will let you know when a seat is free for you.',
      mailSent: 'A confirmation was sent to %e.',
      cancelQ: 'Cancel the registration of %n?', cancelYes: 'Yes, cancel', cancelNo: 'No', cancelled: 'Registration cancelled.',
      promoted: 'A seat is now free for you! Registration confirmed:',
      soonStart: 'Starts in %m min:', started: 'Has just started:',
      offline: 'No internet connection. Please try again when you are back online.',
      err: {
        invalid_name: 'Please enter the first and last name.', invalid_phone: 'Please check the phone number.',
        invalid_email: 'Please check the e-mail.', already: 'This person is already registered for this session.',
        overlap: 'You cannot register for two sessions at the same time. Already registered for "%x".',
        category: 'You can register for at most %l sessions of the category "%c".',
        full: 'Sorry, no seats are left.', device: 'One phone can register at most %x people per session.',
        soon: 'Registration has not opened yet.', closed: 'Registration for this session is closed.', late: 'Registration can now be cancelled only at the HelpDesk.',
        desktop: 'Registration is from mobile phones only.', other: 'Something went wrong. Please try again or come to the HelpDesk.'
      }
    },
    he: {
      register: 'הרשמה', another: 'רישום אדם נוסף', waitlist: 'הרשמה לרשימת ההמתנה',
      anotherWait: 'אדם נוסף - לרשימת ההמתנה', full: 'אין מקומות', closed: 'ההרשמה סגורה',
      you: 'רשום/ה', waitPos: 'רשימת המתנה, מס׳ ', cancel: 'ביטול', late: 'כעת ניתן לבטל את ההרשמה רק בעמדת ה-HelpDesk',
      soon: 'ההרשמה תיפתח ב-%d בשעה %t.', title: 'הרשמה למפגש', titleWait: 'הרשמה לרשימת ההמתנה',
      waitNote: 'כל המקומות תפוסים. תירשמו לרשימת ההמתנה - אם מישהו יבטל או לא יגיע בזמן, תוכלו להשתתף.',
      name: 'שם פרטי ומשפחה', phone: 'טלפון', email: 'דוא״ל', send: 'הרשמה', close: 'סגירה',
      okConfirmed: 'ההרשמה התקבלה!', okWaiting: 'נרשמתם לרשימת ההמתנה, מס׳ %n.', gone: 'בזמן שמילאתם את הטופס, המקומות הפנויים נגמרו.',
      okWaitingMore: 'אם מישהו יבטל או לא יגיע בזמן, תוכלו להשתתף. נודיע לכם כשיתפנה מקום.',
      mailSent: 'אישור נשלח אל %e.',
      cancelQ: 'לבטל את ההרשמה של %n?', cancelYes: 'כן, לבטל', cancelNo: 'לא', cancelled: 'ההרשמה בוטלה.',
      promoted: 'התפנה עבורך מקום! ההרשמה אושרה:',
      soonStart: 'מתחיל בעוד %m דק׳:', started: 'כבר התחיל:',
      offline: 'אין חיבור לאינטרנט. נסו שוב כשהחיבור יחזור.',
      err: {
        invalid_name: 'נא למלא שם פרטי ומשפחה.', invalid_phone: 'נא לבדוק את מספר הטלפון.',
        invalid_email: 'נא לבדוק את כתובת הדוא״ל.', already: 'האדם הזה כבר רשום למפגש.',
        overlap: 'אי אפשר להירשם לשני מפגשים באותו זמן. כבר קיימת הרשמה ל"%x".',
        category: 'ניתן להירשם לכל היותר ל-%l מפגשים בקטגוריה "%c".',
        full: 'מצטערים, אין יותר מקומות.', device: 'מטלפון אחד ניתן לרשום עד %x אנשים למפגש.',
        soon: 'ההרשמה עוד לא נפתחה.', closed: 'ההרשמה למפגש סגורה.', late: 'כעת ניתן לבטל את ההרשמה רק בעמדת ה-HelpDesk.',
        desktop: 'ההרשמה - מטלפון נייד בלבד.', other: 'משהו השתבש. נסו שוב או פנו לעמדת ה-HelpDesk.'
      }
    }
  }[LANG] || null;
  if (!T) return;
  var LOCALE = {ru: 'ru-RU', ukr: 'uk-UA', en: 'en-GB', he: 'he-IL'}[LANG];

  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  // the same anonymous id as the usage stats (assets/js/stats_report.js)
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
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; });
  }

  var state = null;       // last reply of registration.php
  var loading = null;

  function call(body) {
    body.app = APP;
    body.device = deviceId();
    return fetch('../registration.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body), cache: 'no-store'
    }).then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); });
  }

  function refresh() {
    // pages without registration blocks: only for devices that registered (to tell them about a seat from the waiting list)
    if (!document.querySelector('.reg-block') && get('reg-used') !== '1') return Promise.resolve();
    if (loading) return loading;
    loading = call({action: 'status'}).then(function (st) {
      if (st && st.ok) { setState(st); if (st.mine.length) set('reg-used', '1'); renderAll(); announcePromotions(); remind(); }
    }).catch(function () { renderAll(); }).then(function () { loading = null; });
    return loading;
  }

  function setState(st) {
    state = st;
    try { localStorage.setItem('reg-state', JSON.stringify(st)); } catch (e) {}
  }
  function cachedState() {
    try { return JSON.parse(get('reg-state') || 'null'); } catch (e) { return null; }
  }

  // reminder in the app: once per registration, only from 15 minutes before the start until 5 minutes after it
  // (uses the last known status, so it also works without internet)
  function remind() {
    var st = state || cachedState();
    if (!st || !st.mine || !st.mine.length || dlg) return;
    var now = Date.now(), items = [], ids = [];
    st.mine.forEach(function (m) {
      var s = st.sessions[m.session];
      if (m.status !== 'confirmed' || !s || !s.start || get('reg-reminded:' + m.id)) return;
      var start = new Date(s.start).getTime();
      if (now < start - 15 * 60000 || now > start + 5 * 60000) return;
      ids.push(m.id);
      var key = String(m.session);
      var it = items.filter(function (x) { return x.key === key; })[0];
      if (!it) {
        it = {key: key, start: start, title: (LANG === 'he' && s.title2) ? s.title2 : s.title, room: s.room, names: []};
        items.push(it);
      }
      it.names.push(m.name);
    });
    if (!items.length) return;
    ids.forEach(function (id) { set('reg-reminded:' + id, '1'); });
    message('', items.map(function (it) {
      var min = Math.ceil((it.start - now) / 60000);
      var head = min > 0 ? T.soonStart.replace('%m', min) : T.started;
      return '<p class="reg-ok"><i class="fa fa-bell"></i> ' + esc(head) + '</p><p><b>' + esc(it.title) + '</b>' +
             (it.room ? '<br><i class="fa fa-map-marker-alt"></i> ' + esc(it.room) : '') + '<br>' + esc(it.names.join(', ')) + '</p>';
    }).join(''));
  }

  function sessionTitle(sid) {
    var h = document.querySelector('#title-' + sid + ' h4') || document.querySelector('#session-' + sid + ' .session-name a');
    return h ? h.textContent.trim() : '';
  }
  function mineFor(sid) {
    return state ? state.mine.filter(function (m) { return String(m.session) === String(sid); }) : [];
  }
  function fmt(iso) {
    var d = new Date(iso), tz = state && state.tz;
    try {
      return {d: d.toLocaleDateString(LOCALE, {day: 'numeric', month: 'long', timeZone: tz}),
              t: d.toLocaleTimeString(LOCALE, {hour: '2-digit', minute: '2-digit', hour12: false, timeZone: tz})};
    } catch (e) {
      return {d: d.toLocaleDateString(), t: d.toLocaleTimeString().slice(0, 5)};
    }
  }

  function render(block) {
    var box = block.querySelector('.reg-actions');
    if (!box) return;
    var sid = block.getAttribute('data-session');
    var st = state && state.sessions[sid];
    var mine = mineFor(sid);
    var html = '';
    mine.forEach(function (m) {
      html += '<div class="reg-mine ' + (m.status === 'waiting' ? 'is-waiting' : 'is-confirmed') + '">' +
        '<i class="fa ' + (m.status === 'waiting' ? 'fa-hourglass-half' : 'fa-check-circle') + '" aria-hidden="true"></i> ' +
        '<b>' + esc(m.name) + '</b> - ' + (m.status === 'waiting' ? T.waitPos + m.pos : T.you) +
        (m.canCancel ? ' <button type="button" class="reg-cancel" data-id="' + m.id + '" data-name="' + esc(m.name) + '">' + T.cancel + '</button>'
                     : '<div class="reg-note">' + T.late + '</div>') +
        '</div>';
    });
    var s = st ? st.state : 'unknown';
    if (s === 'closed') {
      if (!mine.length) html += '<div class="reg-note">' + T.closed + '</div>';
    } else if (s === 'full') {
      html += '<button type="button" class="reg-go" disabled>' + T.full + '</button>';
    } else if (mine.length < 3) {
      var wait = s === 'waitlist';
      var label = wait ? (mine.length ? T.anotherWait : T.waitlist) : (mine.length ? T.another : T.register);
      html += '<button type="button" class="reg-go' + (mine.length ? ' is-secondary' : '') + '" data-session="' + sid + '">' + label + '</button>';
    }
    box.innerHTML = html;
  }
  function renderAll() {
    Array.prototype.forEach.call(document.querySelectorAll('.reg-block'), render);
  }

  // ---- dialog ----
  var dlg = null;
  function dialog(html) {
    closeDialog();
    dlg = document.createElement('div');
    dlg.className = 'reg-dialog';
    dlg.innerHTML = '<div class="reg-dialog-card" role="dialog" aria-modal="true">' +
      '<button type="button" class="reg-dialog-close" aria-label="' + T.close + '">&times;</button>' + html + '</div>';
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg || e.target.closest('.reg-dialog-close') || e.target.closest('.reg-dialog-ok')) closeDialog();
    });
    document.body.appendChild(dlg);
    document.body.classList.add('reg-dialog-open');
    return dlg;
  }
  function closeDialog() {
    if (dlg) { dlg.remove(); dlg = null; }
    document.body.classList.remove('reg-dialog-open');
  }
  function message(title, text) {
    dialog((title ? '<h4>' + esc(title) + '</h4>' : '') + '<div class="reg-dialog-text">' + text + '</div>' +
           '<div class="reg-dialog-buttons"><button type="button" class="reg-dialog-ok reg-primary">OK</button></div>');
  }
  function errorText(r) {
    var code = r.error === 'invalid' ? 'invalid_' + r.detail : r.error;
    var t = T.err[code] || T.err.other;
    if (code === 'category') {
      var p = String(r.detail || '').split('|');
      t = t.replace('%c', esc(p[0] || '')).replace('%l', esc(p[1] || ''));
    }
    if (code === 'soon' && r.detail) {
      var f = fmt(r.detail);
      t = T.soon.replace('%d', f.d).replace('%t', f.t);
    }
    return t.replace('%x', esc(r.detail || ''));
  }

  function openForm(sid) {
    var st = state && state.sessions[sid];
    if (!navigator.onLine || !state) { message('', T.offline); refresh(); return; }
    if (st && st.state === 'soon') {
      var f = fmt(st.opens);
      message(sessionTitle(sid), T.soon.replace('%d', f.d).replace('%t', f.t));
      return;
    }
    var wait = st && st.state === 'waitlist';
    var saved = {};
    try { saved = JSON.parse(get('reg-contact') || '{}') || {}; } catch (e) {}
    var first = !mineFor(sid).length;
    var d = dialog(
      '<h4>' + (wait ? T.titleWait : T.title) + '</h4>' +
      '<div class="reg-dialog-session">' + esc(sessionTitle(sid)) + '</div>' +
      (wait ? '<div class="reg-dialog-wait">' + T.waitNote + '</div>' : '') +
      '<form class="reg-form" novalidate>' +
      '<label>' + T.name + '<input name="name" autocomplete="name" maxlength="80" value="' + esc(first && saved.name || '') + '"></label>' +
      '<label>' + T.phone + '<input name="phone" type="tel" autocomplete="tel" maxlength="20" dir="ltr" value="' + esc(saved.phone || '') + '"></label>' +
      '<label>' + T.email + '<input name="email" type="email" autocomplete="email" maxlength="120" dir="ltr" value="' + esc(saved.email || '') + '"></label>' +
      '<div class="reg-form-error" aria-live="polite"></div>' +
      '<div class="reg-dialog-buttons"><button type="submit" class="reg-primary">' + T.send + '</button></div>' +
      '</form>');
    var form = d.querySelector('form');
    var F = function (n) { return form.querySelector('[name=' + n + ']'); };   // form.name is the form's own name
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type=submit]');
      var v = {name: F('name').value.trim(), phone: F('phone').value.trim(), email: F('email').value.trim()};
      var errBox = form.querySelector('.reg-form-error');
      errBox.textContent = '';
      if (v.name.length < 2) { errBox.textContent = T.err.invalid_name; F('name').focus(); return; }
      if (v.phone.replace(/\D/g, '').length < 9) { errBox.textContent = T.err.invalid_phone; F('phone').focus(); return; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.email)) { errBox.textContent = T.err.invalid_email; F('email').focus(); return; }
      btn.disabled = true;
      call({action: 'register', session: Number(sid), name: v.name, phone: v.phone, email: v.email, lang: LANG, formOpen: true}).then(function (r) {
        if (r.state && r.state.ok) { setState(r.state); renderAll(); }
        if (!r.ok) {
          btn.disabled = false;
          errBox.innerHTML = errorText(r);
          return;
        }
        set('reg-used', '1');
        set('reg-contact', JSON.stringify(first ? v : {name: saved.name || '', phone: v.phone, email: v.email}));
        var mail = '<p class="reg-dialog-mail">' + esc(T.mailSent.replace('%e', v.email)) + '</p>';
        if (r.status === 'confirmed') {
          message(sessionTitle(sid), '<p class="reg-ok"><i class="fa fa-check-circle"></i> ' + T.okConfirmed + '</p>' + mail);
        } else {
          message(sessionTitle(sid), (wait ? '' : '<p class="reg-dialog-wait">' + T.gone + '</p>') +
                  '<p class="reg-ok is-waiting"><i class="fa fa-hourglass-half"></i> ' +
                  T.okWaiting.replace('%n', r.pos) + '</p><p>' + T.okWaitingMore + '</p>' + mail);
        }
      }).catch(function () {
        btn.disabled = false;
        errBox.textContent = navigator.onLine ? T.err.other : T.offline;
      });
    });
  }

  function askCancel(id, name) {
    var d = dialog('<div class="reg-dialog-text">' + esc(T.cancelQ.replace('%n', name)) + '</div>' +
      '<div class="reg-dialog-buttons"><button type="button" class="reg-dialog-ok">' + T.cancelNo + '</button>' +
      '<button type="button" class="reg-primary reg-danger">' + T.cancelYes + '</button></div>');
    d.querySelector('.reg-danger').addEventListener('click', function (e) {
      e.target.disabled = true;
      call({action: 'cancel', id: Number(id)}).then(function (r) {
        if (r.state && r.state.ok) { setState(r.state); renderAll(); }
        message('', r.ok ? T.cancelled : errorText(r));
      }).catch(function () { message('', navigator.onLine ? T.err.other : T.offline); });
    });
  }

  // moved from the waiting list to a seat (by a cancellation or at the HelpDesk): tell once
  function announcePromotions() {
    if (!state || dlg) return;
    var p = state.mine.filter(function (m) { return m.promoted; });
    if (!p.length) return;
    message('', '<p class="reg-ok"><i class="fa fa-check-circle"></i> ' + T.promoted + '</p><ul>' + p.map(function (m) {
      return '<li><b>' + esc(sessionTitle(m.session) || '') + '</b> - ' + esc(m.name) + '</li>';
    }).join('') + '</ul>');
    call({action: 'seen', ids: p.map(function (m) { return m.id; })}).catch(function () {});
    p.forEach(function (m) { m.promoted = false; });
  }

  document.addEventListener('click', function (e) {
    var go = e.target.closest && e.target.closest('.reg-go');
    if (go && !go.disabled) { e.preventDefault(); e.stopPropagation(); openForm(go.getAttribute('data-session')); return; }
    var c = e.target.closest && e.target.closest('.reg-cancel');
    if (c) { e.preventDefault(); e.stopPropagation(); askCancel(c.getAttribute('data-id'), c.getAttribute('data-name')); }
  }, true);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dlg) closeDialog(); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { remind(); refresh(); } });
  window.addEventListener('online', refresh);

  // the Сетка session card copies the session details when it opens: fill its block too
  if (window.MutationObserver) {
    var pending = false;
    new MutationObserver(function () {
      if (pending) return;
      pending = true;
      setTimeout(function () {
        pending = false;
        var fresh = Array.prototype.filter.call(document.querySelectorAll('.reg-block'), function (b) {
          var box = b.querySelector('.reg-actions');
          return box && !box.hasChildNodes();
        });
        if (!fresh.length) return;
        if (state) fresh.forEach(render);
        refresh();
      }, 50);
    }).observe(document.body, {childList: true, subtree: true});
  }

  function init() {
    renderAll();
    refresh();
    remind();
    setInterval(function () { if (document.visibilityState === 'visible') remind(); }, 30000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
