import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = [
    'particleCanvas',
    'waveformCanvas',
    'audio',
    'entryOverlay',
    'portrait',
    'playButton',
    'playIcon',
    'pauseIcon',
    'replayIcon',
    'muteButton',
    'muteIcon',
    'unmuteIcon',
    'fullscreenButton',
    'statusText',
    'errorMessage'
  ];

  connect() {
    this.isAudioStarted = false;
    this.isPlaying = false;
    this.audioContext = null;
    this.analyser = null;
    this.sourceNode = null;
    this.animationFrameId = null;
    this.waveformFrameId = null;
    this.particles = [];
    this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    this.initCanvasSize();
    this.initParticles();
    this.startParticleLoop();

    this.boundResize = this.onResize.bind(this);
    this.boundVisibility = this.onVisibilityChange.bind(this);
    window.addEventListener('resize', this.boundResize);
    document.addEventListener('visibilitychange', this.boundVisibility);

    this.initAudioEventListeners();
  }

  disconnect() {
    window.removeEventListener('resize', this.boundResize);
    document.removeEventListener('visibilitychange', this.boundVisibility);

    if (this.animationFrameId) {
      cancelAnimationFrame(this.animationFrameId);
    }
    if (this.waveformFrameId) {
      cancelAnimationFrame(this.waveformFrameId);
    }
    if (this.audioContext && this.audioContext.state !== 'closed') {
      this.audioContext.close();
    }
  }

  onResize() {
    this.initCanvasSize();
    this.initParticles();
  }

  onVisibilityChange() {
    if (document.hidden) {
      if (this.animationFrameId) {
        cancelAnimationFrame(this.animationFrameId);
        this.animationFrameId = null;
      }
      if (this.waveformFrameId) {
        cancelAnimationFrame(this.waveformFrameId);
        this.waveformFrameId = null;
      }
    } else {
      if (!this.animationFrameId) {
        this.startParticleLoop();
      }
      if (this.isAudioStarted && !this.waveformFrameId) {
        this.drawWaveform();
      }
    }
  }

  initCanvasSize() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);

    if (this.hasParticleCanvasTarget) {
      const canvas = this.particleCanvasTarget;
      this.width = canvas.parentElement.clientWidth;
      this.height = canvas.parentElement.clientHeight;
      canvas.width = this.width * dpr;
      canvas.height = this.height * dpr;
      this.pCtx = canvas.getContext('2d');
      this.pCtx.scale(dpr, dpr);
    }

    if (this.hasWaveformCanvasTarget) {
      const wCanvas = this.waveformCanvasTarget;
      this.wWidth = wCanvas.parentElement.clientWidth;
      this.wHeight = 48;
      wCanvas.width = this.wWidth * dpr;
      wCanvas.height = this.wHeight * dpr;
      this.wCtx = wCanvas.getContext('2d');
      this.wCtx.scale(dpr, dpr);
    }
  }

  initParticles() {
    this.particles = [];
    const count = Math.min(Math.floor((this.width * this.height) / 8000), 160);

    for (let i = 0; i < count; i++) {
      this.particles.push({
        x: Math.random() * this.width,
        y: Math.random() * this.height,
        baseY: Math.random() * this.height,
        size: Math.random() * 1.6 + 0.4,
        speed: (Math.random() * 0.2 + 0.08) * (this.reducedMotion ? 0 : 1),
        waveOffset: Math.random() * Math.PI * 2,
        waveFreq: Math.random() * 0.008 + 0.003,
        waveAmp: Math.random() * 12 + 4,
        alphaBase: Math.random() * 0.4 + 0.2
      });
    }
  }

  startParticleLoop() {
    let lastTime = performance.now();

    const render = (currentTime) => {
      const dt = Math.min((currentTime - lastTime) / 1000, 0.1);
      lastTime = currentTime;

      this.pCtx.clearRect(0, 0, this.width, this.height);

      const time = currentTime * 0.001;

      for (let i = 0; i < this.particles.length; i++) {
        const p = this.particles[i];

        if (!this.reducedMotion) {
          p.baseY -= p.speed * 20 * dt;
          if (p.baseY < -10) {
            p.baseY = this.height + 10;
            p.x = Math.random() * this.width;
          }
          // Mathematical slow horizontal wave
          p.y = p.baseY + Math.sin(time * 0.8 + p.waveOffset + p.x * p.waveFreq) * p.waveAmp;
        }

        // Particle radiance: brighter near the top, fading towards bottom
        const depthFactor = Math.max(0, 1 - (p.y / this.height));
        const alpha = p.alphaBase * (depthFactor * 0.85 + 0.15);

        this.pCtx.fillStyle = `rgba(228, 228, 231, ${alpha.toFixed(3)})`;
        this.pCtx.beginPath();
        this.pCtx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
        this.pCtx.fill();
      }

      this.animationFrameId = requestAnimationFrame(render);
    };

    this.animationFrameId = requestAnimationFrame(render);
  }

  // User click on "Boshlash"
  async startExperience() {
    // Request fullscreen with graceful fallback
    this.requestFullscreenGracefully();

    // Unlock / initialize Web Audio
    try {
      this.initAudioContext();
    } catch (err) {
      console.warn('AudioContext initialization note:', err);
    }

    // Fade out entry overlay
    if (this.hasEntryOverlayTarget) {
      this.entryOverlayTarget.classList.add('opacity-0', 'pointer-events-none');
      setTimeout(() => {
        this.entryOverlayTarget.style.display = 'none';
      }, 700);
    }

    // Reveal portrait smoothly
    if (this.hasPortraitTarget) {
      this.portraitTarget.classList.remove('opacity-30', 'brightness-50');
      this.portraitTarget.classList.add('opacity-100', 'brightness-100');
    }

    // Start audio
    if (this.hasAudioTarget) {
      try {
        await this.audioTarget.play();
        this.isAudioStarted = true;
        this.isPlaying = true;
        this.updatePlayStateUI(true);
        this.drawWaveform();
      } catch (err) {
        console.error('Audio playback error:', err);
        this.showErrorMessage("Ovozni ijro etishda muammo yuz berdi. Tugmani bosib qayta urinib ko'ring.");
      }
    }
  }

  initAudioContext() {
    if (this.audioContext) {
      if (this.audioContext.state === 'suspended') {
        this.audioContext.resume();
      }
      return;
    }

    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) {
      return;
    }

    this.audioContext = new AudioContextClass();
    this.analyser = this.audioContext.createAnalyser();
    this.analyser.fftSize = 256;
    this.analyser.smoothingTimeConstant = 0.82;

    if (this.hasAudioTarget) {
      try {
        this.sourceNode = this.audioContext.createMediaElementSource(this.audioTarget);
        this.sourceNode.connect(this.analyser);
        this.analyser.connect(this.audioContext.destination);
      } catch (e) {
        console.warn('MediaElementSource already connected or error:', e);
      }
    }

    this.dataArray = new Uint8Array(this.analyser.frequencyBinCount);
  }

  drawWaveform() {
    if (!this.hasWaveformCanvasTarget || !this.wCtx) {
      return;
    }

    const canvas = this.waveformCanvasTarget;
    const width = this.wWidth;
    const height = this.wHeight;
    const centerY = height / 2;

    const renderWave = () => {
      this.wCtx.clearRect(0, 0, width, height);

      let hasSignal = false;

      if (this.analyser && this.isPlaying) {
        this.analyser.getByteTimeDomainData(this.dataArray);
        for (let i = 0; i < this.dataArray.length; i++) {
          if (Math.abs(this.dataArray[i] - 128) > 2) {
            hasSignal = true;
            break;
          }
        }
      }

      this.wCtx.lineWidth = 1.2;
      this.wCtx.strokeStyle = hasSignal ? 'rgba(244, 244, 245, 0.85)' : 'rgba(161, 161, 170, 0.35)';

      this.wCtx.beginPath();

      const sliceWidth = width / (this.dataArray ? this.dataArray.length : 128);
      let x = 0;

      if (hasSignal && this.dataArray) {
        for (let i = 0; i < this.dataArray.length; i++) {
          const v = this.dataArray[i] / 128.0; // 0 to 2, 1 is center
          const y = v * centerY;

          if (i === 0) {
            this.wCtx.moveTo(x, y);
          } else {
            this.wCtx.lineTo(x, y);
          }

          x += sliceWidth;
        }
      } else {
        // Subtle resting line with micro-shimmer
        const t = performance.now() * 0.002;
        for (let i = 0; i <= width; i += 4) {
          const shimmer = Math.sin(t + i * 0.04) * (hasSignal ? 2 : 0.6);
          const y = centerY + shimmer;
          if (i === 0) {
            this.wCtx.moveTo(i, y);
          } else {
            this.wCtx.lineTo(i, y);
          }
        }
      }

      this.wCtx.stroke();

      this.waveformFrameId = requestAnimationFrame(renderWave);
    };

    if (this.waveformFrameId) {
      cancelAnimationFrame(this.waveformFrameId);
    }
    this.waveformFrameId = requestAnimationFrame(renderWave);
  }

  togglePlay() {
    if (!this.hasAudioTarget) return;

    if (this.audioContext && this.audioContext.state === 'suspended') {
      this.audioContext.resume();
    }

    if (this.audioTarget.ended) {
      this.audioTarget.currentTime = 0;
      this.audioTarget.play();
      this.isPlaying = true;
      this.updatePlayStateUI(true);
      return;
    }

    if (this.audioTarget.paused) {
      this.audioTarget.play();
      this.isPlaying = true;
      this.updatePlayStateUI(true);
    } else {
      this.audioTarget.pause();
      this.isPlaying = false;
      this.updatePlayStateUI(false);
    }
  }

  toggleMute() {
    if (!this.hasAudioTarget) return;

    this.audioTarget.muted = !this.audioTarget.muted;
    const isMuted = this.audioTarget.muted;

    if (this.hasMuteIconTarget && this.hasUnmuteIconTarget) {
      if (isMuted) {
        this.muteIconTarget.classList.add('hidden');
        this.unmuteIconTarget.classList.remove('hidden');
      } else {
        this.muteIconTarget.classList.remove('hidden');
        this.unmuteIconTarget.classList.add('hidden');
      }
    }

    if (this.hasMuteButtonTarget) {
      this.muteButtonTarget.setAttribute('aria-label', isMuted ? "Ovozni yoqish" : "Ovozni o'chirish");
    }
  }

  toggleFullscreen() {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch((err) => {
        console.warn('Fullscreen request failed:', err);
      });
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      }
    }
  }

  requestFullscreenGracefully() {
    if (document.fullscreenEnabled && !document.fullscreenElement) {
      const promise = document.documentElement.requestFullscreen();
      if (promise && promise.catch) {
        promise.catch(() => {
          // Gracefully continue without fullscreen
        });
      }
    }
  }

  initAudioEventListeners() {
    if (!this.hasAudioTarget) return;

    this.audioTarget.addEventListener('ended', () => {
      this.isPlaying = false;
      this.updatePlayStateUI(false, true);
      if (this.hasStatusTextTarget) {
        this.statusTextTarget.textContent = 'IJRO YAKUNLANDI // QAYTA TINGLASH';
      }
    });

    this.audioTarget.addEventListener('play', () => {
      this.isPlaying = true;
      this.updatePlayStateUI(true);
      if (this.hasStatusTextTarget) {
        this.statusTextTarget.textContent = 'AUDIO // 44.1 kHz // ANALYSER';
      }
    });

    this.audioTarget.addEventListener('pause', () => {
      this.isPlaying = false;
      this.updatePlayStateUI(false);
    });

    this.audioTarget.addEventListener('error', (e) => {
      console.error('Audio loading error:', e);
      this.showErrorMessage("Audio faylini yuklashda xatolik yuz berdi.");
    });
  }

  updatePlayStateUI(isPlaying, isEnded = false) {
    if (!this.hasPlayIconTarget || !this.hasPauseIconTarget) return;

    if (isEnded && this.hasReplayIconTarget) {
      this.playIconTarget.classList.add('hidden');
      this.pauseIconTarget.classList.add('hidden');
      this.replayIconTarget.classList.remove('hidden');
      if (this.hasPlayButtonTarget) {
        this.playButtonTarget.setAttribute('aria-label', "Qayta tinglash");
      }
      return;
    }

    if (this.hasReplayIconTarget) {
      this.replayIconTarget.classList.add('hidden');
    }

    if (isPlaying) {
      this.playIconTarget.classList.add('hidden');
      this.pauseIconTarget.classList.remove('hidden');
      if (this.hasPlayButtonTarget) {
        this.playButtonTarget.setAttribute('aria-label', "Pauza");
      }
    } else {
      this.playIconTarget.classList.remove('hidden');
      this.pauseIconTarget.classList.add('hidden');
      if (this.hasPlayButtonTarget) {
        this.playButtonTarget.setAttribute('aria-label', "Ijro etish");
      }
    }
  }

  showErrorMessage(msg) {
    if (this.hasErrorMessageTarget) {
      this.errorMessageTarget.textContent = msg;
      this.errorMessageTarget.classList.remove('hidden');
    }
  }
}
