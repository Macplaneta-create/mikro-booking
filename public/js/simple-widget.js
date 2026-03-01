/**
 * Simple Booking Widget
 * Guest-friendly booking form without bed selection
 */

(function() {
    'use strict';

    let availableBeds = [];
    let roomPricingMode = 'per_bed';
    let roomCapacity = 0;

    /**
     * Escape HTML
     */
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Get guest count
     */
    function getGuestCount(container) {
        const adults = parseInt(container.querySelector('#mp-adults')?.value || '1', 10);
        const children = parseInt(container.querySelector('#mp-children')?.value || '0', 10);
        return adults + children;
    }

    /**
     * Check availability
     */
    async function checkAvailability(settings, checkIn, checkOut, guests) {
        try {
            let url = `${settings.apiUrl}/public/availability/beds?check_in=${checkIn}&check_out=${checkOut}`;
            if (settings.roomId) {
                url += `&room_id=${settings.roomId}`;
            }
            
            console.log('[Availability] Fetching:', url);
            
            const response = await fetch(url, {
                headers: { 'X-WP-Nonce': settings.nonce || '' }
            });
            
            console.log('[Availability] Response status:', response.status);
            
            const data = await response.json();
            
            console.log('[Availability] Response data:', data);
            
            if (data.success && Array.isArray(data.data)) {
                // Count available capacity
                const availableCapacity = data.data.reduce((sum, bed) => {
                    const bedType = bed.bed_type || 'single';
                    return sum + ((bedType === 'bunk') ? 2 : 1);
                }, 0);
                
                console.log('[Availability] Capacity:', availableCapacity, 'Guests:', guests);
                
                return {
                    available: availableCapacity >= guests,
                    availableCapacity: availableCapacity,
                    beds: data.data
                };
            }
            
            console.log('[Availability] No success or not array');
            return { available: false, availableCapacity: 0, beds: [] };
        } catch (err) {
            console.error('[Availability Check Error]', err);
            return { available: false, availableCapacity: 0, beds: [], error: true };
        }
    }

    /**
     * Calculate price
     */
    async function calculatePrice(settings, checkIn, checkOut, guests, container) {
        try {
            // Get adults and children separately for proper pricing with multipliers
            const adults = parseInt(container.querySelector('#mp-adults')?.value || '1', 10);
            const children = parseInt(container.querySelector('#mp-children')?.value || '0', 10);

            console.log('[Price] Calculating for:', { checkIn, checkOut, adults, children, roomId: settings.roomId });

            // First, get available beds
            const availabilityUrl = `${settings.apiUrl}/public/availability/beds?check_in=${checkIn}&check_out=${checkOut}` +
                (settings.roomId ? `&room_id=${settings.roomId}` : '');

            const availResponse = await fetch(availabilityUrl, {
                headers: { 'X-WP-Nonce': settings.nonce || '' }
            });
            const availData = await availResponse.json();

            console.log('[Price] Availability:', availData);

            if (!availData.success || !availData.data || availData.data.length === 0) {
                console.log('[Price] No beds available');
                return 0;
            }

            // Select enough beds for the guests
            const bedIds = [];
            let capacitySum = 0;
            for (const bed of availData.data) {
                if (capacitySum >= guests) break;
                bedIds.push(bed.id);
                const bedType = bed.bed_type || 'single';
                const bedCapacity = (bedType === 'bunk') ? 2 : 1;
                capacitySum += bedCapacity;
            }

            console.log('[Price] Selected bed_ids:', bedIds, 'Capacity:', capacitySum);

            if (bedIds.length === 0) {
                console.log('[Price] No beds needed');
                return 0;
            }

            // Use calculate-group endpoint (POST method)
            const url = `${settings.apiUrl}/pricing/calculate-group`;

            const body = {
                check_in: checkIn,
                check_out: checkOut,
                adults: adults,
                children: children,
                bed_ids: bedIds,
            };

            // Add room_id if specified (for room card widget)
            if (settings.roomId) {
                body.room_id = settings.roomId;
            }

            console.log('[Price] Request body:', body);

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': settings.nonce || '',
                },
                body: JSON.stringify(body),
            });

            const data = await response.json();

            console.log('[Price] Response:', data);

            if (data.success && data.data) {
                const totalPrice = data.data.total || data.data.price || 0;
                console.log('[Price] Total price:', totalPrice);
                return totalPrice;
            }

            console.log('[Price] No price data in response');
            return 0;
        } catch (err) {
            console.error('[Price Calc Error]', err);
            return 0;
        }
    }

    /**
     * Setup widget
     */
    function setupWidget(container) {
        const globalSettings = typeof mpBookingData !== 'undefined' ? mpBookingData : {};
        let localSettings = {};
        
        try {
            const raw = container.getAttribute('data-mp-settings');
            // Decode URL encoding first, then parse JSON
            localSettings = raw ? JSON.parse(decodeURIComponent(raw)) : {};
        } catch (e) {
            console.error('[SimpleWidget] Failed to parse settings:', e);
            localSettings = {};
        }

        const settings = {
            ...globalSettings,
            ...localSettings,
            captcha: { ...(globalSettings.captcha || {}), ...(localSettings.captcha || {}) },
            i18n: { ...(globalSettings.i18n || {}), ...(localSettings.i18n || {}) },
        };

        console.log('[SimpleWidget] Settings:', settings);
        console.log('[SimpleWidget] LocalSettings:', localSettings);
        console.log('[SimpleWidget] RoomId:', settings.roomId);
        console.log('[SimpleWidget] Raw data-mp-settings:', container.getAttribute('data-mp-settings'));

        const prefill = settings.prefill || {};
        const isHcaptcha = settings.captcha && settings.captcha.provider === 'hcaptcha';
        
        // Get today's date for min attribute
        const today = new Date().toISOString().split('T')[0];

        container.innerHTML = `
            <div class="mp-booking-form-wrapper">
                <div class="mp-booking-form__header">
                    <h3 class="mp-booking-form-wrapper__title">${escapeHtml(settings.title || 'Rezerwacja')}</h3>
                    <div class="mp-booking-form__steps">
                        <div class="mp-booking-form__step mp-booking-form__step--active" data-step="1">
                            <span class="mp-booking-form__step-number">1</span>
                            <span class="mp-booking-form__step-label">Termin</span>
                        </div>
                        <div class="mp-booking-form__step" data-step="2">
                            <span class="mp-booking-form__step-number">2</span>
                            <span class="mp-booking-form__step-label">Dane</span>
                        </div>
                    </div>
                </div>

                <!-- Step 1: Dates & Guests -->
                <div class="mp-booking-form__step-content mp-booking-form__step-content--active" id="mp-step-1">
                    <div class="mp-booking-form__grid">
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.checkIn || 'Przyjazd')} *</label>
                            <input type="date" id="mp-check-in" value="${escapeHtml(prefill.checkIn || '')}" min="${today}" class="mp-booking-form__input" />
                        </div>
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.checkOut || 'Wyjazd')} *</label>
                            <input type="date" id="mp-check-out" value="${escapeHtml(prefill.checkOut || '')}" min="${today}" class="mp-booking-form__input" />
                        </div>
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.adults || 'Dorośli')}</label>
                            <input type="number" id="mp-adults" min="1" max="50" value="${escapeHtml(String(prefill.adults || 1))}" class="mp-booking-form__number-input" />
                        </div>
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.children || 'Dzieci')}</label>
                            <input type="number" id="mp-children" min="0" max="50" value="${escapeHtml(String(prefill.children || 0))}" class="mp-booking-form__number-input" />
                        </div>
                    </div>

                    <!-- Availability check message (hidden by default) -->
                    <div id="mp-availability-message" class="mp-booking-form__message mp-hidden" style="display: none;"></div>

                    <div class="mp-booking-form__footer">
                        <button type="button" id="mp-step-1-next" class="mp-booking-form__btn mp-booking-form__btn--primary mp-booking-form__btn--full">
                            ${escapeHtml(settings.i18n.next || 'Dalej')}
                        </button>
                    </div>
                </div>

                <!-- Step 2: Guest Data -->
                <div class="mp-booking-form__step-content" id="mp-step-2">
                    <div class="mp-booking-form__summary">
                        <h4 class="mp-booking-form__summary-title">Podsumowanie</h4>
                        <div class="mp-booking-form__summary-details">
                            <div class="mp-booking-form__summary-item">
                                <span class="mp-booking-form__summary-label">Termin:</span>
                                <span class="mp-booking-form__summary-value" id="mp-summary-dates"></span>
                            </div>
                            <div class="mp-booking-form__summary-item">
                                <span class="mp-booking-form__summary-label">Goście:</span>
                                <span class="mp-booking-form__summary-value" id="mp-summary-guests"></span>
                            </div>
                            <div class="mp-booking-form__summary-item mp-booking-form__summary-item--price">
                                <span class="mp-booking-form__summary-label">Szacowany koszt:</span>
                                <span class="mp-booking-form__summary-value mp-booking-form__summary-value--price" id="mp-summary-price">-- zł</span>
                            </div>
                        </div>
                    </div>

                    <div class="mp-booking-form__grid">
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.firstName || 'Imię')} *</label>
                            <input type="text" id="mp-first-name" class="mp-booking-form__input" placeholder="Jan" />
                        </div>
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.lastName || 'Nazwisko')} *</label>
                            <input type="text" id="mp-last-name" class="mp-booking-form__input" placeholder="Kowalski" />
                        </div>
                        <div class="mp-booking-form__group mp-booking-form__group--full">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.email || 'Email')} *</label>
                            <input type="email" id="mp-email" class="mp-booking-form__input" placeholder="jan@example.pl" />
                        </div>
                        <div class="mp-booking-form__group mp-booking-form__group--full">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.phone || 'Telefon')}</label>
                            <input type="tel" id="mp-phone" class="mp-booking-form__input" placeholder="+48 123 456 789" />
                        </div>
                    </div>

                    <div class="mp-booking-form__group mp-booking-form__group--notes">
                        <label class="mp-booking-form__label">${escapeHtml(settings.i18n.notes || 'Uwagi')}</label>
                        <textarea id="mp-notes" rows="3" class="mp-booking-form__textarea" placeholder="Dodatkowe uwagi..."></textarea>
                    </div>

                    <div class="mp-booking-form__consent">
                        <label class="mp-booking-form__consent-label">
                            <input type="checkbox" id="mp-consent-data" class="mp-booking-form__checkbox" />
                            <span>
                                <strong>Wyrażam zgodę na przetwarzanie moich danych osobowych</strong> w celu realizacji rezerwacji.
                                Administratorem danych jest <strong>${escapeHtml(settings.hotelName || 'Hotel')}</strong>.
                                Więcej informacji w <a href="${escapeHtml(settings.privacyPolicyUrl || '#')}" target="_blank">Polityce prywatności</a>.
                            </span>
                        </label>

                        <label class="mp-booking-form__consent-label">
                            <input type="checkbox" id="mp-consent-terms" class="mp-booking-form__checkbox" />
                            <span>
                                <strong>Zapoznałem się i akceptuję Regulamin</strong> świadczenia usług rezerwacji.
                                <a href="${escapeHtml(settings.termsUrl || '#')}" target="_blank">Przeczytaj regulamin</a>.
                            </span>
                        </label>

                        <label class="mp-booking-form__consent-label mp-booking-form__consent-label--optional">
                            <input type="checkbox" id="mp-consent-marketing" class="mp-booking-form__checkbox" />
                            <span>
                                Chcę otrzymywać newsletter z ofertami specjalnymi i informacjami o hotelu.
                            </span>
                        </label>
                    </div>

                    <div id="mp-consent-error" class="mp-booking-form__message mp-booking-form__message--error mp-hidden" style="display: none;">
                        Musisz wyrazić wymagane zgody przed wysłaniem rezerwacji.
                    </div>

                    <div id="mp-hcaptcha-wrap" class="mp-booking-form__hcaptcha" ${isHcaptcha ? '' : 'style="display:none;"'}>
                        <div class="h-captcha" data-sitekey="${escapeHtml(settings.captcha.hcaptchaSiteKey || '')}" data-callback="mpHcaptchaDone" data-expired-callback="mpHcaptchaExpired"></div>
                    </div>

                    <div class="mp-booking-form__footer mp-booking-form__footer--split">
                        <button type="button" id="mp-step-2-back" class="mp-booking-form__btn mp-booking-form__btn--secondary">Wstecz</button>
                        <button type="button" id="mp-submit-btn" class="mp-booking-form__btn mp-booking-form__btn--primary">${escapeHtml(settings.i18n.submit || 'Wyślij')}</button>
                    </div>
                    <div id="mp-results-container" class="mp-booking-form__results"></div>
                </div>
            </div>
        `;

        // Step navigation
        const step1 = container.querySelector('#mp-step-1');
        const step2 = container.querySelector('#mp-step-2');
        const step1Next = container.querySelector('#mp-step-1-next');
        const step2Back = container.querySelector('#mp-step-2-back');
        const submitBtn = container.querySelector('#mp-submit-btn');
        const results = container.querySelector('#mp-results-container');
        const availabilityMsg = container.querySelector('#mp-availability-message');
        
        let isSubmitting = false; // Prevent duplicate submissions

        const goToStep = (step) => {
            const stepIndicators = container.querySelectorAll('.mp-booking-form__step');
            
            if (step === 1) {
                step1.classList.add('mp-booking-form__step-content--active');
                step2.classList.remove('mp-booking-form__step-content--active');
                stepIndicators[0].classList.add('mp-booking-form__step--active');
                stepIndicators[1].classList.remove('mp-booking-form__step--active');
            } else {
                step1.classList.remove('mp-booking-form__step-content--active');
                step2.classList.add('mp-booking-form__step-content--active');
                stepIndicators[0].classList.remove('mp-booking-form__step--active');
                stepIndicators[1].classList.add('mp-booking-form__step--active');
                updateSummary();
            }
        };

        const updateSummary = () => {
            console.log('[Summary] Updating summary...');
            
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            const adults = parseInt(container.querySelector('#mp-adults').value || '1', 10);
            const children = parseInt(container.querySelector('#mp-children').value || '0', 10);
            const guests = adults + children;
            
            console.log('[Summary] Data:', { checkIn, checkOut, adults, children, guests, roomId: settings.roomId });

            if (checkIn && checkOut) {
                const checkInDate = new Date(checkIn);
                const checkOutDate = new Date(checkOut);
                const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                document.getElementById('mp-summary-dates').textContent = `${checkIn} → ${checkOut} (${nights} ${nights === 1 ? 'noc' : 'noce'})`;
            }

            document.getElementById('mp-summary-guests').textContent = `${adults} dorosłych${children > 0 ? ', ' + children + ' dzieci' : ''}`;

            // Calculate price
            if (checkIn && checkOut) {
                console.log('[Summary] Calling calculatePrice...');
                const priceEl = document.getElementById('mp-summary-price');
                if (priceEl) {
                    priceEl.textContent = 'Obliczanie...';
                }
                calculatePrice(settings, checkIn, checkOut, guests, container).then(price => {
                    console.log('[Summary] Price result:', price);
                    const priceEl = document.getElementById('mp-summary-price');
                    if (priceEl) {
                        priceEl.textContent = price > 0 ? `${price.toFixed(2)} zł` : '-- zł';
                    }
                }).catch(err => {
                    console.error('[Summary] Price error:', err);
                    const priceEl = document.getElementById('mp-summary-price');
                    if (priceEl) {
                        priceEl.textContent = '-- zł';
                    }
                });
            } else {
                console.log('[Summary] Missing data:', { hasCheckIn: !!checkIn, hasCheckOut: !!checkOut });
            }
        };

        // Step 1: Check availability
        step1Next.addEventListener('click', async function() {
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            const guests = getGuestCount(container);

            if (!checkIn || !checkOut) {
                availabilityMsg.className = 'mp-booking-form__message mp-booking-form__message--error';
                availabilityMsg.style.display = 'block';
                availabilityMsg.textContent = 'Wybierz daty przyjazdu i wyjazdu.';
                return;
            }

            availabilityMsg.style.display = 'block';
            availabilityMsg.className = 'mp-booking-form__message mp-booking-form__message--loading';
            availabilityMsg.textContent = 'Sprawdzam dostępność...';

            const result = await checkAvailability(settings, checkIn, checkOut, guests);

            if (result.error) {
                availabilityMsg.className = 'mp-booking-form__message mp-booking-form__message--error';
                availabilityMsg.textContent = 'Błąd sprawdzania dostępności.';
                return;
            }

            if (!result.available) {
                availabilityMsg.className = 'mp-booking-form__message mp-booking-form__message--error';
                availabilityMsg.textContent = `Brak miejsc dla ${guests} osób. Dostępnych: ${result.availableCapacity} miejsc.`;
                return;
            }

            availabilityMsg.style.display = 'none';
            goToStep(2);
        });

        // Step 2: Back
        step2Back.addEventListener('click', function() {
            goToStep(1);
        });

        // Submit
        submitBtn.addEventListener('click', async function() {
            console.log('[Submit] Button clicked!');
            
            // Prevent duplicate submissions
            if (isSubmitting) {
                console.log('[Submit] Already submitting, skipping');
                return;
            }
            isSubmitting = true;

            const firstName = container.querySelector('#mp-first-name').value.trim();
            const lastName = container.querySelector('#mp-last-name').value.trim();
            const email = container.querySelector('#mp-email').value.trim();
            const phone = container.querySelector('#mp-phone').value.trim();
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            const adults = parseInt(container.querySelector('#mp-adults').value || '1', 10);
            const children = parseInt(container.querySelector('#mp-children').value || '0', 10);
            const notes = container.querySelector('#mp-notes').value.trim();
            
            console.log('[Submit] Form data:', { firstName, lastName, email, checkIn, checkOut, adults, children });

            if (!firstName || !lastName || !email || !checkIn || !checkOut) {
                console.log('[Submit] Validation failed - missing fields');
                results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">Wypełnij wszystkie wymagane pola.</div>`;
                isSubmitting = false;
                return;
            }

            // Validate consent checkboxes
            const consentData = container.querySelector('#mp-consent-data');
            const consentTerms = container.querySelector('#mp-consent-terms');
            const consentError = container.querySelector('#mp-consent-error');

            if (!consentData || !consentTerms || !consentData.checked || !consentTerms.checked) {
                consentError.style.display = 'block';
                consentError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                isSubmitting = false;
                return;
            }
            consentError.style.display = 'none';

            // Get consent values
            const consents = {
                data_processing: consentData.checked,
                terms_accepted: consentTerms.checked,
                marketing: container.querySelector('#mp-consent-marketing')?.checked || false,
                timestamp: new Date().toISOString(),
            };

            submitBtn.disabled = true;
            results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--loading">Wysyłanie...</div>`;

            try {
                // First check availability and get all bed_ids
                const checkIn = container.querySelector('#mp-check-in').value;
                const checkOut = container.querySelector('#mp-check-out').value;
                const guests = getGuestCount(container);
                
                const availabilityResult = await checkAvailability(settings, checkIn, checkOut, guests);
                
                if (!availabilityResult.available) {
                    throw new Error('Brak dostępnych miejsc na wybrane daty.');
                }
                
                // Collect enough bed_ids for all guests
                const bedIds = [];
                let capacitySum = 0;

                for (const bed of availabilityResult.beds) {
                    if (capacitySum >= guests) break;
                    bedIds.push(bed.id);
                    const bedType = bed.bed_type || 'single';
                    capacitySum += (bedType === 'bunk' ? 2 : 1);
                }
                
                if (bedIds.length === 0) {
                    throw new Error('Brak dostępnych łóżek.');
                }

                const response = await fetch(`${settings.apiUrl}/public/reservations`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': settings.nonce || '',
                    },
                    body: JSON.stringify({
                        guest: {
                            first_name: firstName,
                            last_name: lastName,
                            email: email,
                            phone: phone,
                        },
                        room_id: settings.roomId || undefined,
                        bed_ids: bedIds,
                        check_in: checkIn,
                        check_out: checkOut,
                        adults: adults,
                        children: children,
                        notes: notes,
                        consents: consents,
                        captcha_token: 'disabled',
                    }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // SUCCESS: Lock form and show clear success message
                    results.innerHTML = `
                        <div class="mp-booking-form__message mp-booking-form__message--success" style="background: #ecfdf5; border: 2px solid #10b981; color: #065f46; padding: 20px; border-radius: 12px; text-align: center; margin-top: 20px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 10px;">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            <h3 style="margin: 0 0 10px; font-size: 18px; font-weight: 700;">Rezerwacja wysłana!</h3>
                            <p style="margin: 0; font-size: 14px;">Sprawdź email z potwierdzeniem.</p>
                        </div>
                    `;
                    
                    // LOCK FORM: Disable all inputs and buttons
                    submitBtn.disabled = true;
                    step2Back.disabled = true;
                    
                    const allInputs = container.querySelectorAll('input, textarea, button');
                    allInputs.forEach(input => {
                        if (input !== submitBtn && input !== step2Back) {
                            input.disabled = true;
                        }
                    });
                    
                    // Visual feedback - gray out form
                    const wrapper = container.querySelector('.mp-booking-form-wrapper');
                    if (wrapper) {
                        wrapper.style.opacity = '0.6';
                        wrapper.style.pointerEvents = 'none';
                    }
                    
                    isSubmitting = false; // Reset flag
                } else {
                    throw new Error(data.message || 'Błąd wysyłania');
                }
            } catch (err) {
                results.innerHTML = `
                    <div class="mp-booking-form__message mp-booking-form__message--error" style="background: #fef2f2; border: 2px solid #ef4444; color: #991b1b; padding: 20px; border-radius: 12px; text-align: center; margin-top: 20px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 10px;">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <h3 style="margin: 0 0 10px; font-size: 18px; font-weight: 700;">Błąd</h3>
                        <p style="margin: 0; font-size: 14px;">${escapeHtml(err.message)}</p>
                    </div>
                `;
                submitBtn.disabled = false;
                isSubmitting = false; // Allow retry
            }
        });
    }

    // Export
    window.setupSimpleWidget = setupWidget;

    // Auto-initialize global widgets (shortcode: [mikroplaneta_booking])
    document.addEventListener('DOMContentLoaded', function() {
        const containers = document.querySelectorAll('.mp-booking-widget-container');
        console.log('[SimpleWidget] Found containers:', containers.length);
        
        containers.forEach((container) => {
            console.log('[SimpleWidget] Initializing container:', container);
            setupWidget(container);
        });
    });
})();
