/**
 * Room Card Modal
 * Simple, clean modal for room booking
 */

(function() {
    'use strict';

    let modal = null;
    let modalBody = null;

    /**
     * Get or create modal
     */
    function getModal() {
        if (!modal) {
            modal = document.createElement('div');
            modal.className = 'mp-booking-modal';
            modal.innerHTML = `
                <div class="mp-modal-backdrop"></div>
                <div class="mp-modal-content">
                    <div class="mp-modal-header">
                        <h3 class="mp-modal-title">
                            <span class="mp-modal-room-name">Rezerwacja</span>
                        </h3>
                        <button type="button" class="mp-modal-close" aria-label="Zamknij">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="mp-modal-body"></div>
                </div>
            `;

            // Close handlers
            modal.querySelector('.mp-modal-backdrop').addEventListener('click', closeModal);
            modal.querySelector('.mp-modal-close').addEventListener('click', closeModal);
            document.addEventListener('keydown', function handleEsc(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    closeModal();
                    document.removeEventListener('keydown', handleEsc);
                }
            });

            document.body.appendChild(modal);
            modalBody = modal.querySelector('.mp-modal-body');
        }

        return { element: modal, body: modalBody };
    }

    /**
     * Open modal with booking widget
     */
    function openModal(roomId, roomName, prefillData) {
        const { element, body } = getModal();

        // Update title
        element.querySelector('.mp-modal-room-name').textContent = roomName || 'Rezerwacja';

        // Create widget container with prefill data
        body.innerHTML = '<div class="mp-booking-widget-container mp-modal-widget" data-mp-settings="' +
            encodeURIComponent(JSON.stringify({
                roomId: roomId,
                roomName: roomName,
                title: '',
                prefill: prefillData || {}
            })) + '"></div>';

        // Show modal
        element.style.display = 'flex';
        element.classList.add('mp-modal-open');
        document.body.style.overflow = 'hidden';

        // Initialize widget - use simple widget if available
        const widgetContainer = body.querySelector('.mp-booking-widget-container');
        if (widgetContainer) {
            // Try simple widget first, fallback to full widget
            if (typeof setupSimpleWidget === 'function') {
                setupSimpleWidget(widgetContainer);
            } else if (typeof setupWidget === 'function') {
                setupWidget(widgetContainer);
            }
        }
    }

    /**
     * Close modal
     */
    function closeModal() {
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('mp-modal-open');
            document.body.style.overflow = '';
            if (modalBody) {
                modalBody.innerHTML = '';
            }
        }
    }

    /**
     * Initialize
     */
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('.mp-room-card__btn[data-room-id]');
        
        buttons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const roomId = parseInt(btn.getAttribute('data-room-id'), 10);
                const roomName = btn.getAttribute('data-room-name') || '';
                
                if (roomId > 0) {
                    openModal(roomId, roomName);
                }
            });
        });
    });
})();
