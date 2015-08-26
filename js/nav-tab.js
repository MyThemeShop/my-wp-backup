(function($){
    'use strict';
    $.fn.navTab = function() {
        var wrap = $(this);

        $('.nav-tab').click(function(e) {
            e.preventDefault();

            var link = $(this);
            var section = $(link.attr('href'));

            wrap.find('.nav-tab.nav-tab-active').removeClass('nav-tab-active');
            link.addClass('nav-tab-active');

            wrap
                .find('.nav-tab-content.nav-tab-content-active')
                .removeClass('nav-tab-content-active');

            if (section) section.addClass('nav-tab-content-active');
        });
    };
})(jQuery);