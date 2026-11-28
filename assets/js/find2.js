$(function() {
    var currentMatch = null;
    var matches = [];

    $('#find-button-div').show();

    // phone keyboard: fixed elements at the bottom stay under it (iOS keeps the layout viewport full height),
    // so lift the whole stack (now / search / field / "next" / top) by the height the keyboard takes
    var vv = window.visualViewport;
    function lift() {
        var kb = vv ? Math.max(0, window.innerHeight - vv.height - vv.offsetTop) : 0;
        if (kb < 80) kb = 0;   // ignore toolbar wobble, only a real keyboard
        $('#now-button, #find-button-div, #find-field, #find-next, #down-button').css('transform', kb ? 'translateY(' + (-kb) + 'px)' : '');
    }
    if (vv) {
        vv.addEventListener('resize', lift);
        vv.addEventListener('scroll', lift);
    }

    function find() {
        let val = $('#find-field').val();
        currentMatch = null;
        matches = [];

        $('.find-target').each(function() {
            var elem = this;
            var m = new Mark(elem);
    
            m.unmark({
                done: function() {
                    m.mark(val, {
                        caseSensitive: false,
                        separateWordSearch: false,
                        each: function(e) {
                            var s = $(e).closest('.find-container')[0];
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
        $('#find-button-div').addClass('is-open');
        $('#find-button').html('<div style="font-size: 16px"><i class="fa fa-times" aria-hidden="true"></i></div>');
    }

    function hide_find() {
        $('#find-field, #find-next').css('display', 'none');
        $('#find-button-div').removeClass('is-open');
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
        if (!currentMatch) return;
        // scroll to the highlighted text itself (not the top of the session), into the part of the screen
        // that is really visible: below the fixed menu / sticky search row, above the phone keyboard
        var target = $(currentMatch).find('mark')[0] || currentMatch;
        // horizontally scrolled containers (Сетка): bring the match into view sideways first
        try { target.scrollIntoView({block: 'nearest', inline: 'center'}); } catch (e) {}
        var cover = 0;
        $('.navbar, .search-sticky').each(function () {
            var cs = getComputedStyle(this);
            if (cs.position === 'fixed' || cs.position === 'sticky') {
                cover = Math.max(cover, (parseFloat(cs.top) || 0) + this.offsetHeight);
            }
        });
        var vh = window.visualViewport ? window.visualViewport.height : window.innerHeight;
        var top = target.getBoundingClientRect().top + window.pageYOffset;
        var y = Math.max(0, top - cover - Math.max(20, (vh - cover) * 0.3));
        $([document.documentElement, document.body]).stop().animate({scrollTop: y}, 400);
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
