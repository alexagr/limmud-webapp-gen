function check_notify() {
  $.ajax({
    url: 'data/notify.json',
    type: 'GET',
    success: function(data) {
      let msg = data['msg'];
      let buttonText = 'ЗАКРЫТЬ';
      if ($('html')[0].lang == 'he') {
        msg = data['msg_he'];
        buttonText = 'סגור';
      }
      if (msg && ((localStorage.hasOwnProperty('webapp_notify_timestamp') === false) ||
                  (localStorage['webapp_notify_timestamp'] < data['timestamp']))) {
        localStorage['webapp_notify_timestamp'] = data['timestamp'];
        Snackbar.show({
          text: msg,
          pos: 'bottom-center',
          actionText: buttonText,
          duration: 30000
        });
      }
      setTimeout(check_notify, 5 * 60 * 1000);
    },
    error: function(data) {
      setTimeout(check_notify, 5 * 60 * 1000);
    }
  });
}

$(function() {
  setTimeout(check_notify, 10 * 1000);
});
