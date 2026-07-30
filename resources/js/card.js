/**
 * Event tracking for a public digital business card.
 *
 * Livewire owns contact-exchange forms and Alpine owns modal/UI state. This
 * module intentionally handles only the existing analytics endpoint.
 */
export function initDigitalBusinessCard(root = document.querySelector('[data-digital-card]')) {
    if (!root || root.dataset.digitalCardInitialized === 'true') {
        return;
    }

    root.dataset.digitalCardInitialized = 'true';

    const eventUrl = root.dataset.eventsUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
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

    root.querySelectorAll('[data-track]').forEach((element) => {
        element.addEventListener('click', () => track(element.dataset.track, element.dataset.blockId || null));
    });
    root.querySelectorAll('[data-download-vcard]').forEach((element) => {
        element.addEventListener('click', () => track('vcard'));
    });
}

function bootstrap() {
    initDigitalBusinessCard();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
} else {
    bootstrap();
}
