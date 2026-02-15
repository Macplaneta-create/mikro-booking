import React, { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight, BedDouble, Loader2, ChevronDown, ChevronRight as ChevronRightIcon, Home, Check, X, History, CreditCard } from 'lucide-react';
import { format, addDays, startOfWeek, addWeeks, subWeeks, isSameDay, parseISO, differenceInDays, isAfter, isBefore } from 'date-fns';
import { pl } from 'date-fns/locale';
import { RoomsAPI, ReservationsAPI, Room, Reservation } from '../services/api';
import ReservationModal from './ReservationModal';
import BookingHistory from './BookingHistory';

const CalendarView: React.FC = () => {
    const [currentDate, setCurrentDate] = useState(new Date());
    const [rooms, setRooms] = useState<Room[]>([]);
    const [bookings, setBookings] = useState<Reservation[]>([]);
    const [loading, setLoading] = useState(true);
    const [expandedRooms, setExpandedRooms] = useState<Record<number, boolean>>({});

    // Selection state
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalData, setModalData] = useState<{ bedId?: number, bedIds?: number[], roomId?: number, checkIn?: string, checkOut?: string }>({});

    const [selection, setSelection] = useState<{ bedId: number, roomId: number, start: Date | null, end: Date | null } | null>(null);
    const [selectedBeds, setSelectedBeds] = useState<Set<number>>(new Set());

    // Reservation details modal
    const [selectedReservation, setSelectedReservation] = useState<Reservation | null>(null);
    const [isDetailsModalOpen, setIsDetailsModalOpen] = useState(false);
    const [showHistory, setShowHistory] = useState(false);

    const startDate = startOfWeek(currentDate, { weekStartsOn: 1 });
    const days = Array.from({ length: 14 }).map((_, i) => addDays(startDate, i));
    const endDate = days[days.length - 1];

    const getVisibleStats = () => {
        const visibleBookings = bookings.filter((booking) => {
            if (booking.status === 'cancelled') return false;
            const checkIn = parseISO(booking.check_in);
            const checkOut = parseISO(booking.check_out);
            return checkOut > startDate && checkIn <= endDate;
        });

        const reservationIds = new Set<number>();
        const bedIds = new Set<number>();

        visibleBookings.forEach((booking) => {
            if (booking.id) reservationIds.add(booking.id);
            if (booking.bed_id) bedIds.add(booking.bed_id);
        });

        return {
            reservations: reservationIds.size,
            beds: bedIds.size
        };
    };

    const getVisibleStatusLegend = () => {
        const visibleBookings = bookings.filter((booking) => {
            if (booking.status === 'cancelled') return false;
            const checkIn = parseISO(booking.check_in);
            const checkOut = parseISO(booking.check_out);
            return checkOut > startDate && checkIn <= endDate;
        });

        const statusCounts = {
            confirmed: 0,
            pending: 0,
            checked_in: 0,
            checked_out: 0,
            cancelled: 0
        };

        visibleBookings.forEach((booking) => {
            if (booking.status && booking.status in statusCounts) {
                statusCounts[booking.status as keyof typeof statusCounts]++;
            }
        });

        return Object.entries(statusCounts)
            .filter(([_, count]) => count > 0)
            .map(([status, count]) => ({
                status,
                count,
                color: getStatusColor(status),
                label: getStatusLabel(status)
            }));
    };

    const fetchData = async () => {
        setLoading(true);
        try {
            const roomsData = await RoomsAPI.getAll();
            const roomsWithBeds = await Promise.all(roomsData.map(async (room) => {
                if (room.id) {
                    const beds = await RoomsAPI.getBeds(room.id);
                    return { ...room, beds };
                }
                return room;
            }));
            setRooms(roomsWithBeds);

            if (Object.keys(expandedRooms).length === 0) {
                const initialExpanded: Record<number, boolean> = {};
                roomsWithBeds.slice(0, 3).forEach(r => { if (r.id) initialExpanded[r.id] = true; });
                setExpandedRooms(initialExpanded);
            }

            const reservations = await ReservationsAPI.getAll({
                check_in_from: format(subWeeks(startDate, 1), 'yyyy-MM-dd'),
                check_out_to: format(addWeeks(endDate, 1), 'yyyy-MM-dd'),
                limit: 300
            });
            setBookings(reservations);
        } catch (error) {
            console.error("Failed to fetch calendar data", error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [currentDate]);

    const handleNext = () => setCurrentDate(prev => addWeeks(prev, 1));
    const handlePrev = () => setCurrentDate(prev => subWeeks(prev, 1));

    const toggleRoom = (roomId: number) => {
        setExpandedRooms(prev => ({
            ...prev,
            [roomId]: !prev[roomId]
        }));
    };

    // --- MULTI-BED SELECTION LOGIC ---
    const handleCellClick = (bedId: number, roomId: number, date: Date, event: React.MouseEvent) => {
        // Turnover-aware occupancy check
        const isOccupied = bookings.some(b => {
            if (b.bed_id !== bedId || b.status === 'cancelled') return false;
            const bIn = parseISO(b.check_in);
            const bOut = parseISO(b.check_out);

            if (!selection) {
                // Starting selection: block if the night starting on this day is occupied
                // A night is occupied if date >= check_in AND date < check_out
                return !isBefore(date, bIn) && isBefore(date, bOut);
            } else {
                // Ending selection: block if our range overlaps existing nights
                // Our nights: [selection.start, date-1]. Existing nights: [bIn, bOut-1]
                return isBefore(selection.start!, bOut) && isAfter(date, bIn);
            }
        });

        if (isOccupied) return;

        // Ctrl+Click for multi-bed selection
        if (event.ctrlKey || event.metaKey) {
            if (!selection || !selection.start || !selection.end) {
                // Need to have a date range first
                return;
            }

            // Toggle bed in selection
            const newSelectedBeds = new Set(selectedBeds);
            if (newSelectedBeds.has(bedId)) {
                newSelectedBeds.delete(bedId);
            } else {
                // Verify this bed is available for the selected date range
                const isAvailable = !bookings.some(b =>
                    b.bed_id === bedId &&
                    b.status !== 'cancelled' &&
                    (parseISO(b.check_in) < selection.end! && parseISO(b.check_out) > selection.start!)
                );

                if (isAvailable) {
                    newSelectedBeds.add(bedId);
                } else {
                    alert(`Łóżko #${bedId} nie jest dostępne w wybranym terminie`);
                    return;
                }
            }
            setSelectedBeds(newSelectedBeds);
            return;
        }

        // Normal single-bed date selection
        if (!selection || selection.bedId !== bedId) {
            // Start new selection
            setSelectedBeds(new Set());
            setSelection({ bedId, roomId, start: date, end: null });
        } else if (selection.start && !selection.end) {
            // Set end date
            if (isSameDay(date, selection.start)) {
                setSelection(null);
                setSelectedBeds(new Set());
            } else if (isBefore(date, selection.start)) {
                setSelection({ ...selection, start: date, end: null });
            } else {
                setSelection({ ...selection, end: date });
                setSelectedBeds(new Set([bedId]));
            }
        } else {
            setSelectedBeds(new Set());
            setSelection({ bedId, roomId, start: date, end: null });
        }
    };

    const handleConfirmSelection = () => {
        if (!selection || !selection.start || !selection.end) return;

        const bedIds = Array.from(selectedBeds);

        setModalData({
            bedId: selection.bedId,
            bedIds: bedIds.length > 0 ? bedIds : undefined,
            roomId: selection.roomId,
            checkIn: format(selection.start, 'yyyy-MM-dd'),
            checkOut: format(selection.end, 'yyyy-MM-dd')
        });
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setSelection(null);
        setModalData({});
    };


    const getBookingSlices = (booking: Reservation) => {
        const checkIn = parseISO(booking.check_in);
        const checkOut = parseISO(booking.check_out);
        const slices = [];

        // Determine which days (indices 0-13) this reservation covers
        for (let i = 0; i < 14; i++) {
            const day = addDays(startDate, i);
            const isStartDay = isSameDay(day, checkIn);
            const isEndDay = isSameDay(day, checkOut);
            const isMiddle = isAfter(day, checkIn) && isBefore(day, checkOut);

            if (!isStartDay && !isEndDay && !isMiddle) continue;

            // Visual boundaries within the day segment (0.0 to 1.0)
            let startOfSlice = 0;
            let endOfSlice = 1;

            if (isStartDay) startOfSlice = 0.6; // Arrives afternoon
            if (isEndDay) endOfSlice = 0.4;     // Leaves morning

            // If a booking starts and ends on the same day (rare/illegal in nights system)
            // our logic would give a negative or zero width. Skip or handle.
            if (isStartDay && isEndDay) continue;

            const left = (i + startOfSlice) * (100 / 14);
            const width = (endOfSlice - startOfSlice) * (100 / 14);

            if (width <= 0) continue;

            const statusClass = booking.status === 'cancelled'
                ? 'opacity-40'
                : booking.status === 'checked_out'
                    ? 'opacity-70'
                    : booking.status === 'checked_in'
                        ? 'ring-2 ring-emerald-700'
                        : booking.status === 'pending'
                            ? 'animate-pulse'
                            : booking.status === 'selecting'
                                ? 'ring-2 ring-brand-700 shadow-lg'
                                : 'ring-1 ring-white/40';

            const colorClass = getStatusColor(booking.status);

            // Rounded corners only for the very start and very end of the whole trip
            const isFirstDayOfBooking = isStartDay;
            const isLastDayOfBooking = isEndDay;

            slices.push({
                key: `${booking.id}-${i}`,
                style: { left: `${left}%`, width: `${width}%` },
                className: `absolute top-1 bottom-1 flex items-center text-[10px] text-white shadow-sm cursor-pointer hover:brightness-110 transition z-10 overflow-hidden ${colorClass} ${statusClass} ${isFirstDayOfBooking ? 'rounded-l-sm' : ''} ${isLastDayOfBooking ? 'rounded-r-sm' : ''}`,
                booking,
                showLabel: isFirstDayOfBooking || (i === 0 && isMiddle)
            });
        }
        return slices;
    };

    const statusColors = {
        confirmed: 'bg-green-500',
        pending: 'bg-amber-500',
        checked_in: 'bg-emerald-600',
        checked_out: 'bg-gray-500',
        cancelled: 'bg-gray-400'
    };

    const statusLabels = {
        confirmed: 'Potwierdzona',
        pending: 'Oczekująca',
        checked_in: 'Zalogowana',
        checked_out: 'Wylogowana',
        cancelled: 'Anulowana'
    };

    const getStatusColor = (status: string | undefined): string => {
        if (!status || !statusColors[status as keyof typeof statusColors]) {
            return 'bg-blue-500';
        }
        return statusColors[status as keyof typeof statusColors];
    };

    const getStatusLabel = (status: string): string => {
        if (!statusLabels[status as keyof typeof statusLabels]) {
            return status;
        }
        return statusLabels[status as keyof typeof statusLabels];
    };

    const getRoomBookingStyle = (check_in: string, check_out: string) => {
        const checkIn = parseISO(check_in);
        const checkOut = parseISO(check_out);

        const isStartVisible = !isBefore(checkIn, startDate);

        const startDayIndex = differenceInDays(checkIn, startDate);
        const endDayIndex = differenceInDays(checkOut, startDate);

        let left = (Math.max(0, startDayIndex) + (isStartVisible ? 0.6 : 0)) * (100 / 14);

        const rightEdgeIndex = Math.min(14, endDayIndex + 0.4);
        const leftEdgeIndex = Math.max(0, startDayIndex + (isStartVisible ? 0.6 : 0));

        let width = (rightEdgeIndex - leftEdgeIndex) * (100 / 14);

        if (isBefore(checkOut, startDate) || isSameDay(checkOut, startDate) || isAfter(checkIn, endDate)) return { display: 'none' } as const;

        return {
            left: `${left}%`,
            width: `${width}%`
        };
    };

    const getRoomReservations = (room: Room): Reservation[] => {
        const bedIds = new Set((room.beds || []).map(b => b.id).filter(Boolean) as number[]);
        const unique = new Map<number, Reservation>();

        bookings.forEach((booking) => {
            if (!bedIds.has(booking.bed_id) || booking.status === 'cancelled') {
                return;
            }

            if (booking.id && !unique.has(booking.id)) {
                unique.set(booking.id, booking);
            }
        });

        return Array.from(unique.values());
    };

    const buildRoomLanes = (reservations: Reservation[]) => {
        const sorted = [...reservations].sort((a, b) =>
            parseISO(a.check_in).getTime() - parseISO(b.check_in).getTime()
        );

        const lanes: { end: Date; items: Reservation[] }[] = [];

        sorted.forEach((reservation) => {
            const start = parseISO(reservation.check_in);
            const end = parseISO(reservation.check_out);

            let placed = false;
            for (const lane of lanes) {
                if (end <= lane.end || start >= lane.end) {
                    if (start >= lane.end) {
                        lane.items.push(reservation);
                        lane.end = end;
                        placed = true;
                        break;
                    }
                }
            }

            if (!placed) {
                lanes.push({ end, items: [reservation] });
            }
        });

        return lanes;
    };

    if (loading && rooms.length === 0) {
        return <div className="p-20 flex justify-center h-full items-center"><Loader2 className="animate-spin text-brand-600" /></div>;
    }

    return (
        <div className="flex flex-col h-[calc(100vh-140px)] bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden select-none">
            {/* Toolbar */}
            <div className="flex items-center justify-between p-4 border-b border-gray-100 bg-white z-30">
                <div className="flex items-center gap-4">
                    <h2 className="text-xl font-bold text-gray-800 capitalize">
                        {format(currentDate, 'LLLL yyyy', { locale: pl })}
                    </h2>
                    <div className="flex bg-gray-100 rounded-lg p-1">
                        <button onClick={handlePrev} className="p-1 hover:bg-white rounded-md shadow-sm transition"><ChevronLeft size={20} /></button>
                        <button onClick={() => setCurrentDate(new Date())} className="px-3 text-sm font-medium">Dziś</button>
                        <button onClick={handleNext} className="p-1 hover:bg-white rounded-md shadow-sm transition"><ChevronRight size={20} /></button>
                    </div>
                </div>

                <div className="flex flex-col gap-2">
                    <div className="flex gap-3 text-xs text-brand-700 font-medium bg-brand-50 px-3 py-1.5 rounded-full border border-brand-100">
                        <span className="flex items-center gap-2">✨ Kliknij datę przyjazdu i wyjazdu | Ctrl+klik aby dodać więcej łóżek (rezerwacja grupowa)</span>
                        <span className="text-brand-300">•</span>
                        <span>
                            {`Rezerwacje: ${getVisibleStats().reservations} • Zajęte łóżka: ${getVisibleStats().beds} `}
                        </span>
                    </div>
                    {getVisibleStatusLegend().length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 text-[11px] text-gray-600">
                            <span className="font-bold text-gray-500 uppercase tracking-wide">Status rezerwacji:</span>
                            {getVisibleStatusLegend().map((statusItem) => (
                                <span key={statusItem.status} className="flex items-center gap-1.5 bg-gray-50 border border-gray-100 rounded-full px-2 py-0.5">
                                    <span className={`w-2.5 h-2.5 rounded-full ${statusItem.color}`} />
                                    <span className="truncate">{statusItem.label} ({statusItem.count})</span>
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex gap-2">
                    <button
                        onClick={() => { setModalData({}); setIsModalOpen(true); }}
                        className="bg-brand-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-700 transition shadow-md"
                    >
                        + Rezerwacja
                    </button>
                </div>
            </div>

            <div className="flex-1 overflow-auto relative">
                <div className="min-w-[1200px]">
                    <div className="flex border-b border-gray-200 sticky top-0 bg-white z-20 shadow-sm">
                        <div className="w-48 p-3 font-semibold text-gray-500 bg-gray-50 border-r border-gray-200 sticky left-0 z-20 flex items-center">
                            Pokoje i Łóżka
                        </div>
                        {days.map(day => (
                            <div key={day.toString()} className={`flex-1 min-w-[80px] p-2 text-center border-r border-gray-100 ${isSameDay(day, new Date()) ? 'bg-brand-50' : ''}`}>
                                <div className="text-[10px] text-gray-400 font-bold uppercase">{format(day, 'EEE', { locale: pl })}</div>
                                <div className={`text-sm font-bold ${isSameDay(day, new Date()) ? 'text-brand-600' : 'text-gray-700'}`}>
                                    {format(day, 'd')}
                                </div>
                            </div>
                        ))}
                    </div>

                    {rooms.map(room => {
                        const isExpanded = !!expandedRooms[room.id!];
                        const bedCount = room.beds?.length || 0;

                        const roomReservations = getRoomReservations(room);
                        const roomLanes = buildRoomLanes(roomReservations);
                        const maxLanes = 3;
                        const hiddenLanes = Math.max(0, roomLanes.length - maxLanes);

                        return (
                            <React.Fragment key={room.id}>
                                <div
                                    className="flex bg-gray-50/50 border-b border-gray-200 sticky left-0 group cursor-pointer hover:bg-gray-100/80 transition-colors h-12"
                                    onClick={() => toggleRoom(room.id!)}
                                >
                                    <div className="w-48 p-2 border-r border-gray-200 sticky left-0 bg-gray-50 z-10 flex items-center gap-2 pl-3">
                                        <div className="text-gray-400">
                                            {isExpanded ? <ChevronDown size={16} /> : <ChevronRightIcon size={16} />}
                                        </div>
                                        <Home size={14} className="text-brand-600" />
                                        <div className="flex flex-col">
                                            <span className="font-bold text-gray-900 text-[11px] truncate leading-tight uppercase tracking-wider">{room.name}</span>
                                            <span className="text-[9px] text-gray-500">
                                                {bedCount} {bedCount === 1 ? 'miejsce' : 'miejsca'}
                                                {!isExpanded && hiddenLanes > 0 ? ` • +${hiddenLanes} grup` : ''}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex-1 relative">
                                        {!isExpanded && roomLanes.slice(0, maxLanes).map((lane, laneIndex) => (
                                            lane.items.map((reservation) => {
                                                const style = getRoomBookingStyle(reservation.check_in, reservation.check_out);
                                                if ('display' in style && style.display === 'none') return null;

                                                const colorClass = getStatusColor(reservation.status);
                                                const top = 6 + laneIndex * 10;

                                                return (
                                                    <div
                                                        key={`room-${room.id}-res-${reservation.id}-lane-${laneIndex}`}
                                                        className={`absolute ${colorClass} h-2 rounded-full shadow-sm`}
                                                        style={{ left: style.left, width: style.width, top }}
                                                        title={`Rezerwacja #${reservation.id} | ${reservation.first_name} ${reservation.last_name} | Status: ${getStatusLabel(reservation.status || 'pending')}`}
                                                    />
                                                );
                                            })
                                        ))}
                                    </div>
                                </div>

                                {isExpanded && room.beds?.map(bed => (
                                    <div key={bed.id} className="flex border-b border-gray-100 h-14 relative hover:bg-gray-50/30 transition-colors group/row">
                                        <div className={`w-48 px-4 border-r border-gray-200 sticky left-0 z-10 flex items-center gap-3 text-sm text-gray-600 pl-8 ${selectedBeds.has(bed.id!) ? 'bg-brand-50 border-l-4 border-l-brand-500' : 'bg-white border-l-4 border-l-brand-100/50'}`}>
                                            <div className="w-5 h-5 bg-gray-50 border border-gray-100 rounded text-[9px] flex items-center justify-center font-bold text-gray-500 group-hover/row:bg-brand-50 group-hover/row:text-brand-600 transition-colors">
                                                {bed.bed_number}
                                            </div>
                                            <div className="flex flex-col">
                                                <span className="text-[11px] font-medium text-gray-700">Miejsce {bed.bed_number}</span>
                                                <span className="text-[9px] text-gray-400 capitalize">{bed.bed_type}</span>
                                            </div>
                                        </div>

                                        <div className="flex-1 flex relative">
                                            {days.map(day => (
                                                <div
                                                    key={day.toString()}
                                                    onClick={(e) => handleCellClick(bed.id!, room.id!, day, e)}
                                                    className={`flex-1 min-w-[80px] border-r border-gray-200 relative cursor-pointer hover:bg-brand-50/20 transition-colors ${isSameDay(day, new Date()) ? 'bg-brand-50/10' : ''}`}
                                                >
                                                    {/* Noon marker (Check-in/Check-out time) */}
                                                    <div className="absolute left-1/2 top-1 bottom-1 w-px border-l border-dashed border-gray-300 pointer-events-none opacity-40" />
                                                </div>
                                            ))}

                                            {/* SELECTION PREVIEW - Updated for Multi-Bed */}
                                            {selection && (selection.bedId === bed.id || selectedBeds.has(bed.id!)) && selection.start && (
                                                <>
                                                    {/* Start Day Indicator */}
                                                    <div
                                                        className="absolute top-1 bottom-1 bg-emerald-600 rounded-l-md z-20 flex items-center justify-center text-[10px] text-white font-bold animate-in fade-in pointer-events-none"
                                                        style={{
                                                            left: `${(differenceInDays(selection.start, startDate)) * (100 / 14)}% `,
                                                            width: `${100 / 14}% `
                                                        }}
                                                    >
                                                        PRZYJAZD
                                                    </div>

                                                    {selection.end && (
                                                        <>
                                                            {/* Range Highlight */}
                                                            {days.map(day => {
                                                                if (isAfter(day, selection.start!) && isBefore(day, selection.end!)) {
                                                                    return (
                                                                        <div
                                                                            key={`range - ${day.toString()} `}
                                                                            className="absolute top-1.5 bottom-1.5 bg-brand-200/60 z-10 pointer-events-none"
                                                                            style={{
                                                                                left: `${(differenceInDays(day, startDate)) * (100 / 14)}% `,
                                                                                width: `${100 / 14}% `
                                                                            }}
                                                                        />
                                                                    );
                                                                }
                                                                return null;
                                                            })}

                                                            {/* End Day Indicator */}
                                                            <div
                                                                className="absolute top-1 bottom-1 bg-amber-600 rounded-r-md z-20 flex items-center justify-center text-[10px] text-white font-bold animate-in fade-in pointer-events-none"
                                                                style={{
                                                                    left: `${(differenceInDays(selection.end, startDate)) * (100 / 14)}% `,
                                                                    width: `${100 / 14}% `
                                                                }}
                                                            >
                                                                WYJAZD
                                                            </div>

                                                            {/* CTA Button - Only on the primary selected bed or last selected to avoid clutter */}
                                                            {selection.bedId === bed.id && (
                                                                <div
                                                                    className="absolute -bottom-8 left-1/2 -translate-x-1/2 z-[100] animate-in slide-in-from-top-2"
                                                                    style={{ left: `${(differenceInDays(selection.start, startDate) + differenceInDays(selection.end, selection.start) / 2 + 0.5) * (100 / 14)}% ` }}
                                                                >
                                                                    <button
                                                                        onClick={(e) => { e.stopPropagation(); handleConfirmSelection(); }}
                                                                        className="bg-brand-600 text-white px-4 py-2 rounded-full text-xs font-bold shadow-xl flex items-center gap-1.5 hover:bg-brand-700 hover:scale-105 transition-all whitespace-nowrap ring-2 ring-white"
                                                                    >
                                                                        <Check size={16} />
                                                                        ZAREZERWUJ {selectedBeds.size > 1 ? `(${selectedBeds.size} ŁÓŻKA)` : ''}
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </>
                                                    )}
                                                </>
                                            )}

                                            {bookings
                                                .filter(b => b.bed_id === bed.id && b.status !== 'cancelled')
                                                .flatMap(booking => getBookingSlices(booking))
                                                .map(slice => (
                                                    <div
                                                        key={slice.key}
                                                        className={slice.className}
                                                        style={slice.style}
                                                        title={`${slice.booking.first_name} ${slice.booking.last_name} | Rezerwacja #${slice.booking.id}`}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setSelectedReservation(slice.booking);
                                                            setIsDetailsModalOpen(true);
                                                        }}
                                                    >
                                                        {slice.showLabel && (
                                                            <span className="truncate flex-1 font-bold px-1">
                                                                {slice.booking.first_name?.[0]}. {slice.booking.last_name}
                                                            </span>
                                                        )}
                                                    </div>
                                                ))
                                            }
                                        </div>
                                    </div>
                                ))}
                            </React.Fragment>
                        );
                    })}

                    {rooms.length === 0 && !loading && (
                        <div className="p-20 text-center">
                            <BedDouble size={48} className="mx-auto text-gray-200 mb-4" />
                            <p className="text-gray-500">Brak pokoi do wyświetlenia.</p>
                            <p className="text-sm text-gray-400 mt-1">Skonfiguruj pokoje w zakładce "Pokoje".</p>
                        </div>
                    )}
                </div>
            </div>

            <ReservationModal
                isOpen={isModalOpen}
                onClose={handleCloseModal}
                onSuccess={fetchData}
                initialData={modalData}
                rooms={rooms}
            />

            {/* Reservation Details Modal */}
            {isDetailsModalOpen && selectedReservation && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-100">
                        <div className="flex items-center justify-between p-6 border-b border-gray-100 bg-gray-50/50">
                            <div className="flex items-center gap-3">
                                <h3 className="text-xl font-bold text-gray-900">Rezerwacja #{selectedReservation.id}</h3>
                                <button
                                    onClick={() => setShowHistory(!showHistory)}
                                    className={`p-2 rounded-lg transition-colors ${showHistory ? 'bg-brand-100 text-brand-700' : 'bg-white text-gray-500 hover:text-gray-900 hover:bg-gray-100'}`}
                                    title="Historia zmian"
                                >
                                    <History size={18} />
                                </button>
                            </div>
                            <button
                                onClick={() => {
                                    setIsDetailsModalOpen(false);
                                    setShowHistory(false);
                                }}
                                className="p-2 text-gray-400 hover:text-gray-600 hover:bg-white rounded-xl transition-all shadow-sm"
                            >
                                <X size={20} />
                            </button>
                        </div>
                        <div className="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                            {showHistory ? (
                                <BookingHistory reservationId={selectedReservation.id!} />
                            ) : (
                                <>
                                    <div>
                                        <p className="text-xs text-gray-500 uppercase font-bold mb-1">Gość</p>
                                        <p className="text-lg font-bold text-gray-900">{selectedReservation.first_name} {selectedReservation.last_name}</p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-xs text-gray-500 uppercase font-bold mb-1">Przyjazd</p>
                                            <p className="font-medium">{format(parseISO(selectedReservation.check_in), 'd MMM yyyy', { locale: pl })}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-gray-500 uppercase font-bold mb-1">Wyjazd</p>
                                            <p className="font-medium">{format(parseISO(selectedReservation.check_out), 'd MMM yyyy', { locale: pl })}</p>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-xs text-gray-500 uppercase font-bold mb-1">Dorośli</p>
                                            <p className="font-medium">{selectedReservation.adults || 1}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-gray-500 uppercase font-bold mb-1">Dzieci</p>
                                            <p className="font-medium">{selectedReservation.children || 0}</p>
                                        </div>
                                    </div>
                                    <div>
                                        <p className="text-xs text-gray-500 uppercase font-bold mb-1">Status</p>
                                        <span className={`inline-block px-3 py-1 rounded-full text-xs font-bold ${selectedReservation.status === 'confirmed' ? 'bg-green-100 text-green-700' :
                                            selectedReservation.status === 'pending' ? 'bg-amber-100 text-amber-700' :
                                                selectedReservation.status === 'checked_in' ? 'bg-emerald-100 text-emerald-700' :
                                                    selectedReservation.status === 'checked_out' ? 'bg-gray-100 text-gray-700' :
                                                        'bg-gray-100 text-gray-700'
                                            }`}>
                                            {selectedReservation.status === 'confirmed' ? 'Potwierdzona' :
                                                selectedReservation.status === 'pending' ? 'Oczekująca' :
                                                    selectedReservation.status === 'checked_in' ? 'Zalogowana' :
                                                        selectedReservation.status === 'checked_out' ? 'Wylogowana' :
                                                            'Anulowana'}
                                        </span>
                                    </div>
                                    {selectedReservation.notes && (
                                        <div>
                                            <p className="text-xs text-gray-500 uppercase font-bold mb-1">Notatki</p>
                                            <p className="text-sm text-gray-700">{selectedReservation.notes}</p>
                                        </div>
                                    )}
                                    <div className="pt-2 border-t border-gray-100 mt-2">
                                        <div className="bg-emerald-50 p-4 rounded-xl flex items-center justify-between">
                                            <div className="flex items-center gap-2 text-emerald-700">
                                                <div className="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center text-white">
                                                    <CreditCard size={16} />
                                                </div>
                                                <span className="text-xs font-bold uppercase tracking-wider">Do zapłaty</span>
                                            </div>
                                            <span className="text-xl font-black text-emerald-700">
                                                {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(selectedReservation.total_price || 0)}
                                            </span>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                        <div className="p-6 border-t border-gray-100 flex gap-3">
                            {selectedReservation.status === 'pending' && (
                                <button
                                    onClick={async () => {
                                        try {
                                            await ReservationsAPI.confirm(selectedReservation.id!);
                                            setIsDetailsModalOpen(false);
                                            fetchData();
                                        } catch (error) {
                                            alert('Błąd podczas potwierdzania rezerwacji');
                                            console.error(error);
                                        }
                                    }}
                                    className="flex-1 px-4 py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 transition-all text-sm flex items-center justify-center gap-2"
                                >
                                    <Check size={16} />
                                    Potwierdź
                                </button>
                            )}
                            <button
                                onClick={async (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    if (window.confirm('Czy na pewno chcesz anulować tę rezerwację?')) {
                                        try {
                                            await ReservationsAPI.cancel(selectedReservation.id!, 'Anulowano przez użytkownika');
                                            setIsDetailsModalOpen(false);
                                            fetchData();
                                        } catch (error) {
                                            alert('Błąd podczas anulowania rezerwacji');
                                            console.error(error);
                                        }
                                    }
                                }}
                                className="flex-1 px-4 py-3 border border-red-200 rounded-xl font-bold text-red-600 hover:bg-red-50 transition-all text-sm"
                            >
                                Anuluj Rezerwację
                            </button>
                            <button
                                onClick={() => setIsDetailsModalOpen(false)}
                                className="flex-1 px-4 py-3 bg-brand-600 text-white rounded-xl font-bold hover:bg-brand-700 transition-all text-sm"
                            >
                                Zamknij
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default CalendarView;
