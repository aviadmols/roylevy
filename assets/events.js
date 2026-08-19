document.addEventListener('DOMContentLoaded', function() {
    const sections = Array.from(document.querySelectorAll('.rle-showcase'));

    if (!sections.length) {
        return;
    }

    const clamp = function(value, min, max) {
        return Math.min(Math.max(value, min), max);
    };

    let frame = null;

    const updateBackground = function() {
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

        sections.forEach(function(section) {
            const rect = section.getBoundingClientRect();
            const progress = clamp(-rect.top / Math.max(viewportHeight * 0.72, 1), 0, 1);
            const blur = progress * 6;
            const scale = 1 + progress * 0.018;
            const shade = progress * 0.24;

            section.style.setProperty('--rle-image-blur', blur.toFixed(2) + 'px');
            section.style.setProperty('--rle-image-scale', scale.toFixed(4));
            section.style.setProperty('--rle-shade-opacity', shade.toFixed(4));
        });

        frame = null;
    };

    const requestUpdate = function() {
        if (frame) {
            return;
        }

        frame = requestAnimationFrame(updateBackground);
    };

    const closePopup = function(popup) {
        if (!popup) {
            return;
        }

        const iframe = popup.querySelector('[data-rle-popup-iframe]');
        popup.classList.remove('is-open', 'is-loaded');
        popup.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('rle-popup-open');

        if (iframe) {
            iframe.src = 'about:blank';
        }
    };

    sections.forEach(function(section) {
        const popup = section.querySelector('[data-rle-popup]');
        const iframe = popup ? popup.querySelector('[data-rle-popup-iframe]') : null;
        const buttons = section.querySelectorAll('[data-rle-popup-url]');
        const closeButtons = popup ? popup.querySelectorAll('[data-rle-popup-close]') : [];

        buttons.forEach(function(button) {
            button.addEventListener('click', function(event) {
                const url = button.getAttribute('data-rle-popup-url');

                if (!popup || !iframe || !url) {
                    return;
                }

                event.preventDefault();
                popup.classList.remove('is-loaded');
                popup.classList.add('is-open');
                popup.setAttribute('aria-hidden', 'false');
                document.body.classList.add('rle-popup-open');
                iframe.src = url;
            });
        });

        if (iframe) {
            iframe.addEventListener('load', function() {
                if (iframe.src !== 'about:blank') {
                    popup.classList.add('is-loaded');
                }
            });
        }

        Array.from(closeButtons).forEach(function(button) {
            button.addEventListener('click', function() {
                closePopup(popup);
            });
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') {
            return;
        }

        const openPopup = document.querySelector('.rle-popup.is-open');
        closePopup(openPopup);
    });

    updateBackground();
    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate);
});
