import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Mention from '@tiptap/extension-mention';
import Placeholder from '@tiptap/extension-placeholder';
import { createMentionSuggestion } from './mention-suggestion.js';
import { createFloatingToolbar } from './floating-toolbar.js';
import { createEmojiPicker } from './emoji-picker.js';

export function tiptapEditor({
  placeholder = 'Nachricht schreiben...',
  fetchUsers = null,
  fetchContexts = null,
  onSubmit = null,
  toolbar = true,
  emojis = true,
} = {}) {
  return {
    editor: null,
    isEmpty: true,
    _destroyToolbar: null,
    _destroyEmoji: null,

    init() {
      const extensions = [
        StarterKit.configure({
          heading: false,
          codeBlock: false,
          blockquote: false,
          horizontalRule: false,
          bulletList: false,
          orderedList: false,
          listItem: false,
        }),
        Placeholder.configure({ placeholder }),
        Mention.configure({
          HTMLAttributes: { class: 'mention' },
          suggestion: createMentionSuggestion(fetchUsers),
        }),
      ];

      this.editor = new Editor({
        element: this.$refs.editorEl,
        extensions,
        editorProps: {
          attributes: {
            class: 'tiptap-editor',
          },
          handleKeyDown: (view, event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              const mentionActive = document.querySelector('.tippy-box .mention-dropdown');
              if (mentionActive) return false;

              event.preventDefault();
              this.submit();
              return true;
            }
            return false;
          },
        },
        onUpdate: ({ editor }) => {
          this.isEmpty = editor.isEmpty;
          this._autoResize();
        },
      });

      this.isEmpty = this.editor.isEmpty;

      if (toolbar) {
        this._destroyToolbar = createFloatingToolbar(this.editor);
      }

      if (emojis && this.$refs.emojiSlot) {
        const picker = createEmojiPicker(this.editor);
        this.$refs.emojiSlot.appendChild(picker.element);
        this._destroyEmoji = picker.destroy;
      }
    },

    _autoResize() {
      // Let the ProseMirror content determine height, then adjust terminal
      const proseMirror = this.$refs.editorEl?.querySelector('.ProseMirror');
      if (!proseMirror) return;

      // Reset to auto to measure real scroll height
      proseMirror.style.height = 'auto';
      const scrollH = proseMirror.scrollHeight;
      const maxH = 200; // max editor height in px

      if (scrollH > maxH) {
        proseMirror.style.height = maxH + 'px';
        proseMirror.style.overflowY = 'auto';
      } else {
        proseMirror.style.height = scrollH + 'px';
        proseMirror.style.overflowY = 'hidden';
      }

      // Grow terminal panel if needed
      this._adjustTerminalHeight();
    },

    _adjustTerminalHeight() {
      // Find the slide container (parent with transition-[height])
      const slideEl = this.$el.closest('[wire\\:key="terminal-slide"]')
        || this.$el.closest('.w-full.border-t');
      if (!slideEl) return;

      const minH = 320; // 20rem = 320px
      const maxH = Math.floor(window.innerHeight * 0.5); // max 50vh

      // Measure natural content height
      const contentEl = slideEl.querySelector('[wire\\:key="terminal-content"]');
      if (!contentEl) return;

      const naturalH = contentEl.scrollHeight;
      const targetH = Math.max(minH, Math.min(naturalH, maxH));

      slideEl.style.height = targetH + 'px';
    },

    submit() {
      if (!this.editor || this.editor.isEmpty) return;

      const html = this.editor.getHTML();
      const text = this.editor.getText();
      const json = this.editor.getJSON();

      // Defer clear to next event-loop tick — avoids "mismatched transaction"
      // when called from handleKeyDown (Enter key), because ProseMirror needs
      // the full dispatch cycle to finish before we can apply a new transaction.
      setTimeout(() => {
        this.editor.commands.clearContent(true);
        this.isEmpty = true;
        this.editor.commands.focus();
        this.$nextTick(() => this._autoResize());
      }, 0);

      if (typeof onSubmit === 'function') {
        onSubmit(html, text, json);
      } else {
        console.log('tiptapEditor.submit:', { html, text });
      }
    },

    destroy() {
      if (this._destroyToolbar) {
        this._destroyToolbar();
        this._destroyToolbar = null;
      }
      if (this._destroyEmoji) {
        this._destroyEmoji();
        this._destroyEmoji = null;
      }
      if (this.editor) {
        this.editor.destroy();
        this.editor = null;
      }
    },
  };
}
