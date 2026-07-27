/**
 * Alpine.js integration for digital business cards
 * Handles modal management and form interactions
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('modalManager', () => ({
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

            // Focus management
            this.$nextTick(() => {
                const dialog = document.querySelector(`[data-modal="${name}"]`);
                dialog?.querySelector('button, input, textarea, select, a[href], [tabindex]:not([tabindex="-1"])')?.focus();
            });
        },

        close() {
            this.openModal = null;
            this.returnFocusTo?.focus();
            this.returnFocusTo = null;
        },

        closeAll() {
            this.openModal = null;
            this.returnFocusTo?.focus();
            this.returnFocusTo = null;
        },

        isOpen(name) {
            return this.openModal === name;
        },

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
    }));

    // Global event bus for Livewire communication
    Alpine.store('events', {
        modals: {
            open: null,
            returnFocusTo: null,
        },
        
        openModal(name) {
            if (this.modals.open === null) {
                this.modals.returnFocusTo = document.activeElement instanceof HTMLElement 
                    ? document.activeElement 
                    : null;
            }
            this.modals.open = name;
            
            document.body.classList.add('digital-card-modal-open');
            
            requestAnimationFrame(() => {
                const dialog = document.querySelector(`[data-modal="${name}"]`);
                dialog?.querySelector('button, input, textarea, select, a[href], [tabindex]:not([tabindex="-1"])')?.focus();
            });
        },
        
        closeModal() {
            this.modals.open = null;
            document.body.classList.remove('digital-card-modal-open');
            this.modals.returnFocusTo?.focus();
            this.modals.returnFocusTo = null;
        },
        
        closeAll() {
            this.modals.open = null;
            document.body.classList.remove('digital-card-modal-open');
            this.modals.returnFocusTo?.focus();
            this.modals.returnFocusTo = null;
        }
    });
});