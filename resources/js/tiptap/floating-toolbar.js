import tippy from 'tippy.js';

const BUTTONS = [
  { cmd: 'toggleBold',   mark: 'bold',   icon: 'B',  title: 'Fett (Cmd+B)',        style: 'font-weight:700' },
  { cmd: 'toggleItalic', mark: 'italic', icon: 'I',  title: 'Kursiv (Cmd+I)',      style: 'font-style:italic' },
  { cmd: 'toggleStrike', mark: 'strike', icon: 'S',  title: 'Durchgestrichen',     style: 'text-decoration:line-through' },
  { cmd: 'toggleCode',   mark: 'code',   icon: '<>', title: 'Code (Cmd+E)',        style: 'font-family:monospace;font-size:11px' },
];

function renderToolbar(editor) {
  return BUTTONS.map(b => {
    const active = editor.isActive(b.mark);
    return `<button
      type="button"
      data-cmd="${b.cmd}"
      title="${b.title}"
      class="floating-toolbar-btn ${active ? 'is-active' : ''}"
      style="${b.style}"
    >${b.icon}</button>`;
  }).join('');
}

/**
 * Creates a floating toolbar that appears on text selection.
 * Call with the editor instance; returns a destroy function.
 */
export function createFloatingToolbar(editor) {
  let popup = null;
  let container = null;

  container = document.createElement('div');
  container.className = 'floating-toolbar';
  container.innerHTML = renderToolbar(editor);

  function attachHandlers() {
    container.querySelectorAll('.floating-toolbar-btn').forEach(btn => {
      btn.addEventListener('mousedown', (e) => {
        e.preventDefault();
        const cmd = btn.dataset.cmd;
        editor.chain().focus()[cmd]().run();
        updateButtons();
      });
    });
  }

  function updateButtons() {
    container.innerHTML = renderToolbar(editor);
    attachHandlers();
  }

  attachHandlers();

  // Hidden tippy anchored to selection
  const editorEl = editor.options.element;
  popup = tippy(editorEl, {
    content: container,
    interactive: true,
    trigger: 'manual',
    placement: 'top',
    offset: [0, 8],
    maxWidth: 'none',
    appendTo: () => document.body,
    getReferenceClientRect: () => {
      const { from, to } = editor.state.selection;
      const start = editor.view.coordsAtPos(from);
      const end = editor.view.coordsAtPos(to);
      return {
        top: Math.min(start.top, end.top),
        bottom: Math.max(start.bottom, end.bottom),
        left: start.left,
        right: end.right,
        width: end.right - start.left,
        height: Math.max(start.bottom, end.bottom) - Math.min(start.top, end.top),
        x: start.left,
        y: Math.min(start.top, end.top),
      };
    },
  });

  function onSelectionUpdate() {
    const { from, to, empty } = editor.state.selection;
    if (empty || to - from < 1) {
      popup.hide();
      return;
    }
    updateButtons();
    popup.setProps({
      getReferenceClientRect: () => {
        const start = editor.view.coordsAtPos(from);
        const end = editor.view.coordsAtPos(to);
        return {
          top: Math.min(start.top, end.top),
          bottom: Math.max(start.bottom, end.bottom),
          left: start.left,
          right: end.right,
          width: end.right - start.left,
          height: Math.max(start.bottom, end.bottom) - Math.min(start.top, end.top),
          x: start.left,
          y: Math.min(start.top, end.top),
        };
      },
    });
    popup.show();
  }

  editor.on('selectionUpdate', onSelectionUpdate);
  editor.on('blur', () => popup.hide());

  return () => {
    editor.off('selectionUpdate', onSelectionUpdate);
    if (popup) popup.destroy();
  };
}
