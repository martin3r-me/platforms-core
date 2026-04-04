import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Mention from '@tiptap/extension-mention';
import Placeholder from '@tiptap/extension-placeholder';
import { createMentionSuggestion } from './mention-suggestion.js';

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
} = {}) {
  return {
    editor: null,
    isEmpty: true,

    init() {
      const extensions = [
        StarterKit.configure({
          // Only keep essentials for a chat input
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
            // Enter without shift → submit
            if (event.key === 'Enter' && !event.shiftKey) {
              // Don't submit if mention dropdown is active
              const mentionActive = document.querySelector('.mention-dropdown');
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
      if (this.editor) {
        this.editor.destroy();
        this.editor = null;
      }
    },
  };
}
