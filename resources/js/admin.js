import '../css/admin.css';
import Alpine from 'alpinejs';
import { registerBlatUI } from '@blatui/blatui-core.js';

// registerBlatUI already registers the anchor/focus/collapse plugins and the
// theme store; calling alpine.plugin(...) for them here would double-register.
document.addEventListener('alpine:init', () => {
    registerBlatUI(window.Alpine, { darkMode: 'system' });
});

if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}
