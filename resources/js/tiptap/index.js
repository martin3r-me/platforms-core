/**
 * Platform Tiptap — IIFE Entry
 *
 * Exports on window.PlatformTiptap and auto-registers Alpine component.
 */
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Mention from '@tiptap/extension-mention';
import Placeholder from '@tiptap/extension-placeholder';
import { createMentionSuggestion } from './mention-suggestion.js';
import { createFloatingToolbar } from './floating-toolbar.js';
import { createEmojiPicker } from './emoji-picker.js';
import { tiptapEditor } from './alpine-integration.js';

// Inject editor styles into <head>
import editorCSS from './editor.css';

function injectStyles() {
  if (document.getElementById('platform-tiptap-styles')) return;
  const style = document.createElement('style');
  style.id = 'platform-tiptap-styles';
  style.textContent = editorCSS;
  document.head.appendChild(style);
}

// Auto-register Alpine component when Livewire boots
function autoRegister() {
  const Alpine = window.Alpine;
  if (!Alpine) return;
  Alpine.data('tiptapEditor', tiptapEditor);
}

// Register on livewire:init (Livewire 3) or DOMContentLoaded fallback
if (typeof document !== 'undefined') {
  injectStyles();

  document.addEventListener('livewire:init', autoRegister);
  // Fallback if Livewire already loaded or not present
  if (document.readyState !== 'loading') {
    setTimeout(autoRegister, 0);
  } else {
    document.addEventListener('DOMContentLoaded', autoRegister);
  }
}

// Public API
export {
  Editor,
  StarterKit,
  Mention,
  Placeholder,
  createMentionSuggestion,
  createFloatingToolbar,
  createEmojiPicker,
  tiptapEditor,
};
