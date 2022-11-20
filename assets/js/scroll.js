$(function() {

  $('#down-button').click(function() {
    $('#down-button').css('color', '#1090f0');
    setTimeout(function() { $('#down-button').css('color', '#999'); }, 500);

    $('html, body').animate({scrollTop: 0}, 500);
  });

});
