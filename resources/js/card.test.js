import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initDigitalBusinessCard } from './card';

function renderCard() {
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    document.body.innerHTML = `
        <main data-digital-card data-events-url="/card/anna/events">
            <button data-track="vcard">Сохранить в контакты</button>
            <button data-track="contact">Обменяться контактами</button>
            <a data-track="cta" data-block-id="7">Портфолио</a>
            <a data-download-vcard>Скачать vCard</a>
        </main>
    `;

    return document.querySelector('[data-digital-card]');
}

describe('digital business card event tracking', () => {
    beforeEach(() => {
        globalThis.fetch = vi.fn(() => Promise.resolve({ ok: true }));
    });

    afterEach(() => {
        vi.restoreAllMocks();
        document.head.innerHTML = '';
        document.body.innerHTML = '';
    });

    it('does nothing when the page is not a digital card', () => {
        expect(() => initDigitalBusinessCard(null)).not.toThrow();
    });

    it('initializes once and tracks configured events', () => {
        const root = renderCard();

        initDigitalBusinessCard(root);
        initDigitalBusinessCard(root);
        root.querySelector('[data-track="vcard"]').click();
        root.querySelector('[data-block-id="7"]').click();

        expect(root.dataset.digitalCardInitialized).toBe('true');
        expect(globalThis.fetch).toHaveBeenCalledTimes(2);
        expect(globalThis.fetch).toHaveBeenNthCalledWith(1, '/card/anna/events', expect.objectContaining({
            body: JSON.stringify({ type: 'vcard', block_id: null }),
        }));
        expect(globalThis.fetch).toHaveBeenNthCalledWith(2, '/card/anna/events', expect.objectContaining({
            body: JSON.stringify({ type: 'cta', block_id: '7' }),
        }));
    });

    it('tracks downloads and tolerates missing tracking configuration', () => {
        const root = renderCard();

        initDigitalBusinessCard(root);
        root.querySelector('[data-download-vcard]').click();
        expect(globalThis.fetch).toHaveBeenCalledWith('/card/anna/events', expect.objectContaining({
            body: JSON.stringify({ type: 'vcard', block_id: null }),
        }));

        document.body.innerHTML = '<main data-digital-card><button data-track="contact"></button></main>';
        initDigitalBusinessCard(document.querySelector('[data-digital-card]'));
        document.querySelector('[data-track]').click();
        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    });
});
