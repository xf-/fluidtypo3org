jQuery(document).ready(function($) {
	var originalHeight = $('#teaser').height();
	var newHeight = $('#teaser').width() * 0.5625;
	$('[data-toggle="tooltip"]').tooltip();
	$('[data-toggle="dropdown"]').dropdown();
	$('#videolink').click(function() {
		var original = $('#teaser .original');
		var variant = $('#teaser .video');
		var video = $('#video');
		video.find('source').attr('src', video.find('source').attr('data-temporary-src'));
		var player = videojs('video');
		$('#teaser').css({
			'height': newHeight
		});
		player.ready(function() {
			original.slideUp();
			variant.slideDown();
			newHeight = $('#video').width() * 0.5625;
			$('#teaser').animate({
				height: newHeight
			}, 'fast');
			this.height(newHeight);
			this.play();
			this.on('ended', function() {
				original.slideDown();
				variant.slideUp();
				$('#teaser').animate({
					height: 'auto'
				}, 'fast');
				$(window).unbind('resize');
			});
			$(window).resize(function() {
				newHeight = $('#video').width() * 0.5625;
				$('#video').height(newHeight);
				$('#teaser').css({
					'height': newHeight
				});
			});
		});
	});
});
