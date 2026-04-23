import interact from 'interactjs';

const COLOR_HEX = {
  yellow: '#fbbf24', blue: '#60a5fa', green: '#4ade80', pink: '#f472b6',
  purple: '#a78bfa', orange: '#fb923c', teal: '#2dd4bf', red: '#f87171',
};
const COLOR_BG = {
  yellow: '#fef9c3', blue: '#dbeafe', green: '#dcfce7', pink: '#fce7f3',
  purple: '#f3e8ff', orange: '#ffedd5', teal: '#ccfbf1', red: '#fee2e2',
};

/**
 * workshopBoard — JS-owned infinite canvas.
 *
 * Blade delivers initial data, JS renders all notes.
 * Livewire calls are fire-and-forget persistence only.
 * Board DOM is protected by wire:ignore.
 */
export function workshopBoard({ notes = [], canvasBlocks = [], gridLayout = {} } = {}) {
  return {
    // State
    panX: 0,
    panY: 0,
    scale: 1,
    _isPanning: false,
    _panStart: null,
    _panButton: -1,
    _spaceDown: false,
    _listeners: [],
    _saveTimers: {},
    _textTimers: {},
    _nextTempId: -1,
    colorPickerOpen: null,

    colors: Object.keys(COLOR_HEX),

    // ─── Lifecycle ─────────────────────────────────────────
    init() {
      this.$nextTick(() => {
        this._renderNotes(notes);
        this._initPanZoom();
        this._initInteract();
        this._fitGrid();
      });
    },

    destroy() {
      this._listeners.forEach(([el, ev, fn, opts]) => el.removeEventListener(ev, fn, opts));
      this._listeners = [];
      interact('.workshop-note').unset();
      interact('.workshop-grid-block').unset();
    },

    _on(el, ev, fn, opts) {
      el.addEventListener(ev, fn, opts);
      this._listeners.push([el, ev, fn, opts]);
    },

    // ─── Render notes from data (JS-owned DOM) ─────────────
    _renderNotes(noteList) {
      const board = this.$refs.board;
      noteList.forEach(n => board.appendChild(this._createNoteEl(n)));
    },

    _createNoteEl(n) {
      const color = n.color || 'yellow';
      const x = n.x ?? 0;
      const y = n.y ?? 0;
      const w = n.width ?? 200;
      const h = n.height ?? 150;

      const el = document.createElement('div');
      el.className = `workshop-note workshop-note-${color}`;
      el.dataset.noteId = n.id;
      el.dataset.x = x;
      el.dataset.y = y;
      el.style.cssText = `width:${w}px;height:${h}px;transform:translate(${x}px,${y}px);`;

      el.innerHTML = `
        <div class="drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            <div class="color-dot-wrap" style="position:relative;">
              <div class="color-dot" style="background:${COLOR_HEX[color] || COLOR_HEX.yellow};" data-action="color"></div>
              <div class="color-picker-dd" style="display:none;position:absolute;top:100%;left:0;margin-top:4px;padding:4px;background:white;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);border:1px solid #e5e7eb;z-index:50;gap:3px;flex-wrap:nowrap;">
                ${this.colors.map(c => `<div class="color-dot${c === color ? ' active' : ''}" style="background:${COLOR_HEX[c]};" data-pick-color="${c}"></div>`).join('')}
              </div>
            </div>
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
          </button>
        </div>
        <div class="note-body">
          <input type="text" value="${this._esc(n.title || '')}" placeholder="Titel..." />
          <textarea placeholder="Notiz...">${this._esc(n.content || '')}</textarea>
        </div>
        <div class="resize-handle"></div>
      `;

      // Event delegation for this note
      el.addEventListener('click', (e) => {
        const action = e.target.closest('[data-action]')?.dataset.action;
        const pickColor = e.target.closest('[data-pick-color]')?.dataset.pickColor;
        const noteId = parseInt(el.dataset.noteId);

        if (pickColor) {
          e.stopPropagation();
          this._changeColor(el, noteId, pickColor);
          return;
        }
        if (action === 'color') {
          e.stopPropagation();
          this._toggleColorPicker(el);
          return;
        }
        if (action === 'delete') {
          e.stopPropagation();
          if (confirm('Notiz loeschen?')) this._deleteNote(el, noteId);
          return;
        }
      });

      // Text save on blur
      const input = el.querySelector('.note-body input');
      const textarea = el.querySelector('.note-body textarea');
      const saveText = () => {
        const noteId = parseInt(el.dataset.noteId);
        if (noteId < 0) return; // temp note, not yet persisted
        clearTimeout(this._textTimers[noteId]);
        this._textTimers[noteId] = setTimeout(() => {
          this.$wire.call('updateNoteText', noteId, input.value, textarea.value);
        }, 400);
      };
      input.addEventListener('blur', saveText);
      textarea.addEventListener('blur', saveText);
      input.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.target.blur(); });

      // Close color picker on outside click
      el.addEventListener('pointerdown', (e) => e.stopPropagation());

      return el;
    },

    _esc(s) {
      const d = document.createElement('div');
      d.textContent = s;
      return d.innerHTML;
    },

    // ─── Pan / Zoom ─────────────────────────────────────────
    _applyTransform() {
      const board = this.$refs.board;
      if (board) board.style.transform = `translate(${this.panX}px,${this.panY}px) scale(${this.scale})`;
    },

    _screenToBoard(sx, sy) {
      return { x: (sx - this.panX) / this.scale, y: (sy - this.panY) / this.scale };
    },

    _zoomTo(newScale, cx, cy) {
      const parent = this.$refs.board?.parentElement;
      if (!parent) return;
      const rect = parent.getBoundingClientRect();
      const px = cx - rect.left;
      const py = cy - rect.top;
      const clamped = Math.max(0.1, Math.min(4, newScale));
      const ratio = clamped / this.scale;
      this.panX = px - (px - this.panX) * ratio;
      this.panY = py - (py - this.panY) * ratio;
      this.scale = clamped;
      this._applyTransform();
    },

    _initPanZoom() {
      const board = this.$refs.board;
      if (!board) return;
      const parent = board.parentElement;
      board.style.transformOrigin = '0 0';

      // Wheel: trackpad scroll = pan, Ctrl/Meta+wheel = zoom
      this._on(parent, 'wheel', (e) => {
        e.preventDefault();
        if (e.ctrlKey || e.metaKey) {
          this._zoomTo(this.scale * (1 - e.deltaY * 0.003), e.clientX, e.clientY);
        } else {
          this.panX -= e.deltaX;
          this.panY -= e.deltaY;
          this._applyTransform();
        }
      }, { passive: false });

      // Middle-click pan
      this._on(parent, 'pointerdown', (e) => {
        // Middle button (1), or left button (0) when space is held
        if (e.button === 1 || (e.button === 0 && this._spaceDown)) {
          this._isPanning = true;
          this._panButton = e.button;
          this._panStart = { x: e.clientX, y: e.clientY, px: this.panX, py: this.panY };
          parent.style.cursor = 'grabbing';
          parent.setPointerCapture(e.pointerId);
          e.preventDefault();
        }
      }, false);

      this._on(parent, 'pointermove', (e) => {
        if (this._isPanning && this._panStart) {
          this.panX = this._panStart.px + (e.clientX - this._panStart.x);
          this.panY = this._panStart.py + (e.clientY - this._panStart.y);
          this._applyTransform();
        }
      }, false);

      this._on(parent, 'pointerup', (e) => {
        if (this._isPanning) {
          this._isPanning = false;
          this._panStart = null;
          parent.style.cursor = this._spaceDown ? 'grab' : '';
        }
      }, false);

      // Prevent context menu on middle click
      this._on(parent, 'contextmenu', (e) => {
        if (this._panButton === 1) e.preventDefault();
      }, false);

      // Space key for pan mode
      this._on(document, 'keydown', (e) => {
        if (e.code === 'Space' && !e.repeat && !e.target.matches('input,textarea,[contenteditable]')) {
          e.preventDefault();
          this._spaceDown = true;
          parent.style.cursor = 'grab';
        }
      }, false);

      this._on(document, 'keyup', (e) => {
        if (e.code === 'Space') {
          this._spaceDown = false;
          if (!this._isPanning) parent.style.cursor = '';
        }
      }, false);

      // Close any open color pickers on board click
      this._on(document, 'click', () => {
        board.querySelectorAll('.color-picker-dd[style*="flex"]').forEach(dd => dd.style.display = 'none');
      }, false);
    },

    zoomIn() { this._zoomToCenter(this.scale * 1.3); },
    zoomOut() { this._zoomToCenter(this.scale / 1.3); },
    resetZoom() { this.scale = 1; this.panX = 0; this.panY = 0; this._applyTransform(); },
    fitToScreen() { this._fitGrid(); },

    _zoomToCenter(s) {
      const p = this.$refs.board?.parentElement;
      if (!p) return;
      const r = p.getBoundingClientRect();
      this._zoomTo(s, r.left + p.clientWidth / 2, r.top + p.clientHeight / 2);
    },

    _fitGrid() {
      const board = this.$refs.board;
      const parent = board?.parentElement;
      const gridEl = board?.querySelector('.workshop-canvas-background');
      if (!gridEl || !parent) return;
      const gw = gridEl.offsetWidth, gh = gridEl.offsetHeight;
      const gx = gridEl.offsetLeft, gy = gridEl.offsetTop;
      const vw = parent.clientWidth, vh = parent.clientHeight;
      const pad = 40;
      const fitScale = Math.min((vw - pad * 2) / gw, (vh - pad * 2) / gh, 1);
      this.scale = fitScale;
      this.panX = (vw - gw * fitScale) / 2 - gx * fitScale;
      this.panY = (vh - gh * fitScale) / 2 - gy * fitScale;
      this._applyTransform();
    },

    // ─── interact.js ────────────────────────────────────────
    _initInteract() {
      const self = this;

      // Draggable
      interact('.workshop-note').draggable({
        allowFrom: '.drag-handle',
        inertia: false,
        listeners: {
          start(ev) { ev.target.classList.add('dragging'); },
          move(ev) {
            const t = ev.target;
            const x = (parseFloat(t.dataset.x) || 0) + ev.dx / self.scale;
            const y = (parseFloat(t.dataset.y) || 0) + ev.dy / self.scale;
            t.style.transform = `translate(${x}px,${y}px)`;
            t.dataset.x = x;
            t.dataset.y = y;
          },
          end(ev) {
            ev.target.classList.remove('dragging');
            const t = ev.target;
            const id = parseInt(t.dataset.noteId);
            if (id < 0) return;
            self._savePos(id, t);
          },
        },
      });

      // Resizable
      interact('.workshop-note').resizable({
        edges: { right: '.resize-handle', bottom: '.resize-handle' },
        modifiers: [interact.modifiers.restrictSize({ min: { width: 120, height: 80 } })],
        listeners: {
          move(ev) {
            const t = ev.target;
            let x = parseFloat(t.dataset.x) || 0;
            let y = parseFloat(t.dataset.y) || 0;
            t.style.width = (ev.rect.width / self.scale) + 'px';
            t.style.height = (ev.rect.height / self.scale) + 'px';
            x += ev.deltaRect.left / self.scale;
            y += ev.deltaRect.top / self.scale;
            t.style.transform = `translate(${x}px,${y}px)`;
            t.dataset.x = x;
            t.dataset.y = y;
          },
          end(ev) {
            const t = ev.target;
            const id = parseInt(t.dataset.noteId);
            if (id < 0) return;
            self._savePos(id, t);
          },
        },
      });

      // Dropzones on grid blocks
      interact('.workshop-grid-block').dropzone({
        accept: '.workshop-note',
        overlap: 0.3,
        ondragenter(ev) { ev.target.classList.add('adopt-highlight'); },
        ondragleave(ev) { ev.target.classList.remove('adopt-highlight'); },
        ondrop(ev) {
          ev.target.classList.remove('adopt-highlight');
          const noteId = parseInt(ev.relatedTarget.dataset.noteId);
          const blockId = parseInt(ev.target.dataset.blockId);
          if (noteId > 0 && blockId) {
            const label = ev.target.querySelector('h4')?.textContent?.trim() || 'Block';
            if (confirm(`Notiz in "${label}" uebernehmen?`)) {
              ev.relatedTarget.remove();
              self.$wire.call('adoptNote', noteId, blockId);
            }
          }
        },
      });
    },

    _savePos(noteId, el) {
      clearTimeout(this._saveTimers[noteId]);
      this._saveTimers[noteId] = setTimeout(() => {
        this.$wire.call('updateNotePosition', noteId, {
          x: parseFloat(el.dataset.x) || 0,
          y: parseFloat(el.dataset.y) || 0,
          width: parseInt(el.style.width) || 200,
          height: parseInt(el.style.height) || 150,
        });
      }, 300);
    },

    // ─── Note actions ───────────────────────────────────────
    addNote() {
      const parent = this.$refs.board?.parentElement;
      if (!parent) return;
      const rect = parent.getBoundingClientRect();
      const cx = (rect.width / 2 - this.panX) / this.scale;
      const cy = (rect.height / 2 - this.panY) / this.scale;
      const x = Math.round(cx - 100);
      const y = Math.round(cy - 75);

      // Optimistic: add note to DOM immediately
      const tempId = this._nextTempId--;
      const el = this._createNoteEl({ id: tempId, title: '', content: '', color: 'yellow', x, y, width: 200, height: 150 });
      this.$refs.board.appendChild(el);

      // Persist and update ID
      this.$wire.call('addWorkshopNote', { x, y }).then((result) => {
        // After Livewire processes, we need the real ID
        // Livewire will return the component state; we re-fetch notes
        this.$wire.call('getWorkshopNotes').then((serverNotes) => {
          if (Array.isArray(serverNotes) && serverNotes.length > 0) {
            // Find the note we just created (highest ID)
            const newest = serverNotes.reduce((a, b) => a.id > b.id ? a : b);
            el.dataset.noteId = newest.id;
          }
        });
      });

      // Focus title
      setTimeout(() => el.querySelector('.note-body input')?.focus(), 100);
    },

    _deleteNote(el, noteId) {
      el.remove();
      if (noteId > 0) this.$wire.call('deleteWorkshopNote', noteId);
    },

    _changeColor(el, noteId, color) {
      // Update DOM immediately
      el.className = `workshop-note workshop-note-${color}`;
      el.querySelector('.drag-handle .color-dot')?.setAttribute('style', `background:${COLOR_HEX[color]}`);
      el.querySelector('.color-picker-dd').style.display = 'none';
      // Update active state
      el.querySelectorAll('.color-picker-dd .color-dot').forEach(d => {
        d.classList.toggle('active', d.dataset.pickColor === color);
      });
      if (noteId > 0) this.$wire.call('updateNoteColor', noteId, color);
    },

    _toggleColorPicker(el) {
      const dd = el.querySelector('.color-picker-dd');
      if (!dd) return;
      const isOpen = dd.style.display === 'flex';
      // Close all others first
      this.$refs.board.querySelectorAll('.color-picker-dd').forEach(d => d.style.display = 'none');
      dd.style.display = isOpen ? 'none' : 'flex';
    },
  };
}
