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

    const syncBodyLock = function() {
        const open = document.querySelector('.rle-popup.is-open, .rle-overlay.is-open');
        document.body.classList.toggle('rle-popup-open', Boolean(open));
    };

    const closePopup = function(popup) {
        if (!popup) {
            return;
        }

        const iframe = popup.querySelector('[data-rle-popup-iframe]');
        popup.classList.remove('is-open', 'is-loaded');
        popup.setAttribute('aria-hidden', 'true');

        if (iframe) {
            iframe.src = 'about:blank';
        }

        syncBodyLock();
    };

    const closeOverlays = function(section) {
        const overlays = section ? section.querySelectorAll('.rle-overlay.is-open') : document.querySelectorAll('.rle-overlay.is-open');

        overlays.forEach(function(overlay) {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
        });

        syncBodyLock();
    };

    const openOverlay = function(section, name) {
        closeOverlays(section);
        closePopup(section.querySelector('[data-rle-popup]'));

        const overlay = section.querySelector('[data-rle-overlay="' + name + '"]');
        if (!overlay) {
            return;
        }

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        syncBodyLock();
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
                closeOverlays(section);
                popup.classList.remove('is-loaded');
                popup.classList.add('is-open');
                popup.setAttribute('aria-hidden', 'false');
                syncBodyLock();
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

        section.querySelectorAll('[data-rle-overlay-open]').forEach(function(trigger) {
            trigger.addEventListener('click', function() {
                openOverlay(section, trigger.getAttribute('data-rle-overlay-open'));
            });
        });

        section.querySelectorAll('[data-rle-overlay-close]').forEach(function(button) {
            button.addEventListener('click', function() {
                closeOverlays(section);
            });
        });

        const form = section.querySelector('[data-rle-contact-form]');
        if (form) {
            const status = form.querySelector('[data-rle-contact-status]');
            const whatsapp = form.querySelector('[data-rle-whatsapp]');

            form.addEventListener('submit', function(event) {
                event.preventDefault();
            });

            if (whatsapp) {
                whatsapp.addEventListener('click', function(event) {
                    const nameField = form.querySelector('[name="name"]');
                    const phoneField = form.querySelector('[name="phone"]');
                    const messageField = form.querySelector('[name="message"]');
                    const name = nameField ? nameField.value.trim() : '';
                    const phone = phoneField ? phoneField.value.trim() : '';
                    const message = messageField ? messageField.value.trim() : '';

                    if (!name || !phone || !message) {
                        event.preventDefault();
                        if (status) {
                            status.hidden = false;
                            status.textContent = 'יש למלא שם, טלפון והודעה.';
                            status.classList.add('is-error');
                        }
                        form.reportValidity();
                        return;
                    }

                    if (status) {
                        status.hidden = true;
                        status.classList.remove('is-error');
                    }

                    const text = 'שם: ' + name + '\nטלפון: ' + phone + '\n\n' + message;
                    const base = whatsapp.getAttribute('href').split('?')[0];
                    whatsapp.setAttribute('href', base + '?text=' + encodeURIComponent(text));
                });
            }
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') {
            return;
        }

        const openOverlayEl = document.querySelector('.rle-overlay.is-open');
        if (openOverlayEl) {
            closeOverlays();
            return;
        }

        const openPopup = document.querySelector('.rle-popup.is-open');
        closePopup(openPopup);
    });

    updateBackground();
    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate);
});
