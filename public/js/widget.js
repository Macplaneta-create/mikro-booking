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
            bedsContainer.innerHTML = `<div style="padding:8px; font-size:13px; color:#6b7280;">${escapeHtml(settings.i18n.noBeds)}</div>`;
            return;
        }

        bedsContainer.innerHTML = beds.map((bed) => {
            const label = `#${bed.id} • Pokój ${bed.room_id} • Łóżko ${bed.bed_number} (${bed.bed_type})`;
            return `
                <label style="display:flex; align-items:center; gap:8px; padding:6px 0; font-size:13px; color:#374151;">
                    <input type="checkbox" name="mp-bed-checkbox" value="${bed.id}" />
                    <span>${escapeHtml(label)}</span>
                </label>
            `;
        }).join('');
    }

    async function fetchAvailableBeds(settings, checkIn, checkOut) {
        let url = `${settings.apiUrl}/public/availability/beds?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`;
        if (settings.roomId && Number(settings.roomId) > 0) {
            url += `&room_id=${encodeURIComponent(String(settings.roomId))}`;
        }
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        });

        const data = await response.json();
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

        container.innerHTML = `
            <div class="mp-booking-form-wrapper" style="max-width: 680px; margin: 20px auto; padding: 25px; background: white; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f0f0f0;">
                <h3 style="margin-top:0; color: #111827; font-size: 1.25rem; font-weight: 700; margin-bottom: 20px; text-align:center;">${escapeHtml(settings.title || settings.i18n.submit)}</h3>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${escapeHtml(settings.i18n.firstName)} *</label>
                        <input type="text" id="mp-first-name" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px;" />
                    </div>
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${escapeHtml(settings.i18n.lastName)} *</label>
                        <input type="text" id="mp-last-name" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px;" />
                    </div>
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${escapeHtml(settings.i18n.email)} *</label>
                        <input type="email" id="mp-email" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px;" />
                    </div>
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${escapeHtml(settings.i18n.phone)}</label>
                        <input type="text" id="mp-phone" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px;" />
                    </div>
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${escapeHtml(settings.i18n.checkIn)} *</label>
                        <input type="date" id="mp-check-in" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px;" />
                    </div>
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${escapeHtml(settings.i18n.checkOut)} *</label>
                        <input type="date" id="mp-check-out" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px;" />
                    </div>
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${escapeHtml(settings.i18n.adults)}</label>
                        <input type="number" id="mp-adults" min="1" value="1" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px;" />
                    </div>
                    <div>
                        <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${escapeHtml(settings.i18n.children)}</label>
                        <input type="number" id="mp-children" min="0" value="0" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px;" />
                    </div>
                </div>
                <div style="margin-top:12px; border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <label style="font-size: 12px; font-weight: 600; color: #6b7280;">${escapeHtml(settings.i18n.availableBeds)} *</label>
                        <div style="display:flex; gap:6px;">
                            <button type="button" id="mp-suggest-beds-btn" style="background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3; padding:6px 10px; border-radius:6px; font-size:12px; cursor:pointer;">
                                ${escapeHtml(settings.i18n.suggestBeds)}
                            </button>
                            <button type="button" id="mp-find-beds-btn" style="background:#f3f4f6; border:1px solid #e5e7eb; color:#111827; padding:6px 10px; border-radius:6px; font-size:12px; cursor:pointer;">
                                ${escapeHtml(settings.i18n.findBeds)}
                            </button>
                        </div>
                    </div>
                    <div id="mp-beds-list" style="margin-top:8px; max-height:180px; overflow:auto;"></div>
                    <div id="mp-beds-summary" style="margin-top:8px; font-size:12px; color:#6b7280;"></div>
                </div>
                <div style="margin-top:12px;">
                    <label style="display:block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;">${escapeHtml(settings.i18n.notes)}</label>
                    <textarea id="mp-notes" rows="3" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px;"></textarea>
                </div>
                <div id="mp-hcaptcha-wrap" style="margin-top:12px; ${isHcaptcha ? '' : 'display:none;'}">
                    <div class="h-captcha"
                         data-sitekey="${escapeHtml(settings.captcha.hcaptchaSiteKey || '')}"
                         data-callback="mpHcaptchaDone"
                         data-expired-callback="mpHcaptchaExpired"></div>
                </div>
                <button id="mp-submit-btn" type="button" style="margin-top:14px; width:100%; background:#2563eb; color:white; border:none; padding:12px; border-radius:8px; font-weight:600; cursor:pointer;">
                    ${escapeHtml(settings.i18n.submit)}
                </button>
                <div id="mp-results-container" style="margin-top:16px;"></div>
            </div>
        `;

        const submitBtn = container.querySelector('#mp-submit-btn');
        const findBedsBtn = container.querySelector('#mp-find-beds-btn');
        const suggestBedsBtn = container.querySelector('#mp-suggest-beds-btn');
        const results = container.querySelector('#mp-results-container');

        findBedsBtn.addEventListener('click', async function () {
            const checkIn = container.querySelector('#mp-check-in').value;
            const checkOut = container.querySelector('#mp-check-out').value;
            if (!checkIn || !checkOut) {
                results.innerHTML = `<div style="padding:12px; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; color:#991b1b;">${escapeHtml(settings.i18n.formInvalid)}</div>`;
                return;
            }

            findBedsBtn.disabled = true;
            results.innerHTML = `<div style="padding:12px; color:#6b7280;">${escapeHtml(settings.i18n.loading)}</div>`;
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
                    ? `<div style="padding:10px; background:#eff6ff; border:1px solid #dbeafe; border-radius:8px; color:#1e3a8a;">${escapeHtml(settings.i18n.suggestedBeds)}</div>`
                    : '';
            } catch (err) {
                results.innerHTML = `<div style="padding:12px; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; color:#991b1b;">${escapeHtml(err && err.message ? err.message : settings.i18n.error)}</div>`;
            } finally {
                findBedsBtn.disabled = false;
            }
        });

        suggestBedsBtn.addEventListener('click', function () {
            const selected = suggestBedsSelection(container, availableBeds);
            updateSelectionSummary(container, settings, availableBeds);
            if (selected.length > 0) {
                results.innerHTML = `<div style="padding:10px; background:#eff6ff; border:1px solid #dbeafe; border-radius:8px; color:#1e3a8a;">${escapeHtml(settings.i18n.suggestedBeds)}</div>`;
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
            const bedIds = getSelectedBedIds(container);

            if (!firstName || !lastName || !email || !checkIn || !checkOut) {
                results.innerHTML = `<div style="padding:12px; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; color:#991b1b;">${escapeHtml(settings.i18n.formInvalid)}</div>`;
                return;
            }
            if (bedIds.length === 0) {
                results.innerHTML = `<div style="padding:12px; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; color:#991b1b;">${escapeHtml(settings.i18n.bedRequired)}</div>`;
                return;
            }

            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
            results.innerHTML = `<div style="padding:12px; color:#6b7280;">${escapeHtml(settings.i18n.loading)}</div>`;

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
                        bed_ids: bedIds,
                        check_in: checkIn,
                        check_out: checkOut,
                        adults: adults,
                        children: children,
                        notes: notes,
                        captcha_token: captchaToken,
                    }),
                });

                const data = await response.json();
                if (!response.ok || !data || !data.success) {
                    const msg = (data && data.message) ? data.message : settings.i18n.error;
                    throw new Error(msg);
                }

                results.innerHTML = `<div style="padding:12px; background:#ecfdf5; border:1px solid #d1fae5; border-radius:8px; color:#065f46;">${escapeHtml(settings.i18n.success)}</div>`;
                hcaptchaToken = '';
            } catch (err) {
                results.innerHTML = `<div style="padding:12px; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; color:#991b1b;">${escapeHtml(err && err.message ? err.message : settings.i18n.error)}</div>`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const containers = document.querySelectorAll('.mp-booking-widget-container');
        if (!containers || containers.length === 0) return;

        containers.forEach((container) => setupWidget(container));

        const jumpButtons = document.querySelectorAll('.mp-booking-open-widget');
        jumpButtons.forEach((btn) => {
            btn.addEventListener('click', function () {
                const roomId = parseInt(btn.getAttribute('data-room-id') || '0', 10);
                const target = Array.from(document.querySelectorAll('.mp-booking-widget-container')).find((node) => {
                    try {
                        const cfg = JSON.parse(node.getAttribute('data-mp-settings') || '{}');
                        return parseInt(cfg.roomId || '0', 10) === roomId;
                    } catch (e) {
                        return false;
                    }
                });

                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    });
})();
