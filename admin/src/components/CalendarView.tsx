import React, { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight, BedDouble, Loader2, ChevronDown, ChevronRight as ChevronRightIcon, Home, Check } from 'lucide-react';
import { format, addDays, startOfWeek, addWeeks, subWeeks, isSameDay, parseISO, differenceInDays, isAfter, isBefore } from 'date-fns';
import { pl } from 'date-fns/locale';
import { RoomsAPI, ReservationsAPI, Room, Reservation } from '../services/api';
import ReservationModal from './ReservationModal';

const CalendarView: React.FC = () => {
    const [currentDate, setCurrentDate] = useState(new Date());
    const [rooms, setRooms] = useState<Room[]>([]);
    const [bookings, setBookings] = useState<Reservation[]>([]);
    const [loading, setLoading] = useState(true);
    const [expandedRooms, setExpandedRooms] = useState<Record<number, boolean>>({});

    // Selection state
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalData, setModalData] = useState<{ bedId?: number, roomId?: number, checkIn?: string, checkOut?: string }>({});

    const [isSelecting, setIsSelecting] = useState(false);
    const [selection, setSelection] = useState<{ bedId: number, roomId: number, start: Date, end: Date } | null>(null);

    const startDate = startOfWeek(currentDate, { weekStartsOn: 1 });
    const days = Array.from({ length: 14 }).map((_, i) => addDays(startDate, i));
    const endDate = days[days.length - 1];

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

    // --- SELECTION LOGIC ---
    const onMouseDown = (bedId: number, roomId: number, date: Date) => {
        const isOccupied = bookings.some(b => b.bed_id === bedId && isSameDay(parseISO(b.check_in), date));
        if (isOccupied) return;

        setIsSelecting(true);
        setSelection({ bedId, roomId, start: date, end: date });
    };

    const onMouseEnter = (bedId: number, date: Date) => {
        if (!isSelecting || !selection) return;
        if (selection.bedId !== bedId) return;

        setSelection(prev => prev ? ({ ...prev, end: date }) : null);
    };

    const onMouseUp = () => {
        if (!isSelecting || !selection) return;

        const { bedId, roomId, start, end } = selection;
        const d1 = isBefore(start, end) ? start : end;
        const d2 = isAfter(start, end) ? start : end;

        const finalEnd = addDays(d2, 1);

        setModalData({
            bedId,
            roomId,
            checkIn: format(d1, 'yyyy-MM-dd'),
            checkOut: format(finalEnd, 'yyyy-MM-dd')
        });

        setIsSelecting(false);
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setSelection(null);
        setModalData({});
    };

    useEffect(() => {
        const handleGlobalMouseUp = () => {
            if (isSelecting) {
                setIsSelecting(false);
            }
        };
        window.addEventListener('mouseup', handleGlobalMouseUp);
        return () => window.removeEventListener('mouseup', handleGlobalMouseUp);
    }, [isSelecting]);

    const getBookingStyle = (booking: { check_in: string, check_out: string, status?: string }) => {
        const checkIn = parseISO(booking.check_in);
        const checkOut = parseISO(booking.check_out);

        const effectiveStart = checkIn < startDate ? startDate : checkIn;
        const diffStart = differenceInDays(effectiveStart, startDate);
        const visibleDuration = differenceInDays(checkOut, effectiveStart);

        const dayWidth = 100 / 14;
        const left = diffStart * dayWidth;
        const width = Math.max(0.5, Math.min(visibleDuration, 14 - diffStart)) * dayWidth;

        if (checkOut <= startDate || checkIn > endDate) return { display: 'none' };

        let bgColor = 'bg-blue-500';
        if (booking.status === 'confirmed') bgColor = 'bg-blue-600';
        if (booking.status === 'checked_in') bgColor = 'bg-emerald-600';
        if (booking.status === 'cancelled') bgColor = 'bg-red-400 opacity-50';
        if (booking.status === 'checked_out') bgColor = 'bg-gray-500';
        if (booking.status === 'selecting') bgColor = 'bg-brand-500 ring-2 ring-brand-700 shadow-lg';

        return {
            left: `${left}%`,
            width: `${width}%`,
            className: `absolute top-1 bottom-1 rounded-md px-2 flex items-center text-[10px] text-white shadow-sm cursor-pointer hover:brightness-110 transition z-10 overflow-hidden ${bgColor}`
        };
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

                <div className="flex gap-4 text-xs text-brand-600 font-medium bg-brand-50 px-3 py-1.5 rounded-full border border-brand-100">
                    <span className="flex items-center gap-2">✨ Przeciągnij zakres dat, aby szybko zarezerwować</span>
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

                        return (
                            <React.Fragment key={room.id}>
                                <div
                                    className="flex bg-gray-50/50 border-b border-gray-200 sticky left-0 group cursor-pointer hover:bg-gray-100/80 transition-colors h-10"
                                    onClick={() => toggleRoom(room.id!)}
                                >
                                    <div className="w-48 p-2 border-r border-gray-200 sticky left-0 bg-gray-50 z-10 flex items-center gap-2 pl-3">
                                        <div className="text-gray-400">
                                            {isExpanded ? <ChevronDown size={16} /> : <ChevronRightIcon size={16} />}
                                        </div>
                                        <Home size={14} className="text-brand-600" />
                                        <div className="flex flex-col">
                                            <span className="font-bold text-gray-900 text-[11px] truncate leading-tight uppercase tracking-wider">{room.name}</span>
                                            <span className="text-[9px] text-gray-500">{bedCount} {bedCount === 1 ? 'miejsce' : 'miejsca'}</span>
                                        </div>
                                    </div>
                                    <div className="flex-1" />
                                </div>

                                {isExpanded && room.beds?.map(bed => (
                                    <div key={bed.id} className="flex border-b border-gray-100 h-14 relative hover:bg-gray-50/30 transition-colors group/row">
                                        <div className="w-48 px-4 border-r border-gray-200 sticky left-0 bg-white z-10 flex items-center gap-3 text-sm text-gray-600 pl-8 border-l-4 border-l-brand-100/50">
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
                                                    onMouseDown={() => onMouseDown(bed.id!, room.id!, day)}
                                                    onMouseEnter={() => onMouseEnter(bed.id!, day)}
                                                    onMouseUp={onMouseUp}
                                                    className={`flex-1 min-w-[80px] border-r border-gray-50 cursor-pointer ${isSameDay(day, new Date()) ? 'bg-brand-50/10' : ''}`}
                                                />
                                            ))}

                                            {/* SELECTION PREVIEW - No more spinner, just a checkmark */}
                                            {selection && selection.bedId === bed.id && (
                                                <div
                                                    className={getBookingStyle({
                                                        check_in: format(isBefore(selection.start, selection.end) ? selection.start : selection.end, 'yyyy-MM-dd'),
                                                        check_out: format(addDays(isAfter(selection.start, selection.end) ? selection.start : selection.end, 1), 'yyyy-MM-dd'),
                                                        status: 'selecting'
                                                    }).className}
                                                    style={getBookingStyle({
                                                        check_in: format(isBefore(selection.start, selection.end) ? selection.start : selection.end, 'yyyy-MM-dd'),
                                                        check_out: format(addDays(isAfter(selection.start, selection.end) ? selection.start : selection.end, 1), 'yyyy-MM-dd'),
                                                    })}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <Check size={12} className="text-white" />
                                                        <span className="font-bold">Wybrano</span>
                                                    </div>
                                                </div>
                                            )}

                                            {bookings
                                                .filter(b => b.bed_id === bed.id && b.status !== 'cancelled')
                                                .map(booking => {
                                                    const style = getBookingStyle(booking);
                                                    if (style.display === 'none') return null;

                                                    return (
                                                        <div
                                                            key={booking.id}
                                                            className={style.className}
                                                            style={{ left: style.left, width: style.width }}
                                                            title={`${booking.first_name} ${booking.last_name} | Rezerwacja #${booking.id}`}
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                alert(`Rezerwacja #${booking.id}: ${booking.first_name} ${booking.last_name}`);
                                                            }}
                                                        >
                                                            <span className="truncate flex-1 font-bold">
                                                                {booking.first_name?.[0]}. {booking.last_name}
                                                            </span>
                                                        </div>
                                                    );
                                                })
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
        </div>
    );
};

export default CalendarView;
