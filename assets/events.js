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

    const fadeMs = 500;

    const syncBodyLock = function() {
        const open = document.querySelector('.rle-popup.is-open, .rle-overlay.is-open, .rle-popup.is-closing, .rle-overlay.is-closing');
        document.body.classList.toggle('rle-popup-open', Boolean(open));
    };

    const closePopup = function(popup) {
        if (!popup || (!popup.classList.contains('is-open') && !popup.classList.contains('is-closing'))) {
            return;
        }

        const iframe = popup.querySelector('[data-rle-popup-iframe]');
        popup.classList.remove('is-open', 'is-loaded');
        popup.classList.add('is-closing');
        popup.setAttribute('aria-hidden', 'true');
        syncBodyLock();

        window.setTimeout(function() {
            popup.classList.remove('is-closing');

            if (!popup.classList.contains('is-open') && iframe) {
                iframe.src = 'about:blank';
            }

            syncBodyLock();
        }, fadeMs);
    };

    const closeOverlays = function(section) {
        const overlays = section ? section.querySelectorAll('.rle-overlay.is-open') : document.querySelectorAll('.rle-overlay.is-open');

        overlays.forEach(function(overlay) {
            overlay.classList.remove('is-open');
            overlay.classList.add('is-closing');
            overlay.setAttribute('aria-hidden', 'true');

            window.setTimeout(function() {
                overlay.classList.remove('is-closing');
                syncBodyLock();
            }, fadeMs);
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

        overlay.classList.remove('is-closing');
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
                popup.classList.remove('is-loaded', 'is-closing');
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
            const submit = form.querySelector('[type="submit"]');

            form.addEventListener('submit', function(event) {
                event.preventDefault();

                if (!window.RLEFront || !RLEFront.ajaxUrl) {
                    return;
                }

                const data = new FormData(form);
                data.append('action', 'rle_contact');
                data.append('nonce', RLEFront.nonce);

                if (status) {
                    status.hidden = true;
                    status.classList.remove('is-error');
                }

                if (submit) {
                    submit.disabled = true;
                }

                fetch(RLEFront.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                }).then(function(response) {
                    return response.json().then(function(payload) {
                        return { ok: response.ok, payload: payload };
                    });
                }).then(function(result) {
                    const payload = result.payload || {};
                    const message = (payload.data && payload.data.message) || (payload.success ? 'ההודעה נשלחה.' : 'שליחת ההודעה נכשלה.');

                    if (status) {
                        status.hidden = false;
                        status.textContent = message;
                        status.classList.toggle('is-error', !payload.success);
                    }

                    if (payload.success) {
                        form.reset();
                    }
                }).catch(function() {
                    if (status) {
                        status.hidden = false;
                        status.textContent = 'שליחת ההודעה נכשלה. נסו שוב מאוחר יותר.';
                        status.classList.add('is-error');
                    }
                }).finally(function() {
                    if (submit) {
                        submit.disabled = false;
                    }
                });
            });
        }

        const whatsapp = section.querySelector('[data-rle-whatsapp]');
        if (whatsapp) {
            whatsapp.addEventListener('click', function(event) {
                const number = whatsapp.getAttribute('data-rle-whatsapp') || '';

                if (!number) {
                    event.preventDefault();
                    return;
                }

                const form = section.querySelector('[data-rle-contact-form]');
                const nameField = form ? form.querySelector('[name="name"]') : null;
                const phoneField = form ? form.querySelector('[name="phone"]') : null;
                const messageField = form ? form.querySelector('[name="message"]') : null;
                const name = nameField ? nameField.value.trim() : '';
                const phone = phoneField ? phoneField.value.trim() : '';
                const message = messageField ? messageField.value.trim() : '';
                let url = 'https://wa.me/' + number;

                if (name || phone || message) {
                    const text = 'שם: ' + name + '\nטלפון: ' + phone + '\n\n' + message;
                    url += '?text=' + encodeURIComponent(text);
                }

                whatsapp.setAttribute('href', url);
            });
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
