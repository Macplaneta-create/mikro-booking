import React, { useEffect, useState } from 'react';
import { LogsAPI } from '../services/api';
import { format } from 'date-fns';
import { pl } from 'date-fns/locale';
import { Clock, User, ChevronRight } from 'lucide-react';

interface LogEntry {
    id: number;
    reservation_id: number;
    changed_by: number;
    change_type: string;
    old_value: any;
    new_value: any;
    created_at: string;
    user_name: string;
}

interface BookingHistoryProps {
    reservationId: number;
}

const BookingHistory: React.FC<BookingHistoryProps> = ({ reservationId }) => {
    const [logs, setLogs] = useState<LogEntry[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const fetchLogs = async () => {
            try {
                setLoading(true);
                const data = await LogsAPI.getByReservation(reservationId);
                setLogs(data);
            } catch (err) {
                console.error("Failed to fetch logs", err);
                setError("Nie udało się pobrać historii zmian.");
            } finally {
                setLoading(false);
            }
        };

        fetchLogs();
    }, [reservationId]);

    if (loading) {
        return <div className="p-4 text-center text-gray-500">Ładowanie historii...</div>;
    }

    if (error) {
        return <div className="p-4 text-center text-red-500">{error}</div>;
    }

    if (logs.length === 0) {
        return <div className="p-4 text-center text-gray-500">Brak historii zmian dla tej rezerwacji.</div>;
    }

    const formatChangeValue = (val: any) => {
        if (!val) return '-';
        if (typeof val === 'object') {
            return JSON.stringify(val, null, 2);
        }
        return String(val);
    };

    const getActionLabel = (type: string) => {
        switch (type) {
            case 'created': return 'Utworzono rezerwację';
            case 'updated': return 'Zaktualizowano dane';
            case 'cancelled': return 'Anulowano rezerwację';
            case 'status_changed': return 'Zmiana statusu';
            case 'checked_in': return 'Zameldowano';
            case 'checked_out': return 'Wymeldowano';
            default: return type;
        }
    };

    return (
        <div className="space-y-4 max-h-[400px] overflow-y-auto pr-2">
            {logs.map((log) => (
                <div key={log.id} className="relative pl-6 pb-4 border-l-2 border-gray-200 last:border-l-0 last:pb-0">
                    <div className="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-brand-100 border-2 border-brand-500"></div>

                    <div className="bg-gray-50 rounded-lg p-3 text-sm">
                        <div className="flex justify-between items-start mb-2">
                            <span className="font-bold text-gray-900">{getActionLabel(log.change_type)}</span>
                            <span className="text-gray-500 text-xs flex items-center gap-1">
                                <Clock size={12} />
                                {format(new Date(log.created_at), 'd MMM HH:mm', { locale: pl })}
                            </span>
                        </div>

                        <div className="flex items-center gap-2 mb-2 text-gray-600 text-xs">
                            <User size={12} />
                            <span>{log.user_name}</span>
                        </div>

                        {/* Simple diff visualization */}
                        {(log.change_type === 'updated' || log.change_type === 'status_changed') && (
                            <div className="bg-white border border-gray-200 rounded p-2 text-xs font-mono mt-2">
                                {Object.keys(log.new_value || {}).map(key => (
                                    <div key={key} className="flex gap-2">
                                        <span className="text-gray-500">{key}:</span>
                                        {log.old_value && log.old_value[key] !== undefined && (
                                            <>
                                                <span className="text-red-600 line-through">{formatChangeValue(log.old_value[key])}</span>
                                                <ChevronRight size={10} className="text-gray-400 mt-1" />
                                            </>
                                        )}
                                        <span className="text-green-600 font-bold">{formatChangeValue(log.new_value[key])}</span>
                                    </div>
                                ))}
                            </div>
                        )}

                        {log.change_type === 'cancelled' && log.new_value?.reason && (
                            <div className="text-red-600 italic mt-1">
                                Powód: {log.new_value.reason}
                            </div>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
};

export default BookingHistory;
