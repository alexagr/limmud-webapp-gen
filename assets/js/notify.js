function check_notify() {
  $.ajax({
    url: 'data/notify.json',
    type: 'GET',
    success: function(data) {
      let title = 'Сообщение';
      let msg = data['msg'];
      let buttonText = 'ЗАКРЫТЬ';
      if (document.documentElement.lang == 'he') {
        title = 'הודעה';
        msg = data['msg_he'];
        buttonText = 'סגור';
      }
      if (msg && 
          (Date.now() > Date.parse(data['not_before'])) &&
          (Date.now() < Date.parse(data['not_after'])) &&
          ((localStorage.hasOwnProperty('webapp_notify_timestamp') === false) ||
           (localStorage['webapp_notify_timestamp'] < data['timestamp']))) {
        localStorage['webapp_notify_timestamp'] = data['timestamp'];
        
        Swal.fire({
          icon: 'info',
          /* title: title, */
          text: msg,
          confirmButtonText: buttonText
        });
      }
      setTimeout(check_notify, 3 * 60 * 1000);
    },
    error: function(data) {
      setTimeout(check_notify, 3 * 60 * 1000);
    }
  });
}

$(function() {
  setTimeout(check_notify, 10 * 1000);
});
