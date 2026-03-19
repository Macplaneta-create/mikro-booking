/**
 * Simple Booking Widget
 * Guest-friendly booking form without bed selection
 */

(function() {
    'use strict';

    const nativeConsole = window.console || {};
    const debugEnabled = Boolean(window.mpBookingData && window.mpBookingData.debug);
    const console = {
        log: debugEnabled && typeof nativeConsole.log === 'function' ? nativeConsole.log.bind(nativeConsole) : function () {},
        warn: typeof nativeConsole.warn === 'function' ? nativeConsole.warn.bind(nativeConsole) : function () {},
        error: typeof nativeConsole.error === 'function' ? nativeConsole.error.bind(nativeConsole) : function () {},
        info: typeof nativeConsole.info === 'function' ? nativeConsole.info.bind(nativeConsole) : function () {},
    };

    let availableBeds = [];
    let hcaptchaToken = '';

    window.mpHcaptchaDone = function (token) {
        hcaptchaToken = token || '';
    };

    window.mpHcaptchaExpired = function () {
        hcaptchaToken = '';
    };

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
            let url = `${settings.apiUrl}/public/availability/beds?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`;
            if (settings.roomId) {
                url += `&room_id=${encodeURIComponent(String(settings.roomId))}`;
            }

            const response = await fetch(url, {
                headers: { 'X-WP-Nonce': settings.nonce || '' }
            });

            const data = await response.json();

            if (data.success && Array.isArray(data.data)) {
                const availableCapacity = data.data.reduce((sum, bed) => {
                    return sum + bedCapacity(bed);
                }, 0);

                return {
                    available: availableCapacity >= guests,
                    availableCapacity: availableCapacity,
                    beds: data.data
                };
            }

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
            const adults = parseInt(container.querySelector('#mp-adults')?.value || '1', 10);
            const children = parseInt(container.querySelector('#mp-children')?.value || '0', 10);

            const availabilityUrl = `${settings.apiUrl}/public/availability/beds?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}` +
                (settings.roomId ? `&room_id=${encodeURIComponent(String(settings.roomId))}` : '');

            const availResponse = await fetch(availabilityUrl, {
                headers: { 'X-WP-Nonce': settings.nonce || '' }
            });
            const availData = await availResponse.json();

            if (!availData.success || !availData.data || availData.data.length === 0) {
                return 0;
            }

            const bedIds = [];
            let capacitySum = 0;
            for (const bed of availData.data) {
                if (capacitySum >= guests) break;
                bedIds.push(bed.id);
                capacitySum += bedCapacity(bed);
            }

            if (bedIds.length === 0) {
                return 0;
            }

            const url = `${settings.apiUrl}/pricing/calculate-group`;

            const body = {
                check_in: checkIn,
                check_out: checkOut,
                adults: adults,
                children: children,
                bed_ids: bedIds,
            };

            if (settings.roomId) {
                body.room_id = settings.roomId;
            }

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': settings.nonce || '',
                },
                body: JSON.stringify(body),
            });

            const data = await response.json();

            if (data.success && data.data) {
                const totalPrice = data.data.total || data.data.price || 0;
                return totalPrice;
            }

            return 0;
        } catch (err) {
            console.error('[Price Calc Error]', err);
            return 0;
        }
    }

    /**
     * Get bed capacity
     */
    function bedCapacity(bed) {
        const explicit = Number(bed && typeof bed.available_places !== 'undefined' ? bed.available_places : bed && typeof bed.capacity !== 'undefined' ? bed.capacity : 0);
        if (Number.isFinite(explicit) && explicit > 0) {
            return explicit;
        }

        return bed && (bed.bed_type === 'bunk') ? 2 : 1;
    }

    function isValidDateRange(checkIn, checkOut) {
        if (!checkIn || !checkOut) {
            return false;
        }

        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);

        if (Number.isNaN(checkInDate.getTime()) || Number.isNaN(checkOutDate.getTime())) {
            return false;
        }

        return checkOutDate > checkInDate;
    }

    async function getCaptchaToken(settings) {
        const provider = settings.captcha && settings.captcha.provider ? settings.captcha.provider : 'recaptcha_v3';

        if (provider === 'none') {
            return 'disabled';
        }

        if (provider === 'hcaptcha') {
            if (!settings.captcha.hcaptchaSiteKey) {
                throw new Error(settings.i18n.captchaMissing || 'Captcha is not configured.');
            }
            if (!hcaptchaToken) {
                throw new Error(settings.i18n.captchaMissing || 'Captcha is not configured.');
            }
            return hcaptchaToken;
        }

        if (!settings.captcha.recaptchaSiteKey) {
            throw new Error(settings.i18n.captchaMissing || 'Captcha is not configured.');
        }

        if (typeof grecaptcha === 'undefined') {
            throw new Error(settings.i18n.captchaMissing || 'Captcha is not configured.');
        }

        return new Promise((resolve, reject) => {
            grecaptcha.ready(function () {
                grecaptcha
                    .execute(settings.captcha.recaptchaSiteKey, {
                        action: settings.captcha.recaptchaAction || 'booking_submit',
                    })
                    .then(resolve)
                    .catch(() => reject(new Error(settings.i18n.captchaMissing || 'Captcha is not configured.')));
            });
        });
    }

    /**
     * Get selected bed IDs
     */
    function getSelectedBedIds(container) {
        return Array.from(container.querySelectorAll('input[name="mp-bed-checkbox"]:checked'))
            .map((el) => parseInt(el.value, 10))
            .filter((id) => Number.isInteger(id) && id > 0);
    }

    /**
     * Compute selected capacity
     */
    function computeSelectedCapacity(container, beds) {
        const selectedIds = getSelectedBedIds(container);
        if (selectedIds.length === 0 || !Array.isArray(beds) || beds.length === 0) {
            return 0;
        }
        return beds
            .filter((bed) => selectedIds.includes(bed.id))
            .reduce((sum, bed) => sum + bedCapacity(bed), 0);
    }

    /**
     * Choose optimal beds using dynamic programming
     */
    function chooseOptimalBeds(beds, targetGuests) {
        if (!Array.isArray(beds) || beds.length === 0 || targetGuests <= 0) {
            return [];
        }

        const totalCapacity = beds.reduce((sum, bed) => sum + bedCapacity(bed), 0);
        if (totalCapacity < targetGuests) {
            return [];
        }

        // Sort by capacity (larger first), then by id
        const sorted = [...beds].sort((a, b) => {
            const diff = bedCapacity(b) - bedCapacity(a);
            if (diff !== 0) return diff;
            return (a.id || 0) - (b.id || 0);
        });

        // Dynamic programming to find minimum beds for exact capacity
        const dp = new Map();
        dp.set(0, []);

        sorted.forEach((bed) => {
            const cap = bedCapacity(bed);
            const entries = Array.from(dp.entries());
            entries.forEach(([used, picked]) => {
                const next = used + cap;
                const candidate = [...picked, bed.id];
                const existing = dp.get(next);
                if (!existing || existing.length > candidate.length) {
                    dp.set(next, candidate);
                }
            });
        });

        // Find best fit (minimum overfill, minimum beds)
        let bestIds = [];
        let bestOverfill = Number.POSITIVE_INFINITY;
        let bestCount = Number.POSITIVE_INFINITY;

        dp.forEach((ids, cap) => {
            if (cap < targetGuests) return;
            const overfill = cap - targetGuests;
            if (overfill < bestOverfill || (overfill === bestOverfill && ids.length < bestCount)) {
                bestIds = ids;
                bestOverfill = overfill;
                bestCount = ids.length;
            }
        });

        return bestIds;
    }

    /**
     * Suggest beds selection - prefers single room, then minimum overfill
     */
    function suggestBedsSelection(container, beds) {
        const targetGuests = getGuestCount(container);
        if (!Array.isArray(beds) || beds.length === 0 || targetGuests <= 0) {
            return [];
        }

        // Group beds by room
        const byRoom = beds.reduce((acc, bed) => {
            const roomId = Number(bed.room_id) || 0;
            if (!acc[roomId]) acc[roomId] = [];
            acc[roomId].push(bed);
            return acc;
        }, {});

        const candidates = [];

        // Try each room first (prefer single room)
        Object.values(byRoom).forEach((roomBeds) => {
            const ids = chooseOptimalBeds(roomBeds, targetGuests);
            if (ids.length > 0) {
                const capacity = roomBeds
                    .filter((bed) => ids.includes(bed.id))
                    .reduce((sum, bed) => sum + bedCapacity(bed), 0);
                candidates.push({
                    ids,
                    sameRoom: true,
                    overfill: capacity - targetGuests,
                    count: ids.length,
                });
            }
        });

        // Global selection (may span multiple rooms)
        const globalIds = chooseOptimalBeds(beds, targetGuests);
        if (globalIds.length > 0) {
            const capacity = beds
                .filter((bed) => globalIds.includes(bed.id))
                .reduce((sum, bed) => sum + bedCapacity(bed), 0);
            candidates.push({
                ids: globalIds,
                sameRoom: false,
                overfill: capacity - targetGuests,
                count: globalIds.length,
            });
        }

        if (candidates.length === 0) {
            return [];
        }

        // Sort: same room first, then minimum overfill, then minimum beds
        candidates.sort((a, b) => {
            if (a.sameRoom !== b.sameRoom) return a.sameRoom ? -1 : 1;
            if (a.overfill !== b.overfill) return a.overfill - b.overfill;
            return a.count - b.count;
        });

        const selected = candidates[0].ids;

        // Check the checkboxes
        const checks = container.querySelectorAll('input[name="mp-bed-checkbox"]');
        checks.forEach((input) => {
            input.checked = selected.includes(parseInt(input.value, 10));
        });

        return selected;
    }

    /**
     * Render beds list
     */
    function renderBedsList(container, settings, beds) {
        const bedsContainer = container.querySelector('#mp-beds-list');
        if (!bedsContainer) return;

        if (!Array.isArray(beds) || beds.length === 0) {
            bedsContainer.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--loading">${escapeHtml(settings.i18n.noBeds || 'Brak dostępnych łóżek.')}</div>`;
            return;
        }

        // Group beds by room for better display
        const byRoom = beds.reduce((acc, bed) => {
            const roomId = Number(bed.room_id) || 0;
            if (!acc[roomId]) acc[roomId] = [];
            acc[roomId].push(bed);
            return acc;
        }, {});

        let html = '';
        Object.entries(byRoom).forEach(([roomId, roomBeds]) => {
            const roomCapacity = roomBeds.reduce((sum, bed) => sum + bedCapacity(bed), 0);
            html += `<div class="mp-booking-form__room-group">`;
            html += `<div class="mp-booking-form__room-title">Pokój #${roomId} (${roomCapacity} miejsc)</div>`;
            html += `<div class="mp-booking-form__beds-list">`;

            roomBeds.forEach((bed) => {
                const cap = bedCapacity(bed);
                const bedType = bed.bed_type || 'single';
                const bedNumber = bed.bed_number || '?';
                const label = `Łóżko ${bedNumber} (${bedType === 'bunk' ? 'piętrowe' : bedType === 'double' ? 'podwójne' : 'pojedyncze'}, ${cap} miejsc${cap > 1 ? 'a' : 'e'})`;

                html += `
                    <label class="mp-booking-form__bed-item">
                        <input type="checkbox" name="mp-bed-checkbox" value="${bed.id}" />
                        <span>${escapeHtml(label)}</span>
                    </label>
                `;
            });

            html += `</div></div>`;
        });

        bedsContainer.innerHTML = html;
    }

    /**
     * Update selection summary
     */
    function updateSelectionSummary(container, settings, beds) {
        const summary = container.querySelector('#mp-beds-summary');
        if (!summary) return;

        const guests = getGuestCount(container);
        const capacity = computeSelectedCapacity(container, beds);
        const diff = capacity - guests;

        let tone = '#374151';
        let extra = '';
        if (capacity === 0) {
            extra = settings.i18n.summaryNone || 'Wybierz łóżka z listy.';
        } else if (diff < 0) {
            tone = '#991b1b';
            extra = ` ${settings.i18n.summaryMissing || 'Brakuje miejsc:'} ${Math.abs(diff)}.`;
        } else if (diff > 0) {
            tone = '#92400e';
            extra = ` ${settings.i18n.summaryExtra || 'Nadmiar miejsc:'} ${diff}.`;
        } else {
            tone = '#065f46';
            extra = ` ${settings.i18n.summaryPerfect || 'Dobór idealny.'}`;
        }

        summary.style.color = tone;
        summary.textContent = `${settings.i18n.summaryBase || 'Wybrano'} ${capacity} ${settings.i18n.summaryPlaces || 'miejsc'} ${settings.i18n.summaryFor || 'dla'} ${guests} ${settings.i18n.summaryGuests || 'gości'}.${extra}`;
    }

    /**
     * Setup widget
     */
    function setupWidget(container) {
        const globalSettings = typeof mpBookingData !== 'undefined' ? mpBookingData : {};
        let localSettings = {};

        try {
            const raw = container.getAttribute('data-mp-settings');
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

                    <!-- Beds selection section -->
                    <div class="mp-booking-form__beds-section">
                        <div class="mp-booking-form__beds-header">
                            <label class="mp-booking-form__beds-title">
                                Dostępne łóżka
                            </label>
                            <button type="button" id="mp-suggest-beds-btn" class="mp-booking-form__btn mp-booking-form__btn--suggest">
                                🔮 Auto-wybór
                            </button>
                        </div>
                        <div id="mp-beds-list" class="mp-booking-form__beds-list"></div>
                        <div id="mp-beds-summary" class="mp-booking-form__beds-summary"></div>
                    </div>

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

        const lockSubmittedForm = () => {
            submitBtn.disabled = true;
            step2Back.disabled = true;

            const allInputs = container.querySelectorAll('input, textarea, button');
            allInputs.forEach(input => {
                if (input !== submitBtn && input !== step2Back) {
                    input.disabled = true;
                }
            });

            const wrapper = container.querySelector('.mp-booking-form-wrapper');
            if (wrapper) {
                wrapper.style.opacity = '0.6';
                wrapper.style.pointerEvents = 'none';
            }
        };

        const renderSubmitSuccess = (emailValue, paymentHtml) => {
            results.innerHTML = `
                <div class="mp-booking-form__message mp-booking-form__message--success" style="background: #ecfdf5; border: 2px solid #10b981; color: #065f46; padding: 20px; border-radius: 12px; text-align: center; margin-top: 20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 10px;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <h3 style="margin: 0 0 10px; font-size: 18px; font-weight: 700;">${escapeHtml(settings.i18n.success || 'Rezerwacja została wysłana pomyślnie.')}</h3>
                    <p style="margin: 0 0 15px; font-size: 14px;">
                        Na adres <strong>${escapeHtml(emailValue || 'brak email')}</strong> wysłaliśmy potwierdzenie.
                    </p>
                    ${paymentHtml}
                </div>
            `;
        };

        const renderSubmitError = (message) => {
            results.innerHTML = `
                <div class="mp-booking-form__message mp-booking-form__message--error" style="background: #fef2f2; border: 2px solid #ef4444; color: #991b1b; padding: 20px; border-radius: 12px; text-align: center; margin-top: 20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 10px;">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <h3 style="margin: 0 0 10px; font-size: 18px; font-weight: 700;">Błąd</h3>
                    <p style="margin: 0; font-size: 14px;">${escapeHtml(message)}</p>
                </div>
            `;
        };

        const renderResultMessage = (variant, message) => {
            results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--${variant}">${escapeHtml(message)}</div>`;
        };

        const renderAvailabilityMessage = (variant, message, visible = true) => {
            availabilityMsg.className = `mp-booking-form__message mp-booking-form__message--${variant}`;
            availabilityMsg.style.display = visible ? 'block' : 'none';
            availabilityMsg.textContent = message;
        };

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
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            const adults = parseInt(container.querySelector('#mp-adults').value || '1', 10);
            const children = parseInt(container.querySelector('#mp-children').value || '0', 10);
            const guests = adults + children;

            if (checkIn && checkOut) {
                const checkInDate = new Date(checkIn);
                const checkOutDate = new Date(checkOut);
                const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                document.getElementById('mp-summary-dates').textContent = `${checkIn} → ${checkOut} (${nights} ${nights === 1 ? 'noc' : 'noce'})`;
            }

            document.getElementById('mp-summary-guests').textContent = `${adults} dorosłych${children > 0 ? ', ' + children + ' dzieci' : ''}`;

            if (checkIn && checkOut) {
                const priceEl = document.getElementById('mp-summary-price');
                if (priceEl) {
                    priceEl.textContent = 'Obliczanie...';
                }
                calculatePrice(settings, checkIn, checkOut, guests, container).then(price => {
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
            }
        };

        // Step 1: Check availability and load beds
        step1Next.addEventListener('click', async function() {
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            const guests = getGuestCount(container);

            if (!checkIn || !checkOut) {
                renderAvailabilityMessage('error', 'Wybierz daty przyjazdu i wyjazdu.');
                return;
            }

            if (!isValidDateRange(checkIn, checkOut)) {
                renderAvailabilityMessage('error', settings.i18n.invalidDateRange || 'Data wyjazdu musi być późniejsza niż data przyjazdu.');
                return;
            }

            renderAvailabilityMessage('loading', 'Sprawdzam dostępność...');

            const result = await checkAvailability(settings, checkIn, checkOut, guests);

            if (result.error) {
                renderAvailabilityMessage('error', 'Błąd sprawdzania dostępności.');
                return;
            }

            if (!result.available) {
                renderAvailabilityMessage('error', `Brak miejsc dla ${guests} osób. Dostępnych: ${result.availableCapacity} miejsc.`);
                return;
            }

            // Load beds and auto-suggest
            availableBeds = result.beds;
            renderBedsList(container, settings, availableBeds);

            // Auto-suggest beds
            const selected = suggestBedsSelection(container, availableBeds);
            updateSelectionSummary(container, settings, availableBeds);

            if (selected.length > 0) {
                renderAvailabilityMessage('info', `✅ System wybrał ${selected.length} łóżek dla ${guests} osób. Możesz zmienić wybór.`);
            } else {
                renderAvailabilityMessage('info', settings.i18n.bedRequired || 'Wybierz łóżka z listy.');
            }

            // Validate beds selection before proceeding
            const selectedCapacity = computeSelectedCapacity(container, availableBeds);
            if (selectedCapacity < guests) {
                renderAvailabilityMessage('error', `Za mało miejsc. Wybrano: ${selectedCapacity}, potrzeba: ${guests}.`);
                return;
            }

            renderAvailabilityMessage('info', '', false);
            goToStep(2);
        });

        // Step 2: Back
        step2Back.addEventListener('click', function() {
            goToStep(1);
        });

        // Auto-suggest button
        const suggestBtn = container.querySelector('#mp-suggest-beds-btn');
        if (suggestBtn) {
            suggestBtn.addEventListener('click', function() {
                if (availableBeds.length === 0) {
                    renderAvailabilityMessage('info', 'Najpierw wybierz daty.');
                    return;
                }
                const selected = suggestBedsSelection(container, availableBeds);
                updateSelectionSummary(container, settings, availableBeds);
                if (selected.length > 0) {
                    renderAvailabilityMessage('info', `✅ Auto-wybór: ${selected.length} łóżek.`);
                }
            });
        }

        // Auto-load beds when dates change
        const checkInInput = container.querySelector('#mp-check-in');
        const checkOutInput = container.querySelector('#mp-check-out');

        const autoLoadBeds = async function() {
            const checkIn = checkInInput?.value;
            const checkOut = checkOutInput?.value;
            const guests = getGuestCount(container);

            if (!checkIn || !checkOut) return;
            if (!isValidDateRange(checkIn, checkOut)) {
                renderAvailabilityMessage('error', settings.i18n.invalidDateRange || 'Data wyjazdu musi być późniejsza niż data przyjazdu.');
                return;
            }

            renderAvailabilityMessage('loading', 'Ładowanie dostępności...');

            try {
                const result = await checkAvailability(settings, checkIn, checkOut, guests);
                if (result.success !== false && result.beds && result.beds.length > 0) {
                    availableBeds = result.beds;
                    renderBedsList(container, settings, availableBeds);
                    const selected = suggestBedsSelection(container, availableBeds);
                    updateSelectionSummary(container, settings, availableBeds);
                    renderAvailabilityMessage('info', `✅ Załadowano ${availableBeds.length} łóżek. Auto-wybór: ${selected.length} łóżek.`);
                } else {
                    renderAvailabilityMessage('error', settings.i18n.noBeds || 'Brak dostępnych łóżek.');
                }
            } catch (err) {
                renderAvailabilityMessage('error', 'Błąd ładowania dostępności.');
            }
        };

        if (checkInInput) checkInInput.addEventListener('change', autoLoadBeds);
        if (checkOutInput) checkOutInput.addEventListener('change', autoLoadBeds);

        // Update summary when beds selection changes
        container.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'mp-bed-checkbox') {
                updateSelectionSummary(container, settings, availableBeds);
            }
        });

        // Auto-suggest when guests change
        const adultsInput = container.querySelector('#mp-adults');
        const childrenInput = container.querySelector('#mp-children');
        [adultsInput, childrenInput].forEach((input) => {
            input.addEventListener('change', function() {
                if (availableBeds.length === 0) return;
                suggestBedsSelection(container, availableBeds);
                updateSelectionSummary(container, settings, availableBeds);
            });
        });

        // Submit
        submitBtn.addEventListener('click', async function() {
            if (isSubmitting) {
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

            if (!firstName || !lastName || !email || !checkIn || !checkOut) {
                renderResultMessage('error', 'Wypełnij wszystkie wymagane pola.');
                isSubmitting = false;
                return;
            }

            if (!isValidDateRange(checkIn, checkOut)) {
                renderResultMessage('error', settings.i18n.invalidDateRange || 'Data wyjazdu musi być późniejsza niż data przyjazdu.');
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
            renderResultMessage('loading', 'Wysyłanie...');

            try {
                const captchaToken = await getCaptchaToken(settings);

                // Get selected bed IDs from checkboxes
                const bedIds = getSelectedBedIds(container);
                const guests = getGuestCount(container);

                // Validate beds selection
                if (bedIds.length === 0) {
                    throw new Error(settings.i18n.bedRequired || 'Wybierz łóżka z listy.');
                }

                const selectedCapacity = computeSelectedCapacity(container, availableBeds);
                if (selectedCapacity < guests) {
                    throw new Error(`Za mało miejsc. Wybrano: ${selectedCapacity}, potrzeba: ${guests}.`);
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
                        captcha_token: captchaToken,
                    }),
                });

                const data = await response.json();
                console.log('[Submit] API Response:', data);

                if (response.ok && data.success) {
                    // Extract payment info from response
                    const responseData = data.data || {};
                    const deposit_required = responseData.deposit_required || false;
                    const deposit_amount = responseData.deposit_amount || 0;
                    const deposit_percent = responseData.deposit_percent || 30;
                    const payment_deadline = responseData.payment_deadline || '';
                    const payment_info = responseData.payment_info || null;
                    const total_price = responseData.total_price || 0;

                    console.log('[Submit] Payment info:', { deposit_required, deposit_amount, deposit_percent, payment_info });

                    // Format deadline date
                    const formatDeadline = (dateString) => {
                        if (!dateString) return '';
                        const date = new Date(dateString);
                        return date.toLocaleString('pl-PL', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    };

                    // Calculate hours until deadline
                    const getHoursUntilDeadline = (dateString) => {
                        if (!dateString) return 48;
                        const deadline = new Date(dateString);
                        const now = new Date();
                        const diffMs = deadline - now;
                        const diffHours = Math.ceil(diffMs / (1000 * 60 * 60));
                        return Math.max(1, diffHours);
                    };

                    // Build payment info HTML
                    let paymentHtml = '';
                    if (deposit_required && payment_info) {
                        const hours = getHoursUntilDeadline(payment_deadline);
                        paymentHtml = `
                            <div class="mp-booking-form__payment-info">
                                <h4 class="mp-booking-form__payment-title">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="5" width="20" height="14" rx="2"/>
                                        <line x1="2" y1="10" x2="22" y2="10"/>
                                    </svg>
                                    Wymagana zaliczka
                                </h4>
                                
                                <div class="mp-booking-form__deposit-amount">
                                    ${deposit_amount.toFixed(2)} zł (${deposit_percent}%)
                                </div>
                                
                                ${payment_info.account_number ? `
                                    <div class="mp-booking-form__payment-details">
                                        <div class="mp-booking-form__payment-detail-row">
                                            <span class="mp-booking-form__payment-detail-label">Nr konta:</span>
                                            <span class="mp-booking-form__payment-detail-value">${escapeHtml(payment_info.account_number)}</span>
                                        </div>
                                        ${payment_info.bank_name ? `
                                        <div class="mp-booking-form__payment-detail-row">
                                            <span class="mp-booking-form__payment-detail-label">Bank:</span>
                                            <span class="mp-booking-form__payment-detail-value">${escapeHtml(payment_info.bank_name)}</span>
                                        </div>
                                        ` : ''}
                                        <div class="mp-booking-form__payment-detail-row">
                                            <span class="mp-booking-form__payment-detail-label">Tytuł:</span>
                                            <span class="mp-booking-form__payment-detail-value">${escapeHtml(payment_info.title)}</span>
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <div class="mp-booking-form__payment-deadline">
                                    <div class="mp-booking-form__payment-deadline-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        Czas na płatność: ${hours} godzin
                                    </div>
                                    <div class="mp-booking-form__payment-deadline-time">
                                        do ${formatDeadline(payment_deadline)}
                                    </div>
                                </div>
                                
                                ${payment_info.additional_info ? `
                                    <p class="mp-booking-form__payment-note">
                                        ${escapeHtml(payment_info.additional_info)}
                                    </p>
                                ` : ''}
                                
                                <p class="mp-booking-form__payment-note">
                                    <strong>Po zaksięgowaniu wpłaty</strong> otrzymasz ostateczne potwierdzenie rezerwacji.
                                </p>
                            </div>
                        `;
                        console.log('[Submit] Payment HTML generated:', paymentHtml.length);
                    } else {
                        console.log('[Submit] No payment info - deposit_required:', deposit_required, 'payment_info:', payment_info);
                    }

                    console.log('[Submit] Rendering success message, email:', email);

                    // SUCCESS: Lock form and show clear success message
                    renderSubmitSuccess(email, paymentHtml);
                    
                    console.log('[Submit] Success message rendered, results.innerHTML length:', results.innerHTML.length);

                    // LOCK FORM: Disable all inputs and buttons
                    lockSubmittedForm();

                    isSubmitting = false; // Reset flag
                } else {
                    const msg = (data && data.message) ? data.message : (settings.i18n.error || 'Wystąpił błąd. Spróbuj ponownie.');
                    throw new Error(msg);
                }
            } catch (err) {
                const msg = err && err.message ? err.message : (settings.i18n.error || 'Wystąpił błąd. Spróbuj ponownie.');
                renderSubmitError(msg);
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

        containers.forEach((container) => {
            setupWidget(container);
        });
    });
})();
