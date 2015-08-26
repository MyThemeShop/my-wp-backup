/** globals jQuery:false,MyWPBackupOptions:false,hopscotch:false,MyWPBackup_tour:false */

(function($){
    'use strict';

    if (typeof MyWPBackup_tour !== 'undefined' && MyWPBackupOptions.onTour) {
        hopscotch.startTour(MyWPBackup_tour);

        if ( MyWPBackup_tour.id === 'MyWPBackup-newjob' ) {
            hopscotch.listen('next', function() {
                var step = hopscotch.getCurrStepNum() + 1;
                step = step === 6 ? 1 : step;
                $('.nav-tab:nth-child( ' + step + ')').click();
            });
        }
    }
})(jQuery);