/**
 * Handles the Autoshare JS.
 *
 * @todo soooo much dependency :facepalm:
 */
(function($) {
	'use strict';

	var $tweetPost = $('#autoshare-for-twitter-enable'),
		$icon = $('#autoshare-for-twitter-icon'),
		$tweetText = $('#autoshare-for-twitter-text'),
		$editLink = $('#autoshare-for-twitter-edit'),
		$editBody = $('#autoshare-for-twitter-override-body'),
		$hideLink = $('.cancel-tweet-text'),
		$allowTweetImage = $('#autoshare-for-twitter-tweet-allow-image'),		
		errorMessageContainer = document.getElementById('autoshare-for-twitter-error-message'),
		counterWrap = document.getElementById('autoshare-for-twitter-counter-wrap'),
		allowTweetImageWrap = $('.autoshare-for-twitter-tweet-allow-image-wrap'),
		limit = 280;

	// Add enabled class if checked
	if ($tweetPost.prop('checked')) {
		$icon.addClass('enabled');
	}

	// Event handlers.
	$tweetPost.on('click', handleRequest);
	$tweetText.change(handleRequest);
	$tweetPost.change(toggleAllowImageVisibility);
	$allowTweetImage.change(handleRequest);
	$editLink.on('click', function() {
		$editBody.slideToggle();
		updateRemainingField();
		$(this).hide();
	});
	$tweetText.on('keyup', function() {
		updateRemainingField();
	});
	$hideLink.on('click', function(e) {
		e.preventDefault();
		$('#autoshare-for-twitter-override-body').slideToggle();
		$editLink.show();
	});

	// Runs on page load to auto-enable posts to be tweeted
	window.onload = function(event) {
		if ('' === adminAutoshareForTwitter.currentStatus) {
			handleRequest(event, true);
		}
	};

	/**
	 * Callback for failed requests.
	 */
	function onRequestFail(error) {
		var errorText = '';
		if ('statusText' in error && 'status' in error) {
			errorText = `${adminAutoshareForTwitter.errorText} ${error.status}: ${error.statusText}`;
		} else {
			errorText = adminAutoshareForTwitter.unkonwnErrorText;
		}

		errorMessageContainer.innerText = errorText;

		$icon.removeClass('pending');
		$tweetPost.prop('checked', false);
		$('#submit').attr('disabled', true);
	}

	/**
	 * AJAX handler
	 * @param event
	 */
	function handleRequest(event, status = $tweetPost.prop('checked')) {
		var data = {};
		data[adminAutoshareForTwitter.enableAutoshareKey] = status;
		data[adminAutoshareForTwitter.tweetBodyKey] = $tweetText.val();
		data[adminAutoshareForTwitter.allowTweetImageKey] = $allowTweetImage.prop('checked');
		$('#submit').attr('disabled', true);

		wp.apiFetch({
			url: adminAutoshareForTwitter.restUrl,
			data: data,
			method: 'POST',
			parse: false, // We'll check the response for errors.
		})
			.then(function(response) {
				if (!response.ok) {
					throw response;
				}

				return response.json();
			})
			.then(function(data) {
				errorMessageContainer.innerText = '';

				$icon.removeClass('pending');
				if (data.enabled) {
					$icon.removeClass('disabled');
					$icon.addClass('enabled');
					$tweetPost.prop('checked', true);
				} else {
					$icon.removeClass('enabled');
					$icon.addClass('disabled');
					$tweetPost.prop('checked', false);
				}

				if (data.allowImage) {
					$allowTweetImage.prop('checked', true);
				} else {
					$allowTweetImage.prop('checked', false);
				}

				$('#submit').attr('disabled', false);
			})
			.catch(onRequestFail);
	}

	/**
	 * Updates the counter
	 */
	function updateRemainingField() {
		var count = $tweetText.val().length;

		$(counterWrap).text(count);

		// Toggle the .over-limit class.
		if (limit < count) {
			counterWrap.classList.add('over-limit');
		} else if (counterWrap.classList.contains('over-limit')) {
			counterWrap.classList.remove('over-limit');
		}
	}

	/**
	 * Helper for toggling classes to indicate something is happening.
	 */
	function pendingStatus() {
		$icon.toggleClass('pending');
		$icon.removeClass('enabled');
		$icon.removeClass('disabled');
	}

	// Show/Hide "Use featured image in Tweet" checkbox.
	if ( allowTweetImageWrap && wp.media.featuredImage ) {
		toggleAllowImageVisibility();
		// Listen event for add/remove featured image.
		wp.media.featuredImage.frame().on( 'select', toggleAllowImageVisibility );
		$('#postimagediv').on( 'click', '#remove-post-thumbnail', toggleAllowImageVisibility );
	}

	/**
	 * Show/Hide "Use featured image in Tweet" checkbox.
	 */
	function toggleAllowImageVisibility( event ) {
		let hasMedia = wp.media.featuredImage.get();
		// Handle remove post thumbnail click
		if( event && event.target && 'remove-post-thumbnail' === event.target.id && 'click' === event.type ) {
			hasMedia = -1;
		}
		const autoshareEnabled = $tweetPost.prop('checked');
		// Autoshare is enabled and post has featured image.
		if ( hasMedia > 0 && autoshareEnabled ) {
			allowTweetImageWrap.show();
		} else {
			allowTweetImageWrap.hide();
		}
	}
})(jQuery);
