jQuery(document).ready(function() {
    var placement = {};
    if("click"==aflb.slideon.replace(/"/g, "") || ((navigator.userAgent.match(/iPhone/i)) || (navigator.userAgent.match(/iPod/i)) || (navigator.userAgent.match(/iPad/i)) || "1" === aflb.ismobile)) {
        jQuery(".aspexifblikebox .aspexi_facebook_button, .aspexifblikebox .aspexi_facebook_iframe").click(function() {
            if(0==parseInt(jQuery('.aspexifblikebox').css(aflb.placement),10)) {
                placement[aflb.placement] = -(parseInt(aflb.width));
                jQuery('.aspexifblikebox').stop().animate(placement, 400);
            } else {
                placement[aflb.placement] = 0;
                jQuery('.aspexifblikebox').stop().animate(placement, 400);
            }
        });
    } else {
        jQuery(".aspexifblikebox .aspexi_facebook_button, .aspexifblikebox .aspexi_facebook_iframe").hover(function() {
            placement[aflb.placement] = 0;
            jQuery('.aspexifblikebox').stop().animate(placement, 400);
        }, function() {
            placement[aflb.placement] = -(parseInt(aflb.width));
            jQuery('.aspexifblikebox').stop().animate(placement, 400);
        });
    }
});