/**
 * Video Stream Buffer — Frontend JavaScript.
 *
 * Provides buffer progress visualization for the <video> elements rendered
 * by this plugin. Listens for the 'progress' event and updates the visual
 * buffer bar width using CSS custom properties.
 *
 * No jQuery dependency — vanilla JavaScript only.
 *
 * @package VideoStreamBuffer
 * @since   1.0.0
 */
(function () {
	'use strict';

	/**
	 * Initialize buffer progress tracking for a single video element.
	 *
	 * @param {HTMLVideoElement} video The video element to track.
	 * @param {HTMLElement}     bar   The buffer bar fill element.
	 */
	function initBufferBar(video, bar) {
		if (!video || !bar) {
			return;
		}

		// Add the buffering class so CSS picks up the custom property.
		bar.classList.add('vsb-buffering');

		/**
		 * Update the buffer bar width based on buffered time ranges.
		 *
		 * We use video.buffered.end(0) which returns the end time (in seconds)
		 * of the first buffered range. Dividing by duration gives a 0–1 fraction.
		 */
		function updateBuffer() {
			if (!video.duration || video.duration === Infinity) {
				bar.style.setProperty('--vsb-buffer-progress', '0%');
				return;
			}

			try {
				if (video.buffered.length > 0) {
					var bufferedEnd = video.buffered.end(video.buffered.length - 1);
					var progress = (bufferedEnd / video.duration) * 100;
					// Clamp between 0 and 100.
					progress = Math.min(100, Math.max(0, progress));
					bar.style.setProperty('--vsb-buffer-progress', progress + '%');
				}
			} catch (e) {
				// Silently ignore errors from buffered property access.
				bar.style.setProperty('--vsb-buffer-progress', '0%');
			}
		}

		// Listen for progress events (fired as the browser downloads data).
		video.addEventListener('progress', updateBuffer);

		// Also update on loadedmetadata (when duration becomes available).
		video.addEventListener('loadedmetadata', updateBuffer);

		// Update on seeked (buffered ranges may change after seeking).
		video.addEventListener('seeked', updateBuffer);

		// Initial update attempt (may run before metadata, which is fine).
		updateBuffer();
	}

	/**
	 * Handle video load errors gracefully.
	 *
	 * @param {HTMLVideoElement} video The video element that failed.
	 */
	function handleVideoError(video) {
		video.addEventListener('error', function () {
			var wrapper = video.closest('.vsb-video-wrapper');
			if (!wrapper) {
				return;
			}

			// Only insert the error message if one doesn't already exist.
			if (!wrapper.querySelector('.vsb-error-message')) {
				var errorMsg = document.createElement('div');
				errorMsg.className = 'vsb-error vsb-error-message';
				errorMsg.textContent = 'Video failed to load. The file may be missing or inaccessible.';

				// Insert after the buffer bar if present, otherwise prepend.
				var bufferBar = wrapper.querySelector('.vsb-buffer-bar');
				if (bufferBar) {
					bufferBar.insertAdjacentElement('afterend', errorMsg);
				} else {
					wrapper.prepend(errorMsg);
				}
			}
		});
	}

	/**
	 * Scan the page for plugin video elements and initialize buffer bars.
	 */
	function initAll() {
		var wrappers = document.querySelectorAll('.vsb-video-wrapper.vsb-show-buffer');

		for (var i = 0; i < wrappers.length; i++) {
			var wrapper = wrappers[i];
			var video   = wrapper.querySelector('video');
			var bar     = wrapper.querySelector('.vsb-buffer-bar-fill');

			if (video && bar) {
				initBufferBar(video, bar);
			}

			if (video) {
				handleVideoError(video);
			}
		}

		// Also handle video elements in non-buffer wrappers for error handling.
		var allWrappers = document.querySelectorAll('.vsb-video-wrapper');
		for (var j = 0; j < allWrappers.length; j++) {
			var v = allWrappers[j].querySelector('video');
			if (v && !allWrappers[j].querySelector('.vsb-buffer-bar-fill')) {
				handleVideoError(v);
			}
		}
	}

	// Run on DOM ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
