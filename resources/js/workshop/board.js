import interact from 'interactjs';

export function workshopBoard({ notes = [], canvasBlocks = [], gridLayout = {} } = {}) {
  return {
    // Pan/zoom state (manual — no panzoom lib, no pointer conflicts)
    panX: 0,
    panY: 0,
    scale: 1,
    _spaceDown: false,
    _isPanning: false,
    _panStart: null,
    _onKeyDown: null,
    _onKeyUp: null,

    _saveTimers: {},
    _textTimers: {},
    colorPickerOpen: null,

    colors: ['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'teal', 'red'],

    init() {
      this.$nextTick(() => {
        this._initPanZoom();
        this._initDraggable();
        this._initResizable();
        this._initAdoptDropzones();
        this._restorePositions();
        this._fitGrid();
      });
    },

    destroy() {
      if (this._onKeyDown) document.removeEventListener('keydown', this._onKeyDown);
      if (this._onKeyUp) document.removeEventListener('keyup', this._onKeyUp);
      interact('.workshop-note').unset();
      interact('.workshop-grid-block').unset();
    },

    // ─── Manual Pan / Zoom ──────────────────────────────────
    _applyTransform() {
      const board = this.$refs.board;
      if (board) {
        board.style.transform = `translate(${this.panX}px, ${this.panY}px) scale(${this.scale})`;
      }
    },

    _zoomToPoint(newScale, clientX, clientY) {
      const parent = this.$refs.board?.parentElement;
      if (!parent) return;
      const rect = parent.getBoundingClientRect();
      const px = clientX - rect.left;
      const py = clientY - rect.top;
      const clamped = Math.max(0.15, Math.min(3, newScale));
      const ratio = clamped / this.scale;
      this.panX = px - (px - this.panX) * ratio;
      this.panY = py - (py - this.panY) * ratio;
      this.scale = clamped;
      this._applyTransform();
    },

    _zoomToCenter(newScale) {
      const parent = this.$refs.board?.parentElement;
      if (!parent) return;
      const rect = parent.getBoundingClientRect();
      this._zoomToPoint(newScale, rect.left + parent.clientWidth / 2, rect.top + parent.clientHeight / 2);
    },

    _initPanZoom() {
      const board = this.$refs.board;
      if (!board) return;
      const parent = board.parentElement;
      board.style.transformOrigin = '0 0';

      // Wheel: scroll = pan, Ctrl+scroll = zoom toward pointer
      parent.addEventListener('wheel', (e) => {
        e.preventDefault();
        if (e.ctrlKey || e.metaKey) {
          const factor = -e.deltaY * 0.002;
          this._zoomToPoint(this.scale * (1 + factor), e.clientX, e.clientY);
        } else {
          this.panX -= e.deltaX;
          this.panY -= e.deltaY;
          this._applyTransform();
        }
      }, { passive: false });

      // Space key → grab cursor, enable pointer-pan
      this._onKeyDown = (e) => {
        if (e.code === 'Space' && !e.repeat && !e.target.matches('input,textarea,[contenteditable]')) {
          e.preventDefault();
          this._spaceDown = true;
          parent.style.cursor = 'grab';
        }
      };
      this._onKeyUp = (e) => {
        if (e.code === 'Space') {
          this._spaceDown = false;
          this._isPanning = false;
          this._panStart = null;
          parent.style.cursor = '';
        }
      };
      document.addEventListener('keydown', this._onKeyDown);
      document.addEventListener('keyup', this._onKeyUp);

      // Space + pointer drag = pan
      parent.addEventListener('pointerdown', (e) => {
        if (this._spaceDown) {
          this._isPanning = true;
          this._panStart = { x: e.clientX, y: e.clientY, px: this.panX, py: this.panY };
          parent.style.cursor = 'grabbing';
          parent.setPointerCapture(e.pointerId);
          e.preventDefault();
        }
      });
      parent.addEventListener('pointermove', (e) => {
        if (this._isPanning && this._panStart) {
          this.panX = this._panStart.px + (e.clientX - this._panStart.x);
          this.panY = this._panStart.py + (e.clientY - this._panStart.y);
          this._applyTransform();
        }
      });
      parent.addEventListener('pointerup', () => {
        if (this._isPanning) {
          this._isPanning = false;
          this._panStart = null;
          parent.style.cursor = this._spaceDown ? 'grab' : '';
        }
      });
    },

    zoomIn() { this._zoomToCenter(this.scale * 1.25); },
    zoomOut() { this._zoomToCenter(this.scale / 1.25); },
    resetZoom() {
      this.scale = 1;
      this.panX = 0;
      this.panY = 0;
      this._applyTransform();
    },
    fitToScreen() { this._fitGrid(); },

    _fitGrid() {
      const board = this.$refs.board;
      const parent = board?.parentElement;
      const gridEl = board?.querySelector('.workshop-canvas-background');
      if (!gridEl || !parent) return;

      const gw = gridEl.offsetWidth;
      const gh = gridEl.offsetHeight;
      const gx = gridEl.offsetLeft;
      const gy = gridEl.offsetTop;
      const vw = parent.clientWidth;
      const vh = parent.clientHeight;
      const pad = 60;

      const fitScale = Math.min((vw - pad * 2) / gw, (vh - pad * 2) / gh, 1);
      this.scale = fitScale;
      this.panX = (vw - gw * fitScale) / 2 - gx * fitScale;
      this.panY = (vh - gh * fitScale) / 2 - gy * fitScale;
      this._applyTransform();
    },

    // ─── Restore positions from data attributes ─────────────
    _restorePositions() {
      this.$refs.board.querySelectorAll('.workshop-note').forEach((el) => {
        const x = parseFloat(el.dataset.x) || 0;
        const y = parseFloat(el.dataset.y) || 0;
        el.style.transform = `translate(${x}px, ${y}px)`;
        el.setAttribute('data-x', x);
        el.setAttribute('data-y', y);
      });
    },

    // ─── interact.js Draggable (scale-aware) ────────────────
    _initDraggable() {
      const self = this;
      interact('.workshop-note').draggable({
        allowFrom: '.drag-handle',
        inertia: false,
        listeners: {
          start(event) {
            event.target.classList.add('dragging');
          },
          move(event) {
            const target = event.target;
            // Divide by scale so mouse movement maps correctly to board coords
            const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx / self.scale;
            const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy / self.scale;
            target.style.transform = `translate(${x}px, ${y}px)`;
            target.setAttribute('data-x', x);
            target.setAttribute('data-y', y);
          },
          end(event) {
            event.target.classList.remove('dragging');
            const target = event.target;
            const noteId = parseInt(target.dataset.noteId);
            const x = parseFloat(target.getAttribute('data-x')) || 0;
            const y = parseFloat(target.getAttribute('data-y')) || 0;
            const w = parseInt(target.style.width) || null;
            const h = parseInt(target.style.height) || null;
            self.savePosition(noteId, { x, y, width: w, height: h });
          },
        },
      });
    },

    // ─── interact.js Resizable (scale-aware) ────────────────
    _initResizable() {
      const self = this;
      interact('.workshop-note').resizable({
        edges: { right: '.resize-handle', bottom: '.resize-handle' },
        modifiers: [
          interact.modifiers.restrictSize({ min: { width: 120, height: 80 } }),
        ],
        listeners: {
          move(event) {
            const target = event.target;
            let x = parseFloat(target.getAttribute('data-x')) || 0;
            let y = parseFloat(target.getAttribute('data-y')) || 0;

            // Scale-aware: convert screen-space rect to board-space
            target.style.width = (event.rect.width / self.scale) + 'px';
            target.style.height = (event.rect.height / self.scale) + 'px';

            x += event.deltaRect.left / self.scale;
            y += event.deltaRect.top / self.scale;

            target.style.transform = `translate(${x}px, ${y}px)`;
            target.setAttribute('data-x', x);
            target.setAttribute('data-y', y);
          },
          end(event) {
            const target = event.target;
            const noteId = parseInt(target.dataset.noteId);
            const x = parseFloat(target.getAttribute('data-x')) || 0;
            const y = parseFloat(target.getAttribute('data-y')) || 0;
            self.savePosition(noteId, {
              x,
              y,
              width: parseInt(target.style.width),
              height: parseInt(target.style.height),
            });
          },
        },
      });
    },

    // ─── Adopt Dropzones (grid blocks) ──────────────────────
    _initAdoptDropzones() {
      const self = this;
      interact('.workshop-grid-block').dropzone({
        accept: '.workshop-note',
        overlap: 0.3,
        ondragenter(event) {
          event.target.classList.add('adopt-highlight');
        },
        ondragleave(event) {
          event.target.classList.remove('adopt-highlight');
        },
        ondrop(event) {
          event.target.classList.remove('adopt-highlight');
          const noteEl = event.relatedTarget;
          const blockEl = event.target;
          const noteId = parseInt(noteEl.dataset.noteId);
          const blockId = parseInt(blockEl.dataset.blockId);

          if (noteId && blockId) {
            const label = blockEl.querySelector('h4')?.textContent?.trim() || 'Block';
            if (confirm(`Notiz in "${label}" uebernehmen?`)) {
              self.$wire.call('adoptNote', noteId, blockId);
            }
          }
        },
      });
    },

    // ─── Livewire calls ─────────────────────────────────────
    savePosition(noteId, pos) {
      clearTimeout(this._saveTimers[noteId]);
      this._saveTimers[noteId] = setTimeout(() => {
        this.$wire.call('updateNotePosition', noteId, pos);
      }, 500);
    },

    addNote() {
      // Calculate viewport center in board coordinates
      const parent = this.$refs.board?.parentElement;
      if (parent) {
        const cx = (parent.clientWidth / 2 - this.panX) / this.scale;
        const cy = (parent.clientHeight / 2 - this.panY) / this.scale;
        this.$wire.call('addWorkshopNote', { x: Math.round(cx - 100), y: Math.round(cy - 75) });
      } else {
        this.$wire.call('addWorkshopNote', {});
      }
    },

    deleteNote(noteId) {
      this.$wire.call('deleteWorkshopNote', noteId);
    },

    updateNoteText(noteId, title, content) {
      clearTimeout(this._textTimers[noteId]);
      this._textTimers[noteId] = setTimeout(() => {
        this.$wire.call('updateNoteText', noteId, title, content);
      }, 500);
    },

    changeColor(noteId, color) {
      this.colorPickerOpen = null;
      this.$wire.call('updateNoteColor', noteId, color);
    },

    toggleColorPicker(noteId) {
      this.colorPickerOpen = this.colorPickerOpen === noteId ? null : noteId;
    },
  };
}
