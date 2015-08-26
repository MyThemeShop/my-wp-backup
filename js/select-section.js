(function($){
    'use strict';
    $.fn.selectSection = function(wrapSelector) {
        var wrap = $(wrapSelector);

        this.change(function() {
            var val = $(this).val();

            if (!(val instanceof Array)) {
                val = [val];
            }

            wrap.children('.select-section.section-active').removeClass('section-active');
            val.forEach(function(val) {
                wrap.children('.select-section.section-' + val).addClass('section-active');
            });
        })
    };

})(jQuery);