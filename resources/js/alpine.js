/**
 * Alpine.js integration for digital business cards
 * Handles modal management and form interactions
 */

document.addEventListener('alpine:init', () => {
    // Shared modal state management
    const sharedModalState = () => ({
        openModal: null,
        returnFocusTo: null,

        open(name) {
            // Preserve the original opener while moving between modals
            if (this.openModal === null) {
                this.returnFocusTo = document.activeElement instanceof HTMLElement 
                    ? document.activeElement 
                    : null;
            }

            this.openModal = name;
            document.body.classList.add('digital-card-modal-open');
            
            // Focus management
            requestAnimationFrame(() => {
                const dialog = document.querySelector(`[data-modal="${name}"]`);
                dialog?.querySelector('button, input, textarea, select, a[href], [tabindex]:not([tabindex="-1"])')?.focus();
            });
        },

        close() {
            this.openModal = null;
            document.body.classList.remove('digital-card-modal-open');
            this.returnFocusTo?.focus();
            this.returnFocusTo = null;
        },

        closeAll() {
            this.openModal = null;
            document.body.classList.remove('digital-card-modal-open');
            this.returnFocusTo?.focus();
            this.returnFocusTo = null;
        },

        isOpen(name) {
            return this.openModal === name;
        },
    });

    Alpine.data('modalManager', () => {
        const state = sharedModalState();

        return {
            ...state,

            init() {
                // Handle Escape key
                this.$el.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.closeAll();
                    }
                });

                // Handle Tab key for focus trap
                this.$el.addEventListener('keydown', (e) => {
                    if (e.key !== 'Tab') return;

                    const visibleModal = document.querySelector('[data-modal]:not([hidden])');
                    if (!visibleModal) return;

                    const focusable = [...visibleModal.querySelectorAll(
                        'button:not([disabled]), a[href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    )].filter(el => !el.hidden);

                    if (focusable.length === 0) return;

                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                });
            }
        };
    });

    // Global event bus for Livewire communication
    Alpine.store('events', sharedModalState());
});
