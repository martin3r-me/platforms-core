import tippy from 'tippy.js';

const COLORS = ['emerald', 'blue', 'amber', 'violet', 'rose'];

const COLOR_MAP = {
  emerald: { bg: '#d1fae5', text: '#059669' },
  blue:    { bg: '#dbeafe', text: '#2563eb' },
  amber:   { bg: '#fef3c7', text: '#d97706' },
  violet:  { bg: '#ede9fe', text: '#7c3aed' },
  rose:    { bg: '#ffe4e6', text: '#e11d48' },
  gray:    { bg: '#f3f4f6', text: '#4b5563' },
};

function renderDropdown(items, selectedIndex) {
  if (!items.length) {
    return '<div class="mention-dropdown-empty">Keine Ergebnisse</div>';
  }
  return items.map((item, i) => {
    const colors = COLOR_MAP[item.color] || COLOR_MAP[COLORS[item.id % COLORS.length]] || COLOR_MAP.gray;
    const avatarInner = item.avatar
      ? `<img src="${item.avatar}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">`
      : (item.initials || item.label.charAt(0));
    return `<div class="mention-dropdown-item ${i === selectedIndex ? 'is-selected' : ''}" data-index="${i}">
      <div class="mention-dropdown-avatar" style="background:${item.avatar ? 'transparent' : colors.bg};color:${colors.text}">${avatarInner}</div>
      <span>${item.label}</span>
    </div>`;
  }).join('');
}

export function createMentionSuggestion(fetchUsers) {
  return {
    items: async ({ query }) => {
      if (typeof fetchUsers === 'function') {
        try {
          const results = await fetchUsers(query);
          if (Array.isArray(results)) return results.slice(0, 8);
        } catch (e) {
          console.warn('fetchUsers failed:', e);
        }
      }
      return [];
    },

    render: () => {
      let popup = null;
      let container = null;
      let items = [];
      let selectedIndex = 0;

      function updateDropdown() {
        if (!container) return;
        container.innerHTML = renderDropdown(items, selectedIndex);
        container.querySelectorAll('.mention-dropdown-item').forEach(el => {
          el.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const idx = parseInt(el.dataset.index, 10);
            if (items[idx]) selectItem(idx);
          });
        });
      }

      let commandFn = null;
      function selectItem(index) {
        const item = items[index];
        if (item && commandFn) {
          commandFn({ id: item.id, label: item.label });
        }
      }

      return {
        onStart: (props) => {
          commandFn = props.command;
          container = document.createElement('div');
          container.className = 'mention-dropdown';
          items = props.items;
          selectedIndex = 0;
          updateDropdown();

          popup = tippy(document.body, {
            getReferenceClientRect: props.clientRect,
            appendTo: () => document.body,
            content: container,
            showOnCreate: true,
            interactive: true,
            trigger: 'manual',
            placement: 'top-start',
            offset: [0, 8],
            maxWidth: 280,
          });
        },

        onUpdate: (props) => {
          items = props.items;
          selectedIndex = 0;
          updateDropdown();
          if (popup) {
            popup.setProps({ getReferenceClientRect: props.clientRect });
          }
        },

        onKeyDown: ({ event }) => {
          if (event.key === 'ArrowUp') {
            selectedIndex = (selectedIndex - 1 + items.length) % items.length;
            updateDropdown();
            return true;
          }
          if (event.key === 'ArrowDown') {
            selectedIndex = (selectedIndex + 1) % items.length;
            updateDropdown();
            return true;
          }
          if (event.key === 'Enter') {
            selectItem(selectedIndex);
            return true;
          }
          if (event.key === 'Escape') {
            if (popup) popup.hide();
            return true;
          }
          return false;
        },

        onExit: () => {
          if (popup) {
            popup.destroy();
          }
          popup = null;
          container = null;
        },
      };
    },
  };
}
