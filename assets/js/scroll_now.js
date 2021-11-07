$(function() {
  let now = new Date();
  let nowDate = now.getFullYear() + '-' + ('0' + (now.getMonth()+1)).slice(-2) + '-' + ('0' + now.getDate()).slice(-2);
  $('.day-filter').each(function() {
    if ($(this).hasClass(nowDate)) {
      $('#now-button').show();
    }
  });

  $('#now-button').click(function() {
    let now = new Date();
    let nowDate = now.getFullYear() + '-' + ('0' + (now.getMonth()+1)).slice(-2) + '-' + ('0' + now.getDate()).slice(-2);
    let nowTime = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
    let elem;

    $('.day-filter').each(function() {
      if ($(this).hasClass(nowDate) && !$(this).hasClass('hide')) {
        $(this).find('.time-filter').each(function() {
          let eventTime = $(this).find('.eventtime').eq(0).find('h4').eq(0).text();
          if (eventTime < nowTime) {
            elem = $(this);
          } else {
            if (!elem) {
              elem = $(this);
            }
            return false;
          }
        });
        return false;
      }
    });

    if (elem) {
      $('html, body').animate({scrollTop: elem.offset().top - 100}, 1000);
    }
  });
});
