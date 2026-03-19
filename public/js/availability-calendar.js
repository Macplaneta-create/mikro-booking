(function() {
    'use strict';

    function parseLocalSettings(container) {
        try {
            var raw = container.getAttribute('data-mp-settings') || '{}';
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function toIsoDate(date) {
        return date.toISOString().split('T')[0];
    }

    function parseIsoDate(value) {
        var date = new Date(value + 'T00:00:00');
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        return date;
    }

    function addDays(date, days) {
        var next = new Date(date.getTime());
        next.setDate(next.getDate() + days);
        return next;
    }

    function daysInMonth(year, monthZeroBased) {
        return new Date(year, monthZeroBased + 1, 0).getDate();
    }

    function formatMonthValue(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        return y + '-' + m;
    }

    function bedCapacity(bed) {
        var explicit = Number(bed && typeof bed.available_places !== 'undefined' ? bed.available_places : bed && typeof bed.capacity !== 'undefined' ? bed.capacity : 0);
        if (!Number.isNaN(explicit) && explicit > 0) {
            return explicit;
        }

        return (bed && bed.bed_type === 'bunk') ? 2 : 1;
    }

    function openBookingModal(container, settings, room, checkIn, checkOut) {
        var modal = container.querySelector('.mp-availability-widget__modal');
        var modalBody = container.querySelector('.mp-availability-widget__modal-body');

        if (!modal || !modalBody) {
            return;
        }

        modalBody.innerHTML = '';

        var bookingContainer = document.createElement('div');
        bookingContainer.className = 'mp-booking-widget-container';
        bookingContainer.setAttribute('data-mp-settings', encodeURIComponent(JSON.stringify({
            roomId: room.id,
            title: (settings.bookingTitle || 'Rezerwacja') + ': ' + room.name,
            prefill: {
                checkIn: checkIn,
                checkOut: checkOut,
                adults: 1,
                children: 0
            }
        })));

        modalBody.appendChild(bookingContainer);
        modal.style.display = 'flex';

        if (typeof window.setupSimpleWidget === 'function') {
            window.setupSimpleWidget(bookingContainer);
        } else {
            bookingContainer.innerHTML = '<p>Nie udało się załadować formularza rezerwacji. Odśwież stronę.</p>';
        }
    }

    function closeBookingModal(container) {
        var modal = container.querySelector('.mp-availability-widget__modal');
        var modalBody = container.querySelector('.mp-availability-widget__modal-body');

        if (modal) {
            modal.style.display = 'none';
        }
        if (modalBody) {
            modalBody.innerHTML = '';
        }
    }

    function setMessage(container, type, message) {
        var el = container.querySelector('.mp-availability-widget__message');
        if (!el) {
            return;
        }

        el.className = 'mp-availability-widget__message mp-availability-widget__message--' + type;
        el.textContent = message || '';
    }

    function renderBase(container, settings) {
        var now = new Date();
        var nextNight = addDays(now, 1);

        container.innerHTML = [
            '<div class="mp-availability-widget">',
            '  <div class="mp-availability-widget__header">',
            '    <h3 class="mp-availability-widget__title">' + escapeHtml(settings.title || 'Kalendarz dostępności') + '</h3>',
            '    <p class="mp-availability-widget__subtitle">Kalendarz miesięczny z liczbą wolnych miejsc (np. 3/7) oraz szybkie Rezerwuj.</p>',
            '  </div>',
            '  <div class="mp-availability-widget__filters">',
            '    <label>Miesiąc',
            '      <input type="month" class="mp-availability-widget__month" value="' + formatMonthValue(now) + '">',
            '    </label>',
            '    <label>Domyślny przyjazd',
            '      <input type="date" class="mp-availability-widget__checkin" value="' + toIsoDate(now) + '">',
            '    </label>',
            '    <label>Domyślny wyjazd',
            '      <input type="date" class="mp-availability-widget__checkout" value="' + toIsoDate(nextNight) + '">',
            '    </label>',
            '    <button type="button" class="mp-availability-widget__search">Odśwież kalendarz</button>',
            '  </div>',
            '  <div class="mp-availability-widget__message"></div>',
            '  <div class="mp-availability-widget__calendar-wrap">',
            '    <div class="mp-availability-widget__calendar"></div>',
            '  </div>',
            '  <div class="mp-availability-widget__legend">',
            '    <span><i class="legend-ok"></i> są wolne miejsca</span>',
            '    <span><i class="legend-low"></i> mała dostępność</span>',
            '    <span><i class="legend-full"></i> brak miejsc</span>',
            '  </div>',
            '  <div class="mp-availability-widget__modal" style="display:none;">',
            '    <div class="mp-availability-widget__modal-panel">',
            '      <button type="button" class="mp-availability-widget__modal-close" aria-label="Zamknij">×</button>',
            '      <div class="mp-availability-widget__modal-body"></div>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('');
    }

    async function fetchAvailableBeds(settings, checkIn, checkOut) {
        var apiUrl = String(settings.apiUrl || '').replace(/\/+$/, '');
        if (!apiUrl) {
            throw new Error('Brak konfiguracji API.');
        }

        var url = apiUrl + '/public/availability/beds?check_in=' + encodeURIComponent(checkIn) + '&check_out=' + encodeURIComponent(checkOut);
        var response = await fetch(url, {
            headers: {
                'X-WP-Nonce': settings.nonce || ''
            }
        });

        var data = await response.json();
        if (!response.ok || !data || !data.success) {
            throw new Error((data && data.message) ? data.message : 'Nie udało się pobrać danych dostępności.');
        }

        return Array.isArray(data.data) ? data.data : [];
    }

    async function fetchMonthAvailability(settings, monthValue) {
        var parts = (monthValue || '').split('-');
        if (parts.length !== 2) {
            throw new Error('Nieprawidłowy miesiąc.');
        }

        var year = Number(parts[0]);
        var monthIndex = Number(parts[1]) - 1;
        if (Number.isNaN(year) || Number.isNaN(monthIndex) || monthIndex < 0 || monthIndex > 11) {
            throw new Error('Nieprawidłowy miesiąc.');
        }

        var totalDays = daysInMonth(year, monthIndex);
        var dayNumbers = [];
        var availabilityByRoom = {};

        var dayPromises = [];
        for (var day = 1; day <= totalDays; day++) {
            dayNumbers.push(day);
            var checkIn = new Date(year, monthIndex, day);
            var checkOut = addDays(checkIn, 1);
            dayPromises.push((function(dayNum, inDate, outDate) {
                return fetchAvailableBeds(settings, toIsoDate(inDate), toIsoDate(outDate)).then(function(beds) {
                    return {
                        day: dayNum,
                        beds: beds
                    };
                });
            })(day, checkIn, checkOut));
        }

        var dayResults = await Promise.all(dayPromises);
        dayResults.forEach(function(result) {
            var perRoom = {};
            (result.beds || []).forEach(function(bed) {
                var roomId = Number(bed.room_id) || 0;
                if (!perRoom[roomId]) {
                    perRoom[roomId] = {
                        free_places: 0,
                        free_beds: 0
                    };
                }
                perRoom[roomId].free_places += Number(bed.available_places) || bedCapacity(bed);
                perRoom[roomId].free_beds += 1;
            });

            Object.keys(perRoom).forEach(function(roomIdKey) {
                if (!availabilityByRoom[roomIdKey]) {
                    availabilityByRoom[roomIdKey] = {};
                }
                availabilityByRoom[roomIdKey][result.day] = perRoom[roomIdKey];
            });
        });

        return {
            year: year,
            monthIndex: monthIndex,
            dayNumbers: dayNumbers,
            availabilityByRoom: availabilityByRoom
        };
    }

    function cellClass(freePlaces, totalPlaces) {
        if (freePlaces <= 0) {
            return 'cell-full';
        }
        if (totalPlaces > 0 && freePlaces / totalPlaces <= 0.3) {
            return 'cell-low';
        }
        return 'cell-ok';
    }

    function renderCalendar(container, settings, monthData, checkIn, checkOut) {
        var wrap = container.querySelector('.mp-availability-widget__calendar');
        var rooms = Array.isArray(settings.rooms) ? settings.rooms : [];

        if (!wrap) {
            return;
        }
        if (rooms.length === 0) {
            wrap.innerHTML = '<p class="mp-availability-widget__empty">Brak aktywnych pokoi/domków.</p>';
            return;
        }

        var headCells = monthData.dayNumbers.map(function(day) {
            return '<th>' + day + '</th>';
        }).join('');

        var bodyRows = rooms.map(function(room) {
            var totalPlaces = Number(room.total_places) || 0;
            var roomAvailability = monthData.availabilityByRoom[String(room.id)] || {};

            var dayCells = monthData.dayNumbers.map(function(day) {
                var stats = roomAvailability[day] || { free_places: 0, free_beds: 0 };
                var freePlaces = Number(stats.free_places) || 0;
                var klass = cellClass(freePlaces, totalPlaces);
                return '<td class="' + klass + '" title="Wolne miejsca: ' + freePlaces + '/' + totalPlaces + '">' + freePlaces + '/' + totalPlaces + '</td>';
            }).join('');

            return [
                '<tr>',
                '  <th>',
                '    <div class="room-name">' + escapeHtml(room.name || 'Pokój') + '</div>',
                '    <div class="room-meta">' + escapeHtml(room.room_type_label || 'Pokój') + '</div>',
                '    <button type="button" class="mp-availability-inline-reserve" data-room-id="' + room.id + '">Rezerwuj</button>',
                '  </th>',
                dayCells,
                '</tr>'
            ].join('');
        }).join('');

        wrap.innerHTML = [
            '<table class="mp-availability-table">',
            '  <thead>',
            '    <tr>',
            '      <th>Pokój / Domek</th>',
            headCells,
            '    </tr>',
            '  </thead>',
            '  <tbody>',
            bodyRows,
            '  </tbody>',
            '</table>'
        ].join('');

        Array.prototype.forEach.call(wrap.querySelectorAll('.mp-availability-inline-reserve'), function(btn) {
            btn.addEventListener('click', function() {
                var roomId = Number(btn.getAttribute('data-room-id')) || 0;
                var room = rooms.find(function(item) {
                    return Number(item.id) === roomId;
                });

                if (!room) {
                    return;
                }

                openBookingModal(container, settings, room, checkIn, checkOut);
            });
        });
    }

    function safeDefaultDates(container) {
        var checkInInput = container.querySelector('.mp-availability-widget__checkin');
        var checkOutInput = container.querySelector('.mp-availability-widget__checkout');
        var inDate = parseIsoDate(checkInInput.value);
        var outDate = parseIsoDate(checkOutInput.value);

        if (!inDate) {
            inDate = new Date();
            checkInInput.value = toIsoDate(inDate);
        }
        if (!outDate || outDate <= inDate) {
            outDate = addDays(inDate, 1);
            checkOutInput.value = toIsoDate(outDate);
        }

        return {
            checkIn: toIsoDate(inDate),
            checkOut: toIsoDate(outDate)
        };
    }

    function setupAvailabilityWidget(container) {
        var localSettings = parseLocalSettings(container);
        var globalSettings = (typeof window.mpBookingData !== 'undefined') ? window.mpBookingData : {};
        var settings = Object.assign({}, globalSettings, localSettings);

        renderBase(container, settings);

        var monthInput = container.querySelector('.mp-availability-widget__month');
        var searchBtn = container.querySelector('.mp-availability-widget__search');
        var modal = container.querySelector('.mp-availability-widget__modal');
        var closeBtn = container.querySelector('.mp-availability-widget__modal-close');

        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeBookingModal(container);
            });
        }

        if (modal) {
            modal.addEventListener('click', function(ev) {
                if (ev.target === modal) {
                    closeBookingModal(container);
                }
            });
        }

        async function refreshCalendar() {
            var selectedMonth = monthInput.value;
            if (!selectedMonth) {
                setMessage(container, 'error', 'Wybierz miesiąc.');
                return;
            }

            var defaults = safeDefaultDates(container);
            setMessage(container, 'loading', 'Ładowanie kalendarza dostępności...');

            try {
                var monthData = await fetchMonthAvailability(settings, selectedMonth);
                renderCalendar(container, settings, monthData, defaults.checkIn, defaults.checkOut);
                setMessage(container, 'ok', 'Kalendarz został zaktualizowany. Kliknij Rezerwuj przy wybranym pokoju.');
            } catch (error) {
                setMessage(container, 'error', (error && error.message) ? error.message : 'Nie udało się pobrać kalendarza dostępności.');
            }
        }

        searchBtn.addEventListener('click', refreshCalendar);
        monthInput.addEventListener('change', refreshCalendar);

        refreshCalendar();
    }

    document.addEventListener('DOMContentLoaded', function() {
        var widgets = document.querySelectorAll('.mp-availability-widget-container');
        widgets.forEach(function(widget) {
            setupAvailabilityWidget(widget);
        });
    });
})();
