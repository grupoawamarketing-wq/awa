/**
 * jQuery .andSelf() compatibility shim.
 * .andSelf() was removed in jQuery 3.x; this maps it to .addBack().
 */
define(['jquery'], function ($) {
    'use strict';
    if (!$.fn.andSelf && $.fn.addBack) {
        $.fn.andSelf = $.fn.addBack;
    }
    return $;
});
