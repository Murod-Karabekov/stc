import { Controller } from '@hotwired/stimulus';

/**
 * Drives the candidate exam experience.
 *
 * Autosave invariants:
 *  - "Saqlandi" only appears after the server confirms ALL pending saves.
 *  - If a flush is already in-flight, new answers are held in _saveQueue and
 *    flushed immediately when the in-flight batch finishes.
 *  - On network failure, failed answers are re-queued and retried up to
 *    MAX_RETRIES times with exponential back-off.
 *  - _lastServerRevision is tracked and sent with every save so the server can
 *    detect stale writes from a second browser tab (server returns 409).
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

  static MAX_RETRIES = 3;

  connect() {
    this._currentIndex = 0;
    this._saveQueue = {};      // {questionId: optionId}  — pending but not yet sent
    this._retryCount = {};     // {questionId: retries}
    this._debounceTimer = null;
    this._flushInProgress = false;
    this._pendingFlush = false;
    this._expired = false;
    this._lastServerRevision = 0;

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
    clearTimeout(this._debounceTimer);
    document.removeEventListener('visibilitychange', this._onVisibility);
    document.removeEventListener('fullscreenchange', this._onFullscreen);
  }

  // ── Timer ─────────────────────────────────────────────────────────────────

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
    if (this.hasTimerTarget) this.timerTarget.textContent = `${m}:${s}`;
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
    this._showQuestion(parseInt(event.currentTarget.dataset.questionIndex, 10));
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
    this.questionCardTargets.forEach((card, i) => card.classList.toggle('hidden', i !== index));
    this._currentIndex = index;
    this._updateNavState();
    this._updateProgressBar();
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
    if (this.hasProgressBarTarget) this.progressBarTarget.style.width = pct + '%';
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

    // Optimistically update local state so nav and count are correct immediately.
    this._savedAnswers[qid] = oid;
    this._saveQueue[qid] = oid;
    this._updateNavState();
    this._updateUnansweredCount();
    this._scheduleFlush();
    this._setSaveStatus('saving');
  }

  _scheduleFlush() {
    clearTimeout(this._debounceTimer);
    this._debounceTimer = setTimeout(() => this._flush(), 800);
  }

  async _flush() {
    if (this._flushInProgress) {
      // Another flush is running; mark that we need another pass when it finishes.
      this._pendingFlush = true;
      return;
    }

    const entries = Object.entries(this._saveQueue);
    if (entries.length === 0) return;

    this._saveQueue = {};
    this._flushInProgress = true;
    this._pendingFlush = false;

    let allOk = true;
    for (const [qid, oid] of entries) {
      const ok = await this._saveOne(parseInt(qid, 10), oid);
      if (!ok) allOk = false;
    }

    this._flushInProgress = false;

    // If new answers arrived while we were flushing, flush those now.
    if (this._pendingFlush || Object.keys(this._saveQueue).length > 0) {
      this._pendingFlush = false;
      await this._flush();
      return;
    }

    // All queued saves done.
    if (allOk) {
      this._setSaveStatus('saved');
    } else {
      this._setSaveStatus('failed');
    }
  }

  /**
   * Returns true on success, false on recoverable error.
   * Re-queues the answer on failure (up to MAX_RETRIES times with back-off).
   */
  async _saveOne(questionId, optionId, attempt = 0) {
    try {
      const res = await fetch(this.saveUrlValue, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          question_id: questionId,
          option_id: optionId,
          expected_revision: this._lastServerRevision,
        }),
      });

      if (res.status === 410) {
        this._onExpired();
        return true; // Not retryable.
      }

      if (res.status === 409) {
        // Stale revision: another tab saved more recently.
        this._setSaveStatus('conflict');
        return false;
      }

      if (res.ok) {
        const data = await res.json().catch(() => ({}));
        if (typeof data.server_revision === 'number') {
          this._lastServerRevision = data.server_revision;
        }
        return true;
      }

      // Retryable server error (5xx).
      return this._retryOrFail(questionId, optionId, attempt);
    } catch {
      // Network error — retry.
      return this._retryOrFail(questionId, optionId, attempt);
    }
  }

  async _retryOrFail(questionId, optionId, attempt) {
    if (attempt >= this.constructor.MAX_RETRIES) {
      // Re-queue so the user's answer survives the page session.
      this._saveQueue[questionId] = optionId;
      return false;
    }
    const delay = Math.min(1000 * (2 ** attempt), 8000);
    await new Promise(r => setTimeout(r, delay));
    return this._saveOne(questionId, optionId, attempt + 1);
  }

  _setSaveStatus(state) {
    if (!this.hasSaveStatusTarget) return;
    const map = {
      saving:   '⟳ Saqlanmoqda…',
      saved:    '✓ Saqlandi',
      failed:   '⚠ Saqlanmadi — qayta urinilmoqda',
      conflict: '⚠ Ikki oyna ziddiyati — sahifani yangilang',
    };
    this.saveStatusTarget.textContent = map[state] ?? '';
    const isWarn = state === 'failed' || state === 'conflict';
    this.saveStatusTarget.className = [
      'font-mono text-[11px] hidden sm:block',
      isWarn ? 'text-amber-400' : 'text-zinc-500',
    ].join(' ');
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
    this._onVisibility = () => this._sendTelemetry('visibility_change', { hidden: document.hidden });
    this._onFullscreen = () => this._sendTelemetry('fullscreen_change', { active: !!document.fullscreenElement });
    document.addEventListener('visibilitychange', this._onVisibility);
    document.addEventListener('fullscreenchange', this._onFullscreen);
  }

  _sendTelemetry(type, data) {
    if (!this.hasTelemetryUrlValue) return;
    navigator.sendBeacon(this.telemetryUrlValue, JSON.stringify({ event_type: type, data }));
  }
}
