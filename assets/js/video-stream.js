/**
 * Video Stream Buffer — Frontend JavaScript.
 *
 * - Custom video controls UI replacing native browser controls
 * - Buffer progress visualization integrated into the seek bar
 * - Keyboard shortcuts, auto-hide behavior, mobile responsiveness
 * - Vanilla JS — no jQuery dependency.
 *
 * @package VideoStreamBuffer
 * @since   1.1.0
 */
(function () {
    'use strict';

    /**
     * Format seconds into mm:ss or h:mm:ss display string.
     *
     * @param {number} seconds
     * @return {string}
     */
    function formatTime(seconds) {
        if (isNaN(seconds) || !isFinite(seconds) || seconds < 0) {
            return '0:00';
        }
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = Math.floor(seconds % 60);
        if (h > 0) {
            return h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    /**
     * Create an SVG element from a raw SVG string.
     *
     * @param {string} svgString Raw SVG markup.
     * @return {SVGElement}
     */
    function createSVG(svgString) {
        var div = document.createElement('div');
        div.innerHTML = svgString.trim();
        return div.firstChild;
    }

    // -------------------------------------------------------------------------
    // SVG icon definitions (inline, no font dependencies)
    // -------------------------------------------------------------------------
    var ICONS = {
        play: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>',
        pause: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>',
        volumeHigh: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>',
        volumeMuted: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>',
        fullscreen: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>',
        fullscreenExit: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/></svg>',
        centerPlay: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48" fill="currentColor"><path d="M16 10v28l22-14z"/></svg>'
    };

    // -------------------------------------------------------------------------
    // VSBCustomControls — builds and manages the custom control bar
    // -------------------------------------------------------------------------

    /**
     * @constructor
     * @param {HTMLVideoElement} video   The video element.
     * @param {HTMLElement}     wrapper The .vsb-video-wrapper container.
     * @param {boolean}         showBuffer Whether to show the buffered portion in the progress bar.
     */
    function VSBCustomControls(video, wrapper, showBuffer) {
        this.video = video;
        this.wrapper = wrapper;
        this.showBuffer = (showBuffer !== false);
        this.hideTimer = null;
        this.isDragging = false;
        this.isMobile = false;
        this.speedOptions = [0.5, 0.75, 1, 1.25, 1.5, 2];

        // Detect touch support for mobile behavior.
        this.isMobile = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

        this.buildDOM();
        this.bindEvents();
        this.updateAll();
    }

    /**
     * Build the controls DOM and inject it into the wrapper.
     */
    VSBCustomControls.prototype.buildDOM = function () {
        var self = this;
        var wrapper = this.wrapper;

        // Remove any existing controls (avoid duplicates on re-init).
        var existing = wrapper.querySelector('.vsb-controls');
        if (existing) {
            existing.parentNode.removeChild(existing);
        }
        var existingCenter = wrapper.querySelector('.vsb-center-play');
        if (existingCenter) {
            existingCenter.parentNode.removeChild(existingCenter);
        }

        // -- Center play button (shown on pause) --
        var centerPlay = document.createElement('div');
        centerPlay.className = 'vsb-center-play';
        centerPlay.appendChild(createSVG(ICONS.centerPlay));
        this.centerPlay = centerPlay;
        wrapper.appendChild(centerPlay);

        // -- Controls container --
        var controls = document.createElement('div');
        controls.className = 'vsb-controls';

        // Progress bar row.
        var progressRow = document.createElement('div');
        progressRow.className = 'vsb-controls-progress';

        var progressBar = document.createElement('div');
        progressBar.className = 'vsb-progress-bar';

        // Track background.
        var progressTrack = document.createElement('div');
        progressTrack.className = 'vsb-progress-track';

        // Buffered portion.
        var progressBuffered = document.createElement('div');
        progressBuffered.className = 'vsb-progress-buffered';
        this.progressBuffered = progressBuffered;

        // Played portion.
        var progressPlayed = document.createElement('div');
        progressPlayed.className = 'vsb-progress-played';
        this.progressPlayed = progressPlayed;

        // Transparent input range for click/drag seeking.
        var progressInput = document.createElement('input');
        progressInput.type = 'range';
        progressInput.className = 'vsb-progress-input';
        progressInput.min = '0';
        progressInput.max = '1000';
        progressInput.step = '1';
        progressInput.value = '0';
        progressInput.setAttribute('aria-label', 'Seek');
        this.progressInput = progressInput;

        progressTrack.appendChild(progressBuffered);
        progressTrack.appendChild(progressPlayed);
        progressBar.appendChild(progressTrack);
        progressBar.appendChild(progressInput);
        progressRow.appendChild(progressBar);
        controls.appendChild(progressRow);

        // -- Control bar row --
        var barRow = document.createElement('div');
        barRow.className = 'vsb-controls-bar';

        // Play/Pause button.
        var btnPlay = document.createElement('button');
        btnPlay.className = 'vsb-btn vsb-btn-play';
        btnPlay.setAttribute('aria-label', 'Play');
        btnPlay.innerHTML = ICONS.play + ICONS.pause; // both, CSS shows one
        this.btnPlay = btnPlay;
        btnPlay.addEventListener('click', function (e) {
            e.preventDefault();
            self.togglePlay();
        });

        // Volume button.
        var btnVolume = document.createElement('button');
        btnVolume.className = 'vsb-btn vsb-btn-volume';
        btnVolume.setAttribute('aria-label', 'Volume');
        btnVolume.innerHTML = ICONS.volumeHigh + ICONS.volumeMuted;
        this.btnVolume = btnVolume;
        btnVolume.addEventListener('click', function (e) {
            e.preventDefault();
            self.toggleMute();
        });

        // Volume slider.
        var volumeSlider = document.createElement('div');
        volumeSlider.className = 'vsb-volume-slider';
        var volInput = document.createElement('input');
        volInput.type = 'range';
        volInput.className = 'vsb-volume-input';
        volInput.min = '0';
        volInput.max = '100';
        volInput.step = '1';
        volInput.value = '100';
        volInput.setAttribute('aria-label', 'Volume level');
        this.volInput = volInput;
        volumeSlider.appendChild(volInput);

        // Time display.
        var timeDisplay = document.createElement('span');
        timeDisplay.className = 'vsb-time-display';
        timeDisplay.textContent = '0:00 / 0:00';
        this.timeDisplay = timeDisplay;

        // Speed button + dropdown wrapper.
        var speedWrap = document.createElement('span');
        speedWrap.className = 'vsb-speed-wrap';

        // Speed button.
        var btnSpeed = document.createElement('button');
        btnSpeed.className = 'vsb-btn vsb-btn-speed';
        btnSpeed.setAttribute('aria-label', 'Playback speed');
        btnSpeed.textContent = '1x';
        this.btnSpeed = btnSpeed;

        // Speed dropdown.
        var speedDropdown = document.createElement('div');
        speedDropdown.className = 'vsb-speed-dropdown';
        this.speedDropdown = speedDropdown;
        this.buildSpeedOptions(speedDropdown);
        btnSpeed.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            self.toggleSpeedDropdown();
        });

        speedWrap.appendChild(btnSpeed);
        speedWrap.appendChild(speedDropdown);

        // Fullscreen button.
        var btnFullscreen = document.createElement('button');
        btnFullscreen.className = 'vsb-btn vsb-btn-fullscreen';
        btnFullscreen.setAttribute('aria-label', 'Fullscreen');
        btnFullscreen.innerHTML = ICONS.fullscreen + ICONS.fullscreenExit;
        this.btnFullscreen = btnFullscreen;
        btnFullscreen.addEventListener('click', function (e) {
            e.preventDefault();
            self.toggleFullscreen();
        });

        // Assemble bar.
        barRow.appendChild(btnPlay);
        barRow.appendChild(btnVolume);
        barRow.appendChild(volumeSlider);
        barRow.appendChild(timeDisplay);
        barRow.appendChild(speedWrap);
        barRow.appendChild(btnFullscreen);
        controls.appendChild(barRow);

        wrapper.appendChild(controls);

        // Store references.
        this.controls = controls;
        this.progressBar = progressBar;
        this.volumeSlider = volumeSlider;
    };

    /**
     * Build the speed selector dropdown options.
     *
     * @param {HTMLElement} dropdown
     */
    VSBCustomControls.prototype.buildSpeedOptions = function (dropdown) {
        var self = this;
        // Clear existing.
        dropdown.innerHTML = '';

        for (var i = 0; i < this.speedOptions.length; i++) {
            var speed = this.speedOptions[i];
            var opt = document.createElement('div');
            opt.className = 'vsb-speed-option';
            opt.textContent = speed + 'x';
            opt.setAttribute('data-speed', speed);
            opt.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var s = parseFloat(this.getAttribute('data-speed'));
                self.setPlaybackSpeed(s);
                self.speedDropdown.classList.remove('vsb-speed-dropdown-open');
                self.btnSpeed.textContent = s + 'x';
            });
            dropdown.appendChild(opt);
        }
    };

    // -------------------------------------------------------------------------
    // Event binding
    // -------------------------------------------------------------------------

    VSBCustomControls.prototype.bindEvents = function () {
        var self = this;

        // Video events — update control states.
        this.video.addEventListener('play', function () { self.onPlay(); });
        this.video.addEventListener('pause', function () { self.onPause(); });
        this.video.addEventListener('ended', function () { self.onPause(); });
        this.video.addEventListener('timeupdate', function () { self.onTimeUpdate(); });
        this.video.addEventListener('progress', function () { self.onProgress(); });
        this.video.addEventListener('loadedmetadata', function () { self.onLoadedMetadata(); });
        this.video.addEventListener('volumechange', function () { self.onVolumeChange(); });
        this.video.addEventListener('ratechange', function () { self.onRateChange(); });
        this.video.addEventListener('error', function () { self.onError(); });

        // Seeking via progress input.
        this.progressInput.addEventListener('input', function () { self.onSeekInput(); });
        this.progressInput.addEventListener('change', function () { self.onSeekChange(); });
        // Also handle mousedown/mouseup for drag tracking.
        this.progressInput.addEventListener('mousedown', function () { self.isDragging = true; });
        this.progressInput.addEventListener('mouseup', function () { self.isDragging = false; self.onSeekChange(); });
        this.progressInput.addEventListener('touchstart', function () { self.isDragging = true; });
        this.progressInput.addEventListener('touchend', function () { self.isDragging = false; self.onSeekChange(); });

        // Volume slider.
        this.volInput.addEventListener('input', function () { self.onVolumeInput(); });

        // Click on center play to toggle playback.
        this.centerPlay.addEventListener('click', function (e) {
            e.preventDefault();
            self.togglePlay();
        });

        // Click on video to toggle play/pause.
        this.video.addEventListener('click', function (e) {
            e.preventDefault();
            self.togglePlay();
        });

        // Mouse movement on wrapper for auto-hide.
        if (!this.isMobile) {
            this.wrapper.addEventListener('mousemove', function () { self.showControls(); });
            this.wrapper.addEventListener('mouseenter', function () { self.showControls(); });
            this.wrapper.addEventListener('mouseleave', function () {
                if (!self.video.paused) {
                    self.hideControls();
                }
            });
        }

        // Keyboard shortcuts.
        this.video.addEventListener('keydown', function (e) { self.onKeyDown(e); });
        // Make video element focusable for keyboard events.
        if (!this.video.hasAttribute('tabindex')) {
            this.video.setAttribute('tabindex', '0');
            this.video.style.outline = 'none';
        }

        // Close speed dropdown on outside click.
        document.addEventListener('click', function (e) {
            if (self.speedDropdown.classList.contains('vsb-speed-dropdown-open')) {
                if (!self.btnSpeed.contains(e.target) && !self.speedDropdown.contains(e.target)) {
                    self.speedDropdown.classList.remove('vsb-speed-dropdown-open');
                }
            }
        });

        // Fullscreen change event (e.g., user presses Esc).
        document.addEventListener('fullscreenchange', function () { self.onFullscreenChange(); });
        document.addEventListener('webkitfullscreenchange', function () { self.onFullscreenChange(); });
        document.addEventListener('mozfullscreenchange', function () { self.onFullscreenChange(); });
        document.addEventListener('MSFullscreenChange', function () { self.onFullscreenChange(); });

        // If mobile: controls always visible, remove auto-hide.
        if (this.isMobile) {
            this.controls.classList.add('vsb-controls-visible');
        }

        // Initial volume from video.
        this.volInput.value = Math.round(this.video.volume * 100);
    };

    // -------------------------------------------------------------------------
    // Control actions
    // -------------------------------------------------------------------------

    VSBCustomControls.prototype.togglePlay = function () {
        if (this.video.paused || this.video.ended) {
            this.video.play().catch(function () {
                // Play was prevented (e.g., autoplay policy). That's OK.
            });
        } else {
            this.video.pause();
        }
    };

    VSBCustomControls.prototype.toggleMute = function () {
        this.video.muted = !this.video.muted;
    };

    VSBCustomControls.prototype.toggleFullscreen = function () {
        var el = this.wrapper;
        if (this.isFullscreen()) {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        } else {
            if (el.requestFullscreen) {
                el.requestFullscreen();
            } else if (el.webkitRequestFullscreen) {
                el.webkitRequestFullscreen();
            } else if (el.mozRequestFullScreen) {
                el.mozRequestFullScreen();
            } else if (el.msRequestFullscreen) {
                el.msRequestFullscreen();
            }
        }
    };

    VSBCustomControls.prototype.isFullscreen = function () {
        return !!(document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.mozFullScreenElement ||
            document.msFullscreenElement);
    };

    VSBCustomControls.prototype.setPlaybackSpeed = function (speed) {
        this.video.playbackRate = speed;
    };

    VSBCustomControls.prototype.toggleSpeedDropdown = function () {
        this.speedDropdown.classList.toggle('vsb-speed-dropdown-open');
    };

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    VSBCustomControls.prototype.onPlay = function () {
        this.btnPlay.classList.add('vsb-playing');
        this.centerPlay.classList.remove('vsb-center-play-visible');
        if (!this.isMobile) {
            this.startHideTimer();
        }
    };

    VSBCustomControls.prototype.onPause = function () {
        this.btnPlay.classList.remove('vsb-playing');
        this.centerPlay.classList.add('vsb-center-play-visible');
        this.showControls();
    };

    VSBCustomControls.prototype.onTimeUpdate = function () {
        if (this.isDragging) {
            return; // Don't fight the user while they're dragging.
        }
        this.updateProgress();
        this.updateTimeDisplay();
    };

    VSBCustomControls.prototype.onProgress = function () {
        this.updateBufferProgress();
    };

    VSBCustomControls.prototype.onLoadedMetadata = function () {
        this.updateAll();
    };

    VSBCustomControls.prototype.onVolumeChange = function () {
        var vol = this.video.muted ? 0 : Math.round(this.video.volume * 100);
        this.volInput.value = vol;
        if (this.video.muted || vol === 0) {
            this.btnVolume.classList.add('vsb-muted');
        } else {
            this.btnVolume.classList.remove('vsb-muted');
        }
    };

    VSBCustomControls.prototype.onRateChange = function () {
        var rate = this.video.playbackRate;
        this.btnSpeed.textContent = rate + 'x';
    };

    VSBCustomControls.prototype.onError = function () {
        // Show error in the controls area.
        this.controls.classList.add('vsb-controls-error');
        var timeDisplay = this.timeDisplay;
        if (timeDisplay && !this.controls.querySelector('.vsb-error-text')) {
            var errSpan = document.createElement('span');
            errSpan.className = 'vsb-error-text';
            errSpan.textContent = 'Video failed to load.';
            timeDisplay.parentNode.insertBefore(errSpan, timeDisplay.nextSibling);
        }
    };

    VSBCustomControls.prototype.onSeekInput = function () {
        var val = parseInt(this.progressInput.value, 10);
        var pct = val / 1000;
        this.progressPlayed.style.width = (pct * 100) + '%';
        this.updateTimeDisplayForSeek(pct);
    };

    VSBCustomControls.prototype.onSeekChange = function () {
        var val = parseInt(this.progressInput.value, 10);
        var pct = val / 1000;
        if (this.video.duration && isFinite(this.video.duration)) {
            this.video.currentTime = pct * this.video.duration;
        }
        this.isDragging = false;
    };

    VSBCustomControls.prototype.onVolumeInput = function () {
        var vol = parseInt(this.volInput.value, 10) / 100;
        this.video.volume = vol;
        this.video.muted = (vol === 0);
    };

    VSBCustomControls.prototype.onFullscreenChange = function () {
        if (this.isFullscreen()) {
            this.btnFullscreen.classList.add('vsb-fullscreen-active');
        } else {
            this.btnFullscreen.classList.remove('vsb-fullscreen-active');
        }
    };

    // -------------------------------------------------------------------------
    // Keyboard support
    // -------------------------------------------------------------------------

    /**
     * Handle keyboard shortcuts.
     *
     * Space      = play/pause
     * Left/Right = skip 5s
     * F          = fullscreen
     * M          = mute
     * Up/Down    = volume up/down 5%
     *
     * @param {KeyboardEvent} e
     */
    VSBCustomControls.prototype.onKeyDown = function (e) {
        // Don't capture if user is typing in an input.
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
            return;
        }

        switch (e.key) {
            case ' ':
            case 'Spacebar':
                e.preventDefault();
                this.togglePlay();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                this.skipTime(-5);
                break;
            case 'ArrowRight':
                e.preventDefault();
                this.skipTime(5);
                break;
            case 'f':
            case 'F':
                e.preventDefault();
                this.toggleFullscreen();
                break;
            case 'm':
            case 'M':
                e.preventDefault();
                this.toggleMute();
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.adjustVolume(0.05);
                break;
            case 'ArrowDown':
                e.preventDefault();
                this.adjustVolume(-0.05);
                break;
        }
    };

    VSBCustomControls.prototype.skipTime = function (delta) {
        if (this.video.duration && isFinite(this.video.duration)) {
            this.video.currentTime = Math.max(0, Math.min(this.video.duration, this.video.currentTime + delta));
        }
    };

    VSBCustomControls.prototype.adjustVolume = function (delta) {
        var newVol = Math.max(0, Math.min(1, this.video.volume + delta));
        this.video.volume = newVol;
        this.video.muted = (newVol === 0);
        this.volInput.value = Math.round(newVol * 100);
    };

    // -------------------------------------------------------------------------
    // Auto-hide behavior
    // -------------------------------------------------------------------------

    VSBCustomControls.prototype.showControls = function () {
        this.controls.classList.remove('vsb-controls-hidden');
        this.controls.classList.add('vsb-controls-visible');
        if (!this.video.paused && !this.isMobile) {
            this.startHideTimer();
        }
    };

    VSBCustomControls.prototype.hideControls = function () {
        if (this.isMobile) {
            return;
        }
        if (!this.video.paused) {
            this.controls.classList.remove('vsb-controls-visible');
            this.controls.classList.add('vsb-controls-hidden');
        }
    };

    VSBCustomControls.prototype.startHideTimer = function () {
        var self = this;
        if (this.hideTimer) {
            clearTimeout(this.hideTimer);
        }
        this.hideTimer = setTimeout(function () {
            self.hideControls();
        }, 3000);
    };

    // -------------------------------------------------------------------------
    // UI updates
    // -------------------------------------------------------------------------

    VSBCustomControls.prototype.updateAll = function () {
        this.updateProgress();
        this.updateTimeDisplay();
        this.updateBufferProgress();
        this.updatePlayButtonState();
    };

    VSBCustomControls.prototype.updatePlayButtonState = function () {
        if (this.video.paused) {
            this.btnPlay.classList.remove('vsb-playing');
            this.centerPlay.classList.add('vsb-center-play-visible');
        } else {
            this.btnPlay.classList.add('vsb-playing');
            this.centerPlay.classList.remove('vsb-center-play-visible');
        }
    };

    VSBCustomControls.prototype.updateProgress = function () {
        if (!this.video.duration || !isFinite(this.video.duration)) {
            this.progressPlayed.style.width = '0%';
            this.progressInput.value = 0;
            return;
        }
        var pct = (this.video.currentTime / this.video.duration) * 100;
        this.progressPlayed.style.width = pct + '%';
        this.progressInput.value = Math.round((this.video.currentTime / this.video.duration) * 1000);
    };

    VSBCustomControls.prototype.updateBufferProgress = function () {
        if (!this.showBuffer) {
            this.progressBuffered.style.width = '0%';
            return;
        }
        if (!this.video.duration || !isFinite(this.video.duration)) {
            this.progressBuffered.style.width = '0%';
            return;
        }
        try {
            if (this.video.buffered.length > 0) {
                var bufferedEnd = this.video.buffered.end(this.video.buffered.length - 1);
                var progress = (bufferedEnd / this.video.duration) * 100;
                progress = Math.min(100, Math.max(0, progress));
                this.progressBuffered.style.width = progress + '%';
                // Also set CSS custom property on the progress bar for external theming.
                this.progressBar.style.setProperty('--vsb-buffer-progress', progress + '%');
            }
        } catch (e) {
            this.progressBuffered.style.width = '0%';
        }
    };

    VSBCustomControls.prototype.updateTimeDisplay = function () {
        var current = this.video.currentTime || 0;
        var duration = this.video.duration || 0;
        this.timeDisplay.textContent = formatTime(current) + ' / ' + formatTime(duration);
    };

    VSBCustomControls.prototype.updateTimeDisplayForSeek = function (pct) {
        var duration = this.video.duration || 0;
        var seekTime = pct * duration;
        this.timeDisplay.textContent = formatTime(seekTime) + ' / ' + formatTime(duration);
    };

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Initialize custom controls for all matching wrappers on the page.
     */
    function initCustomControls() {
        var wrappers = document.querySelectorAll('.vsb-video-wrapper.vsb-custom-controls');

        for (var i = 0; i < wrappers.length; i++) {
            var wrapper = wrappers[i];
            var video = wrapper.querySelector('video');

            if (!video) {
                continue;
            }

            // Check if it already has custom controls initialized.
            if (video._vsbCustomControls) {
                continue;
            }

            // Determine if buffer bar should be shown.
            var showBuffer = wrapper.classList.contains('vsb-show-buffer');

            // Build custom controls.
            var controls = new VSBCustomControls(video, wrapper, showBuffer);
            video._vsbCustomControls = controls;
        }
    }

    /**
     * Initialize legacy buffer bars for non-custom-controls wrappers.
     */
    function initLegacyBufferBars() {
        var wrappers = document.querySelectorAll('.vsb-video-wrapper.vsb-show-buffer:not(.vsb-custom-controls)');

        for (var i = 0; i < wrappers.length; i++) {
            var wrapper = wrappers[i];
            var video = wrapper.querySelector('video');
            var bar = wrapper.querySelector('.vsb-buffer-bar-fill');

            if (video && bar) {
                initBufferBar(video, bar);
            }
            if (video) {
                handleVideoErrorLegacy(video);
            }
        }
    }

    /**
     * Legacy: initialize buffer progress for a single video.
     */
    function initBufferBar(video, bar) {
        if (!video || !bar) {
            return;
        }
        bar.classList.add('vsb-buffering');

        function updateBuffer() {
            if (!video.duration || !isFinite(video.duration)) {
                bar.style.setProperty('--vsb-buffer-progress', '0%');
                return;
            }
            try {
                if (video.buffered.length > 0) {
                    var bufferedEnd = video.buffered.end(video.buffered.length - 1);
                    var progress = (bufferedEnd / video.duration) * 100;
                    progress = Math.min(100, Math.max(0, progress));
                    bar.style.setProperty('--vsb-buffer-progress', progress + '%');
                }
            } catch (e) {
                bar.style.setProperty('--vsb-buffer-progress', '0%');
            }
        }

        video.addEventListener('progress', updateBuffer);
        video.addEventListener('loadedmetadata', updateBuffer);
        video.addEventListener('seeked', updateBuffer);
        updateBuffer();
    }

    /**
     * Legacy: handle video load errors.
     */
    function handleVideoErrorLegacy(video) {
        video.addEventListener('error', function () {
            var wrapper = video.closest('.vsb-video-wrapper');
            if (!wrapper) return;

            if (!wrapper.querySelector('.vsb-error-message')) {
                var errorMsg = document.createElement('div');
                errorMsg.className = 'vsb-error vsb-error-message';
                errorMsg.textContent = 'Video failed to load. The file may be missing or inaccessible.';
                var bufferBar = wrapper.querySelector('.vsb-buffer-bar');
                if (bufferBar) {
                    bufferBar.insertAdjacentElement('afterend', errorMsg);
                } else {
                    wrapper.appendChild(errorMsg);
                }
            }
        });
    }

    // Run on DOM ready.
    function initAll() {
        initCustomControls();
        initLegacyBufferBars();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
