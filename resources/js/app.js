import './bootstrap';
const navbar = document.getElementById("top-navbar");

window.addEventListener("scroll", () => {
    if (window.scrollY > 0) {
        navbar.classList.add("scrolled");
    } else {
        navbar.classList.remove("scrolled");
    }
});
