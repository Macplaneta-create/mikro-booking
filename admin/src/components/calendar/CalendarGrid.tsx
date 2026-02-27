/**
 * CalendarGrid
 * 
 * Renders the room/bed grid with day columns, booking bars, and selection preview.
 * This is the main visual body of the calendar.
 */

import React from 'react';
import { BedDouble, ChevronDown, ChevronRight as ChevronRightIcon, Home, Check } from 'lucide-react';
import { format, isSameDay, isAfter, isBefore, differenceInDays } from 'date-fns';
import { pl } from 'date-fns/locale';
import { Room, Reservation } from '../../services/api';
import {
    getStatusColor,
    getStatusLabel,
    getBookingSlices,
    buildRoomLanes,
    getRoomBookingStyle,
    getRoomReservations,
} from './calendarUtils';

// --- Types ---

export interface SelectionState {
    bedId: number;
    roomId: number;
    start: Date | null;
    end: Date | null;
}

interface CalendarGridProps {
    rooms: Room[];
    bookings: Reservation[];
    days: Date[];
    startDate: Date;
    loading: boolean;

    // Room expand/collapse
    expandedRooms: Record<number, boolean>;
    onToggleRoom: (roomId: number) => void;

    // Selection
    selection: SelectionState | null;
    selectedBeds: Set<number>;
    onCellClick: (bedId: number, roomId: number, date: Date, event: React.MouseEvent) => void;
    onConfirmSelection: () => void;

    // Booking details
    onBookingClick: (reservation: Reservation) => void;
}

const FIRST_COLUMN_CLASS = 'w-48 min-w-48 max-w-48 flex-none';

const CalendarGrid: React.FC<CalendarGridProps> = ({
    rooms,
    bookings,
    days,
    startDate,
    loading,
    expandedRooms,
    onToggleRoom,
    selection,
    selectedBeds,
    onCellClick,
    onConfirmSelection,
    onBookingClick,
}) => {
    const dayCount = days.length;
    const isWeekendDay = (day: Date) => day.getDay() === 0 || day.getDay() === 6;

    return (
        <div className="flex-1 overflow-auto relative">
            <div className="min-w-[1200px]">
                {/* Day headers */}
                <div className="flex border-b border-gray-200 sticky top-0 bg-white z-20 shadow-sm">
                    <div className={`${FIRST_COLUMN_CLASS} p-3 font-semibold text-gray-500 bg-gray-50 border-r border-gray-200 sticky left-0 z-20 flex items-center`}>
                        Pokoje i Łóżka
                    </div>
                    {days.map((day, index) => (
                        <div
                            key={day.toString()}
                            className={`flex-1 min-w-[80px] p-2 text-center border-r border-gray-100 ${
                                isWeekendDay(day)
                                    ? 'bg-amber-100/70'
                                    : index % 2 === 1
                                        ? 'bg-slate-100/75'
                                        : 'bg-white'
                            } ${isSameDay(day, new Date()) ? 'ring-1 ring-inset ring-brand-200 bg-brand-50/80' : ''}`}
                        >
                            <div className="text-[10px] text-gray-400 font-bold uppercase">{format(day, 'EEE', { locale: pl })}</div>
                            <div className={`text-sm font-bold ${isSameDay(day, new Date()) ? 'text-brand-600' : isWeekendDay(day) ? 'text-amber-700' : 'text-gray-700'}`}>
                                {format(day, 'd')}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Room rows */}
                {rooms.map(room => {
                    const isExpanded = !!expandedRooms[room.id!];
                    const bedCount = room.beds?.length || 0;
                    const roomReservations = getRoomReservations(room, bookings);
                    const roomLanes = buildRoomLanes(roomReservations);
                    const maxLanes = 3;
                    const hiddenLanes = Math.max(0, roomLanes.length - maxLanes);

                    return (
                        <React.Fragment key={room.id}>
                            {/* Room header (collapsed view) */}
                            <div
                                className="flex bg-gray-50/50 border-b border-gray-200 sticky left-0 group cursor-pointer hover:bg-gray-100/80 transition-colors h-12"
                                onClick={() => onToggleRoom(room.id!)}
                            >
                                <div className={`${FIRST_COLUMN_CLASS} p-2 border-r border-gray-200 sticky left-0 bg-gray-50 z-10 flex items-center gap-2 pl-3`}>
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
                                    {/* Day background guides to improve day separation in collapsed room view */}
                                    <div className="absolute inset-0 flex pointer-events-none">
                                        {days.map((day, index) => (
                                            <div
                                                key={`room-bg-${room.id}-${day.toString()}`}
                                                className={`flex-1 min-w-[80px] border-r border-gray-200/70 ${
                                                    isWeekendDay(day)
                                                        ? 'bg-amber-100/55'
                                                        : index % 2 === 1
                                                            ? 'bg-slate-100/60'
                                                            : 'bg-transparent'
                                                }`}
                                            />
                                        ))}
                                    </div>
                                    {!isExpanded && roomLanes.slice(0, maxLanes).map((lane, laneIndex) => (
                                        lane.items.map((reservation) => {
                                            const style = getRoomBookingStyle(reservation.check_in, reservation.check_out, startDate, dayCount);
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

                            {/* Expanded bed rows */}
                            {isExpanded && room.beds?.map(bed => (
                                <BedRow
                                    key={bed.id}
                                    bed={bed}
                                    room={room}
                                    days={days}
                                    startDate={startDate}
                                    dayCount={dayCount}
                                    bookings={bookings}
                                    selection={selection}
                                    selectedBeds={selectedBeds}
                                    onCellClick={onCellClick}
                                    onConfirmSelection={onConfirmSelection}
                                    onBookingClick={onBookingClick}
                                />
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
    );
};

// --- BedRow sub-component ---

interface BedRowProps {
    bed: { id?: number; bed_number: number; bed_type: string };
    room: Room;
    days: Date[];
    startDate: Date;
    dayCount: number;
    bookings: Reservation[];
    selection: SelectionState | null;
    selectedBeds: Set<number>;
    onCellClick: (bedId: number, roomId: number, date: Date, event: React.MouseEvent) => void;
    onConfirmSelection: () => void;
    onBookingClick: (reservation: Reservation) => void;
}

const BedRow: React.FC<BedRowProps> = ({
    bed,
    room,
    days,
    startDate,
    dayCount,
    bookings,
    selection,
    selectedBeds,
    onCellClick,
    onConfirmSelection,
    onBookingClick,
}) => {
    const isWeekendDay = (day: Date) => day.getDay() === 0 || day.getDay() === 6;
    const bedBookings = bookings
        .filter(b => bed.id && b.bed_ids?.includes(bed.id) && b.status !== 'cancelled')
        .flatMap(booking => getBookingSlices(booking, startDate, dayCount));

    const isSelected = selectedBeds.has(bed.id!);
    const showSelectionPreview = selection && (selection.bedId === bed.id || selectedBeds.has(bed.id!)) && selection.start;

    return (
        <div className="flex border-b border-gray-100 h-14 relative hover:bg-gray-50/30 transition-colors group/row">
            {/* Bed label */}
            <div className={`${FIRST_COLUMN_CLASS} px-4 border-r border-gray-200 sticky left-0 z-10 flex items-center gap-3 text-sm text-gray-600 pl-8 ${isSelected ? 'bg-brand-50 border-l-4 border-l-brand-500' : 'bg-white border-l-4 border-l-brand-100/50'}`}>
                <div className="w-5 h-5 bg-gray-50 border border-gray-100 rounded text-[9px] flex items-center justify-center font-bold text-gray-500 group-hover/row:bg-brand-50 group-hover/row:text-brand-600 transition-colors">
                    {bed.bed_number}
                </div>
                <div className="flex flex-col">
                    <span className="text-[11px] font-medium text-gray-700">Miejsce {bed.bed_number}</span>
                    <span className="text-[9px] text-gray-400 capitalize">{bed.bed_type}</span>
                </div>
            </div>

            {/* Day cells + booking bars */}
            <div className="flex-1 flex relative">
                {days.map((day, index) => (
                    <div
                        key={day.toString()}
                        onClick={(e) => onCellClick(bed.id!, room.id!, day, e)}
                        className={`flex-1 min-w-[80px] border-r border-gray-200 relative cursor-pointer hover:bg-brand-50/20 transition-colors ${
                            isWeekendDay(day)
                                ? 'bg-amber-100/50'
                                : index % 2 === 1
                                    ? 'bg-slate-100/45'
                                    : ''
                        } ${isSameDay(day, new Date()) ? 'bg-brand-50/20 ring-1 ring-inset ring-brand-100' : ''}`}
                    >
                        {/* Noon marker */}
                        <div className="absolute left-1/2 top-1 bottom-1 w-px border-l border-dashed border-gray-300 pointer-events-none opacity-40" />
                    </div>
                ))}

                {/* Selection preview */}
                {showSelectionPreview && (
                    <>
                        {/* Start Day Indicator */}
                        <div
                            className="absolute top-1 bottom-1 bg-emerald-600 rounded-l-md z-20 flex items-center justify-center text-[10px] text-white font-bold animate-in fade-in pointer-events-none"
                            style={{
                                left: `${(differenceInDays(selection!.start!, startDate)) * (100 / dayCount)}%`,
                                width: `${100 / dayCount}%`,
                            }}
                        >
                            PRZYJAZD
                        </div>

                        {selection!.end && (
                            <>
                                {/* Range highlight */}
                                {days.map(day => {
                                    if (isAfter(day, selection!.start!) && isBefore(day, selection!.end!)) {
                                        return (
                                            <div
                                                key={`range-${day.toString()}`}
                                                className="absolute top-1.5 bottom-1.5 bg-brand-200/60 z-10 pointer-events-none"
                                                style={{
                                                    left: `${(differenceInDays(day, startDate)) * (100 / dayCount)}%`,
                                                    width: `${100 / dayCount}%`,
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
                                        left: `${(differenceInDays(selection!.end!, startDate)) * (100 / dayCount)}%`,
                                        width: `${100 / dayCount}%`,
                                    }}
                                >
                                    WYJAZD
                                </div>

                                {/* CTA Button - only on the primary selected bed */}
                                {selection!.bedId === bed.id && (
                                    <div
                                        className="absolute -bottom-8 left-1/2 -translate-x-1/2 z-[100] animate-in slide-in-from-top-2"
                                        style={{ left: `${(differenceInDays(selection!.start!, startDate) + differenceInDays(selection!.end!, selection!.start!) / 2 + 0.5) * (100 / dayCount)}%` }}
                                    >
                                        <button
                                            onClick={(e) => { e.stopPropagation(); onConfirmSelection(); }}
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

                {/* Booking bars */}
                {bedBookings.map(slice => (
                    <div
                        key={slice.key}
                        className={slice.className}
                        style={slice.style}
                        title={`${slice.booking.first_name} ${slice.booking.last_name} | Rezerwacja #${slice.booking.id}`}
                        onClick={(e) => {
                            e.stopPropagation();
                            onBookingClick(slice.booking);
                        }}
                    >
                        {slice.showLabel && (
                            <span className="truncate flex-1 font-bold px-1">
                                {slice.booking.first_name?.[0]}. {slice.booking.last_name}
                            </span>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default CalendarGrid;
