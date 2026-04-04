import tippy from 'tippy.js';

const EMOJI_CATEGORIES = [
  {
    name: 'Häufig',
    emojis: ['👍', '❤️', '😊', '😂', '🎉', '🔥', '✅', '👀', '🙏', '💪', '😍', '🤔', '👏', '💯', '🚀'],
  },
  {
    name: 'Smileys',
    emojis: ['😀', '😃', '😄', '😁', '😅', '🤣', '😇', '🙂', '😉', '😌', '😋', '😎', '🤩', '🥳', '😏'],
  },
  {
    name: 'Gesten',
    emojis: ['👋', '🤝', '✌️', '🤞', '👌', '🤙', '👆', '👇', '👈', '👉', '☝️', '✋', '🤚', '🖐️', '🫡'],
  },
  {
    name: 'Objekte',
    emojis: ['💡', '📌', '📎', '✏️', '📝', '📅', '📊', '📈', '💻', '📱', '⏰', '🔔', '📧', '🗂️', '🏷️'],
  },
  {
    name: 'Symbole',
    emojis: ['✅', '❌', '⚠️', '❓', '❗', '💬', '🔗', '⭐', '🏆', '🎯', '🔒', '🔑', '♻️', '➡️', '⬅️'],
  },
];

function renderPicker(activeCategory) {
  const tabs = EMOJI_CATEGORIES.map((cat, i) => {
    const active = i === activeCategory;
    return `<button type="button" data-cat="${i}" class="emoji-picker-tab ${active ? 'is-active' : ''}">${cat.emojis[0]}</button>`;
  }).join('');

  const cat = EMOJI_CATEGORIES[activeCategory];
  const grid = cat.emojis.map(e =>
    `<button type="button" data-emoji="${e}" class="emoji-picker-emoji" title="${e}">${e}</button>`
  ).join('');

  return `<div class="emoji-picker-tabs">${tabs}</div><div class="emoji-picker-grid">${grid}</div>`;
}

/**
 * Creates an emoji picker button + tippy dropdown.
 * Returns { element, destroy } — append element to DOM yourself.
 */
export function createEmojiPicker(editor) {
  let popup = null;
  let activeCategory = 0;

  // Trigger button
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'emoji-picker-trigger';
  btn.innerHTML = '😊';
  btn.title = 'Emoji einfügen';

  // Container
  const container = document.createElement('div');
  container.className = 'emoji-picker';

  function render() {
    container.innerHTML = renderPicker(activeCategory);
    attachHandlers();
  }

  function attachHandlers() {
    container.querySelectorAll('.emoji-picker-tab').forEach(tab => {
      tab.addEventListener('mousedown', (e) => {
        e.preventDefault();
        activeCategory = parseInt(tab.dataset.cat, 10);
        render();
      });
    });
    container.querySelectorAll('.emoji-picker-emoji').forEach(el => {
      el.addEventListener('mousedown', (e) => {
        e.preventDefault();
        const emoji = el.dataset.emoji;
        editor.chain().focus().insertContent(emoji).run();
        popup.hide();
      });
    });
  }

  render();

  popup = tippy(btn, {
    content: container,
    interactive: true,
    trigger: 'click',
    placement: 'top-end',
    offset: [0, 8],
    maxWidth: 'none',
    appendTo: () => document.body,
    onShow: () => render(),
  });

  return {
    element: btn,
    destroy: () => {
      if (popup) popup.destroy();
    },
  };
}
