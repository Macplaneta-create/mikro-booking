/**
 * CalendarView
 * 
 * Main calendar container. Owns state, fetches data, and orchestrates sub-components.
 * Refactored from 886 lines to ~220 lines by extracting:
 *   - CalendarToolbar (navigation, stats, legend)
 *   - CalendarGrid (room/bed rows, booking bars, selection)
 *   - ReservationDetailsModal (details + actions)
 *   - calendarUtils.ts (pure utility functions)
 */

import React, { useState, useEffect } from 'react';
import { Loader2 } from 'lucide-react';
import { format, addDays, startOfWeek, addWeeks, subWeeks, isSameDay, parseISO, isBefore, startOfDay } from 'date-fns';
import { RoomsAPI, ReservationsAPI, Room, Reservation, Bed, BedPlace } from '../services/api';

import CalendarToolbar from './calendar/CalendarToolbar';
import CalendarGrid, { SelectionState } from './calendar/CalendarGrid';
import ReservationDetailsModal from './calendar/ReservationDetailsModal';
import ReservationModal from './ReservationModal';
import { getVisibleStats, getVisibleStatusLegend } from './calendar/calendarUtils';

type ReservationModalData = {
    bedId?: number;
    bedIds?: number[];
    placeIds?: number[];
    roomId?: number;
    checkIn?: string;
    checkOut?: string;
};

const CalendarView: React.FC = () => {
    const [currentDate, setCurrentDate] = useState(new Date());
    const [rooms, setRooms] = useState<Room[]>([]);
    const [bookings, setBookings] = useState<Reservation[]>([]);
    const [loading, setLoading] = useState(true);
    const [expandedRooms, setExpandedRooms] = useState<Record<number, boolean>>({});

    // Selection state
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalData, setModalData] = useState<ReservationModalData>({});
    const [selection, setSelection] = useState<SelectionState | null>(null);
    const [selectedBeds, setSelectedBeds] = useState<Set<number>>(new Set());

    // Details modal
    const [selectedReservation, setSelectedReservation] = useState<Reservation | null>(null);
    const [isDetailsModalOpen, setIsDetailsModalOpen] = useState(false);

    // Derived
    const startDate = startOfWeek(currentDate, { weekStartsOn: 1 });
    const days = Array.from({ length: 14 }).map((_, i) => addDays(startDate, i));
    const endDate = days[days.length - 1];

    // --- Data Fetching ---

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
                limit: 300,
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

    // Keep selected reservation in sync with freshly fetched bookings.
    useEffect(() => {
        if (!selectedReservation?.id) {
            return;
        }

        const fresh = bookings.find(b => b.id === selectedReservation.id);
        if (fresh && fresh !== selectedReservation) {
            setSelectedReservation(fresh);
        }
    }, [bookings, selectedReservation]);

    // Handle deep-linking to specific reservation once bookings are loaded.
    useEffect(() => {
        if (bookings.length === 0) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const resId = params.get('id');
        if (!resId) {
            return;
        }

        const booking = bookings.find(b => b.id === parseInt(resId, 10));
        if (booking) {
            setSelectedReservation(booking);
            setIsDetailsModalOpen(true);
        }
    }, [bookings]);

    // --- Navigation ---

    const handleNext = () => setCurrentDate(prev => addWeeks(prev, 1));
    const handlePrev = () => setCurrentDate(prev => subWeeks(prev, 1));
    const handleToday = () => setCurrentDate(new Date());

    const toggleRoom = (roomId: number) => {
        setExpandedRooms(prev => ({ ...prev, [roomId]: !prev[roomId] }));
    };

    const getAllBeds = (): Bed[] => rooms.flatMap(room => room.beds || []);

    const findBedById = (bedId: number): Bed | undefined => {
        return getAllBeds().find(bed => bed.id === bedId);
    };

    const getSortedActivePlacesForBed = (bedId: number): BedPlace[] => {
        const bed = findBedById(bedId);

        return [...(bed?.places || [])]
            .filter(place => place.id && place.is_active)
            .sort((left, right) => left.place_number - right.place_number);
    };

    const getBedCapacity = (bedId: number): number => {
        const bed = findBedById(bedId);
        const explicitCapacity = Number(bed?.capacity ?? 0);
        if (explicitCapacity > 0) {
            return explicitCapacity;
        }

        const placesCapacity = (bed?.places || []).reduce((sum, place) => sum + Number(place.max_persons || 1), 0);
        if (placesCapacity > 0) {
            return placesCapacity;
        }

        return String(bed?.bed_type || 'single') === 'bunk' ? 2 : 1;
    };

    const isBlockingStatus = (status?: string): boolean => {
        return status === 'pending' || status === 'confirmed' || status === 'checked_in';
    };

    const estimateOccupiedPlacesForReservationOnBed = (reservation: Reservation, targetBedId: number): number => {
        const bedIds = Array.from(new Set((reservation.bed_ids || []).map(Number))).filter(id => id > 0);
        if (bedIds.length === 0) {
            return 0;
        }

        const guestCount = Math.max(1, Number(reservation.adults || 0) + Number(reservation.children || 0));
        const sortedBeds = [...bedIds].sort((leftId, rightId) => {
            const capacityDiff = getBedCapacity(rightId) - getBedCapacity(leftId);
            if (capacityDiff !== 0) {
                return capacityDiff;
            }

            return leftId - rightId;
        });

        let remaining = guestCount;
        for (const currentBedId of sortedBeds) {
            const assigned = Math.min(remaining, getBedCapacity(currentBedId));
            if (currentBedId === targetBedId) {
                return assigned;
            }

            remaining -= assigned;
            if (remaining <= 0) {
                break;
            }
        }

        return 0;
    };

    const getOccupiedPlacesForReservationOnBed = (reservation: Reservation, bedId: number): number => {
        if (!reservation.bed_ids?.includes(bedId)) {
            return 0;
        }

        const bed = findBedById(bedId);
        const capacity = getBedCapacity(bedId);

        if (bed?.places?.length && reservation.place_ids?.length) {
            const placeIds = new Set(reservation.place_ids);
            const occupiedPlaces = bed.places.filter(place => place.id && placeIds.has(place.id)).length;
            if (occupiedPlaces > 0) {
                return occupiedPlaces;
            }
        }

        const estimatedOccupiedPlaces = estimateOccupiedPlacesForReservationOnBed(reservation, bedId);
        if (estimatedOccupiedPlaces > 0) {
            return estimatedOccupiedPlaces;
        }

        return capacity;
    };

    const getOccupiedPlacesOnDate = (bedId: number, date: Date): number => {
        return bookings.reduce((sum, booking) => {
            if (!isBlockingStatus(booking.status) || !booking.bed_ids?.includes(bedId)) {
                return sum;
            }

            const bookingCheckIn = parseISO(booking.check_in);
            const bookingCheckOut = parseISO(booking.check_out);
            if (date < bookingCheckIn || date >= bookingCheckOut) {
                return sum;
            }

            return sum + getOccupiedPlacesForReservationOnBed(booking, bedId);
        }, 0);
    };

    const getUnavailablePlaceIdsOnDate = (bedId: number, date: Date): Set<number> => {
        const places = getSortedActivePlacesForBed(bedId);
        if (places.length === 0) {
            return new Set();
        }

        const unavailable = new Set<number>();

        bookings.forEach((booking) => {
            if (!isBlockingStatus(booking.status) || !booking.bed_ids?.includes(bedId)) {
                return;
            }

            const bookingCheckIn = parseISO(booking.check_in);
            const bookingCheckOut = parseISO(booking.check_out);
            if (date < bookingCheckIn || date >= bookingCheckOut) {
                return;
            }

            if (booking.place_ids?.length) {
                const placeIds = new Set(booking.place_ids);
                places.forEach((place) => {
                    if (place.id && placeIds.has(place.id)) {
                        unavailable.add(place.id);
                    }
                });
                return;
            }

            const estimatedOccupied = Math.min(getOccupiedPlacesForReservationOnBed(booking, bedId), places.length);
            places.forEach((place, index) => {
                if (place.id && index < estimatedOccupied) {
                    unavailable.add(place.id);
                }
            });
        });

        return unavailable;
    };

    const getAvailablePlaceIdsForRange = (bedId: number, start: Date, end: Date): number[] => {
        const places = getSortedActivePlacesForBed(bedId);
        if (places.length === 0) {
            return [];
        }

        let availablePlaceIds = places
            .map(place => place.id)
            .filter((placeId): placeId is number => typeof placeId === 'number' && placeId > 0);

        const cursor = new Date(start);
        while (cursor < end && availablePlaceIds.length > 0) {
            const unavailablePlaceIds = getUnavailablePlaceIdsOnDate(bedId, cursor);
            availablePlaceIds = availablePlaceIds.filter(placeId => !unavailablePlaceIds.has(placeId));
            cursor.setDate(cursor.getDate() + 1);
        }

        return availablePlaceIds;
    };

    const hasAvailablePlaceForRange = (bedId: number, start: Date, end: Date): boolean => {
        if (!(start < end)) {
            return true;
        }

        const capacity = getBedCapacity(bedId);
        if (capacity <= 0) {
            return false;
        }

        const cursor = new Date(start);
        while (cursor < end) {
            if (getOccupiedPlacesOnDate(bedId, cursor) >= capacity) {
                return false;
            }
            cursor.setDate(cursor.getDate() + 1);
        }

        return true;
    };

    // --- Multi-Bed Selection Logic ---

    const handleCellClick = (bedId: number, roomId: number, date: Date, event: React.MouseEvent) => {
        const today = startOfDay(new Date());
        const cellDate = startOfDay(date);

        // Block past dates
        if (isBefore(cellDate, today)) return;

        if (!selection && !hasAvailablePlaceForRange(bedId, cellDate, addDays(cellDate, 1))) {
            return;
        }

        // Ctrl+Click for multi-bed selection
        if (event.ctrlKey || event.metaKey) {
            if (!selection || !selection.start || !selection.end) return;

            const newSelectedBeds = new Set(selectedBeds);
            if (newSelectedBeds.has(bedId)) {
                newSelectedBeds.delete(bedId);
            } else {
                const isAvailable = hasAvailablePlaceForRange(bedId, selection.start, selection.end);

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
            setSelectedBeds(new Set());
            setSelection({ bedId, roomId, start: date, end: null });
        } else if (selection.start && !selection.end) {
            if (isSameDay(date, selection.start)) {
                setSelection(null);
                setSelectedBeds(new Set());
            } else if (isBefore(date, selection.start)) {
                setSelection({ ...selection, start: date, end: null });
            } else {
                if (!hasAvailablePlaceForRange(bedId, selection.start, date)) {
                    alert(`Łóżko #${bedId} nie ma wolnego miejsca w całym wybranym terminie`);
                    return;
                }

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

        // Fallback: If no beds selected via Ctrl+click, use the primary bed
        const finalBedIds = bedIds.length > 0 ? bedIds : [selection.bedId];
        const selectedPlaceIds = finalBedIds.flatMap((bedId) => getAvailablePlaceIdsForRange(bedId, selection.start!, selection.end!));

        setModalData({
            bedId: selection.bedId,
            bedIds: finalBedIds,
            placeIds: selectedPlaceIds,
            roomId: selection.roomId,
            checkIn: format(selection.start, 'yyyy-MM-dd'),
            checkOut: format(selection.end, 'yyyy-MM-dd'),
        });
        setIsModalOpen(true);
    };

    const canConfirmSelection = Boolean(selection?.start && selection?.end);

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setSelection(null);
        setModalData({});
    };

    const handleNewReservation = () => {
        setModalData({});
        setIsModalOpen(true);
    };

    // --- Render ---

    if (loading && rooms.length === 0) {
        return <div className="p-20 flex justify-center h-full items-center"><Loader2 className="animate-spin text-brand-600" /></div>;
    }

    const stats = getVisibleStats(bookings, startDate, endDate);
    const statusLegend = getVisibleStatusLegend(bookings, startDate, endDate);

    return (
        <div className="flex flex-col h-[calc(100vh-140px)] bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden select-none">
            <CalendarToolbar
                currentDate={currentDate}
                onPrev={handlePrev}
                onNext={handleNext}
                onToday={handleToday}
                onNewReservation={handleNewReservation}
                onConfirmSelection={handleConfirmSelection}
                canConfirmSelection={canConfirmSelection}
                stats={stats}
                statusLegend={statusLegend}
            />

            <CalendarGrid
                rooms={rooms}
                bookings={bookings}
                days={days}
                startDate={startDate}
                loading={loading}
                expandedRooms={expandedRooms}
                onToggleRoom={toggleRoom}
                selection={selection}
                selectedBeds={selectedBeds}
                onCellClick={handleCellClick}
                onConfirmSelection={handleConfirmSelection}
                onBookingClick={(reservation) => {
                    setSelectedReservation(reservation);
                    setIsDetailsModalOpen(true);
                }}
            />

            <ReservationModal
                isOpen={isModalOpen}
                onClose={handleCloseModal}
                onSuccess={fetchData}
                initialData={modalData}
                rooms={rooms}
            />

            {selectedReservation && (
                <ReservationDetailsModal
                    reservation={selectedReservation}
                    isOpen={isDetailsModalOpen}
                    onClose={() => setIsDetailsModalOpen(false)}
                    onRefresh={fetchData}
                    rooms={rooms}
                />
            )}
        </div>
    );
};

export default CalendarView;
