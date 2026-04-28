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
  note:       { width: 200, height: 150, color: 'yellow' },
  text:       { width: 300, height: 40,  color: 'yellow' },
  section:    { width: 500, height: 400, color: 'yellow' },
  shape:      { width: 120, height: 120, color: 'blue' },
  connector:  { width: 0,   height: 0,   color: 'blue' },
  kanban:     { width: 600, height: 400, color: 'blue' },
  image:      { width: 300, height: 300, color: 'yellow' },
  image_grid: { width: 500, height: 400, color: 'yellow' },
  video:      { width: 480, height: 300, color: 'blue' },
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
    _kanbanTimers: {},
    _nextTempId: -1,
    _pendingUploadTarget: null,
    _mediaTimers: {},
    colorPickerOpen: null,
    _connectorMode: false,
    _connectorFrom: null,
    _svgLayer: null,

    colors: Object.keys(COLOR_HEX),

    isFullscreen: false,

    // ─── Lifecycle ─────────────────────────────────────────
    init() {
      // Guard against re-initialization (Livewire morphing)
      if (this._initialized) return;
      this._initialized = true;

      this.$nextTick(() => {
        // SVG overlay for connectors
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.classList.add('workshop-connectors-layer');
        svg.setAttribute('style', 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;overflow:visible;');
        svg.innerHTML = `<defs>
          <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
            <polygon points="0 0, 10 3.5, 0 7" fill="#6b7280"/>
          </marker>
        </defs>`;
        this.$refs.board.prepend(svg);
        this._svgLayer = svg;

        // Hidden file input for media uploads
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.id = 'workshop-file-input';
        fileInput.style.display = 'none';
        fileInput.accept = 'image/*,video/*';
        this.$refs.board.parentElement.appendChild(fileInput);
        this._fileInput = fileInput;

        fileInput.addEventListener('change', (e) => {
          const file = e.target.files[0];
          if (!file) return;
          this.$wire.upload('workshopFile', file,
            () => {}, // success handled by event
            () => { console.error('Upload failed'); },
            (ev) => {} // progress
          );
          fileInput.value = '';
        });

        // Listen for upload completion from Livewire
        this.$wire.on('workshop-file-uploaded', ([data]) => {
          const target = this._pendingUploadTarget;
          this._pendingUploadTarget = null;
          if (!target || !target.noteEl) return;

          const noteEl = target.noteEl;
          const noteId = parseInt(noteEl.dataset.noteId);

          if (target.type === 'image') {
            this._applyImageUpload(noteEl, data);
            if (noteId > 0) this._saveMediaMetadata(noteEl, noteId);
          } else if (target.type === 'image_grid') {
            this._applyImageGridUpload(noteEl, data);
            if (noteId > 0) this._saveMediaMetadata(noteEl, noteId);
          } else if (target.type === 'video') {
            this._applyVideoUpload(noteEl, data);
            if (noteId > 0) this._saveMediaMetadata(noteEl, noteId);
          }
        });

        this._renderNotes(notes);
        this._initPanZoom();
        this._initInteract();
        this._fitGrid();

        // Close fullscreen on Escape key / cancel connector mode
        this._on(document, 'keydown', (e) => {
          if (e.key === 'Escape') {
            if (this._connectorMode) {
              e.preventDefault();
              this._cancelConnectorMode();
              return;
            }
            if (this.isFullscreen) {
              e.preventDefault();
              this.isFullscreen = false;
              this._fitAfterDelay();
            }
          }
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
      interact('.workshop-kanban').unset();
      interact('.workshop-image').unset();
      interact('.workshop-image-grid').unset();
      interact('.workshop-video').unset();
      interact('.workshop-canvas-background').unset();
      if (this._fileInput) { this._fileInput.remove(); this._fileInput = null; }
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
        case 'text':       return this._createTextEl(n);
        case 'section':    return this._createSectionEl(n);
        case 'shape':      return this._createShapeEl(n);
        case 'connector':  return this._createConnectorEl(n);
        case 'kanban':     return this._createKanbanEl(n);
        case 'image':      return this._createImageEl(n);
        case 'image_grid': return this._createImageGridEl(n);
        case 'video':      return this._createVideoEl(n);
        default:           return this._createStickyEl(n);
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

      // The entire element is the drag handle (class drag-handle on wrapper)
      // Delete button floats top-right on hover
      el.innerHTML = `
        <div class="drag-handle text-drag-handle">
          <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
          <div class="text-body">
            <input type="text" value="${this._esc(n.title || '')}" placeholder="Text eingeben..." style="font-size:${fontSize}px;" />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${DELETE_SVG}</button>
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
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
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

      // Shape visual is the inner .shape-visual (clipped), controls float outside
      el.innerHTML = `
        <div class="shape-visual"></div>
        <div class="drag-handle shape-drag-handle">
          <div style="display:flex;align-items:center;gap:4px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(color)}
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
        if (this._handleConnectorClick(el)) { e.stopPropagation(); return; }
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
        if (this._handleConnectorClick(el)) { e.stopPropagation(); return; }
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
        if (this._handleConnectorClick(el)) { e.stopPropagation(); return; }
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

    // ─── Kanban Board ─────────────────────────────────────
    _createKanbanEl(n) {
      const color = n.color || 'blue';
      const x = n.x ?? 0;
      const y = n.y ?? 0;
      const w = n.width ?? 600;
      const h = n.height ?? 400;
      const columns = n.metadata?.columns || [];

      const el = document.createElement('div');
      el.className = `workshop-kanban workshop-kanban-${color}`;
      el.dataset.noteId = n.id;
      el.dataset.noteType = 'kanban';
      el.dataset.x = x;
      el.dataset.y = y;
      el.style.cssText = `width:${w}px;height:${h}px;transform:translate(${x}px,${y}px);`;

      // Deep-clone columns as JS state
      el._kanbanData = { columns: JSON.parse(JSON.stringify(columns)) };

      el.innerHTML = `
        <div class="drag-handle kanban-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(color)}
            <input type="text" class="kanban-board-title" value="${this._esc(n.title || '')}" placeholder="Board..." />
          </div>
          <div style="display:flex;align-items:center;gap:4px;">
            <button class="kanban-add-col-btn" data-kanban-action="add-column" title="Spalte hinzufuegen">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${DELETE_SVG}</button>
          </div>
        </div>
        <div class="kanban-columns"></div>
        <div class="resize-handle"></div>
      `;

      const colsContainer = el.querySelector('.kanban-columns');
      el._kanbanData.columns.forEach(col => {
        colsContainer.appendChild(this._createKanbanColumnEl(el, col));
      });

      this._bindKanbanEvents(el);
      this._bindNoteEvents(el);
      this._bindKanbanTitleSave(el);

      return el;
    },

    _createKanbanColumnEl(kanbanEl, col) {
      const colEl = document.createElement('div');
      colEl.className = 'kanban-column';
      colEl.dataset.colId = col.id;

      const cardCount = col.cards?.length || 0;
      const wipText = col.wipLimit > 0 ? `${cardCount}/${col.wipLimit}` : `${cardCount}`;
      const wipExceeded = col.wipLimit > 0 && cardCount > col.wipLimit;

      colEl.innerHTML = `
        <div class="kanban-column-header${wipExceeded ? ' wip-exceeded' : ''}">
          <div style="display:flex;align-items:center;gap:4px;flex:1;min-width:0;">
            <input type="text" class="kanban-col-title" value="${this._esc(col.title || '')}" placeholder="Spalte..." />
            <span class="kanban-card-count">${wipText}</span>
          </div>
          <button class="kanban-col-delete" data-kanban-action="delete-column" data-col-id="${col.id}" title="Spalte loeschen">${DELETE_SVG}</button>
        </div>
        <div class="kanban-cards"></div>
        <button class="kanban-add-card" data-kanban-action="add-card" data-col-id="${col.id}">+ Karte</button>
      `;

      const cardsContainer = colEl.querySelector('.kanban-cards');
      (col.cards || []).forEach(card => {
        cardsContainer.appendChild(this._createKanbanCardEl(kanbanEl, card));
      });

      this._bindKanbanDropZone(kanbanEl, colEl);

      // Column title save
      const titleInput = colEl.querySelector('.kanban-col-title');
      titleInput.addEventListener('blur', () => {
        col.title = titleInput.value;
        this._saveKanbanMetadata(kanbanEl);
      });
      titleInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.target.blur(); });

      return colEl;
    },

    _createKanbanCardEl(kanbanEl, card) {
      const cardEl = document.createElement('div');
      cardEl.className = 'kanban-card';
      cardEl.draggable = true;
      cardEl.dataset.cardId = card.id;

      cardEl.innerHTML = `
        <div style="display:flex;align-items:center;gap:4px;">
          <input type="text" class="kanban-card-title" value="${this._esc(card.title || '')}" placeholder="Karte..." />
          <button class="kanban-card-delete" data-kanban-action="delete-card" data-card-id="${card.id}" title="Karte loeschen">${DELETE_SVG}</button>
        </div>
      `;

      // Drag start
      cardEl.addEventListener('dragstart', (e) => {
        e.stopPropagation();
        const colEl = cardEl.closest('.kanban-column');
        e.dataTransfer.setData('application/kanban-card', JSON.stringify({
          cardId: card.id,
          sourceColId: colEl?.dataset.colId || '',
        }));
        e.dataTransfer.effectAllowed = 'move';
        cardEl.classList.add('kanban-card-dragging');
      });
      cardEl.addEventListener('dragend', () => {
        cardEl.classList.remove('kanban-card-dragging');
      });

      // Card title save
      const titleInput = cardEl.querySelector('.kanban-card-title');
      titleInput.addEventListener('blur', () => {
        card.title = titleInput.value;
        this._saveKanbanMetadata(kanbanEl);
      });
      titleInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.target.blur(); });

      return cardEl;
    },

    _bindKanbanDropZone(kanbanEl, colEl) {
      const cardsContainer = colEl.querySelector('.kanban-cards');

      colEl.addEventListener('dragover', (e) => {
        if (!e.dataTransfer.types.includes('application/kanban-card')) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        colEl.classList.add('kanban-drop-target');
      });

      colEl.addEventListener('dragleave', (e) => {
        if (!colEl.contains(e.relatedTarget)) {
          colEl.classList.remove('kanban-drop-target');
        }
      });

      colEl.addEventListener('drop', (e) => {
        e.preventDefault();
        colEl.classList.remove('kanban-drop-target');
        const raw = e.dataTransfer.getData('application/kanban-card');
        if (!raw) return;
        const { cardId, sourceColId } = JSON.parse(raw);
        const targetColId = colEl.dataset.colId;

        const data = kanbanEl._kanbanData;
        const srcCol = data.columns.find(c => c.id === sourceColId);
        const tgtCol = data.columns.find(c => c.id === targetColId);
        if (!srcCol || !tgtCol) return;

        // WIP check
        if (tgtCol.wipLimit > 0 && tgtCol.cards.length >= tgtCol.wipLimit && sourceColId !== targetColId) return;

        // Move card in data
        const cardIdx = srcCol.cards.findIndex(c => c.id === cardId);
        if (cardIdx === -1) return;
        const [card] = srcCol.cards.splice(cardIdx, 1);
        tgtCol.cards.push(card);

        // Move card in DOM
        const cardEl = kanbanEl.querySelector(`[data-card-id="${cardId}"]`);
        if (cardEl) cardsContainer.appendChild(cardEl);

        this._updateKanbanCounts(kanbanEl);
        this._saveKanbanMetadata(kanbanEl);
      });
    },

    _bindKanbanEvents(el) {
      el.addEventListener('click', (e) => {
        const action = e.target.closest('[data-kanban-action]')?.dataset.kanbanAction;
        if (!action) return;

        if (action === 'add-column') {
          e.stopPropagation();
          const newCol = {
            id: 'col_' + Date.now().toString(36),
            title: '',
            wipLimit: 0,
            cards: [],
          };
          el._kanbanData.columns.push(newCol);
          const colEl = this._createKanbanColumnEl(el, newCol);
          el.querySelector('.kanban-columns').appendChild(colEl);
          this._saveKanbanMetadata(el);
          setTimeout(() => colEl.querySelector('.kanban-col-title')?.focus(), 50);
          return;
        }

        if (action === 'add-card') {
          e.stopPropagation();
          const colId = e.target.closest('[data-col-id]')?.dataset.colId;
          const col = el._kanbanData.columns.find(c => c.id === colId);
          if (!col) return;

          // WIP check
          if (col.wipLimit > 0 && col.cards.length >= col.wipLimit) return;

          const newCard = {
            id: 'card_' + Date.now().toString(36),
            title: '',
            content: '',
          };
          col.cards.push(newCard);
          const colEl = el.querySelector(`[data-col-id="${colId}"]`);
          const cardEl = this._createKanbanCardEl(el, newCard);
          colEl.querySelector('.kanban-cards').appendChild(cardEl);
          this._updateKanbanCounts(el);
          this._saveKanbanMetadata(el);
          setTimeout(() => cardEl.querySelector('.kanban-card-title')?.focus(), 50);
          return;
        }

        if (action === 'delete-column') {
          e.stopPropagation();
          const colId = e.target.closest('[data-col-id]')?.dataset.colId;
          el._kanbanData.columns = el._kanbanData.columns.filter(c => c.id !== colId);
          el.querySelector(`[data-col-id="${colId}"]`)?.remove();
          this._saveKanbanMetadata(el);
          return;
        }

        if (action === 'delete-card') {
          e.stopPropagation();
          const cardId = e.target.closest('[data-card-id]')?.dataset.cardId;
          for (const col of el._kanbanData.columns) {
            col.cards = col.cards.filter(c => c.id !== cardId);
          }
          el.querySelector(`[data-card-id="${cardId}"]`)?.remove();
          this._updateKanbanCounts(el);
          this._saveKanbanMetadata(el);
          return;
        }
      });
    },

    _bindKanbanTitleSave(el) {
      const input = el.querySelector('.kanban-board-title');
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

    _saveKanbanMetadata(el) {
      const noteId = parseInt(el.dataset.noteId);
      if (noteId < 0) return;
      clearTimeout(this._kanbanTimers[noteId]);
      this._kanbanTimers[noteId] = setTimeout(() => {
        this.$wire.call('updateNoteMetadata', noteId, { columns: el._kanbanData.columns });
      }, 400);
    },

    _updateKanbanCounts(el) {
      const data = el._kanbanData;
      data.columns.forEach(col => {
        const colEl = el.querySelector(`[data-col-id="${col.id}"]`);
        if (!colEl) return;
        const count = col.cards.length;
        const wipText = col.wipLimit > 0 ? `${count}/${col.wipLimit}` : `${count}`;
        const header = colEl.querySelector('.kanban-column-header');
        const countEl = colEl.querySelector('.kanban-card-count');
        if (countEl) countEl.textContent = wipText;
        if (header) {
          header.classList.toggle('wip-exceeded', col.wipLimit > 0 && count > col.wipLimit);
        }
      });
    },

    // ─── Image Element ────────────────────────────────────
    _createImageEl(n) {
      const color = n.color || 'yellow';
      const x = n.x ?? 0;
      const y = n.y ?? 0;
      const w = n.width ?? 300;
      const h = n.height ?? 300;
      const meta = n.metadata || {};

      const el = document.createElement('div');
      el.className = `workshop-image workshop-image-${color}`;
      el.dataset.noteId = n.id;
      el.dataset.noteType = 'image';
      el.dataset.x = x;
      el.dataset.y = y;
      el.style.cssText = `width:${w}px;height:${h}px;transform:translate(${x}px,${y}px);`;

      el._imageData = { contextFileId: meta.contextFileId || null, src: meta.src || '', alt: meta.alt || '', objectFit: meta.objectFit || 'cover' };

      const hasImage = !!meta.src;
      el.innerHTML = `
        <div class="drag-handle image-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(color)}
          </div>
          <div style="display:flex;align-items:center;gap:4px;">
            <input type="text" class="image-alt-input" value="${this._esc(meta.alt || '')}" placeholder="Alt..." title="Bildbeschreibung" />
            <button class="note-delete" data-action="delete" title="Loeschen">${DELETE_SVG}</button>
          </div>
        </div>
        <div class="image-container">
          ${hasImage
            ? `<img src="${this._esc(meta.src)}" alt="${this._esc(meta.alt || '')}" style="object-fit:${meta.objectFit || 'cover'};" />`
            : `<div class="image-upload-zone" data-action="upload-image">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:32px;height:32px;"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>
                <span>Bild hochladen</span>
              </div>`
          }
        </div>
        <div class="resize-handle"></div>
      `;

      this._bindNoteEvents(el);
      this._bindImageEvents(el);

      return el;
    },

    _bindImageEvents(el) {
      // Upload zone click
      el.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="upload-image"]')) {
          e.stopPropagation();
          this._pendingUploadTarget = { noteEl: el, type: 'image' };
          this._fileInput.accept = 'image/*';
          this._fileInput.click();
        }
      });
      // Alt text save
      const altInput = el.querySelector('.image-alt-input');
      if (altInput) {
        altInput.addEventListener('blur', () => {
          el._imageData.alt = altInput.value;
          const img = el.querySelector('.image-container img');
          if (img) img.alt = altInput.value;
          const noteId = parseInt(el.dataset.noteId);
          if (noteId > 0) this._saveMediaMetadata(el, noteId);
        });
        altInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.target.blur(); });
      }
    },

    _applyImageUpload(el, data) {
      el._imageData.contextFileId = data.contextFileId;
      el._imageData.src = data.url;
      const container = el.querySelector('.image-container');
      container.innerHTML = `<img src="${this._esc(data.url)}" alt="${this._esc(el._imageData.alt)}" style="object-fit:${el._imageData.objectFit};" />`;
    },

    // ─── Image Grid Element ─────────────────────────────────
    _createImageGridEl(n) {
      const color = n.color || 'yellow';
      const x = n.x ?? 0;
      const y = n.y ?? 0;
      const w = n.width ?? 500;
      const h = n.height ?? 400;
      const meta = n.metadata || {};
      const images = meta.images || [];
      const columns = meta.columns || 2;
      const gap = meta.gap || 4;

      const el = document.createElement('div');
      el.className = `workshop-image-grid workshop-image-grid-${color}`;
      el.dataset.noteId = n.id;
      el.dataset.noteType = 'image_grid';
      el.dataset.x = x;
      el.dataset.y = y;
      el.style.cssText = `width:${w}px;height:${h}px;transform:translate(${x}px,${y}px);`;

      el._imageGridData = { images: JSON.parse(JSON.stringify(images)), columns, gap };

      el.innerHTML = `
        <div class="drag-handle image-grid-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(color)}
          </div>
          <div style="display:flex;align-items:center;gap:4px;">
            <div class="image-grid-cols-control">
              <button data-grid-action="cols-dec" title="Weniger Spalten">-</button>
              <span class="image-grid-cols-count">${columns}</span>
              <button data-grid-action="cols-inc" title="Mehr Spalten">+</button>
            </div>
            <button class="image-grid-add-btn" data-grid-action="add-image" title="Bild hinzufuegen">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${DELETE_SVG}</button>
          </div>
        </div>
        <div class="image-grid-container" style="grid-template-columns:repeat(${columns},1fr);gap:${gap}px;"></div>
        <div class="resize-handle"></div>
      `;

      const gridContainer = el.querySelector('.image-grid-container');
      images.forEach(img => gridContainer.appendChild(this._createGridItemEl(el, img)));

      this._bindNoteEvents(el);
      this._bindImageGridEvents(el);

      return el;
    },

    _createGridItemEl(gridEl, img) {
      const item = document.createElement('div');
      item.className = 'image-grid-item';
      item.dataset.imageId = img.id;
      item.innerHTML = `
        <img src="${this._esc(img.src)}" alt="${this._esc(img.alt || '')}" />
        <button class="image-grid-item-delete" data-grid-action="delete-image" data-image-id="${img.id}" title="Entfernen">${DELETE_SVG}</button>
      `;
      return item;
    },

    _bindImageGridEvents(el) {
      el.addEventListener('click', (e) => {
        const action = e.target.closest('[data-grid-action]')?.dataset.gridAction;
        if (!action) return;

        if (action === 'add-image') {
          e.stopPropagation();
          this._pendingUploadTarget = { noteEl: el, type: 'image_grid' };
          this._fileInput.accept = 'image/*';
          this._fileInput.click();
          return;
        }

        if (action === 'cols-dec') {
          e.stopPropagation();
          if (el._imageGridData.columns > 1) {
            el._imageGridData.columns--;
            this._updateGridLayout(el);
            const noteId = parseInt(el.dataset.noteId);
            if (noteId > 0) this._saveMediaMetadata(el, noteId);
          }
          return;
        }

        if (action === 'cols-inc') {
          e.stopPropagation();
          if (el._imageGridData.columns < 6) {
            el._imageGridData.columns++;
            this._updateGridLayout(el);
            const noteId = parseInt(el.dataset.noteId);
            if (noteId > 0) this._saveMediaMetadata(el, noteId);
          }
          return;
        }

        if (action === 'delete-image') {
          e.stopPropagation();
          const imageId = e.target.closest('[data-image-id]')?.dataset.imageId;
          el._imageGridData.images = el._imageGridData.images.filter(i => i.id !== imageId);
          el.querySelector(`[data-image-id="${imageId}"]`)?.closest('.image-grid-item')?.remove();
          const noteId = parseInt(el.dataset.noteId);
          if (noteId > 0) this._saveMediaMetadata(el, noteId);
          return;
        }
      });
    },

    _updateGridLayout(el) {
      const container = el.querySelector('.image-grid-container');
      container.style.gridTemplateColumns = `repeat(${el._imageGridData.columns},1fr)`;
      container.style.gap = `${el._imageGridData.gap}px`;
      el.querySelector('.image-grid-cols-count').textContent = el._imageGridData.columns;
    },

    _applyImageGridUpload(el, data) {
      const imgId = 'img_' + Date.now().toString(36);
      const imgData = { id: imgId, contextFileId: data.contextFileId, src: data.url, alt: '' };
      el._imageGridData.images.push(imgData);
      const gridContainer = el.querySelector('.image-grid-container');
      gridContainer.appendChild(this._createGridItemEl(el, imgData));
    },

    // ─── Video Element ──────────────────────────────────────
    _createVideoEl(n) {
      const color = n.color || 'blue';
      const x = n.x ?? 0;
      const y = n.y ?? 0;
      const w = n.width ?? 480;
      const h = n.height ?? 300;
      const meta = n.metadata || {};

      const el = document.createElement('div');
      el.className = `workshop-video workshop-video-${color}`;
      el.dataset.noteId = n.id;
      el.dataset.noteType = 'video';
      el.dataset.x = x;
      el.dataset.y = y;
      el.style.cssText = `width:${w}px;height:${h}px;transform:translate(${x}px,${y}px);`;

      el._videoData = {
        src: meta.src || '',
        provider: meta.provider || '',
        embedUrl: meta.embedUrl || '',
        contextFileId: meta.contextFileId || null,
      };

      const hasContent = !!(meta.embedUrl || meta.src);
      let contentHTML;
      if (meta.embedUrl) {
        contentHTML = `<iframe src="${this._esc(meta.embedUrl)}" allowfullscreen allow="autoplay; encrypted-media"></iframe>`;
      } else if (meta.src && meta.provider === 'upload') {
        contentHTML = `<video src="${this._esc(meta.src)}" controls></video>`;
      } else if (meta.src) {
        contentHTML = `<video src="${this._esc(meta.src)}" controls></video>`;
      } else {
        contentHTML = `
          <div class="video-upload-zone">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:32px;height:32px;"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
            <input type="text" class="video-url-input" placeholder="YouTube/Vimeo URL einfuegen..." />
            <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
              <span style="font-size:10px;color:#9ca3af;">oder</span>
              <button class="video-upload-btn" data-action="upload-video">Datei hochladen</button>
            </div>
          </div>
        `;
      }

      el.innerHTML = `
        <div class="drag-handle video-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(color)}
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${DELETE_SVG}</button>
        </div>
        <div class="video-container">${contentHTML}</div>
        <div class="resize-handle"></div>
      `;

      this._bindNoteEvents(el);
      this._bindVideoEvents(el);

      return el;
    },

    _bindVideoEvents(el) {
      // URL input
      const urlInput = el.querySelector('.video-url-input');
      if (urlInput) {
        const applyUrl = () => {
          const url = urlInput.value.trim();
          if (!url) return;
          const parsed = this._parseVideoUrl(url);
          el._videoData = { ...el._videoData, ...parsed };
          const container = el.querySelector('.video-container');
          if (parsed.embedUrl) {
            container.innerHTML = `<iframe src="${this._esc(parsed.embedUrl)}" allowfullscreen allow="autoplay; encrypted-media"></iframe>`;
          } else if (parsed.src) {
            container.innerHTML = `<video src="${this._esc(parsed.src)}" controls></video>`;
          }
          const noteId = parseInt(el.dataset.noteId);
          if (noteId > 0) this._saveMediaMetadata(el, noteId);
        };
        urlInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); applyUrl(); } });
        urlInput.addEventListener('blur', applyUrl);
      }

      // Upload button
      el.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="upload-video"]')) {
          e.stopPropagation();
          this._pendingUploadTarget = { noteEl: el, type: 'video' };
          this._fileInput.accept = 'video/*';
          this._fileInput.click();
        }
      });
    },

    _applyVideoUpload(el, data) {
      el._videoData = { src: data.url, provider: 'upload', embedUrl: '', contextFileId: data.contextFileId };
      const container = el.querySelector('.video-container');
      container.innerHTML = `<video src="${this._esc(data.url)}" controls></video>`;
    },

    _parseVideoUrl(url) {
      // YouTube
      let match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
      if (match) {
        return { provider: 'youtube', embedUrl: `https://www.youtube.com/embed/${match[1]}`, src: url };
      }
      // Vimeo
      match = url.match(/vimeo\.com\/(\d+)/);
      if (match) {
        return { provider: 'vimeo', embedUrl: `https://player.vimeo.com/video/${match[1]}`, src: url };
      }
      // Direct
      return { provider: 'direct', src: url, embedUrl: '' };
    },

    // ─── Shared media metadata save ─────────────────────────
    _saveMediaMetadata(el, noteId) {
      clearTimeout(this._mediaTimers[noteId]);
      this._mediaTimers[noteId] = setTimeout(() => {
        const type = el.dataset.noteType;
        let meta = {};
        if (type === 'image') {
          meta = { ...el._imageData };
        } else if (type === 'image_grid') {
          meta = { images: el._imageGridData.images, columns: el._imageGridData.columns, gap: el._imageGridData.gap };
        } else if (type === 'video') {
          meta = { ...el._videoData };
        }
        this.$wire.call('updateNoteMetadata', noteId, meta);
      }, 400);
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
        if (e.target.closest('.workshop-note, .workshop-text, .workshop-section, .workshop-shape, .workshop-kanban, .workshop-image, .workshop-image-grid, .workshop-video, .workshop-toolbar, .workshop-zoom-controls')) return;
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
        // Update connector preview line
        if (this._previewLine && this._connectorMode && this._connectorFrom) {
          const pt = this._screenToBoard(e.clientX, e.clientY);
          this._previewLine.setAttribute('x2', pt.x);
          this._previewLine.setAttribute('y2', pt.y);
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
      this.isFullscreen = !this.isFullscreen;
      this._fitAfterDelay();
    },

    _fitAfterDelay() {
      // Browser needs time to settle the fullscreen layout; retry multiple times
      setTimeout(() => this._fitGrid(), 50);
      setTimeout(() => this._fitGrid(), 200);
      setTimeout(() => this._fitGrid(), 500);
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
      const draggableSelector = '.workshop-note, .workshop-text, .workshop-section, .workshop-shape, .workshop-kanban, .workshop-image, .workshop-image-grid, .workshop-video';
      // Note: connectors are not draggable (they are SVG paths)
      const resizableSelector = draggableSelector;

      // Draggable — all element types use .drag-handle
      interact(draggableSelector).draggable({
        allowFrom: '.drag-handle',
        ignoreFrom: 'input, textarea, .note-delete, .shape-toggle, .color-dot, .color-picker-dd, .kanban-cards, .kanban-card, .kanban-column, .kanban-add-card, .kanban-add-col-btn, .kanban-col-title, .kanban-col-delete, .kanban-card-title, .kanban-card-delete, .image-upload-zone, .image-grid-add-btn, .image-grid-container, .image-grid-cols-control, .video-url-input, .video-upload-btn, .video-upload-zone, .video-container iframe, .video-container video',
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
            self._updateConnectors();
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

      // Resizable — all element types
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
            self._updateConnectors();
          },
          end(ev) {
            const t = ev.target;
            const id = parseInt(t.dataset.noteId);
            if (id < 0) return;
            self._savePos(id, t);
          },
        },
      });

      // Canvas grid background — resizable + persist
      interact('.workshop-canvas-background').resizable({
        edges: { right: true, bottom: true },
        modifiers: [interact.modifiers.restrictSize({ min: { width: 400, height: 300 } })],
        listeners: {
          move(ev) {
            const t = ev.target;
            t.style.width = ev.rect.width / self.scale + 'px';
            t.style.minHeight = ev.rect.height / self.scale + 'px';
          },
          end(ev) {
            const t = ev.target;
            const w = parseInt(t.style.width) || 1200;
            const h = parseInt(t.style.minHeight) || 800;
            clearTimeout(self._gridSaveTimer);
            self._gridSaveTimer = setTimeout(() => {
              self.$wire.call('updateWorkshopSettings', { gridWidth: w, gridHeight: h });
            }, 400);
          },
        },
      });

      // Dropzones on grid blocks — disabled for now
      // interact('.workshop-grid-block').dropzone({ ... });
    },

    _savePos(noteId, el) {
      clearTimeout(this._saveTimers[noteId]);
      this._saveTimers[noteId] = setTimeout(() => {
        const blockId = this._detectBlock(el);
        this.$wire.call('updateNotePosition', noteId, {
          x: parseFloat(el.dataset.x) || 0,
          y: parseFloat(el.dataset.y) || 0,
          width: parseInt(el.style.width) || 200,
          height: parseInt(el.style.height) || 150,
          blockId,
        });
      }, 300);
    },

    /** Check which grid block the note center overlaps (board-coordinate hit test) */
    _detectBlock(el) {
      const x = parseFloat(el.dataset.x) || 0;
      const y = parseFloat(el.dataset.y) || 0;
      const cx = x + (parseInt(el.style.width) || 0) / 2;
      const cy = y + (parseInt(el.style.height) || 0) / 2;

      const blocks = this.$refs.board?.querySelectorAll('.workshop-grid-block[data-block-id]');
      if (!blocks) return null;

      for (const block of blocks) {
        const parent = block.offsetParent;
        const bx = block.offsetLeft + (parent?.offsetLeft || 0);
        const by = block.offsetTop + (parent?.offsetTop || 0);
        const bw = block.offsetWidth;
        const bh = block.offsetHeight;
        if (cx >= bx && cx <= bx + bw && cy >= by && cy <= by + bh) {
          return parseInt(block.dataset.blockId) || null;
        }
      }
      return null;
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
      const metadata = type === 'shape' ? { shape: 'rect' }
        : type === 'kanban' ? { columns: [
            { id: 'col_' + Date.now().toString(36) + 'a', title: 'To Do', wipLimit: 0, cards: [] },
            { id: 'col_' + Date.now().toString(36) + 'b', title: 'In Progress', wipLimit: 3, cards: [] },
            { id: 'col_' + Date.now().toString(36) + 'c', title: 'Done', wipLimit: 0, cards: [] },
          ] }
        : type === 'image_grid' ? { images: [], columns: 2, gap: 4 }
        : null;
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
        const input = el.querySelector('.note-body input, .text-body input, .section-title, .shape-body input, .kanban-board-title, .image-alt-input, .video-url-input');
        input?.focus();
      }, 100);
    },

    // Keep legacy addNote for backwards compat
    addNote() { this.addElement('note'); },

    _deleteNote(el, noteId) {
      el.remove();
      // Remove connector SVG elements referencing this note
      if (this._svgLayer) {
        const noteIdStr = String(noteId);
        this._svgLayer.querySelectorAll('.workshop-connector-path').forEach(path => {
          if (path.dataset.fromNoteId === noteIdStr || path.dataset.toNoteId === noteIdStr) {
            const fo = this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${path.dataset.connectorId}"]`);
            if (fo) fo.remove();
            // Also remove the hidden anchor div
            const anchor = this.$refs.board.querySelector(`[data-note-id="${path.dataset.connectorId}"][data-note-type="connector"]`);
            if (anchor) anchor.remove();
            path.remove();
          }
        });
      }
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
      } else if (noteType === 'kanban') {
        el.className = el.className.replace(/workshop-kanban-\w+/, `workshop-kanban-${color}`);
      } else if (noteType === 'image') {
        el.className = el.className.replace(/workshop-image-\w+/, `workshop-image-${color}`);
      } else if (noteType === 'image_grid') {
        el.className = el.className.replace(/workshop-image-grid-\w+/, `workshop-image-grid-${color}`);
      } else if (noteType === 'video') {
        el.className = el.className.replace(/workshop-video-\w+/, `workshop-video-${color}`);
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
      // Update the visual
      const visual = el.querySelector('.shape-visual');
      if (visual) visual.className = `shape-visual`;
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

    // ─── Connector (Arrow) System ──────────────────────────

    _createConnectorEl(n) {
      const meta = n.metadata || {};
      // Create a hidden anchor div for ID tracking (not visible)
      const el = document.createElement('div');
      el.style.cssText = 'position:absolute;width:0;height:0;pointer-events:none;';
      el.dataset.noteId = n.id;
      el.dataset.noteType = 'connector';
      el.dataset.fromNoteId = meta.fromNoteId || '';
      el.dataset.toNoteId = meta.toNoteId || '';

      // Create SVG path in the SVG layer
      if (this._svgLayer) {
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.classList.add('workshop-connector-path');
        path.dataset.connectorId = n.id;
        path.dataset.fromNoteId = meta.fromNoteId || '';
        path.dataset.toNoteId = meta.toNoteId || '';
        path.setAttribute('marker-end', 'url(#arrowhead)');
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', '#6b7280');
        path.setAttribute('stroke-width', '2');
        path.style.pointerEvents = 'stroke';
        path.style.cursor = 'pointer';
        this._svgLayer.appendChild(path);

        // Delete button (foreignObject)
        const fo = document.createElementNS('http://www.w3.org/2000/svg', 'foreignObject');
        fo.classList.add('connector-delete-fo');
        fo.dataset.connectorId = n.id;
        fo.setAttribute('width', '24');
        fo.setAttribute('height', '24');
        fo.style.overflow = 'visible';
        fo.style.display = 'none';
        fo.innerHTML = `<button xmlns="http://www.w3.org/1999/xhtml" class="connector-delete-btn" title="Loeschen">${DELETE_SVG}</button>`;
        this._svgLayer.appendChild(fo);

        // Show delete on hover
        path.addEventListener('mouseenter', () => {
          fo.style.display = '';
          path.classList.add('hovered');
        });
        path.addEventListener('mouseleave', () => {
          setTimeout(() => {
            if (!fo.matches(':hover')) {
              fo.style.display = 'none';
              path.classList.remove('hovered');
            }
          }, 200);
        });
        fo.addEventListener('mouseleave', () => {
          fo.style.display = 'none';
          path.classList.remove('hovered');
        });

        // Delete connector on click
        fo.querySelector('.connector-delete-btn').addEventListener('click', (e) => {
          e.stopPropagation();
          const connId = parseInt(n.id);
          path.remove();
          fo.remove();
          el.remove();
          if (connId > 0) this.$wire.call('deleteWorkshopNote', connId);
        });

        // Compute initial path
        this._updateSingleConnector(path, fo);
      }

      return el;
    },

    _getAnchorPoint(noteId) {
      const el = this.$refs.board.querySelector(`[data-note-id="${noteId}"]:not([data-note-type="connector"])`);
      if (!el) return null;
      const x = parseFloat(el.dataset.x) || 0;
      const y = parseFloat(el.dataset.y) || 0;
      const w = parseInt(el.style.width) || 0;
      const h = parseInt(el.style.height) || 0;
      return { x, y, w, h, cx: x + w / 2, cy: y + h / 2 };
    },

    _bestAnchors(fromRect, toRect) {
      // Determine best side anchors based on relative position
      const dx = toRect.cx - fromRect.cx;
      const dy = toRect.cy - fromRect.cy;

      let fromPt, toPt;
      if (Math.abs(dx) > Math.abs(dy)) {
        // Horizontal dominant
        if (dx > 0) {
          fromPt = { x: fromRect.x + fromRect.w, y: fromRect.cy };
          toPt   = { x: toRect.x,                y: toRect.cy };
        } else {
          fromPt = { x: fromRect.x,              y: fromRect.cy };
          toPt   = { x: toRect.x + toRect.w,     y: toRect.cy };
        }
      } else {
        // Vertical dominant
        if (dy > 0) {
          fromPt = { x: fromRect.cx, y: fromRect.y + fromRect.h };
          toPt   = { x: toRect.cx,   y: toRect.y };
        } else {
          fromPt = { x: fromRect.cx, y: fromRect.y };
          toPt   = { x: toRect.cx,   y: toRect.y + toRect.h };
        }
      }
      return { from: fromPt, to: toPt };
    },

    _buildConnectorPath(from, to) {
      const dx = to.x - from.x;
      const dy = to.y - from.y;
      const dist = Math.sqrt(dx * dx + dy * dy);
      const offset = Math.min(dist * 0.4, 80);

      // Determine control point direction based on whether connection is more horizontal or vertical
      let cx1, cy1, cx2, cy2;
      if (Math.abs(dx) > Math.abs(dy)) {
        cx1 = from.x + offset * Math.sign(dx);
        cy1 = from.y;
        cx2 = to.x - offset * Math.sign(dx);
        cy2 = to.y;
      } else {
        cx1 = from.x;
        cy1 = from.y + offset * Math.sign(dy);
        cx2 = to.x;
        cy2 = to.y - offset * Math.sign(dy);
      }

      return `M ${from.x},${from.y} C ${cx1},${cy1} ${cx2},${cy2} ${to.x},${to.y}`;
    },

    _updateSingleConnector(path, fo) {
      const fromId = path.dataset.fromNoteId;
      const toId = path.dataset.toNoteId;
      if (!fromId || !toId) return;

      const fromRect = this._getAnchorPoint(fromId);
      const toRect = this._getAnchorPoint(toId);

      if (!fromRect || !toRect) {
        path.setAttribute('d', '');
        return;
      }

      const { from, to } = this._bestAnchors(fromRect, toRect);
      path.setAttribute('d', this._buildConnectorPath(from, to));

      // Position delete button at midpoint
      const mx = (from.x + to.x) / 2 - 12;
      const my = (from.y + to.y) / 2 - 12;
      fo.setAttribute('x', mx);
      fo.setAttribute('y', my);
    },

    _updateConnectors() {
      if (!this._svgLayer) return;
      const paths = this._svgLayer.querySelectorAll('.workshop-connector-path');
      paths.forEach(path => {
        const fo = this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${path.dataset.connectorId}"]`);
        if (fo) this._updateSingleConnector(path, fo);
      });
    },

    // ─── Connector Mode (creation flow) ────────────────────

    startConnectorMode() {
      if (this._connectorMode) {
        this._cancelConnectorMode();
        return;
      }
      this._connectorMode = true;
      this._connectorFrom = null;
      this.$refs.board.parentElement.classList.add('connector-mode');
    },

    _cancelConnectorMode() {
      this._connectorMode = false;
      this._connectorFrom = null;
      this.$refs.board.parentElement.classList.remove('connector-mode');
      this.$refs.board.querySelectorAll('.connector-source-selected').forEach(el => el.classList.remove('connector-source-selected'));
      // Remove preview line
      if (this._previewLine) { this._previewLine.remove(); this._previewLine = null; }
    },

    _handleConnectorClick(noteEl) {
      if (!this._connectorMode) return false;
      const noteId = parseInt(noteEl.dataset.noteId);
      const noteType = noteEl.dataset.noteType;
      if (noteType === 'connector') return false; // can't connect to a connector

      if (!this._connectorFrom) {
        // First click: select source
        this._connectorFrom = noteId;
        noteEl.classList.add('connector-source-selected');

        // Create preview line
        const preview = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        preview.classList.add('workshop-connector-preview');
        preview.setAttribute('stroke', '#f2ca52');
        preview.setAttribute('stroke-width', '2');
        preview.setAttribute('stroke-dasharray', '6 4');
        preview.style.pointerEvents = 'none';
        const fromRect = this._getAnchorPoint(noteId);
        if (fromRect) {
          preview.setAttribute('x1', fromRect.cx);
          preview.setAttribute('y1', fromRect.cy);
          preview.setAttribute('x2', fromRect.cx);
          preview.setAttribute('y2', fromRect.cy);
        }
        this._svgLayer.appendChild(preview);
        this._previewLine = preview;

        return true;
      } else {
        // Second click: select target and create connector
        if (noteId === this._connectorFrom) return true; // same element, ignore

        const fromId = this._connectorFrom;
        const toId = noteId;

        // Optimistic: create connector in DOM
        const tempId = this._nextTempId--;
        const connEl = this._createConnectorEl({
          id: tempId, type: 'connector', title: '', content: '',
          color: 'blue', x: 0, y: 0, width: 0, height: 0,
          metadata: { fromNoteId: fromId, toNoteId: toId, style: 'solid', arrowHead: 'end' },
        });
        this.$refs.board.appendChild(connEl);

        // Persist
        this.$wire.call('addConnector', fromId, toId).then(() => {
          this.$wire.call('getWorkshopNotes').then((serverNotes) => {
            if (Array.isArray(serverNotes)) {
              const connectors = serverNotes.filter(n => n.type === 'connector');
              if (connectors.length > 0) {
                const newest = connectors.reduce((a, b) => a.id > b.id ? a : b);
                connEl.dataset.noteId = newest.id;
                // Update SVG elements with real ID
                const path = this._svgLayer.querySelector(`.workshop-connector-path[data-connector-id="${tempId}"]`);
                const fo = this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${tempId}"]`);
                if (path) path.dataset.connectorId = newest.id;
                if (fo) fo.dataset.connectorId = newest.id;
              }
            }
          });
        });

        this._cancelConnectorMode();
        return true;
      }
    },
  };
}
