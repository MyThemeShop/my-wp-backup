/** jshint browser:true */
/** globals jQuery:false */
(function($) {
    'use strict';

    var pre = $('.terminal');
    var log = pre.children('code');
    var verbose = $('#show-verbose');

    verbose.change(function() {
        if ($(this).is(':checked')) {
            log.addClass('show-verbose');
        } else {
            log.removeClass('show-verbose');
        }
        pre.scrollTop(pre.prop('scrollHeight'));
    });

})(jQuery);
