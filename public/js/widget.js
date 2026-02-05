/**
 * MikroPlaneta Booking Frontend Widget
 */
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('mikroplaneta-booking-widget');
    if (!container) return;

    // Use localized data
    const settings = typeof mpBookingData !== 'undefined' ? mpBookingData : {
        i18n: {
            loading: 'Ładowanie...',
            search: 'Szukaj',
            checkIn: 'Przyjazd',
            checkOut: 'Wyjazd'
        }
    };

    console.log('MikroPlaneta Widget Initialized', settings);

    // Initial HTML Structure
    container.innerHTML = `
        <div class="mp-booking-form-wrapper" style="max-width: 600px; margin: 20px auto; padding: 25px; background: white; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f0f0f0;">
            <h3 style="margin-top:0; color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 20px; text-align:center;">${settings.i18n.search}</h3>
            <div style="display: grid; grid-template-cols: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${settings.i18n.checkIn}</label>
                    <input type="date" class="mp-date-input" style="width:100%; padding:10px; border: 1px solid #e5e7eb; border-radius: 8px; outline: none;" />
                </div>
                <div>
                    <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${settings.i18n.checkOut}</label>
                    <input type="date" class="mp-date-input" style="width:100%; padding:10px; border: 1px solid #e5e7eb; border-radius: 8px; outline: none;" />
                </div>
            </div>
            <button class="mp-search-button" style="width: 100%; background: #2563eb; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                ${settings.i18n.search}
            </button>
            <div id="mp-results-container" style="margin-top: 20px;"></div>
        </div>
    `;

    // Basic animation/interaction
    const btn = container.querySelector('.mp-search-button');
    btn.addEventListener('mouseover', () => btn.style.background = '#1d4ed8');
    btn.addEventListener('mouseout', () => btn.style.background = '#2563eb');

    btn.addEventListener('click', () => {
        const results = document.getElementById('mp-results-container');
        results.innerHTML = `<div style="text-align:center; padding: 20px; color: #6b7280;">
            <div class="mp-spinner" style="width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #2563eb; border-radius: 50%; display: inline-block; animation: spin 1s linear infinite;"></div>
            <span style="margin-left: 10px;">${settings.i18n.loading}</span>
        </div>`;

        // Future: Fetch availability from API
        setTimeout(() => {
            results.innerHTML = `<div style="padding: 15px; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; color: #991b1b; font-size: 14px; text-align: center;">
                System rezerwacji online jest w trakcie konfiguracji przez administratora. Prosimy o kontakt telefoniczny.
            </div>`;
        }, 1200);
    });
});

// Add spinner keyframes
const style = document.createElement('style');
style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
document.head.append(style);
