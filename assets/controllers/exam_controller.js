import { Controller } from '@hotwired/stimulus';

/**
 * Drives the candidate exam experience:
 * - Server-relative countdown timer
 * - Debounced autosave via POST JSON
 * - Multi-question navigation (all cards rendered, one shown)
 * - Anti-cheat telemetry (tab visibility, fullscreen changes)
 * - Confirm/expired modals
 * - Image zoom modal
 */
export default class extends Controller {
  static targets = [
    'timer', 'progressBar', 'saveStatus',
    'questionCard', 'navBtn', 'unansweredCount',
    'submitBtn', 'confirmModal', 'confirmSubmitBtn', 'modalUnanswered', 'submitForm',
    'expiredModal', 'imageModal', 'modalImage',
  ];

  static values = {
    remainingSeconds: Number,
    saveUrl: String,
    submitUrl: String,
    telemetryUrl: String,
    candidateCode: String,
  };

  connect() {
    this._currentIndex = 0;
    this._saveQueue = {}; // {questionId: optionId}
    this._saveTimer = null;
    this._expired = false;

    // Load previously saved answers from embedded JSON
    try {
      this._savedAnswers = JSON.parse(
        document.getElementById('exam-saved-answers')?.textContent ?? '{}'
      );
    } catch {
      this._savedAnswers = {};
    }

    this._totalQuestions = parseInt(
      document.getElementById('exam-question-count')?.textContent ?? '0', 10
    );

    this._startTimer(this.remainingSecondsValue);
    this._updateNavState();
    this._updateUnansweredCount();
    this._updateProgressBar();
    this._attachAntiCheatListeners();
  }

  disconnect() {
    clearInterval(this._timerInterval);
    clearTimeout(this._saveTimer);
    document.removeEventListener('visibilitychange', this._onVisibility);
    document.removeEventListener('fullscreenchange', this._onFullscreen);
  }

  // ── Timer ──────────────────────────────────────────────────────────────────

  _startTimer(seconds) {
    let remaining = Math.max(0, seconds);
    this._renderTimer(remaining);

    this._timerInterval = setInterval(() => {
      remaining -= 1;
      this._renderTimer(remaining);
      if (remaining <= 0) {
        clearInterval(this._timerInterval);
        this._onExpired();
      }
    }, 1000);
  }

  _renderTimer(seconds) {
    const m = Math.floor(seconds / 60).toString().padStart(2, '0');
    const s = (seconds % 60).toString().padStart(2, '0');
    const display = `${m}:${s}`;
    if (this.hasTimerTarget) this.timerTarget.textContent = display;

    // Warn when < 5 minutes
    if (this.hasTimerTarget) {
      this.timerTarget.classList.toggle('text-amber-400', seconds < 300 && seconds > 60);
      this.timerTarget.classList.toggle('text-red-400', seconds <= 60);
    }
  }

  _onExpired() {
    this._expired = true;
    if (this.hasSubmitBtnTarget) this.submitBtnTarget.disabled = true;
    if (this.hasExpiredModalTarget) {
      this.expiredModalTarget.classList.remove('hidden');
      this.expiredModalTarget.classList.add('flex');
    }
  }

  // ── Question navigation ───────────────────────────────────────────────────

  goToQuestion(event) {
    const idx = parseInt(event.currentTarget.dataset.questionIndex, 10);
    this._showQuestion(idx);
  }

  nextQuestion() {
    if (this._currentIndex < this.questionCardTargets.length - 1) {
      this._showQuestion(this._currentIndex + 1);
    }
  }

  prevQuestion() {
    if (this._currentIndex > 0) {
      this._showQuestion(this._currentIndex - 1);
    }
  }

  _showQuestion(index) {
    this.questionCardTargets.forEach((card, i) => {
      card.classList.toggle('hidden', i !== index);
    });
    this._currentIndex = index;
    this._updateNavState();
    this._updateProgressBar();
    // Scroll to top of question on mobile
    this.element.querySelector('.question-card:not(.hidden)')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  _updateNavState() {
    this.navBtnTargets.forEach((btn, i) => {
      const qid = parseInt(btn.dataset.qid, 10);
      const answered = this._savedAnswers[qid] != null;
      const isCurrent = i === this._currentIndex;
      btn.classList.toggle('text-zinc-200', isCurrent || answered);
      btn.classList.toggle('bg-zinc-800', isCurrent);
      btn.classList.toggle('text-emerald-400', answered && !isCurrent);
    });
  }

  _updateProgressBar() {
    const pct = this._totalQuestions > 0
      ? Math.round(((this._currentIndex + 1) / this._totalQuestions) * 100)
      : 0;
    if (this.hasProgressBarTarget) {
      this.progressBarTarget.style.width = pct + '%';
    }
  }

  _updateUnansweredCount() {
    const answered = Object.values(this._savedAnswers).filter(v => v != null).length;
    const count = this._totalQuestions - answered;
    this.unansweredCountTargets.forEach(el => { el.textContent = count; });
    return count;
  }

  // ── Answer autosave ───────────────────────────────────────────────────────

  answerChanged(event) {
    if (this._expired) return;
    const qid = parseInt(event.currentTarget.dataset.qid, 10);
    const oid = parseInt(event.currentTarget.dataset.oid, 10);

    this._savedAnswers[qid] = oid;
    this._saveQueue[qid] = oid;
    this._updateNavState();
    this._updateUnansweredCount();
    this._scheduleFlush();
    this._setSaveStatus('saving');
  }

  _scheduleFlush() {
    clearTimeout(this._saveTimer);
    this._saveTimer = setTimeout(() => this._flush(), 800);
  }

  async _flush() {
    const entries = Object.entries(this._saveQueue);
    if (entries.length === 0) return;
    this._saveQueue = {};

    for (const [qid, oid] of entries) {
      await this._saveOne(parseInt(qid, 10), oid);
    }
  }

  async _saveOne(questionId, optionId) {
    try {
      const res = await fetch(this.saveUrlValue, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...this._csrfHeaders() },
        body: JSON.stringify({ question_id: questionId, option_id: optionId }),
      });

      if (res.status === 410) {
        // Expired on server
        this._onExpired();
        return;
      }

      if (!res.ok) {
        this._setSaveStatus('failed');
        return;
      }

      this._setSaveStatus('saved');
    } catch {
      this._setSaveStatus('failed');
    }
  }

  _setSaveStatus(state) {
    if (!this.hasSaveStatusTarget) return;
    const map = { saving: '⟳ Saqlanmoqda…', saved: '✓ Saqlandi', failed: '⚠ Xato — qaytadan urinilmoqda' };
    this.saveStatusTarget.textContent = map[state] ?? '';
    this.saveStatusTarget.className = [
      'font-mono text-[11px] hidden sm:block',
      state === 'failed' ? 'text-amber-400' : 'text-zinc-500',
    ].join(' ');
  }

  _csrfHeaders() {
    // Use the csrf_protection_controller's generateCsrfHeaders if available
    return {};
  }

  // ── Submit flow ───────────────────────────────────────────────────────────

  confirmSubmit() {
    if (this._expired) return;
    const count = this._updateUnansweredCount();
    if (this.hasModalUnansweredTarget) this.modalUnansweredTarget.textContent = count;
    if (this.hasConfirmModalTarget) {
      this.confirmModalTarget.classList.remove('hidden');
      this.confirmModalTarget.classList.add('flex');
    }
  }

  cancelSubmit() {
    if (this.hasConfirmModalTarget) {
      this.confirmModalTarget.classList.add('hidden');
      this.confirmModalTarget.classList.remove('flex');
    }
  }

  // ── Image modal ───────────────────────────────────────────────────────────

  openImageModal(event) {
    const img = event.currentTarget;
    if (this.hasModalImageTarget) {
      this.modalImageTarget.src = img.src;
      this.modalImageTarget.alt = img.alt;
    }
    if (this.hasImageModalTarget) {
      this.imageModalTarget.classList.remove('hidden');
      this.imageModalTarget.classList.add('flex');
    }
  }

  closeImageModal() {
    if (this.hasImageModalTarget) {
      this.imageModalTarget.classList.add('hidden');
      this.imageModalTarget.classList.remove('flex');
    }
  }

  // ── Anti-cheat telemetry ──────────────────────────────────────────────────

  _attachAntiCheatListeners() {
    this._onVisibility = () => {
      this._sendTelemetry('visibility_change', { hidden: document.hidden });
    };
    this._onFullscreen = () => {
      this._sendTelemetry('fullscreen_change', { active: !!document.fullscreenElement });
    };
    document.addEventListener('visibilitychange', this._onVisibility);
    document.addEventListener('fullscreenchange', this._onFullscreen);
  }

  _sendTelemetry(type, data) {
    navigator.sendBeacon(
      this.telemetryUrlValue,
      JSON.stringify({ event_type: type, data }),
    );
  }
}
