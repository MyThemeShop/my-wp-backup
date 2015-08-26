/* globals jQuery:false,ajaxurl */
(function($){
    'use strict';

    var key = 0;
    var code = $('#view-progress');

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

    (function getProgress() {
        var complete = false;
        $.ajax({
            type: 'GET',
            data: {
                action: 'wp_backup_restore_backup',
                job_id: viewProgress.job_id,
                backup_uniqid: viewProgress.backup_uniqid,
                key: key
            },
            url: ajaxurl,
            success: function(response) {
                if ( typeof response.key === 'undefined' || typeof response.lines === 'undefined' ) {
                    code.append(MyWPBackupi18n.failed);
                    console.log(response);
                    return;
                }
                key = response.key;
                if ( response.lines.length ) {
                    var lines = '';
                    response.lines.forEach(function(line) {
                        lines += transform(line);
                    });
                    code.append(lines);
                    if ( lines.indexOf('Finished') !== -1 ) {
                        complete = true;
                    }
                }
            },
            complete: function() {
                if (!complete) setTimeout( getProgress, 1000 );
            }
        })
    })();

    function transform(line) {
        return '<span class="' + line.level + '">\n' + line.text + '</span>';
    }


})(jQuery);
