import React, { useEffect, useState } from 'react';
import { TrendingUp, Users, LogOut, CheckCircle2, Calendar, Bed, AlertCircle, Loader2 } from 'lucide-react';
import { ReservationsAPI, AvailabilityAPI, Reservation } from '../services/api';

const DashboardContent: React.FC = () => {
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState({
        arrivals: 0,
        departures: 0,
        occupancy: 0,
        revenue: '0 zł'
    });
    const [todayArrivals, setTodayArrivals] = useState<Reservation[]>([]);

    useEffect(() => {
        const fetchDashboardData = async () => {
            try {
                // Get today's date YYYY-MM-DD
                const today = new Date().toISOString().split('T')[0];

                // Fetch Arrivals
                const arrivals = await ReservationsAPI.getAll({
                    check_in_from: today,
                    check_in_to: today,
                    status: ['confirmed', 'checked_in'] // Pending might count too?
                });

                // Fetch Departures
                const departures = await ReservationsAPI.getAll({
                    check_out_from: today,
                    check_out_to: today,
                    status: 'checked_in'
                });

                // Fetch Occupancy
                const occupancyData = await AvailabilityAPI.getOccupancy(
                    { start_date: today, end_date: today }
                );

                setStats({
                    arrivals: arrivals.length,
                    departures: departures.length,
                    occupancy: occupancyData?.rate || 0,
                    revenue: '0 zł' // Need revenue endpoint
                });

                setTodayArrivals(arrivals.slice(0, 5)); // Limit to 5

            } catch (error) {
                console.error("Dashboard fetch error:", error);
            } finally {
                setLoading(false);
            }
        };

        fetchDashboardData();
    }, []);

    if (loading) {
        return <div className="p-20 flex justify-center"><Loader2 className="animate-spin text-brand-600" /></div>;
    }

    return (
        <>
            <header className="flex justify-between items-center mb-8">
                <div>
                    <h2 className="text-2xl font-bold text-gray-900">Witaj, Recepcjonisto!</h2>
                    <p className="text-gray-500 text-sm italic">Oto co dzieje się dzisiaj w hotelu.</p>
                </div>
            </header>

            {/* Stats Grid */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                {[
                    { label: 'Obłożenie', value: stats.occupancy + '%', trend: '', icon: TrendingUp, color: 'text-blue-600', bg: 'bg-blue-50' },
                    { label: 'Przyjazdy (Dziś)', value: stats.arrivals, sub: 'Gości czekających', icon: Users, color: 'text-emerald-600', bg: 'bg-emerald-50' },
                    { label: 'Wyjazdy (Dziś)', value: stats.departures, sub: 'Pokoi do sprzątania', icon: LogOut, color: 'text-orange-600', bg: 'bg-orange-50' },
                    { label: 'Przychód (Dziś)', value: stats.revenue, trend: '', icon: CheckCircle2, color: 'text-purple-600', bg: 'bg-purple-50' },
                ].map((stat, i) => (
                    <div key={i} className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow">
                        <div className="flex justify-between items-start mb-4">
                            <div className={`p-2 rounded-lg ${stat.bg} ${stat.color}`}>
                                <stat.icon size={20} />
                            </div>
                        </div>
                        <h3 className="text-gray-500 text-sm font-medium mb-1">{stat.label}</h3>
                        <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
                        {stat.sub && <p className="text-xs text-gray-400 mt-1">{stat.sub}</p>}
                    </div>
                ))}
            </div>

            {/* Action Center */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <section className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 className="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <Users size={18} className="text-brand-600" />
                        Dzisiejsze Przyjazdy
                    </h3>
                    <div className="space-y-4">
                        {todayArrivals.length === 0 ? (
                            <p className="text-gray-400 text-center py-4">Brak przyjazdów na dzisiaj.</p>
                        ) : (
                            todayArrivals.map(res => (
                                <div key={res.id} className="flex items-center justify-between p-4 rounded-xl border border-gray-50 hover:bg-gray-50 transition-colors cursor-pointer group">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center font-bold text-gray-500">
                                            #{res.id}
                                        </div>
                                        <div>
                                            <p className="font-semibold text-gray-900">Rezerwacja #{res.id}</p>
                                            <p className="text-xs text-gray-500">Łóżko {res.bed_id}</p>
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => res.id && (window.location.href = `admin.php?page=mikroplaneta-booking-reservations&id=${res.id}`)}
                                        className="bg-brand-600 text-white px-4 py-2 rounded-lg text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        Szczegóły
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                </section>

                <section className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 className="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <AlertCircle size={18} className="text-orange-600" />
                        Szybkie Akcje
                    </h3>
                    <div className="grid grid-cols-2 gap-4">
                        <button
                            onClick={() => window.location.href = 'admin.php?page=mikroplaneta-booking-reservations'}
                            className="p-4 rounded-xl bg-gray-50 border border-gray-100 text-left hover:border-brand-200 hover:bg-brand-50 transition-all group"
                        >
                            <Calendar className="text-brand-600 mb-2" size={20} />
                            <p className="font-bold text-sm text-gray-900">Kalendarz</p>
                            <p className="text-xs text-gray-500">Przejdź do rezerwacji</p>
                        </button>
                        <button
                            onClick={() => window.location.href = 'admin.php?page=mikroplaneta-booking-rooms'}
                            className="p-4 rounded-xl bg-gray-50 border border-gray-100 text-left hover:border-brand-200 hover:bg-brand-50 transition-all group"
                        >
                            <Bed className="text-orange-600 mb-2" size={20} />
                            <p className="font-bold text-sm text-gray-900">Pokoje</p>
                            <p className="text-xs text-gray-500">Zarządzaj inventory</p>
                        </button>
                    </div>
                </section>
            </div>
        </>
    );
};

export default DashboardContent;
