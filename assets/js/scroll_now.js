$(function() {
  $('#now-button').show();

  $('#now-button').click(function() {
    let now = new Date();
    let nowDate = now.getFullYear() + '-' + ('0' + (now.getMonth()+1)).slice(-2) + '-' + ('0' + now.getDate()).slice(-2);
    // we increase current time by 30 minutes - so that if we are close to the beginning of the session we still find it, but if too much time has passed we find the next one
    let nowMinutes = now.getHours() * 60 + now.getMinutes() + 30;
    let nowTime = ('0' + Math.floor(nowMinutes / 60).toString()).slice(-2) + ':' + ('0' + (nowMinutes % 60).toString()).slice(-2);

    let day;
    // first we search for the current day in whatever is currently displayed
    $('.day-filter').each(function() {
        if (!$(this).hasClass('hide') && $(this).hasClass(nowDate)) {
            day = this;
        }
    });

    // if we didn't find it - display all days and search again
    if (!day)
    {
        $('.date-button').first().click();
        $('.day-filter').each(function() {
            if (!$(this).hasClass('hide') && $(this).hasClass(nowDate)) {
                day = this;
            }
        });
    }

    // if we still didn't find it - search for the first session
    if (!day)
    {
        day = $('.day-filter').first();
        nowTime = '14:00';
    }

    var elem;
    $(day).find('.time-filter').each(function() {
      let eventTime = $(this).find('.eventtime').eq(0).find('h4').eq(0).text();
      if (!elem || (eventTime < nowTime)) {
        elem = this;
      }
    });

    if (elem) {
      Snackbar.show({
        text: 'Ближайшая сессия - в ' + $(elem).find('.eventtime').first().find('h4').first().text() /*+ ' (' + $(day).find('h4').first().text() + ')'*/,
        pos: 'bottom-center',
        showAction: false,
        duration: 2000
      });

      $('html, body').animate({scrollTop: $(elem).offset().top - 100}, 1000);
    }
  });
});
