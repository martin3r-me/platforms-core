/**
 * Platform Workshop — IIFE Entry
 *
 * Exports on window.PlatformWorkshop and auto-registers Alpine component.
 */
import { workshopBoard } from './board.js';

// Inject workshop styles into <head>
import workshopCSS from './workshop.css';

function injectStyles() {
  if (document.getElementById('platform-workshop-styles')) return;
  const style = document.createElement('style');
  style.id = 'platform-workshop-styles';
  style.textContent = workshopCSS;
  document.head.appendChild(style);
}

// Auto-register Alpine component when Livewire boots
function autoRegister() {
  const Alpine = window.Alpine;
  if (!Alpine) return;
  Alpine.data('workshopBoard', workshopBoard);
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
export { workshopBoard };
