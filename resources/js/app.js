import './bootstrap';
import './reports-charts';

import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();


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
