/**
 * CalendarToolbar
 * 
 * Navigation (prev/next/today), visible stats, status legend, and action buttons.
 */

import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { format } from 'date-fns';
import { pl } from 'date-fns/locale';
import { StatusLegendItem } from './calendarUtils';

interface CalendarToolbarProps {
    currentDate: Date;
    onPrev: () => void;
    onNext: () => void;
    onToday: () => void;
    onNewReservation: () => void;
    stats: { reservations: number; beds: number };
    statusLegend: StatusLegendItem[];
}

const CalendarToolbar: React.FC<CalendarToolbarProps> = ({
    currentDate,
    onPrev,
    onNext,
    onToday,
    onNewReservation,
    stats,
    statusLegend,
}) => {
    return (
        <div className="flex items-center justify-between p-4 border-b border-gray-100 bg-white z-30">
            <div className="flex items-center gap-4">
                <h2 className="text-xl font-bold text-gray-800 capitalize">
                    {format(currentDate, 'LLLL yyyy', { locale: pl })}
                </h2>
                <div className="flex bg-gray-100 rounded-lg p-1">
                    <button onClick={onPrev} className="p-1 hover:bg-white rounded-md shadow-sm transition"><ChevronLeft size={20} /></button>
                    <button onClick={onToday} className="px-3 text-sm font-medium">Dziś</button>
                    <button onClick={onNext} className="p-1 hover:bg-white rounded-md shadow-sm transition"><ChevronRight size={20} /></button>
                </div>
            </div>

            <div className="flex flex-col gap-2">
                <div className="flex gap-3 text-xs text-brand-700 font-medium bg-brand-50 px-3 py-1.5 rounded-full border border-brand-100">
                    <span className="flex items-center gap-2">✨ Kliknij datę przyjazdu i wyjazdu | Ctrl+klik aby dodać więcej łóżek (rezerwacja grupowa)</span>
                    <span className="text-brand-300">•</span>
                    <span>
                        {`Rezerwacje: ${stats.reservations} • Zajęte łóżka: ${stats.beds} `}
                    </span>
                </div>
                {statusLegend.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 text-[11px] text-gray-600">
                        <span className="font-bold text-gray-500 uppercase tracking-wide">Status rezerwacji:</span>
                        {statusLegend.map((statusItem) => (
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
                    onClick={onNewReservation}
                    className="bg-brand-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-700 transition shadow-md"
                >
                    + Rezerwacja
                </button>
            </div>
        </div>
    );
};

export default CalendarToolbar;
