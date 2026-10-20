
const menuToggle = document.getElementById('menuToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function setMenuOpen(isOpen) {
    document.body.classList.toggle('menu-open', isOpen);

    if (menuToggle) {
        menuToggle.setAttribute('aria-expanded', String(isOpen));
    }
}

if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        const isOpen = document.body.classList.contains('menu-open');
        setMenuOpen(!isOpen);
    });
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', () => {
        setMenuOpen(false);
    });
}

document.querySelectorAll('.nav-link').forEach((link) => {
    link.addEventListener('click', () => {
        setMenuOpen(false);
    });
});

window.addEventListener('resize', () => {
    if (window.innerWidth > 760) {
        setMenuOpen(false);
    }
});
