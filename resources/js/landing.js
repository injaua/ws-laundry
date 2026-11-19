// Public site (landing/index, landing/order-status): navbar state, floating WhatsApp, scroll reveal, mobile menu.
import './behaviors';

const nav = document.getElementById('navbar');
const wa = document.getElementById('floatingWa');
let ticking = false;
window.addEventListener(
    'scroll',
    () => {
        if (ticking) return;
        window.requestAnimationFrame(() => {
            const y = window.scrollY;
            nav?.classList.toggle('scrolled', y > 60);
            wa?.classList.toggle('show', y > 300);
            ticking = false;
        });
        ticking = true;
    },
    { passive: true },
);

// Scroll reveal (progressive enhancement: content is visible if JS never runs)
const revealItems = document.querySelectorAll('[data-reveal]');
if (revealItems.length && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-revealed');
                observer.unobserve(entry.target);
            });
        },
        { threshold: 0.08 },
    );
    revealItems.forEach((el) => {
        if (el.getBoundingClientRect().top > window.innerHeight * 0.8) {
            el.classList.add('reveal-pending');
            observer.observe(el);
        }
    });
}

// Mobile menu
const toggler = document.getElementById('navToggler');
const navLinks = document.getElementById('navLinks');
if (toggler && navLinks) {
    const setOpen = (open) => {
        navLinks.classList.toggle('open', open);
        toggler.setAttribute('aria-expanded', String(open));
    };
    toggler.addEventListener('click', () => setOpen(!navLinks.classList.contains('open')));
    navLinks.querySelectorAll('a:not(.dropdown-toggle)').forEach((link) => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') setOpen(false);
    });
    document.addEventListener('click', (e) => {
        if (navLinks.classList.contains('open') && !e.target.closest('#navLinks') && !e.target.closest('#navToggler')) setOpen(false);
    });
}

// Order status page: bring the result into view.
window.addEventListener('load', () => {
    document.querySelector('[data-scroll-on-load]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
