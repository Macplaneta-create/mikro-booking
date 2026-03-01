/**
 * MikroPlaneta Booking Frontend Widget
 */
(function () {
    let hcaptchaToken = '';
    let availableBeds = [];

    window.mpHcaptchaDone = function (token) {
        hcaptchaToken = token || '';
    };

    window.mpHcaptchaExpired = function () {
        hcaptchaToken = '';
    };

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getSelectedBedIds(container) {
        return Array.from(container.querySelectorAll('input[name="mp-bed-checkbox"]:checked'))
            .map((el) => parseInt(el.value, 10))
            .filter((id) => Number.isInteger(id) && id > 0);
    }

    function getGuestsCount(container) {
        const adults = Math.max(1, parseInt(container.querySelector('#mp-adults').value, 10) || 1);
        const children = Math.max(0, parseInt(container.querySelector('#mp-children').value, 10) || 0);
        return adults + children;
    }

    function bedCapacity(bed) {
        return bed && bed.bed_type === 'bunk' ? 2 : 1;
    }

    function computeSelectedCapacity(container, beds) {
        const selectedIds = getSelectedBedIds(container);
        if (selectedIds.length === 0 || !Array.isArray(beds) || beds.length === 0) {
            return 0;
        }
        return beds
            .filter((bed) => selectedIds.includes(bed.id))
            .reduce((sum, bed) => sum + bedCapacity(bed), 0);
    }

    function updateSelectionSummary(container, settings, beds) {
        const summary = container.querySelector('#mp-beds-summary');
        if (!summary) return;

        const guests = getGuestsCount(container);
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

    function chooseOptimalBeds(beds, targetGuests) {
        if (!Array.isArray(beds) || beds.length === 0 || targetGuests <= 0) {
            return [];
        }

        const totalCapacity = beds.reduce((sum, bed) => sum + bedCapacity(bed), 0);
        if (totalCapacity < targetGuests) {
            return [];
        }

        const sorted = [...beds].sort((a, b) => {
            const diff = bedCapacity(b) - bedCapacity(a);
            if (diff !== 0) return diff;
            return (a.id || 0) - (b.id || 0);
        });

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

    function suggestBedsSelection(container, beds) {
        const targetGuests = getGuestsCount(container);
        if (!Array.isArray(beds) || beds.length === 0 || targetGuests <= 0) {
            return [];
        }

        const byRoom = beds.reduce((acc, bed) => {
            const roomId = Number(bed.room_id) || 0;
            if (!acc[roomId]) acc[roomId] = [];
            acc[roomId].push(bed);
            return acc;
        }, {});

        const candidates = [];
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

        candidates.sort((a, b) => {
            if (a.sameRoom !== b.sameRoom) return a.sameRoom ? -1 : 1;
            if (a.overfill !== b.overfill) return a.overfill - b.overfill;
            return a.count - b.count;
        });

        const selected = candidates[0].ids;
        const checks = container.querySelectorAll('input[name="mp-bed-checkbox"]');
        checks.forEach((input) => {
            input.checked = selected.includes(parseInt(input.value, 10));
        });
        return selected;
    }

    function renderBedsList(container, settings, beds) {
        const bedsContainer = container.querySelector('#mp-beds-list');
        if (!bedsContainer) return;

        if (!Array.isArray(beds) || beds.length === 0) {
            bedsContainer.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--loading">${escapeHtml(settings.i18n.noBeds)}</div>`;
            return;
        }

        bedsContainer.innerHTML = beds.map((bed) => {
            const bedType = bed.bed_type || 'single';
            const bedCapacity = (bedType === 'bunk') ? 2 : 1;
            const placesLabel = bedCapacity > 1 ? `${bedCapacity} miejsca` : `${bedCapacity} miejsce`;
            const bedNumber = bed.bed_number || '?';
            const roomId = bed.room_id || '?';
            const label = `#${bed.id} • Pokój ${roomId} • Łóżko ${bedNumber} (${bedType}) • ${placesLabel}`;
            return `
                <label class="mp-booking-form__bed-item">
                    <input type="checkbox" name="mp-bed-checkbox" value="${bed.id}" />
                    <span>${escapeHtml(label)}</span>
                </label>
            `;
        }).join('');
    }

    async function fetchAvailableBeds(settings, checkIn, checkOut) {
        console.log('[MP Booking] fetchAvailableBeds called with roomId:', settings.roomId);
        let url = `${settings.apiUrl}/public/availability/beds?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`;
        if (settings.roomId && Number(settings.roomId) > 0) {
            url += `&room_id=${encodeURIComponent(String(settings.roomId))}`;
            console.log('[MP Booking] Adding room_id filter to URL:', url);
        }
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        });

        const data = await response.json();
        console.log('[MP Booking] Fetched beds:', data);
        if (!response.ok || !data || !data.success || !Array.isArray(data.data)) {
            throw new Error(settings.i18n.error);
        }

        return data.data;
    }

    async function getCaptchaToken(settings) {
        const provider = settings.captcha && settings.captcha.provider ? settings.captcha.provider : 'recaptcha_v3';

        if (provider === 'none') {
            return 'disabled';
        }

        if (provider === 'hcaptcha') {
            if (!settings.captcha.hcaptchaSiteKey) {
                throw new Error(settings.i18n.captchaMissing);
            }
            if (!hcaptchaToken) {
                throw new Error(settings.i18n.captchaMissing);
            }
            return hcaptchaToken;
        }

        if (!settings.captcha.recaptchaSiteKey) {
            throw new Error(settings.i18n.captchaMissing);
        }

        if (typeof grecaptcha === 'undefined') {
            throw new Error(settings.i18n.captchaMissing);
        }

        return new Promise((resolve, reject) => {
            grecaptcha.ready(function () {
                grecaptcha
                    .execute(settings.captcha.recaptchaSiteKey, {
                        action: settings.captcha.recaptchaAction || 'booking_submit',
                    })
                    .then(resolve)
                    .catch(() => reject(new Error(settings.i18n.captchaMissing)));
            });
        });
    }

    function getDefaultSettings() {
        return {
            apiUrl: '',
            nonce: '',
            captcha: {
                provider: 'none',
                recaptchaSiteKey: '',
                hcaptchaSiteKey: '',
                recaptchaAction: 'booking_submit',
            },
            i18n: {
                loading: 'Loading...',
                checkIn: 'Check-in',
                checkOut: 'Check-out',
                firstName: 'First name',
                lastName: 'Last name',
                email: 'Email',
                phone: 'Phone',
                adults: 'Adults',
                children: 'Children',
                availableBeds: 'Available beds',
                findBeds: 'Find available beds',
                suggestBeds: 'Auto-select beds',
                suggestedBeds: 'Beds were suggested automatically for current guest count.',
                bedRequired: 'Select at least one bed from availability list.',
                noBeds: 'No beds available for selected dates.',
                summaryBase: 'Wybrano',
                summaryPlaces: 'miejsc',
                summaryFor: 'dla',
                summaryGuests: 'gości',
                summaryNone: 'Wybierz łóżka z listy.',
                summaryMissing: 'Brakuje miejsc:',
                summaryExtra: 'Nadmiar miejsc:',
                summaryPerfect: 'Dobór idealny.',
                notes: 'Notes',
                submit: 'Send reservation request',
                captchaMissing: 'Captcha is not configured.',
                formInvalid: 'Please fill all required fields.',
                success: 'Reservation request sent successfully.',
                error: 'Failed to send reservation request.',
            },
        };
    }

    function setupWidget(container) {
        const globalSettings = typeof mpBookingData !== 'undefined' ? mpBookingData : getDefaultSettings();
        let localSettings = {};
        try {
            const raw = container.getAttribute('data-mp-settings');
            localSettings = raw ? JSON.parse(raw) : {};
        } catch (e) {
            localSettings = {};
        }

        const settings = {
            ...globalSettings,
            ...localSettings,
            captcha: { ...(globalSettings.captcha || {}), ...(localSettings.captcha || {}) },
            i18n: { ...(globalSettings.i18n || {}), ...(localSettings.i18n || {}) },
        };
        const isHcaptcha = settings.captcha && settings.captcha.provider === 'hcaptcha';

        // Check for prefill data (from modal)
        const prefill = settings.prefill || {};

        // Check if room is per_room or per_bed
        const isPerRoom = settings.roomId && Number(settings.roomId) > 0;
        let roomPricingMode = 'per_bed'; // default
        let roomCapacity = 0; // Store room capacity
        let roomName = ''; // Store room name
        let roomInfoLoaded = false; // Track if room info is loaded

        // Fetch room info to determine pricing mode and capacity
        if (isPerRoom) {
            // Set defaults immediately
            const adultsInput = container.querySelector('#mp-adults');
            const childrenInput = container.querySelector('#mp-children');
            
            // Show loading state
            if (adultsInput) adultsInput.disabled = true;
            if (childrenInput) childrenInput.disabled = true;
            
            // Fetch room data
            fetch(`${settings.apiUrl}/rooms/${settings.roomId}`, {
                headers: { 'X-WP-Nonce': settings.nonce || '' }
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.data) {
                    roomPricingMode = data.data.pricing_mode || 'per_bed';
                    roomName = data.data.name || '';

                    // Calculate room capacity from beds
                    if (data.data.beds && Array.isArray(data.data.beds)) {
                        roomCapacity = data.data.beds.reduce((sum, bed) => {
                            const bedType = bed.bed_type || 'single';
                            return sum + ((bedType === 'bunk') ? 2 : 1);
                        }, 0);
                    }
                    
                    console.log('[MP Booking] Room info:', { 
                        pricing_mode: roomPricingMode, 
                        capacity: roomCapacity,
                        name: roomName 
                    });
                    
                    if (roomPricingMode === 'per_room' && roomCapacity > 0) {
                        // Hide beds section for per_room mode
                        const bedsSection = container.querySelector('.mp-booking-form__beds-section');
                        if (bedsSection) bedsSection.style.display = 'none';
                        
                        // Limit adults to room capacity
                        if (adultsInput) {
                            adultsInput.max = roomCapacity;
                            adultsInput.disabled = false;
                            // Set default to min(2, roomCapacity)
                            const currentAdults = parseInt(adultsInput.value || '1', 10);
                            if (currentAdults > roomCapacity) {
                                adultsInput.value = Math.min(2, roomCapacity);
                            }
                            
                            // Update when adults change
                            adultsInput.addEventListener('change', function() {
                                const adultCount = parseInt(this.value, 10) || 1;
                                if (adultCount > roomCapacity) {
                                    this.value = roomCapacity;
                                }
                                if (childrenInput) {
                                    childrenInput.max = Math.max(0, roomCapacity - adultCount);
                                    if (parseInt(childrenInput.value, 10) > childrenInput.max) {
                                        childrenInput.value = childrenInput.max;
                                    }
                                }
                            });
                        }
                        
                        // Limit children to room capacity minus adults
                        if (childrenInput && adultsInput) {
                            const currentAdults = parseInt(adultsInput.value || '1', 10);
                            childrenInput.max = Math.max(0, roomCapacity - currentAdults);
                            childrenInput.value = 0;
                            childrenInput.disabled = false;
                            
                            childrenInput.addEventListener('change', function() {
                                const childCount = parseInt(this.value, 10) || 0;
                                const adultCount = parseInt(adultsInput.value || '1', 10);
                                if (childCount + adultCount > roomCapacity) {
                                    this.value = Math.max(0, roomCapacity - adultCount);
                                }
                            });
                        }
                        
                        // Mark room info as loaded
                        roomInfoLoaded = true;
                        console.log('[MP Booking] Room info loaded, capacity:', roomCapacity);
                    } else {
                        // Not per_room mode, enable inputs
                        if (adultsInput) adultsInput.disabled = false;
                        if (childrenInput) childrenInput.disabled = false;
                        roomInfoLoaded = true;
                    }
                }
            })
            .catch(err => {
                console.error('[MP Booking] Failed to load room info:', err);
                // Enable inputs even on error
                if (adultsInput) adultsInput.disabled = false;
                if (childrenInput) childrenInput.disabled = false;
                roomInfoLoaded = true;
            });
        } else {
            // Not a room-specific widget, mark as loaded
            roomInfoLoaded = true;
        }

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
                    ${roomPricingMode === 'per_room' ? '<div class="mp-booking-form__mode-badge">Rezerwacja całego obiektu</div>' : ''}
                </div>

                <!-- Step 1: Dates & Guests -->
                <div class="mp-booking-form__step-content mp-booking-form__step-content--active" id="mp-step-1">
                    <div class="mp-booking-form__grid">
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                ${escapeHtml(settings.i18n.checkIn)} *
                            </label>
                            <input type="date" id="mp-check-in" value="${escapeHtml(prefill.checkIn || '')}" class="mp-booking-form__input" />
                        </div>
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                ${escapeHtml(settings.i18n.checkOut)} *
                            </label>
                            <input type="date" id="mp-check-out" value="${escapeHtml(prefill.checkOut || '')}" class="mp-booking-form__input" />
                        </div>
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                ${escapeHtml(settings.i18n.adults)}
                            </label>
                            <input type="number" id="mp-adults" min="1" max="50" value="${escapeHtml(String(prefill.adults || 1))}" class="mp-booking-form__number-input" />
                        </div>
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                ${escapeHtml(settings.i18n.children)}
                            </label>
                            <input type="number" id="mp-children" min="0" max="50" value="${escapeHtml(String(prefill.children || 0))}" class="mp-booking-form__number-input" />
                        </div>
                    </div>

                    <div class="mp-booking-form__beds-section">
                        <div class="mp-booking-form__beds-header">
                            <label class="mp-booking-form__beds-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>
                                ${escapeHtml(settings.i18n.availableBeds)}
                            </label>
                            <div class="mp-booking-form__beds-actions">
                                <button type="button" id="mp-suggest-beds-btn" class="mp-booking-form__btn mp-booking-form__btn--suggest">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    ${escapeHtml(settings.i18n.suggestBeds)}
                                </button>
                                <button type="button" id="mp-find-beds-btn" class="mp-booking-form__btn mp-booking-form__btn--find">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    ${escapeHtml(settings.i18n.findBeds)}
                                </button>
                            </div>
                        </div>
                        <div id="mp-beds-list" class="mp-booking-form__beds-list"></div>
                        <div id="mp-beds-summary" class="mp-booking-form__beds-summary"></div>
                    </div>

                    <div class="mp-booking-form__footer">
                        <button type="button" id="mp-step-1-next" class="mp-booking-form__btn mp-booking-form__btn--primary mp-booking-form__btn--full">
                            ${escapeHtml(settings.i18n.next || 'Dalej')}
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Guest Data -->
                <div class="mp-booking-form__step-content" id="mp-step-2">
                    <!-- Booking Summary -->
                    <div class="mp-booking-form__summary">
                        <h4 class="mp-booking-form__summary-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            Podsumowanie rezerwacji
                        </h4>
                        <div class="mp-booking-form__summary-details">
                            <div class="mp-booking-form__summary-item">
                                <span class="mp-booking-form__summary-label">Termin:</span>
                                <span class="mp-booking-form__summary-value" id="mp-summary-dates"></span>
                            </div>
                            <div class="mp-booking-form__summary-item">
                                <span class="mp-booking-form__summary-label">Goście:</span>
                                <span class="mp-booking-form__summary-value" id="mp-summary-guests"></span>
                            </div>
                            <div class="mp-booking-form__summary-item ${roomPricingMode === 'per_bed' ? '' : 'mp-hidden'}" id="mp-summary-beds-item">
                                <span class="mp-booking-form__summary-label">Wybrane łóżka:</span>
                                <span class="mp-booking-form__summary-value" id="mp-summary-beds"></span>
                            </div>
                            <div class="mp-booking-form__summary-item mp-booking-form__summary-item--price" id="mp-summary-price-item">
                                <span class="mp-booking-form__summary-label">Szacowany koszt:</span>
                                <span class="mp-booking-form__summary-value mp-booking-form__summary-value--price" id="mp-summary-price">-- zł</span>
                            </div>
                        </div>
                    </div>

                    <div class="mp-booking-form__grid">
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.firstName)} *</label>
                            <input type="text" id="mp-first-name" class="mp-booking-form__input" placeholder="Jan" />
                        </div>
                        <div class="mp-booking-form__group">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.lastName)} *</label>
                            <input type="text" id="mp-last-name" class="mp-booking-form__input" placeholder="Kowalski" />
                        </div>
                        <div class="mp-booking-form__group mp-booking-form__group--full">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.email)} *</label>
                            <input type="email" id="mp-email" class="mp-booking-form__input" placeholder="jan@example.pl" />
                        </div>
                        <div class="mp-booking-form__group mp-booking-form__group--full">
                            <label class="mp-booking-form__label">${escapeHtml(settings.i18n.phone)}</label>
                            <input type="tel" id="mp-phone" class="mp-booking-form__input" placeholder="+48 123 456 789" />
                        </div>
                    </div>

                    <div class="mp-booking-form__group mp-booking-form__group--notes">
                        <label class="mp-booking-form__label">${escapeHtml(settings.i18n.notes)}</label>
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
                        <div class="h-captcha"
                             data-sitekey="${escapeHtml(settings.captcha.hcaptchaSiteKey || '')}"
                             data-callback="mpHcaptchaDone"
                             data-expired-callback="mpHcaptchaExpired"></div>
                    </div>

                    <div class="mp-booking-form__footer mp-booking-form__footer--split">
                        <button type="button" id="mp-step-2-back" class="mp-booking-form__btn mp-booking-form__btn--secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            ${escapeHtml(settings.i18n.back || 'Wstecz')}
                        </button>
                        <button type="button" id="mp-submit-btn" class="mp-booking-form__btn mp-booking-form__btn--primary">
                            ${escapeHtml(settings.i18n.submit)}
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        </button>
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
        const findBedsBtn = container.querySelector('#mp-find-beds-btn');
        const suggestBedsBtn = container.querySelector('#mp-suggest-beds-btn');
        const results = container.querySelector('#mp-results-container');

        // Step indicators
        const stepIndicators = container.querySelectorAll('.mp-booking-form__step');

        const goToStep = (step) => {
            if (step === 1) {
                step1.classList.add('mp-booking-form__step-content--active');
                step2.classList.remove('mp-booking-form__step-content--active');
                stepIndicators[0].classList.add('mp-booking-form__step--active');
                stepIndicators[1].classList.remove('mp-booking-form__step--active');
            } else {
                // Update summary before showing step 2
                updateBookingSummary();
                
                step1.classList.remove('mp-booking-form__step-content--active');
                step2.classList.add('mp-booking-form__step-content--active');
                stepIndicators[0].classList.remove('mp-booking-form__step--active');
                stepIndicators[1].classList.add('mp-booking-form__step--active');
            }
        };

        const updateBookingSummary = () => {
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            const adults = parseInt(container.querySelector('#mp-adults').value, 10) || 1;
            const children = parseInt(container.querySelector('#mp-children').value, 10) || 0;
            const totalGuests = adults + children;
            
            // Format dates
            if (checkIn && checkOut) {
                const checkInDate = new Date(checkIn);
                const checkOutDate = new Date(checkOut);
                const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                document.getElementById('mp-summary-dates').textContent = 
                    `${checkIn} → ${checkOut} (${nights} ${nights === 1 ? 'noc' : 'noce'})`;
            }
            
            // Format guests with room capacity info
            let guestsText = `${adults} dorosłych${children > 0 ? `, ${children} dzieci` : ''}`;
            if (roomPricingMode === 'per_room' && roomCapacity > 0) {
                guestsText += ` (max ${roomCapacity} os.)`;
            }
            document.getElementById('mp-summary-guests').textContent = guestsText;
            
            // Format beds (for per_bed mode)
            if (roomPricingMode === 'per_bed') {
                const bedIds = getSelectedBedIds(container);
                const selectedCapacity = computeSelectedCapacity(container, availableBeds);
                document.getElementById('mp-summary-beds').textContent = 
                    bedIds.length > 0 
                        ? `${bedIds.length} łóżek (${selectedCapacity} miejsc)`
                        : 'Brak wybranych łóżek';
                
                // Calculate and show price
                calculatePrice(checkIn, checkOut, bedIds);
            } else {
                // For per_room mode, calculate room price
                calculatePrice(checkIn, checkOut, [settings.roomId]);
            }
        };

        const calculatePrice = async (checkIn, checkOut, ids) => {
            const priceEl = document.getElementById('mp-summary-price');
            if (!checkIn || !checkOut || ids.length === 0) {
                priceEl.textContent = '-- zł';
                return;
            }
            
            try {
                // Use calculate-group endpoint (POST method)
                const url = `${settings.apiUrl}/pricing/calculate-group`;
                
                const body = {
                    check_in: checkIn,
                    check_out: checkOut,
                    adults: parseInt(container.querySelector('#mp-adults').value, 10) || 1,
                    children: parseInt(container.querySelector('#mp-children').value, 10) || 0,
                };
                
                if (roomPricingMode === 'per_room' && settings.roomId) {
                    body.room_id = settings.roomId;
                } else if (roomPricingMode === 'per_bed' && ids.length > 0) {
                    body.bed_ids = ids;
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
                
                console.log('[Price Calc] Response:', data);
                
                if (data && data.success && data.data) {
                    const price = data.data.total || data.data.price || data.data.total_price || 0;
                    priceEl.textContent = `${price.toFixed(2)} zł`;
                } else {
                    console.warn('[Price Calc] No price data:', data);
                    priceEl.textContent = '-- zł';
                }
            } catch (err) {
                console.error('[Price Calc Error]', err);
                priceEl.textContent = '-- zł';
            }
        };

        step1Next.addEventListener('click', async function() {
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            const adults = parseInt(container.querySelector('#mp-adults').value, 10) || 1;
            const children = parseInt(container.querySelector('#mp-children').value, 10) || 0;
            const totalGuests = adults + children;
            
            if (!checkIn || !checkOut) {
                results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">${escapeHtml(settings.i18n.formInvalid)}</div>`;
                return;
            }
            
            // Wait for room info to load (for per_room mode)
            if (isPerRoom && !roomInfoLoaded) {
                results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--loading">Ładowanie informacji o pokoju...</div>`;
                // Wait a bit and retry
                setTimeout(() => {
                    if (roomInfoLoaded) {
                        // Trigger validation again
                        step1Next.click();
                    }
                }, 500);
                return;
            }
            
            // Validate guests against room capacity (for per_room mode)
            if (roomPricingMode === 'per_room' && roomCapacity > 0) {
                if (totalGuests > roomCapacity) {
                    results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">
                        <strong>Nieprawidłowa liczba osób!</strong><br>
                        Pokój "${escapeHtml(roomName)}" mieści maksymalnie <strong>${roomCapacity} osoby</strong>.<br>
                        Wybrano: ${totalGuests} osób (${adults} dorosłych + ${children} dzieci).
                    </div>`;
                    return;
                }
                
                if (totalGuests < 1) {
                    results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">Podaj liczbę gości (minimum 1 osoba).</div>`;
                    return;
                }
            }
            
            // For per_bed mode, require bed selection
            if (roomPricingMode === 'per_bed') {
                // Auto-find beds if not already loaded
                if (availableBeds.length === 0 && findBedsBtn) {
                    findBedsBtn.disabled = true;
                    try {
                        availableBeds = await fetchAvailableBeds(settings, checkIn, checkOut);
                        renderBedsList(container, settings, availableBeds);
                        const checks = container.querySelectorAll('input[name="mp-bed-checkbox"]');
                        checks.forEach((input) => {
                            input.addEventListener('change', function () {
                                updateSelectionSummary(container, settings, availableBeds);
                            });
                        });
                        const selected = suggestBedsSelection(container, availableBeds);
                        updateSelectionSummary(container, settings, availableBeds);
                    } catch (err) {
                        results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">${escapeHtml(err && err.message ? err.message : settings.i18n.error)}</div>`;
                        return;
                    } finally {
                        findBedsBtn.disabled = false;
                    }
                }
                
                // Check if enough beds available
                const selectedCapacity = computeSelectedCapacity(container, availableBeds);
                if (selectedCapacity < totalGuests) {
                    results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">
                        <strong>Za mało miejsc!</strong><br>
                        Wybrano łóżka o łącznej pojemności <strong>${selectedCapacity} miejsc</strong>.<br>
                        Liczba gości: ${totalGuests} osób.
                    </div>`;
                    return;
                }
            }
            
            goToStep(2);
        });

        step2Back.addEventListener('click', function() {
            goToStep(1);
        });

        findBedsBtn.addEventListener('click', async function () {
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            if (!checkIn || !checkOut) {
                results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">${escapeHtml(settings.i18n.formInvalid)}</div>`;
                return;
            }

            findBedsBtn.disabled = true;
            results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--loading">${escapeHtml(settings.i18n.loading)}</div>`;
            try {
                availableBeds = await fetchAvailableBeds(settings, checkIn, checkOut);
                renderBedsList(container, settings, availableBeds);
                const checks = container.querySelectorAll('input[name="mp-bed-checkbox"]');
                checks.forEach((input) => {
                    input.addEventListener('change', function () {
                        updateSelectionSummary(container, settings, availableBeds);
                    });
                });
                const selected = suggestBedsSelection(container, availableBeds);
                updateSelectionSummary(container, settings, availableBeds);
                results.innerHTML = selected.length > 0
                    ? `<div class="mp-booking-form__message mp-booking-form__message--success">${escapeHtml(settings.i18n.suggestedBeds)}</div>`
                    : '';
            } catch (err) {
                results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">${escapeHtml(err && err.message ? err.message : settings.i18n.error)}</div>`;
            } finally {
                findBedsBtn.disabled = false;
            }
        });

        suggestBedsBtn.addEventListener('click', function () {
            const selected = suggestBedsSelection(container, availableBeds);
            updateSelectionSummary(container, settings, availableBeds);
            if (selected.length > 0) {
                results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--info">${escapeHtml(settings.i18n.suggestedBeds)}</div>`;
            }
        });

        const adultsInput = container.querySelector('#mp-adults');
        const childrenInput = container.querySelector('#mp-children');
        [adultsInput, childrenInput].forEach((input) => {
            input.addEventListener('change', function () {
                if (availableBeds.length === 0) return;
                suggestBedsSelection(container, availableBeds);
                updateSelectionSummary(container, settings, availableBeds);
            });
        });

        // Update summary when beds selection changes (for step 2)
        container.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'mp-bed-checkbox') {
                updateSelectionSummary(container, settings, availableBeds);
            }
        });
        
        // Auto-find beds if prefill data is present (modal mode)
        if (prefill.checkIn && prefill.checkOut) {
            // Trigger find beds automatically after a short delay
            setTimeout(async function() {
                const checkIn = container.querySelector('#mp-check-in').value;
                const checkOut = container.querySelector('#mp-check-out').value;
                if (checkIn && checkOut && findBedsBtn) {
                    findBedsBtn.disabled = true;
                    try {
                        availableBeds = await fetchAvailableBeds(settings, checkIn, checkOut);
                        renderBedsList(container, settings, availableBeds);
                        const checks = container.querySelectorAll('input[name="mp-bed-checkbox"]');
                        checks.forEach((input) => {
                            input.addEventListener('change', function () {
                                updateSelectionSummary(container, settings, availableBeds);
                            });
                        });
                        const selected = suggestBedsSelection(container, availableBeds);
                        updateSelectionSummary(container, settings, availableBeds);
                        results.innerHTML = selected.length > 0
                            ? `<div class="mp-booking-form__message mp-booking-form__message--info">${escapeHtml(settings.i18n.suggestedBeds)}</div>`
                            : '';
                    } catch (err) {
                        results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">${escapeHtml(err && err.message ? err.message : settings.i18n.error)}</div>`;
                    } finally {
                        findBedsBtn.disabled = false;
                    }
                }
            }, 300);
        }

        // Auto-find beds when both dates are filled
        const checkInInput = container.querySelector('#mp-check-in');
        const checkOutInput = container.querySelector('#mp-check-out');
        
        const autoFindBeds = async function() {
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            if (checkIn && checkOut && findBedsBtn && !findBedsBtn.disabled) {
                findBedsBtn.disabled = true;
                try {
                    availableBeds = await fetchAvailableBeds(settings, checkIn, checkOut);
                    renderBedsList(container, settings, availableBeds);
                    const checks = container.querySelectorAll('input[name="mp-bed-checkbox"]');
                    checks.forEach((input) => {
                        input.addEventListener('change', function () {
                            updateSelectionSummary(container, settings, availableBeds);
                        });
                    });
                    const selected = suggestBedsSelection(container, availableBeds);
                    updateSelectionSummary(container, settings, availableBeds);
                    results.innerHTML = selected.length > 0
                        ? `<div class="mp-booking-form__message mp-booking-form__message--info">${escapeHtml(settings.i18n.suggestedBeds)}</div>`
                        : `<div class="mp-booking-form__message mp-booking-form__message--info">Wybierz łóżka z listy powyżej.</div>`;
                } catch (err) {
                    results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">${escapeHtml(err && err.message ? err.message : settings.i18n.error)}</div>`;
                } finally {
                    findBedsBtn.disabled = false;
                }
            }
        };

        if (checkInInput) checkInInput.addEventListener('change', autoFindBeds);
        if (checkOutInput) checkOutInput.addEventListener('change', autoFindBeds);

        submitBtn.addEventListener('click', async function () {
            const firstName = container.querySelector('#mp-first-name').value.trim();
            const lastName = container.querySelector('#mp-last-name').value.trim();
            const email = container.querySelector('#mp-email').value.trim();
            const phone = container.querySelector('#mp-phone').value.trim();
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            const adults = parseInt(container.querySelector('#mp-adults').value, 10) || 1;
            const children = parseInt(container.querySelector('#mp-children').value, 10) || 0;
            const notes = container.querySelector('#mp-notes').value.trim();
            
            // For per_bed mode, require bed selection
            let bedIds = [];
            if (roomPricingMode === 'per_bed') {
                bedIds = getSelectedBedIds(container);
                if (bedIds.length === 0) {
                    results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">${escapeHtml(settings.i18n.bedRequired)}</div>`;
                    return;
                }
            }

            // Validate required fields
            if (!firstName || !lastName || !email || !checkIn || !checkOut) {
                results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">${escapeHtml(settings.i18n.formInvalid)}</div>`;
                return;
            }
            
            // Validate consent checkboxes
            const consentData = container.querySelector('#mp-consent-data');
            const consentTerms = container.querySelector('#mp-consent-terms');
            const consentError = container.querySelector('#mp-consent-error');
            
            if (!consentData || !consentTerms || !consentData.checked || !consentTerms.checked) {
                consentError.style.display = 'block';
                consentError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            consentError.style.display = 'none';
            
            // Get consent values
            const consents = {
                data_processing: consentData.checked,
                terms_accepted: consentTerms.checked,
                marketing: container.querySelector('#mp-consent-marketing')?.checked || false,
                ip_address: '', // Will be filled by server
                timestamp: new Date().toISOString(),
            };

            submitBtn.disabled = true;
            submitBtn.classList.add('disabled');
            results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--loading">${escapeHtml(settings.i18n.loading)}</div>`;

            try {
                const captchaToken = await getCaptchaToken(settings);

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
                        room_id: roomPricingMode === 'per_room' ? settings.roomId : undefined,
                        bed_ids: roomPricingMode === 'per_bed' ? bedIds : undefined,
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
                if (!response.ok || !data || !data.success) {
                    const msg = (data && data.message) ? data.message : settings.i18n.error;
                    throw new Error(msg);
                }

                results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--success">${escapeHtml(settings.i18n.success)}</div>`;
                hcaptchaToken = '';
            } catch (err) {
                results.innerHTML = `<div class="mp-booking-form__message mp-booking-form__message--error">${escapeHtml(err && err.message ? err.message : settings.i18n.error)}</div>`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.classList.remove('disabled');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        console.log('[MP Booking] DOMContentLoaded fired');

        const containers = document.querySelectorAll('.mp-booking-widget-container');
        console.log('[MP Booking] Found widget containers:', containers.length);

        // Initialize widget containers if present
        if (containers && containers.length > 0) {
            containers.forEach((container) => {
                console.log('[MP Booking] Initializing widget container:', container);
                setupWidget(container);
            });
        }

        // Handle modal open buttons (from booking cards)
        // This runs regardless of whether widget containers exist
        const modalOpenButtons = document.querySelectorAll('.mp-booking-open-modal');
        console.log('[MP Booking] Found modal open buttons:', modalOpenButtons.length);
        modalOpenButtons.forEach((btn) => {
            // Skip if already has handler
            if (btn.hasAttribute('data-mp-handler-attached')) {
                console.log('[MP Booking] Button already has handler, skipping:', btn);
                return;
            }
            
            console.log('[MP Booking] Attaching click handler to button:', btn);
            btn.setAttribute('data-mp-handler-attached', 'true');
            
            btn.addEventListener('click', function (e) {
                console.log('[MP Booking] Button clicked!', e);
                const roomId = parseInt(btn.getAttribute('data-room-id') || '0', 10);
                const roomName = btn.getAttribute('data-room-name') || '';

                console.log('[MP Booking] Room ID:', roomId, 'Room Name:', roomName);

                // Get dates and guest count from the card's mini form
                const cardWrapper = btn.closest('.mp-booking-room-card');
                console.log('[MP Booking] Card wrapper:', cardWrapper);
                const checkInInput = cardWrapper ? cardWrapper.querySelector('.mp-card-check-in[data-room-id="' + roomId + '"]') : null;
                const checkOutInput = cardWrapper ? cardWrapper.querySelector('.mp-card-check-out[data-room-id="' + roomId + '"]') : null;
                const adultsInput = cardWrapper ? cardWrapper.querySelector('.mp-card-adults[data-room-id="' + roomId + '"]') : null;
                const childrenInput = cardWrapper ? cardWrapper.querySelector('.mp-card-children[data-room-id="' + roomId + '"]') : null;

                const checkIn = checkInInput ? checkInInput.value : '';
                const checkOut = checkOutInput ? checkOutInput.value : '';
                const adults = adultsInput ? parseInt(adultsInput.value, 10) || 1 : 1;
                const children = childrenInput ? parseInt(childrenInput.value, 10) || 0 : 0;
                const totalGuests = adults + children;

                console.log('[MP Booking] Check-in:', checkIn, 'Check-out:', checkOut, 'Adults:', adults, 'Children:', children);
                console.log('[MP Booking] Inputs:', { checkInInput, checkOutInput, adultsInput, childrenInput });

                if (!checkIn || !checkOut) {
                    console.log('[MP Booking] Missing dates, showing alert');
                    alert('Proszę wybrać daty przyjazdu i wyjazdu.');
                    return;
                }
                
                console.log('[MP Booking] Dates OK, validating capacity...');
                
                // Validate room capacity BEFORE opening modal
                if (roomId > 0) {
                    // Fetch room info to check capacity
                    fetch(`${mpBookingData?.apiUrl || '/wp-json/mikroplaneta/v1'}/rooms/${roomId}`, {
                        headers: { 'X-WP-Nonce': mpBookingData?.nonce || '' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.data) {
                            const pricingMode = data.data.pricing_mode || 'per_bed';
                            let capacity = 0;

                            if (data.data.beds && Array.isArray(data.data.beds)) {
                                capacity = data.data.beds.reduce((sum, bed) => {
                                    const bedType = bed.bed_type || 'single';
                                    return sum + ((bedType === 'bunk') ? 2 : 1);
                                }, 0);
                            }

                            console.log('[MP Booking] Room capacity check:', { capacity, pricingMode, totalGuests });
                            
                            if (pricingMode === 'per_room' && capacity > 0 && totalGuests > capacity) {
                                alert(`Nieprawidłowa liczba osób!\n\nPokój "${data.data.name || ''}" mieści maksymalnie ${capacity} osoby.\n\nWybrano: ${totalGuests} osób (${adults} dorosłych + ${children} dzieci).`);
                                return;
                            }
                            
                            // Open modal if validation passes
                            console.log('[MP Booking] Opening modal...');
                            openModal(roomId, roomName, {
                                checkIn: checkIn,
                                checkOut: checkOut,
                                adults: adults,
                                children: children
                            });
                        }
                    })
                    .catch(err => {
                        console.error('[MP Booking] Failed to load room info:', err);
                        // Still open modal on error
                        openModal(roomId, roomName, {
                            checkIn: checkIn,
                            checkOut: checkOut,
                            adults: adults,
                            children: children
                        });
                    });
                } else {
                    // No room ID, just open modal
                    console.log('[MP Booking] Opening modal...');
                    openModal(roomId, roomName, {
                        checkIn: checkIn,
                        checkOut: checkOut,
                        adults: adults,
                        children: children
                    });
                }
            });
        });
        
        // Add validation to card inputs (adults/children)
        const cardAdultsInputs = document.querySelectorAll('.mp-card-adults');
        const cardChildrenInputs = document.querySelectorAll('.mp-card-children');
        
        cardAdultsInputs.forEach(input => {
            input.addEventListener('change', function() {
                const max = parseInt(this.getAttribute('max') || '99', 10);
                const value = parseInt(this.value || '1', 10);
                if (value > max) {
                    this.value = max;
                    alert(`Maksymalna liczba dorosłych to ${max}.`);
                }
            });
        });
        
        cardChildrenInputs.forEach(input => {
            input.addEventListener('change', function() {
                const max = parseInt(this.getAttribute('max') || '99', 10);
                const value = parseInt(this.value || '0', 10);
                const cardWrapper = this.closest('.mp-booking-room-card');
                const adultsInput = cardWrapper ? cardWrapper.querySelector('.mp-card-adults') : null;
                const adults = adultsInput ? parseInt(adultsInput.value || '1', 10) : 1;
                
                if (value + adults > max) {
                    this.value = Math.max(0, max - adults);
                    alert(`Maksymalna łączna liczba osób to ${max}.`);
                }
            });
        });
    });
    
    /**
     * Global modal instance (singleton)
     */
    let modalElement = null;
    let modalBody = null;

    /**
     * Get or create global modal
     */
    function getModal() {
        if (!modalElement) {
            // Create modal
            modalElement = document.createElement('div');
            modalElement.className = 'mp-booking-modal';
            modalElement.innerHTML = `
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
            modalElement.querySelector('.mp-modal-backdrop').addEventListener('click', closeModal);
            modalElement.querySelector('.mp-modal-close').addEventListener('click', closeModal);
            document.addEventListener('keydown', function handleEsc(e) {
                if (e.key === 'Escape' && modalElement && modalElement.style.display === 'flex') {
                    closeModal();
                    document.removeEventListener('keydown', handleEsc);
                }
            });

            document.body.appendChild(modalElement);
            modalBody = modalElement.querySelector('.mp-modal-body');
        }

        return { element: modalElement, body: modalBody };
    }

    /**
     * Open modal with widget
     */
    function openModal(roomId, roomName, prefillData) {
        const { element, body } = getModal();

        // Update room name
        element.querySelector('.mp-modal-room-name').textContent = roomName || 'Rezerwacja';

        // Create widget with prefill
        body.innerHTML = '<div class="mp-booking-widget-container mp-modal-widget" data-mp-settings="' + escapeHtml(JSON.stringify({
            roomId: roomId,
            roomName: roomName,
            title: '',
            prefill: prefillData
        })) + '"></div>';

        // Show modal
        element.style.display = 'flex';
        element.classList.add('mp-modal-open');
        document.body.style.overflow = 'hidden';

        // Initialize widget
        const widgetContainer = body.querySelector('.mp-booking-widget-container');
        if (widgetContainer) {
            setupWidget(widgetContainer);
        }
    }

    /**
     * Close modal
     */
    function closeModal() {
        if (modalElement) {
            modalElement.style.display = 'none';
            modalElement.classList.remove('mp-modal-open');
            document.body.style.overflow = '';
            modalElement.querySelector('.mp-modal-body').innerHTML = '';
        }
    }

    // Export setupWidget for use in room-card-modal.js
    window.setupWidget = setupWidget;
    
    // Only initialize if this is the main widget (not loaded as dependency)
    // Check if simple-widget is present - if so, don't auto-initialize
    if (typeof window.setupSimpleWidget === 'undefined') {
        // Old widget initialization code here if needed
    }
})();
