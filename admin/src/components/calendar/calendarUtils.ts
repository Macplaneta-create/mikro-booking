/**
 * Calendar Utility Functions
 * 
 * Pure functions for status colors, labels, booking slices, and room lane calculations.
 * Extracted from CalendarView.tsx for reusability.
 */

import { addDays, isSameDay, isAfter, isBefore, parseISO, differenceInDays } from 'date-fns';
import { Reservation } from '../../services/api';

// --- Status Configuration ---

export const STATUS_COLORS: Record<string, string> = {
    confirmed: 'bg-green-500',
    pending: 'bg-amber-500',
    checked_in: 'bg-emerald-600',
    checked_out: 'bg-gray-500',
    cancelled: 'bg-gray-400',
};

export const STATUS_LABELS: Record<string, string> = {
    confirmed: 'Potwierdzona',
    pending: 'Oczekująca',
    checked_in: 'Zameldowany',
    checked_out: 'Wymeldowany',
    cancelled: 'Anulowana',
};

export const getStatusColor = (status: string | undefined): string => {
    if (!status || !STATUS_COLORS[status]) return 'bg-blue-500';
    return STATUS_COLORS[status];
};

export const getStatusLabel = (status: string): string => {
    return STATUS_LABELS[status] || status;
};

// --- Booking Slice Calculation ---

export interface BookingSlice {
    key: string;
    style: { left: string; width: string };
    className: string;
    booking: Reservation;
    showLabel: boolean;
}

export const getBookingSlices = (booking: Reservation, startDate: Date, dayCount: number = 14): BookingSlice[] => {
    const checkIn = parseISO(booking.check_in);
    const checkOut = parseISO(booking.check_out);
    const slices: BookingSlice[] = [];

    for (let i = 0; i < dayCount; i++) {
        const day = addDays(startDate, i);
        const isStartDay = isSameDay(day, checkIn);
        const isEndDay = isSameDay(day, checkOut);
        const isMiddle = isAfter(day, checkIn) && isBefore(day, checkOut);

        if (!isStartDay && !isEndDay && !isMiddle) continue;

        let startOfSlice = 0;
        let endOfSlice = 1;

        if (isStartDay) startOfSlice = 0.6; // Arrives afternoon
        if (isEndDay) endOfSlice = 0.4;     // Leaves morning

        // Same-day booking (rare/illegal in nights system)
        if (isStartDay && isEndDay) continue;

        const left = (i + startOfSlice) * (100 / dayCount);
        const width = (endOfSlice - startOfSlice) * (100 / dayCount);

        if (width <= 0) continue;

        const statusClass = booking.status === 'cancelled'
            ? 'opacity-40'
            : booking.status === 'checked_out'
                ? 'opacity-70'
                : booking.status === 'checked_in'
                    ? 'ring-2 ring-emerald-700'
                    : booking.status === 'pending'
                        ? 'ring-1 ring-amber-700/70'
                        : booking.status === 'selecting'
                            ? 'ring-2 ring-brand-700 shadow-lg'
                            : 'ring-1 ring-white/40';

        const colorClass = getStatusColor(booking.status);

        slices.push({
            key: `${booking.id}-${i}`,
            style: { left: `${left}%`, width: `${width}%` },
            className: `absolute top-1 bottom-1 flex items-center text-[10px] text-white shadow-sm cursor-pointer hover:brightness-110 transition z-10 overflow-hidden ${colorClass} ${statusClass} ${isStartDay ? 'rounded-l-sm' : ''} ${isEndDay ? 'rounded-r-sm' : ''}`,
            booking,
            showLabel: isStartDay || (i === 0 && isMiddle),
        });
    }
    return slices;
};

// --- Room Lane Layout (non-overlapping reservation lanes) ---

export interface ReservationLane {
    end: Date;
    items: Reservation[];
}

export const buildRoomLanes = (reservations: Reservation[]): ReservationLane[] => {
    const sorted = [...reservations].sort((a, b) =>
        parseISO(a.check_in).getTime() - parseISO(b.check_in).getTime()
    );

    const lanes: ReservationLane[] = [];

    sorted.forEach((reservation) => {
        const start = parseISO(reservation.check_in);
        const end = parseISO(reservation.check_out);

        let placed = false;
        for (const lane of lanes) {
            if (start >= lane.end) {
                lane.items.push(reservation);
                lane.end = end;
                placed = true;
                break;
            }
        }

        if (!placed) {
            lanes.push({ end, items: [reservation] });
        }
    });

    return lanes;
};

// --- Room-level booking style (collapsed view mini-bars) ---

export const getRoomBookingStyle = (check_in: string, check_out: string, startDate: Date, dayCount: number = 14) => {
    const checkIn = parseISO(check_in);
    const checkOut = parseISO(check_out);
    const endDate = addDays(startDate, dayCount - 1);

    const isStartVisible = !isBefore(checkIn, startDate);

    const startDayIndex = differenceInDays(checkIn, startDate);
    const endDayIndex = differenceInDays(checkOut, startDate);

    const left = (Math.max(0, startDayIndex) + (isStartVisible ? 0.6 : 0)) * (100 / dayCount);

    const rightEdgeIndex = Math.min(dayCount, endDayIndex + 0.4);
    const leftEdgeIndex = Math.max(0, startDayIndex + (isStartVisible ? 0.6 : 0));

    const width = (rightEdgeIndex - leftEdgeIndex) * (100 / dayCount);

    if (isBefore(checkOut, startDate) || isSameDay(checkOut, startDate) || isAfter(checkIn, endDate)) {
        return { display: 'none' as const };
    }

    return { left: `${left}%`, width: `${width}%` };
};

// --- Stats & Legend helpers ---

export const getVisibleStats = (bookings: Reservation[], startDate: Date, endDate: Date) => {
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
        if (booking.bed_ids) {
            booking.bed_ids.forEach(id => id && bedIds.add(id));
        }
    });

    return { reservations: reservationIds.size, beds: bedIds.size };
};

export interface StatusLegendItem {
    status: string;
    count: number;
    color: string;
    label: string;
}

export const getVisibleStatusLegend = (bookings: Reservation[], startDate: Date, endDate: Date): StatusLegendItem[] => {
    const visibleBookings = bookings.filter((booking) => {
        if (booking.status === 'cancelled') return false;
        const checkIn = parseISO(booking.check_in);
        const checkOut = parseISO(booking.check_out);
        return checkOut > startDate && checkIn <= endDate;
    });

    const statusCounts: Record<string, number> = {
        confirmed: 0,
        pending: 0,
        checked_in: 0,
        checked_out: 0,
        cancelled: 0,
    };

    visibleBookings.forEach((booking) => {
        if (booking.status && booking.status in statusCounts) {
            statusCounts[booking.status]++;
        }
    });

    return Object.entries(statusCounts)
        .filter(([_, count]) => count > 0)
        .map(([status, count]) => ({
            status,
            count,
            color: getStatusColor(status),
            label: getStatusLabel(status),
        }));
};

// --- Room reservation filtering ---

export const getRoomReservations = (room: { beds?: { id?: number }[] }, bookings: Reservation[]): Reservation[] => {
    const bedIds = new Set((room.beds || []).map(b => b.id).filter(Boolean) as number[]);
    const unique = new Map<number, Reservation>();

    bookings.forEach((booking) => {
        if (!booking.bed_ids?.some(id => bedIds.has(id)) || booking.status === 'cancelled') {
            return;
        }
        if (booking.id && !unique.has(booking.id)) {
            unique.set(booking.id, booking);
        }
    });

    return Array.from(unique.values());
};
