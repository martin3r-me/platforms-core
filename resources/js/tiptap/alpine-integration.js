import { Editor } from '@tiptap/core';
import { EditorState } from '@tiptap/pm/state';
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
    sending: false,
    sendFailed: false,
    _destroyToolbar: null,
    _destroyEmoji: null,

    init() {
      const extensions = [
        StarterKit.configure({
          heading: false,
          blockquote: false,
          horizontalRule: false,
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

              // Let Enter pass through inside lists and code blocks
              const editor = this.editor;
              if (editor && (editor.isActive('bulletList') || editor.isActive('orderedList') || editor.isActive('codeBlock'))) {
                return false;
              }

              event.preventDefault();
              // Defer submit entirely out of ProseMirror's dispatch cycle
              setTimeout(() => this.submit(), 0);
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

      if (emojis && this.$refs.emojiSlot) {
        const picker = createEmojiPicker(this.editor);
        this.$refs.emojiSlot.appendChild(picker.element);
        this._destroyEmoji = picker.destroy;
      }
    },

    async submit() {
      // Guard gegen Doppel-Senden während ein Send läuft (Editor ist dann noch befüllt).
      if (!this.editor || this.editor.isEmpty || this.sending) return;

      const html = this.editor.getHTML();
      const text = this.editor.getText();
      const json = this.editor.getJSON();

      if (typeof onSubmit !== 'function') {
        console.log('tiptapEditor.submit:', { html, text });
        return;
      }

      // Robustes Senden: Editor NICHT sofort leeren. Erst bei bestätigtem Erfolg
      // leeren; bei Fehler bleibt der Text erhalten (er ist per wire:ignore vor
      // Morph geschützt), damit keine Nachricht stillschweigend verloren geht.
      this.sending = true;
      this.sendFailed = false;
      try {
        await onSubmit(html, text, json);

        // Erfolg → Editor-State direkt ersetzen (umgeht ProseMirror-Transaktions-
        // fehler bei überlappenden Livewire-Morph-Zyklen).
        const { schema, plugins } = this.editor.view.state;
        this.editor.view.updateState(EditorState.create({
          doc: schema.node('doc', null, [schema.node('paragraph')]),
          plugins,
        }));
        this.isEmpty = true;
      } catch (e) {
        this.sendFailed = true;
      } finally {
        this.sending = false;
        this.editor.commands.focus();
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
