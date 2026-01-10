import './bootstrap';
import '@fortawesome/fontawesome-free/css/all.min.css';
import $ from 'jquery';
window.jQuery = window.$ = $;


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
