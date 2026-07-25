/**
 * Client-side behaviour for a public digital business card.
 *
 * The markup deliberately carries its configuration in data attributes so this
 * module stays reusable and its important UX flows can be tested in isolation.
 */
export function initDigitalBusinessCard(root = document.querySelector('[data-digital-card]')) {
    if (!root || root.dataset.digitalCardInitialized === 'true') {
        return;
    }

    root.dataset.digitalCardInitialized = 'true';

    const eventUrl = root.dataset.eventsUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let returnFocusTo = null;

    const track = (type, blockId = null) => {
        if (!eventUrl || !globalThis.fetch) {
            return;
        }

        globalThis.fetch(eventUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                Accept: 'application/json',
            },
            body: JSON.stringify({ type, block_id: blockId }),
            keepalive: true,
        }).catch(() => {});
    };

    const open = (name) => {
        const dialog = document.querySelector(`[data-modal="${name}"]`);

        if (!dialog) {
            return;
        }

        // Preserve the original opener while moving from the delivery dialog
        // to the exchange form; closing the second dialog must return the
        // visitor to the page, not to a control hidden in the first dialog.
        if (!document.querySelector('[data-modal]:not([hidden])')) {
            returnFocusTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        }
        document.querySelectorAll('[data-modal]').forEach((modal) => {
            modal.hidden = modal.dataset.modal !== name;
        });
        document.body.classList.add('digital-card-modal-open');
        dialog.querySelector('button, input, textarea, select, a[href], [tabindex]:not([tabindex="-1"])')?.focus();
    };

    const close = () => {
        document.querySelectorAll('[data-modal]').forEach((modal) => {
            modal.hidden = true;
        });
        document.body.classList.remove('digital-card-modal-open');
        returnFocusTo?.focus();
        returnFocusTo = null;
    };

    root.querySelectorAll('[data-track]').forEach((element) => {
        element.addEventListener('click', () => track(element.dataset.track, element.dataset.blockId || null));
    });
    root.querySelectorAll('[data-save-contact]').forEach((element) => {
        element.addEventListener('click', () => open('save'));
    });
    root.querySelectorAll('[data-open-image]').forEach((element) => {
        element.addEventListener('click', () => open('image'));
    });
    // The save dialog is deliberately rendered next to, rather than inside,
    // the card root so it can overlay the whole page. Bind this action at the
    // document level too, otherwise its "Обменяться контактами" button has no
    // listener.
    document.querySelectorAll('[data-open-exchange]').forEach((element) => {
        element.addEventListener('click', () => open('exchange'));
    });
    document.querySelectorAll('[data-close-modal]').forEach((element) => {
        element.addEventListener('click', close);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const visibleModal = document.querySelector('[data-modal]:not([hidden])');
        const focusable = [...(visibleModal?.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])') || [])]
            .filter((element) => !element.hidden);

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    document.querySelectorAll('[data-download-vcard]').forEach((element) => {
        element.addEventListener('click', () => {
            track('vcard');
            window.setTimeout(() => open('exchange'), 650);
        });
    });

    if (document.querySelector('[data-modal="success"]')) {
        document.body.classList.add('digital-card-modal-open');
        document.querySelector('[data-modal="success"] [data-close-modal]')?.focus();
    }
}

function bootstrap() {
    initDigitalBusinessCard();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
} else {
    bootstrap();
}
