import interact from 'interactjs';

const COLOR_HEX = {
  yellow: '#fbbf24', blue: '#60a5fa', green: '#4ade80', pink: '#f472b6',
  purple: '#a78bfa', orange: '#fb923c', teal: '#2dd4bf', red: '#f87171',
};
const COLOR_BG = {
  yellow: '#fef9c3', blue: '#dbeafe', green: '#dcfce7', pink: '#fce7f3',
  purple: '#f3e8ff', orange: '#ffedd5', teal: '#ccfbf1', red: '#fee2e2',
};

const TYPE_DEFAULTS = {
  note:    { width: 200, height: 150, color: 'yellow' },
  text:    { width: 300, height: 40,  color: 'yellow' },
  section: { width: 500, height: 400, color: 'yellow' },
  shape:   { width: 120, height: 120, color: 'blue' },
};

const DELETE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>';

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

    isFullscreen: false,

    // ─── Lifecycle ─────────────────────────────────────────
    init() {
      // Guard against re-initialization (Livewire morphing)
      if (this._initialized) return;
      this._initialized = true;

      this.$nextTick(() => {
        this._renderNotes(notes);
        this._initPanZoom();
        this._initInteract();
        this._fitGrid();

        // Sync fullscreen state when user presses Escape
        this._on(document, 'fullscreenchange', () => {
          this.isFullscreen = !!document.fullscreenElement;
          setTimeout(() => this._fitGrid(), 100);
        }, false);
      });
    },

    destroy() {
      this._listeners.forEach(([el, ev, fn, opts]) => el.removeEventListener(ev, fn, opts));
      this._listeners = [];
      interact('.workshop-note').unset();
      interact('.workshop-text').unset();
      interact('.workshop-section').unset();
      interact('.workshop-shape').unset();
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

    // ─── Element Factory (dispatch by type) ────────────────
    _createNoteEl(n) {
      const type = n.type || 'note';
      switch (type) {
        case 'text':    return this._createTextEl(n);
        case 'section': return this._createSectionEl(n);
        case 'shape':   return this._createShapeEl(n);
        default:        return this._createStickyEl(n);
      }
    },

    // ─── Sticky Note (default) ─────────────────────────────
    _createStickyEl(n) {
      const color = n.color || 'yellow';
      const x = n.x ?? 0;
      const y = n.y ?? 0;
      const w = n.width ?? 200;
      const h = n.height ?? 150;

      const el = document.createElement('div');
      el.className = `workshop-note workshop-note-${color}`;
      el.dataset.noteId = n.id;
      el.dataset.noteType = 'note';
      el.dataset.x = x;
      el.dataset.y = y;
      el.style.cssText = `width:${w}px;height:${h}px;transform:translate(${x}px,${y}px);`;

      el.innerHTML = `
        <div class="drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(color)}
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${DELETE_SVG}</button>
        </div>
        <div class="note-body">
          <input type="text" value="${this._esc(n.title || '')}" placeholder="Titel..." />
          <textarea placeholder="Notiz...">${this._esc(n.content || '')}</textarea>
        </div>
        <div class="resize-handle"></div>
      `;

      this._bindNoteEvents(el);
      this._bindTextSave(el);

      return el;
    },

    // ─── Text Element ──────────────────────────────────────
    _createTextEl(n) {
      const x = n.x ?? 0;
      const y = n.y ?? 0;
      const w = n.width ?? 300;
      const h = n.height ?? 40;
      const fontSize = n.metadata?.fontSize || Math.max(14, Math.round(w / 12));

      const el = document.createElement('div');
      el.className = 'workshop-text';
      el.dataset.noteId = n.id;
      el.dataset.noteType = 'text';
      el.dataset.x = x;
      el.dataset.y = y;
      el.style.cssText = `width:${w}px;height:${h}px;transform:translate(${x}px,${y}px);`;

      el.innerHTML = `
        <div class="drag-handle text-drag-handle">
          <button class="note-delete" data-action="delete" title="Loeschen">${DELETE_SVG}</button>
        </div>
        <div class="text-body">
          <input type="text" value="${this._esc(n.title || '')}" placeholder="Text eingeben..." style="font-size:${fontSize}px;" />
        </div>
        <div class="resize-handle"></div>
      `;

      this._bindDeleteEvent(el);
      this._bindTextInputSave(el);

      return el;
    },

    // ─── Section (Frame) ───────────────────────────────────
    _createSectionEl(n) {
      const color = n.color || 'yellow';
      const x = n.x ?? 0;
      const y = n.y ?? 0;
      const w = n.width ?? 500;
      const h = n.height ?? 400;

      const el = document.createElement('div');
      el.className = `workshop-section workshop-section-${color}`;
      el.dataset.noteId = n.id;
      el.dataset.noteType = 'section';
      el.dataset.x = x;
      el.dataset.y = y;
      el.style.cssText = `width:${w}px;height:${h}px;transform:translate(${x}px,${y}px);border-color:${COLOR_HEX[color] || COLOR_HEX.yellow};`;

      el.innerHTML = `
        <div class="drag-handle section-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
            ${this._colorDotHTML(color)}
            <input type="text" class="section-title" value="${this._esc(n.title || '')}" placeholder="Section..." />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${DELETE_SVG}</button>
        </div>
        <div class="resize-handle"></div>
      `;

      this._bindNoteEvents(el);
      this._bindSectionTextSave(el);

      return el;
    },

    // ─── Shape ─────────────────────────────────────────────
    _createShapeEl(n) {
      const color = n.color || 'blue';
      const shape = n.metadata?.shape || 'rect';
      const x = n.x ?? 0;
      const y = n.y ?? 0;
      const w = n.width ?? 120;
      const h = n.height ?? 120;

      const el = document.createElement('div');
      el.className = `workshop-shape workshop-shape-${shape} workshop-shape-color-${color}`;
      el.dataset.noteId = n.id;
      el.dataset.noteType = 'shape';
      el.dataset.shape = shape;
      el.dataset.x = x;
      el.dataset.y = y;
      el.style.cssText = `width:${w}px;height:${h}px;transform:translate(${x}px,${y}px);`;

      el.innerHTML = `
        <div class="drag-handle shape-drag-handle">
          <div style="display:flex;align-items:center;gap:4px;">
            ${this._colorDotHTML(color)}
          </div>
          <div style="display:flex;align-items:center;gap:2px;">
            <button class="shape-toggle" data-action="toggle-shape" title="Form wechseln">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:10px;height:10px;"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.598a.75.75 0 00-.75.75v3.634a.75.75 0 001.5 0v-2.033l.312.312a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.06-7.846a.75.75 0 00-1.5 0v2.034l-.312-.312A7 7 0 002.848 8.438a.75.75 0 001.449.39 5.5 5.5 0 019.201-2.466l.312.311H11.38a.75.75 0 000 1.5h3.634a.75.75 0 00.75-.75V3.578z" clip-rule="evenodd"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${DELETE_SVG}</button>
          </div>
        </div>
        <div class="shape-body">
          <input type="text" value="${this._esc(n.title || '')}" placeholder="..." />
        </div>
        <div class="resize-handle"></div>
      `;

      this._bindShapeEvents(el);
      this._bindShapeTextSave(el);

      return el;
    },

    // ─── Shared HTML helpers ───────────────────────────────
    _colorDotHTML(color) {
      return `<div class="color-dot-wrap" style="position:relative;">
        <div class="color-dot" style="background:${COLOR_HEX[color] || COLOR_HEX.yellow};" data-action="color"></div>
        <div class="color-picker-dd" style="display:none;position:absolute;top:100%;left:0;margin-top:4px;padding:4px;background:white;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);border:1px solid #e5e7eb;z-index:50;gap:3px;flex-wrap:nowrap;">
          ${this.colors.map(c => `<div class="color-dot${c === color ? ' active' : ''}" style="background:${COLOR_HEX[c]};" data-pick-color="${c}"></div>`).join('')}
        </div>
      </div>`;
    },

    // ─── Event Binding ─────────────────────────────────────
    _bindNoteEvents(el) {
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
          if (confirm('Element loeschen?')) this._deleteNote(el, noteId);
          return;
        }
      });
    },

    _bindDeleteEvent(el) {
      el.addEventListener('click', (e) => {
        const action = e.target.closest('[data-action]')?.dataset.action;
        const noteId = parseInt(el.dataset.noteId);
        if (action === 'delete') {
          e.stopPropagation();
          if (confirm('Element loeschen?')) this._deleteNote(el, noteId);
        }
      });
    },

    _bindShapeEvents(el) {
      el.addEventListener('click', (e) => {
        const action = e.target.closest('[data-action]')?.dataset.action;
        const pickColor = e.target.closest('[data-pick-color]')?.dataset.pickColor;
        const noteId = parseInt(el.dataset.noteId);

        if (pickColor) {
          e.stopPropagation();
          this._changeShapeColor(el, noteId, pickColor);
          return;
        }
        if (action === 'color') {
          e.stopPropagation();
          this._toggleColorPicker(el);
          return;
        }
        if (action === 'toggle-shape') {
          e.stopPropagation();
          this._toggleShape(el, noteId);
          return;
        }
        if (action === 'delete') {
          e.stopPropagation();
          if (confirm('Element loeschen?')) this._deleteNote(el, noteId);
          return;
        }
      });
    },

    _bindTextSave(el) {
      const input = el.querySelector('.note-body input');
      const textarea = el.querySelector('.note-body textarea');
      const saveText = () => {
        const noteId = parseInt(el.dataset.noteId);
        if (noteId < 0) return;
        clearTimeout(this._textTimers[noteId]);
        this._textTimers[noteId] = setTimeout(() => {
          this.$wire.call('updateNoteText', noteId, input.value, textarea.value);
        }, 400);
      };
      input.addEventListener('blur', saveText);
      textarea.addEventListener('blur', saveText);
      input.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.target.blur(); });
    },

    _bindTextInputSave(el) {
      const input = el.querySelector('.text-body input');
      const saveText = () => {
        const noteId = parseInt(el.dataset.noteId);
        if (noteId < 0) return;
        clearTimeout(this._textTimers[noteId]);
        this._textTimers[noteId] = setTimeout(() => {
          this.$wire.call('updateNoteText', noteId, input.value, '');
        }, 400);
      };
      input.addEventListener('blur', saveText);
      input.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.target.blur(); });
    },

    _bindSectionTextSave(el) {
      const input = el.querySelector('.section-title');
      const saveText = () => {
        const noteId = parseInt(el.dataset.noteId);
        if (noteId < 0) return;
        clearTimeout(this._textTimers[noteId]);
        this._textTimers[noteId] = setTimeout(() => {
          this.$wire.call('updateNoteText', noteId, input.value, '');
        }, 400);
      };
      input.addEventListener('blur', saveText);
      input.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.target.blur(); });
    },

    _bindShapeTextSave(el) {
      const input = el.querySelector('.shape-body input');
      const saveText = () => {
        const noteId = parseInt(el.dataset.noteId);
        if (noteId < 0) return;
        clearTimeout(this._textTimers[noteId]);
        this._textTimers[noteId] = setTimeout(() => {
          this.$wire.call('updateNoteText', noteId, input.value, '');
        }, 400);
      };
      input.addEventListener('blur', saveText);
      input.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.target.blur(); });
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

      // Middle-click pan (skip if target is inside a note or control)
      this._on(parent, 'pointerdown', (e) => {
        if (e.target.closest('.workshop-note, .workshop-text, .workshop-section, .workshop-shape, .workshop-toolbar, .workshop-zoom-controls')) return;
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

    toggleFullscreen() {
      const container = this.$el;
      if (!document.fullscreenElement) {
        container.requestFullscreen().then(() => {
          this.isFullscreen = true;
          // Re-fit after fullscreen transition
          setTimeout(() => this._fitGrid(), 100);
        }).catch(() => {});
      } else {
        document.exitFullscreen().then(() => {
          this.isFullscreen = false;
          setTimeout(() => this._fitGrid(), 100);
        }).catch(() => {});
      }
    },

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
      const draggableSelector = '.workshop-note, .workshop-text, .workshop-section, .workshop-shape';
      const resizableSelector = draggableSelector;

      // Draggable
      interact(draggableSelector).draggable({
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
      interact(resizableSelector).resizable({
        edges: { right: '.resize-handle', bottom: '.resize-handle' },
        modifiers: [interact.modifiers.restrictSize({ min: { width: 60, height: 30 } })],
        listeners: {
          move(ev) {
            const t = ev.target;
            let x = parseFloat(t.dataset.x) || 0;
            let y = parseFloat(t.dataset.y) || 0;
            const newW = ev.rect.width / self.scale;
            const newH = ev.rect.height / self.scale;
            t.style.width = newW + 'px';
            t.style.height = newH + 'px';
            x += ev.deltaRect.left / self.scale;
            y += ev.deltaRect.top / self.scale;
            t.style.transform = `translate(${x}px,${y}px)`;
            t.dataset.x = x;
            t.dataset.y = y;

            // Text: scale font size proportionally
            if (t.dataset.noteType === 'text') {
              const fontSize = Math.max(14, Math.round(newW / 12));
              const input = t.querySelector('.text-body input');
              if (input) input.style.fontSize = fontSize + 'px';
            }
          },
          end(ev) {
            const t = ev.target;
            const id = parseInt(t.dataset.noteId);
            if (id < 0) return;
            self._savePos(id, t);
          },
        },
      });

      // Dropzones on grid blocks (only for sticky notes)
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

    // ─── Element actions ─────────────────────────────────────
    addElement(type = 'note') {
      const parent = this.$refs.board?.parentElement;
      if (!parent) return;
      const rect = parent.getBoundingClientRect();
      const defaults = TYPE_DEFAULTS[type] || TYPE_DEFAULTS.note;
      const cx = (rect.width / 2 - this.panX) / this.scale;
      const cy = (rect.height / 2 - this.panY) / this.scale;
      const x = Math.round(cx - defaults.width / 2);
      const y = Math.round(cy - defaults.height / 2);

      // Optimistic: add to DOM immediately
      const tempId = this._nextTempId--;
      const metadata = type === 'shape' ? { shape: 'rect' } : null;
      const el = this._createNoteEl({
        id: tempId, type, title: '', content: '',
        color: defaults.color, x, y,
        width: defaults.width, height: defaults.height,
        metadata,
      });
      this.$refs.board.appendChild(el);

      // Persist and update ID
      this.$wire.call('addWorkshopNote', { x, y }, type).then(() => {
        this.$wire.call('getWorkshopNotes').then((serverNotes) => {
          if (Array.isArray(serverNotes) && serverNotes.length > 0) {
            const newest = serverNotes.reduce((a, b) => a.id > b.id ? a : b);
            el.dataset.noteId = newest.id;
          }
        });
      });

      // Focus title input
      setTimeout(() => {
        const input = el.querySelector('.note-body input, .text-body input, .section-title, .shape-body input');
        input?.focus();
      }, 100);
    },

    // Keep legacy addNote for backwards compat
    addNote() { this.addElement('note'); },

    _deleteNote(el, noteId) {
      el.remove();
      if (noteId > 0) this.$wire.call('deleteWorkshopNote', noteId);
    },

    _changeColor(el, noteId, color) {
      const noteType = el.dataset.noteType || 'note';

      // Update class-based color
      if (noteType === 'note') {
        el.className = el.className.replace(/workshop-note-\w+/, `workshop-note-${color}`);
      } else if (noteType === 'section') {
        el.className = el.className.replace(/workshop-section-\w+/, `workshop-section-${color}`);
        el.style.borderColor = COLOR_HEX[color] || COLOR_HEX.yellow;
      }

      el.querySelector('.drag-handle .color-dot')?.setAttribute('style', `background:${COLOR_HEX[color]}`);
      el.querySelector('.color-picker-dd').style.display = 'none';
      el.querySelectorAll('.color-picker-dd .color-dot').forEach(d => {
        d.classList.toggle('active', d.dataset.pickColor === color);
      });
      if (noteId > 0) this.$wire.call('updateNoteColor', noteId, color);
    },

    _changeShapeColor(el, noteId, color) {
      el.className = el.className
        .replace(/workshop-shape-color-\w+/, `workshop-shape-color-${color}`);
      el.querySelector('.color-dot')?.setAttribute('style', `background:${COLOR_HEX[color]}`);
      el.querySelector('.color-picker-dd').style.display = 'none';
      el.querySelectorAll('.color-picker-dd .color-dot').forEach(d => {
        d.classList.toggle('active', d.dataset.pickColor === color);
      });
      if (noteId > 0) this.$wire.call('updateNoteColor', noteId, color);
    },

    _toggleShape(el, noteId) {
      const shapes = ['rect', 'circle', 'diamond'];
      const current = el.dataset.shape || 'rect';
      const next = shapes[(shapes.indexOf(current) + 1) % shapes.length];
      el.dataset.shape = next;

      // Update class
      el.className = el.className.replace(/workshop-shape-(?:rect|circle|diamond)/, `workshop-shape-${next}`);

      if (noteId > 0) this.$wire.call('updateNoteMetadata', noteId, { shape: next });
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
