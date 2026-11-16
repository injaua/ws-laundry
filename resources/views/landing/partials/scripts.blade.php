{{-- Shared landing JS: navbar state, floating WhatsApp, scroll reveal, mobile menu --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    "use strict";

    var nav = document.getElementById("navbar");
    var wa = document.getElementById("floatingWa");
    var ticking = false;
    window.addEventListener("scroll", function() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                var y = window.scrollY || window.pageYOffset;
                if (nav) nav.classList.toggle("scrolled", y > 60);
                if (wa) wa.classList.toggle("show", y > 300);
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    // Scroll reveal (progressive enhancement — content is visible if JS never runs)
    var revealItems = document.querySelectorAll("[data-reveal]");
    if (revealItems.length && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = "1";
                    entry.target.style.transform = "translateY(0)";
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08 });
        revealItems.forEach(function(el) {
            if (el.getBoundingClientRect().top > window.innerHeight * 0.8) {
                el.style.opacity = "0";
                el.style.transform = "translateY(22px)";
                el.style.transition = "opacity .55s cubic-bezier(0.16,1,0.3,1), transform .55s cubic-bezier(0.16,1,0.3,1)";
                observer.observe(el);
            }
        });
    }

    // Mobile menu
    var toggler = document.getElementById("navToggler");
    var navLinks = document.getElementById("navLinks");
    if (toggler && navLinks) {
        function setOpen(open) {
            navLinks.classList.toggle("open", open);
            toggler.setAttribute("aria-expanded", String(open));
        }
        toggler.addEventListener("click", function() {
            setOpen(!navLinks.classList.contains("open"));
        });
        navLinks.querySelectorAll("a:not(.dropdown-toggle)").forEach(function(l) {
            l.addEventListener("click", function() { setOpen(false); });
        });
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") setOpen(false);
        });
        document.addEventListener("click", function(e) {
            if (navLinks.classList.contains("open") && !e.target.closest("#navLinks") && !e.target.closest("#navToggler")) setOpen(false);
        });
    }
})();
</script>
