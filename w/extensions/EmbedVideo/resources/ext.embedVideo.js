(function(mw, $, window) {
	$(function() {
        autoResizer(); // run first thing, because we dont need a resize to be broken.

        var autoResizerRTime;
        var autoResizerTimeout = false;
        var autoResizerDelta = 200;

        $(window).resize(function() {
			autoResizerRTime = new Date();
            if (autoResizerTimeout === false) {
                autoResizerTimeout = true;
                setTimeout(autoResizerResizeEnd, autoResizerDelta);
            }
        });

		function isFullscreen() {
			return document.fullscreenElement != null || document.mozFullScreenElement != null || document.webkitFullscreenElement != null;
		}

        function autoResizerResizeEnd() {
			if (isFullscreen()) {
                autoResizerTimeout = false;
				return;
			}
            if (new Date() - autoResizerRTime < autoResizerDelta) {
                setTimeout(autoResizerResizeEnd, autoResizerDelta);
            } else {
                autoResizerTimeout = false;
                autoResizer();
            }
        }

        function autoResizer() {
            $('.autoResize').each(function(){
                var parent = $(this).parent();
                var self = $(this);
                var iframe = self.find('iframe');
                var wrap = self.find('.embedvideowrap');

                if(parent.width() <= 0) {
                    return;
                }

                if(self.hasClass("thumb")) {

                    var originalWidth = iframe.attr("data-orig-width");
                    if(originalWidth === undefined) {
                        originalWidth = iframe.width();
                    }

                    if(parent.width() < iframe.width()) {
                        self.width(parent.width());
                    } else if(parent.width() > originalWidth) {
                        self.width(originalWidth).css('width', originalWidth).attr('width', originalWidth);
                    } else{
                        resizeHandler(self, iframe, parent, wrap);
                    }
                    return;
                }

                if (iframe.width() > parent.width()) {
                    resizeHandler(self, iframe, parent, wrap);
                } else {
                    self.removeClass('autoResized').css('width', '')
                    var originalWidth = iframe.attr("data-orig-width");
                    var originalHeight = iframe.attr("data-orig-height");
                    iframe.width(originalWidth).css('width', originalWidth).attr('width', originalWidth);
                    iframe.height(originalHeight).css('height', originalHeight).attr('height', originalHeight);
                    wrap.width(originalWidth).css('width', originalWidth).attr('width', originalWidth);
                    wrap.height(originalHeight).css('height', originalHeight).attr('height', originalHeight);
                }

                if (!self.hasClass('autoResized') && iframe.width() >= parent.width()) {
                    resizeHandler(self, iframe, parent, wrap);
                }
            });
        }

        window.autoResizer = autoResizer;

        function resizeHandler(self, iframe, parent, wrap) {
            self.addClass('autoResized');

            if (typeof iframe.attr("data-orig-height") == 'undefined') {
                iframe.attr("data-orig-height", iframe.height());
                iframe.attr("data-orig-width", iframe.width());
            }

            var aspect = iframe.width() / iframe.height();
            var newWidth = parent.width();
            var newHeight = newWidth / aspect;

            self.width(newWidth).css('width', newWidth);
            iframe.width(newWidth).css('width', newWidth).attr('width', newWidth);
            iframe.height(newHeight).css('height', newHeight).attr('height', newHeight);
            wrap.width(newWidth).css('width', newWidth).attr('width', newWidth);
            wrap.height(newHeight).css('height', newHeight).attr('height', newHeight);
        }

    });

}(mediaWiki, jQuery, window));
