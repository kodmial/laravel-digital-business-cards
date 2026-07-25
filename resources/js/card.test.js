import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initDigitalBusinessCard } from './card';

function renderCard() {
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    document.body.innerHTML = `
        <main data-digital-card data-events-url="/card/anna/events">
            <button data-save-contact data-track="vcard">Сохранить в контакты</button>
            <button data-open-image>Открыть фотографию</button>
            <button data-open-exchange data-track="contact">Обменяться контактами</button>
            <a data-track="cta" data-block-id="7">Портфолио</a>
        </main>
        <section data-modal="save" hidden><button data-close-modal>Закрыть</button><a data-download-vcard href="/card/anna/contact.vcf">Сохранить в контакты</a><button data-open-exchange>Обменяться контактами</button></section>
        <section data-modal="exchange" hidden><button data-close-modal>Закрыть</button><input name="name"></section>
        <section data-modal="success" hidden><button data-close-modal>Закрыть</button><button data-close-modal>OK</button></section>
        <section data-modal="image" hidden><div data-close-modal data-image-backdrop></div><button data-close-modal>Закрыть фотографию</button></section>
    `;

    return document.querySelector('[data-digital-card]');
}

describe('digital business card UX', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        globalThis.fetch = vi.fn(() => Promise.resolve({ ok: true }));
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        document.head.innerHTML = '';
        document.body.innerHTML = '';
    });

    it('does nothing when the page is not a digital card', () => {
        expect(() => initDigitalBusinessCard(null)).not.toThrow();
    });

    it('initializes a card only once', () => {
        const root = renderCard();

        initDigitalBusinessCard(root);
        initDigitalBusinessCard(root);
        root.querySelector('[data-save-contact]').click();

        expect(root.dataset.digitalCardInitialized).toBe('true');
        expect(document.querySelector('[data-modal="save"]').hidden).toBe(false);
        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    });

    it('keeps interactions working when event tracking is not configured', () => {
        const root = renderCard();
        delete root.dataset.eventsUrl;

        initDigitalBusinessCard(root);
        root.querySelector('[data-save-contact]').click();

        expect(document.querySelector('[data-modal="save"]').hidden).toBe(false);
        expect(globalThis.fetch).not.toHaveBeenCalled();
    });

    it('opens the save-contact dialog, focuses its close control, and records the event', () => {
        const root = renderCard();
        initDigitalBusinessCard(root);

        root.querySelector('[data-save-contact]').click();

        expect(document.querySelector('[data-modal="save"]').hidden).toBe(false);
        expect(document.querySelector('[data-modal="exchange"]').hidden).toBe(true);
        expect(document.activeElement).toBe(document.querySelector('[data-modal="save"] [data-close-modal]'));
        expect(globalThis.fetch).toHaveBeenCalledWith('/card/anna/events', expect.objectContaining({
            body: JSON.stringify({ type: 'vcard', block_id: null }),
        }));
    });

    it('opens exchange after downloading the vCard and returns focus after Escape', () => {
        const root = renderCard();
        initDigitalBusinessCard(root);

        const opener = root.querySelector('[data-save-contact]');
        opener.focus();
        opener.click();
        expect(document.querySelector('[data-modal="save"]').hidden).toBe(false);
        document.querySelector('[data-download-vcard]').click();
        vi.advanceTimersByTime(650);

        expect(document.querySelector('[data-modal="exchange"]').hidden).toBe(false);
        expect(document.activeElement).toBe(document.querySelector('[data-modal="exchange"] [data-close-modal]'));

        document.querySelector('[data-image-backdrop]').click();

        expect(document.querySelector('[data-modal="image"]').hidden).toBe(true);
        expect(document.activeElement).toBe(opener);

        opener.click();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(document.querySelector('[data-modal="save"]').hidden).toBe(true);
        expect(document.querySelector('[data-modal="exchange"]').hidden).toBe(true);
        expect(document.body.classList.contains('digital-card-modal-open')).toBe(false);
        expect(document.activeElement).toBe(opener);
    });

    it('opens exchange directly and records a block CTA with its id', () => {
        const root = renderCard();
        initDigitalBusinessCard(root);

        root.querySelector('[data-open-exchange]').click();
        root.querySelector('[data-block-id="7"]').click();

        expect(document.querySelector('[data-modal="exchange"]').hidden).toBe(false);
        expect(globalThis.fetch).toHaveBeenCalledWith('/card/anna/events', expect.objectContaining({
            body: JSON.stringify({ type: 'cta', block_id: '7' }),
        }));
    });

    it('opens the image lightbox and closes it with the backdrop or Escape', () => {
        const root = renderCard();
        initDigitalBusinessCard(root);

        const opener = root.querySelector('[data-open-image]');
        opener.focus();
        opener.click();

        expect(document.querySelector('[data-modal="image"]').hidden).toBe(false);
        expect(document.activeElement).toBe(document.querySelector('[data-modal="image"] button[data-close-modal]'));

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(document.querySelector('[data-modal="image"]').hidden).toBe(true);
        expect(document.activeElement).toBe(opener);
    });

    it('keeps keyboard focus inside an open dialog', () => {
        const root = renderCard();
        initDigitalBusinessCard(root);
        root.querySelector('[data-save-contact]').click();

        const dialog = document.querySelector('[data-modal="save"]');
        const first = dialog.querySelector('[data-close-modal]');
        const last = dialog.querySelector('[data-open-exchange]');

        last.focus();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
        expect(document.activeElement).toBe(first);

        first.focus();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }));
        expect(document.activeElement).toBe(last);
    });

    it('opens exchange from the action inside the rendered save dialog', () => {
        const root = renderCard();
        initDigitalBusinessCard(root);

        root.querySelector('[data-save-contact]').click();
        document.querySelector('[data-modal="save"] [data-open-exchange]').click();

        expect(document.querySelector('[data-modal="save"]').hidden).toBe(true);
        expect(document.querySelector('[data-modal="exchange"]').hidden).toBe(false);
    });

    it('focuses the close control when the post-exchange success dialog is rendered', () => {
        const root = renderCard();
        document.querySelector('[data-modal="success"]').hidden = false;

        initDigitalBusinessCard(root);

        expect(document.body.classList.contains('digital-card-modal-open')).toBe(true);
        expect(document.activeElement).toBe(document.querySelector('[data-modal="success"] [data-close-modal]'));
        document.querySelector('[data-modal="success"] [data-close-modal]').click();
        expect(document.querySelector('[data-modal="success"]').hidden).toBe(true);
    });

});
