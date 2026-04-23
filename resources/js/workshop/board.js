import Panzoom from '@panzoom/panzoom';
import interact from 'interactjs';

export function workshopBoard({ entries = [], gridLayout = {}, blockDefs = [] } = {}) {
  return {
    panzoom: null,
    scale: 1,
    _saveTimers: {},
    _textTimers: {},
    colorPickerOpen: null,

    colors: ['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'teal', 'red'],

    init() {
      this.$nextTick(() => {
        this._initPanzoom();
        this._initDraggable();
        this._initResizable();
        this._initDropzones();
        this._restorePositions();
      });
    },

    destroy() {
      if (this.panzoom) {
        this.panzoom.destroy();
      }
      interact('.workshop-note').unset();
      interact('.workshop-block-body').unset();
    },

    // ─── Panzoom ─────────────────────────────────────────────
    _initPanzoom() {
      const board = this.$refs.board;
      if (!board) return;

      this.panzoom = Panzoom(board, {
        minScale: 0.3,
        maxScale: 2,
        contain: false,
        cursor: 'default',
        canvas: true,
      });

      const parent = board.parentElement;
      parent.addEventListener('wheel', (e) => {
        if (e.ctrlKey || e.metaKey) {
          e.preventDefault();
          this.panzoom.zoomWithWheel(e);
          this.scale = this.panzoom.getScale();
        }
      }, { passive: false });

      // Disable panzoom on interactive elements
      board.addEventListener('pointerdown', (e) => {
        const isNote = e.target.closest('.workshop-note');
        const isButton = e.target.closest('button');
        const isInput = e.target.closest('input, textarea');
        if (isNote || isButton || isInput) {
          e.stopPropagation();
        }
      });
    },

    zoomIn() {
      if (!this.panzoom) return;
      this.panzoom.zoomIn();
      this.scale = this.panzoom.getScale();
    },

    zoomOut() {
      if (!this.panzoom) return;
      this.panzoom.zoomOut();
      this.scale = this.panzoom.getScale();
    },

    resetZoom() {
      if (!this.panzoom) return;
      this.panzoom.reset();
      this.scale = 1;
    },

    fitToScreen() {
      if (!this.panzoom) return;
      const board = this.$refs.board;
      const parent = board.parentElement;
      const scaleX = parent.clientWidth / board.scrollWidth;
      const scaleY = parent.clientHeight / board.scrollHeight;
      const fitScale = Math.min(scaleX, scaleY, 1) * 0.9;
      this.panzoom.zoom(fitScale);
      this.panzoom.pan(0, 0);
      this.scale = fitScale;
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

    // ─── interact.js Draggable ──────────────────────────────
    _initDraggable() {
      interact('.workshop-note').draggable({
        allowFrom: '.drag-handle',
        inertia: true,
        modifiers: [
          interact.modifiers.snap({
            targets: [interact.snappers.grid({ x: 10, y: 10 })],
            range: Infinity,
            relativePoints: [{ x: 0, y: 0 }],
          }),
        ],
        listeners: {
          start: (event) => {
            event.target.classList.add('dragging');
          },
          move: (event) => {
            const target = event.target;
            const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
            const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;
            target.style.transform = `translate(${x}px, ${y}px)`;
            target.setAttribute('data-x', x);
            target.setAttribute('data-y', y);
          },
          end: (event) => {
            event.target.classList.remove('dragging');
            const target = event.target;
            const entryId = parseInt(target.dataset.entryId);
            const x = parseFloat(target.getAttribute('data-x')) || 0;
            const y = parseFloat(target.getAttribute('data-y')) || 0;
            const w = parseInt(target.style.width) || null;
            const h = parseInt(target.style.height) || null;
            this.savePosition(entryId, { x, y, width: w, height: h });
          },
        },
      });
    },

    // ─── interact.js Resizable ──────────────────────────────
    _initResizable() {
      interact('.workshop-note').resizable({
        edges: { right: '.resize-handle', bottom: '.resize-handle' },
        modifiers: [
          interact.modifiers.restrictSize({
            min: { width: 150, height: 100 },
          }),
        ],
        listeners: {
          move: (event) => {
            const target = event.target;
            let x = parseFloat(target.getAttribute('data-x')) || 0;
            let y = parseFloat(target.getAttribute('data-y')) || 0;

            target.style.width = event.rect.width + 'px';
            target.style.height = event.rect.height + 'px';

            x += event.deltaRect.left;
            y += event.deltaRect.top;

            target.style.transform = `translate(${x}px, ${y}px)`;
            target.setAttribute('data-x', x);
            target.setAttribute('data-y', y);
          },
          end: (event) => {
            const target = event.target;
            const entryId = parseInt(target.dataset.entryId);
            const x = parseFloat(target.getAttribute('data-x')) || 0;
            const y = parseFloat(target.getAttribute('data-y')) || 0;
            this.savePosition(entryId, {
              x,
              y,
              width: parseInt(target.style.width),
              height: parseInt(target.style.height),
            });
          },
        },
      });
    },

    // ─── interact.js Dropzones ──────────────────────────────
    _initDropzones() {
      interact('.workshop-block-body').dropzone({
        accept: '.workshop-note',
        overlap: 0.3,
        ondragenter: (event) => {
          event.target.closest('.workshop-block')?.classList.add('drop-active');
        },
        ondragleave: (event) => {
          event.target.closest('.workshop-block')?.classList.remove('drop-active');
        },
        ondrop: (event) => {
          event.target.closest('.workshop-block')?.classList.remove('drop-active');
          const noteEl = event.relatedTarget;
          const dropzone = event.target;
          const entryId = parseInt(noteEl.dataset.entryId);
          const newBlockId = parseInt(dropzone.dataset.blockId);
          const currentBlockId = parseInt(noteEl.closest('.workshop-block-body')?.dataset.blockId);

          if (newBlockId && newBlockId !== currentBlockId) {
            this.moveToBlock(entryId, newBlockId);
          }
        },
      });
    },

    // ─── Livewire calls ─────────────────────────────────────
    savePosition(entryId, pos) {
      clearTimeout(this._saveTimers[entryId]);
      this._saveTimers[entryId] = setTimeout(() => {
        this.$wire.call('updateNotePosition', entryId, pos);
      }, 500);
    },

    moveToBlock(entryId, newBlockId) {
      this.$wire.call('moveNoteToBlock', entryId, newBlockId);
    },

    addNote(blockKey) {
      this.$wire.call('addWorkshopNote', blockKey);
    },

    deleteNote(entryId) {
      this.$wire.call('deleteWorkshopNote', entryId);
    },

    updateNoteText(entryId, title, content) {
      clearTimeout(this._textTimers[entryId]);
      this._textTimers[entryId] = setTimeout(() => {
        this.$wire.call('updateNoteText', entryId, title, content);
      }, 500);
    },

    changeColor(entryId, color) {
      this.colorPickerOpen = null;
      this.$wire.call('updateNoteColor', entryId, color);
    },

    toggleColorPicker(entryId) {
      this.colorPickerOpen = this.colorPickerOpen === entryId ? null : entryId;
    },
  };
}
