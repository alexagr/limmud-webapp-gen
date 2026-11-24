$(function() {

  $('#down-button').click(function() {
    $('#down-button').addClass('is-flash');
    setTimeout(function() { $('#down-button').removeClass('is-flash'); }, 500);

    $('html, body').animate({scrollTop: 0}, 500);
  });

});
