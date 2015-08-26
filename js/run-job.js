/* jshint browser:true */
/* globals jQuery:false, MyWPBackupJob:false,ajaxurl:false */
jQuery(function($) {
    'use strict';

    var pre = $('#backup-progress');
    var log = pre.children('code');
    var verbose = $('#show-verbose');
    var retry = 0;

    verbose.change(function() {
       if ($(this).is(':checked')) {
           log.addClass('show-verbose');
       } else {
           log.removeClass('show-verbose');
       }
       pre.scrollTop(pre.prop('scrollHeight'));
    });

    if ( ! MyWPBackupJob.uniqid.length ) {
        $.post(ajaxurl, MyWPBackupJob)
            .done(function(response) {
                if(!( response.lines instanceof Array)) {
                    log.append(MyWPBackupi18n.failed);
                    console.log(response);
                }
                var lines = '';
                MyWPBackupJob.uniqid = response.uniqid;
                response.lines.forEach(function(line) {
                    lines += transform(line);
                });
                log.append(lines);
                pre.scrollTop(pre.prop('scrollHeight'));
                setTimeout(getProgress, 1000);
            })
            .fail(function() {
                if ( ++retry < 5 ) {
                    setTimeout(getProgress, 3000);
                }
            });
    } else {
        getProgress();
    }

    function getProgress() {
        $.ajax({
            url: ajaxurl,
            data: MyWPBackupJob,
            success: function(response) {
                var lines = '';

                MyWPBackupJob.key = response.key;

                if(response.lines instanceof Array) {
                    response.lines.forEach(function(line) {
                        lines += transform(line);
                    });
                } else {
                    console.log(response);
                }

                if(lines.length !== 0) {
                    log.append(lines);
                    pre.scrollTop(pre.prop('scrollHeight'));
                }

                setTimeout(getProgress, 1000);
            }
        });
    }

    function transform(line) {
        return '<span class="' + line.level + '">\n' + line.text + '</span>';
    }

});
