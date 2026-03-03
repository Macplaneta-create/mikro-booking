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
import { format, addDays, startOfWeek, addWeeks, subWeeks, isSameDay, parseISO, isAfter, isBefore, startOfDay } from 'date-fns';
import { RoomsAPI, ReservationsAPI, Room, Reservation } from '../services/api';

import CalendarToolbar from './calendar/CalendarToolbar';
import CalendarGrid, { SelectionState } from './calendar/CalendarGrid';
import ReservationDetailsModal from './calendar/ReservationDetailsModal';
import ReservationModal from './ReservationModal';
import { getVisibleStats, getVisibleStatusLegend } from './calendar/calendarUtils';

const CalendarView: React.FC = () => {
    const [currentDate, setCurrentDate] = useState(new Date());
    const [rooms, setRooms] = useState<Room[]>([]);
    const [bookings, setBookings] = useState<Reservation[]>([]);
    const [loading, setLoading] = useState(true);
    const [expandedRooms, setExpandedRooms] = useState<Record<number, boolean>>({});

    // Selection state
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalData, setModalData] = useState<{ bedId?: number; bedIds?: number[]; roomId?: number; checkIn?: string; checkOut?: string }>({});
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

    // --- Multi-Bed Selection Logic ---

    const handleCellClick = (bedId: number, roomId: number, date: Date, event: React.MouseEvent) => {
        const today = startOfDay(new Date());
        const cellDate = startOfDay(date);

        // Block past dates
        if (isBefore(cellDate, today)) return;

        // Occupancy check
        const isOccupied = bookings.some(b => {
            if (!b.bed_ids?.includes(bedId) || b.status === 'cancelled') return false;
            const bIn = parseISO(b.check_in);
            const bOut = parseISO(b.check_out);

            if (!selection) {
                return !isBefore(date, bIn) && isBefore(date, bOut);
            } else {
                return isBefore(selection.start!, bOut) && isAfter(date, bIn);
            }
        });

        if (isOccupied) return;

        // Ctrl+Click for multi-bed selection
        if (event.ctrlKey || event.metaKey) {
            if (!selection || !selection.start || !selection.end) return;

            const newSelectedBeds = new Set(selectedBeds);
            if (newSelectedBeds.has(bedId)) {
                newSelectedBeds.delete(bedId);
            } else {
                const isAvailable = !bookings.some(b =>
                    b.bed_ids?.includes(bedId) &&
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
            setSelectedBeds(new Set());
            setSelection({ bedId, roomId, start: date, end: null });
        } else if (selection.start && !selection.end) {
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

        // Fallback: If no beds selected via Ctrl+click, use the primary bed
        const finalBedIds = bedIds.length > 0 ? bedIds : [selection.bedId];

        setModalData({
            bedId: selection.bedId,
            bedIds: finalBedIds,
            roomId: selection.roomId,
            checkIn: format(selection.start, 'yyyy-MM-dd'),
            checkOut: format(selection.end, 'yyyy-MM-dd'),
        });
        setIsModalOpen(true);
    };

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
