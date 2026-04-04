import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Mention from '@tiptap/extension-mention';
import Placeholder from '@tiptap/extension-placeholder';
import { createMentionSuggestion } from './mention-suggestion.js';
import { createFloatingToolbar } from './floating-toolbar.js';

/**
 * Alpine.js `tiptapEditor` component.
 *
 * Usage in Blade:
 *   <div x-data="tiptapEditor({ placeholder: '...', onSubmit: (html, text, json) => {} })">
 *     <div x-ref="editorEl"></div>
 *     <button @click="submit()" :disabled="isEmpty">Senden</button>
 *   </div>
 */
export function tiptapEditor({
  placeholder = 'Nachricht schreiben...',
  fetchUsers = null,
  fetchContexts = null,
  onSubmit = null,
  toolbar = true,
} = {}) {
  return {
    editor: null,
    isEmpty: true,
    _destroyToolbar: null,

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
        },
      });

      this.isEmpty = this.editor.isEmpty;

      if (toolbar) {
        this._destroyToolbar = createFloatingToolbar(this.editor);
      }
    },

    submit() {
      if (!this.editor || this.editor.isEmpty) return;

      const html = this.editor.getHTML();
      const text = this.editor.getText();
      const json = this.editor.getJSON();

      if (typeof onSubmit === 'function') {
        onSubmit(html, text, json);
      } else {
        console.log('tiptapEditor.submit:', { html, text });
      }

      this.editor.commands.clearContent(true);
      this.isEmpty = true;
      this.editor.commands.focus();
    },

    destroy() {
      if (this._destroyToolbar) {
        this._destroyToolbar();
        this._destroyToolbar = null;
      }
      if (this.editor) {
        this.editor.destroy();
        this.editor = null;
      }
    },
  };
}
