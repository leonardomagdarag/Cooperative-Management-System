import './bootstrap';
import './reports-charts';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;

// window.livewireScriptConfig is set in dashboard.blade.php BEFORE this script loads.
// That prevents livewire.esm from registering a DOMContentLoaded auto-start.
// We call Livewire.start() manually here (once) to boot everything.
Livewire.start();


const navbar = document.getElementById("top-navbar");

if (navbar) {
    window.addEventListener("scroll", () => {
        if (window.scrollY > 0) {
            navbar.classList.add("scrolled");
        } else {
            navbar.classList.remove("scrolled");
        }
    });
}
