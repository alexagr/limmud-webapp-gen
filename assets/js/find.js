$(function() {
    var currentMatch = null;
    var matches = [];

    $('#find-button-div').show();

    function find() {
        let val = $('#find-field').val();
        currentMatch = null;
        matches = [];

        $('.event > h4, h5').each(function() {
            var elem = this;
            var m = new Mark(elem);
    
            m.unmark({
                done: function() {
                    m.mark(val, {
                        caseSensitive: false,
                        separateWordSearch: false,
                        each: function(e) {
                            var s = $(e).closest('.event')[0];
                            if (!matches.includes(s)) {
                                matches.push(s);
                            }
                        }
                    });
                }
            });
        });

        if (matches && !matches.includes(currentMatch)) {
            currentMatch = matches[0];
            if (matches.length > 1) {
                $('#find-next').css('display', 'block');
            } else {
                $('#find-next').css('display', 'none');
            }
        } else {
            currentMatch = null;
        }
        show_current_match();
    }

    function find_next() {
        var nextMatch = null;
        var matchFound = false;
        for (const i in matches) {
            if (matches[i] == currentMatch) {
                matchFound = true;
            } else {
                if (matchFound) {
                    nextMatch = matches[i];
                    break;
                }
            }
        }
        if (!nextMatch) {
            nextMatch = matches[0];
        }
        currentMatch = nextMatch;
        show_current_match();
    }

    function show_find() {
        $('#find-field').css('display', 'block');
        $('#find-field').focus();
        $('#find-button-div').css('background', '#1090f0');
        $('#find-button').html('<div style="font-size: 16px"><i class="fa fa-times" aria-hidden="true"></i></div>');
    }

    function hide_find() {
        $('#find-field, #find-next').css('display', 'none');
        $('#find-button-div').css('background', '#999');
        $('#find-button').html('find-button').html('<i class="fa fa-search" aria-hidden="true"></i>');
    }

    function is_visible(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight)
        );
    }

    function show_current_match() {
        if (currentMatch) {
            $([document.documentElement, document.body]).animate({
              scrollTop: $(currentMatch).offset().top - 60
              }, 500);
        }
    }

    $('#find-field').keyup(function(e) {
        if(e.key === "Escape") {
            document.getElementById('find-field').value = '';
            hide_find();
        }
        find();
    });

    $('#find-button-div').click(function() {
        document.getElementById('find-field').value = '';
        find();
        if ($('#find-button').html() == '<i class="fa fa-search" aria-hidden="true"></i>') {
            show_find();
        }
        else {
            hide_find();
        }
        return false;
    });

    $('#find-next').click(function() {
        find_next();
        return false;
    });

});
