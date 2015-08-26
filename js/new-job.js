/* jshint browser: true */
/* globals jQuery:false,MyWPBackupAuthUrl:false,fileFilter:false,ajaxurl:false */
jQuery(function($) {
    'use strict';

    var win = $(window);
    var wrap = $('.wrap');
    var form = $('#my-wp-backup-form');

    $('#job-name').focus();

    var cronForm = $('.form-cron');
    $('input[name="my-wp-backup-jobs[schedule_type]"]').change(function() {
        if($(this).prop('value') === 'cron') {
            cronForm.show();
        } else {
            cronForm.hide();
        }
    });

    wrap.navTab();

    $('#destination').selectSection('#section-destination');
    $('#rep_destination').selectSection('#section-report');
    $('#mail-method').selectSection('.select-section.section-mail');

    var cleanForm = form.serialize();
    win.on('beforeunload', function() {
        var dirtyForm = form.serialize();

        if(cleanForm !== dirtyForm) {
            return MyWPBackupi18n.form_unsaved;
        }
    });

    form.submit(function() {
        win.off('beforeunload');
    });

    $('#dropbox-token-link').click(function() {
        var win = window.open( MyWPBackupAuthUrl.dropbox, '_blank' );
        win.focus();
    });

    $('#drive-token-link').click(function() {
        var win = window.open( MyWPBackupAuthUrl.drive, '_blank' );
        win.focus();
    });

    $('#file-exclude-link').click(function() {
        var data = {
            action: 'wp_backup_try_file_filters',
            filters: $('#file-filters').val(),
            nonce: fileFilter.nonce
        };
        var cont = $('.terminal.excluded');

        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: data,
            beforeSend: function() {
                cont.text('Please wait...');
            }
        }).done(function(response) {
            if( !( response instanceof Array) ) {
                cont.text(MyWPBackupi18n.failed);
                console.log(response);
                return;
            }
            cont.text(response.join('\n')+'\n\n'+MyWPBackupi18n.test_complete);
        });
    });

    $('body').on('submit', '#TB_ajaxContent form', function(e) {
        e.preventDefault();

        var self = $(this);

        var data = {
            action: self.data('action'),
            code: self.find('.my-wp-backup-access-token').val()
        };

        $.post(ajaxurl, data)
            .done(function(response, textStatus, jqXHR) {
                if ( jqXHR.getResponseHeader('content-type').indexOf('application/json') !== -1 ) {
                    $(self.data('target')).val(response.access_token);
                    $(self.data('target-hidden')).val(htmlEscape(JSON.stringify(response)));
                } else {
                    $(self.data('target')).val(response);
                }

                tb_remove();
            })
            .fail(function() {
                alert(MyWPBackupi18n.failed);
            });
    });

    function htmlEscape(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
});
