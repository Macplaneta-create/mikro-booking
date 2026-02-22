import React, { useEffect, useState } from 'react';
import { TrendingUp, Users, LogOut, CheckCircle2, Calendar, Bed, AlertCircle, Loader2, Home, LayoutList } from 'lucide-react';
import { ReservationsAPI, DashboardAPI, Reservation } from '../services/api';

const DashboardContent: React.FC = () => {
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState({
        arrivals: 0,
        departures: 0,
        occupancy: 0,
        revenue: '0 zł',
        totalRooms: 0,
        totalBeds: 0,
        activeBookings: 0,
        checkedInGuests: 0
    });
    const [todayArrivals, setTodayArrivals] = useState<Reservation[]>([]);
    const [todayDepartures, setTodayDepartures] = useState<Reservation[]>([]);

    const fetchDashboardData = async () => {
        try {
            setLoading(true);
            const today = new Date().toISOString().split('T')[0];
            const dashboardStats = await DashboardAPI.getStats();

            // Fetch Today's Arrivals (including pending)
            const arrivals = await ReservationsAPI.getAll({
                check_in_from: today,
                check_in_to: today,
                status: ['pending', 'confirmed']
            });

            // Fetch Today's Departures
            const departures = await ReservationsAPI.getAll({
                check_out_from: today,
                check_out_to: today,
                status: ['checked_in']
            });

            setStats({
                arrivals: dashboardStats.arrivals_today || 0,
                departures: dashboardStats.departures_today || 0,
                occupancy: dashboardStats.occupancy_rate || 0,
                revenue: '0 zł',
                totalRooms: dashboardStats.total_rooms || 0,
                totalBeds: dashboardStats.total_beds || 0,
                activeBookings: dashboardStats.active_bookings || 0,
                checkedInGuests: dashboardStats.checked_in_guests || 0
            });

            setTodayArrivals(arrivals);
            setTodayDepartures(departures);

        } catch (error) {
            console.error("Dashboard fetch error:", error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchDashboardData();
    }, []);

    const handleCheckIn = async (reservation: Reservation) => {
        const totalGuests = (reservation.adults || 0) + (reservation.children || 0);
        let adjustment: any = null;

        if (totalGuests > 1) {
            const actualCount = window.prompt(`Rezerwacja na ${totalGuests} osób. Ilu gości przyjechało faktycznie?`, totalGuests.toString());

            if (actualCount === null) return; // Anulowane przez użytkownika

            const newCount = parseInt(actualCount);
            if (!isNaN(newCount) && newCount < totalGuests && newCount > 0) {
                const releaseBeds = window.confirm(`Przyjechało mniej osób (${newCount} z ${totalGuests}). Czy chcesz zwolnić nadmiarowe łóżka, aby były dostępne dla innych?`);

                if (releaseBeds) {
                    // Automatycznie zostawiamy pierwsze 'newCount' łóżek
                    const bedsToKeep = reservation.bed_ids?.slice(0, newCount) || [];
                    adjustment = {
                        adults: Math.min(newCount, reservation.adults || 0),
                        children: Math.max(0, newCount - (reservation.adults || 0)),
                        bed_ids: bedsToKeep
                    };
                } else {
                    // Zmieniamy tylko liczbę osób, łóżka zostają zablokowane
                    adjustment = {
                        adults: Math.min(newCount, reservation.adults || 0),
                        children: Math.max(0, newCount - (reservation.adults || 0))
                    };
                }
            } else if (newCount > totalGuests) {
                alert('Nie można zameldować więcej osób niż zarezerwowano w tym widoku. Edytuj rezerwację w kalendarzu.');
                return;
            }
        }

        try {
            await ReservationsAPI.checkIn(reservation.id!, adjustment);
            fetchDashboardData();
        } catch (error) {
            console.error(error);
            alert('Błąd podczas zameldowania');
        }
    };

    const handleCheckOut = async (id: number) => {
        try {
            await ReservationsAPI.checkOut(id);
            fetchDashboardData();
        } catch (error) {
            alert('Błąd podczas wymeldowania');
        }
    };

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
                <button
                    onClick={fetchDashboardData}
                    className="p-2 text-gray-400 hover:text-brand-600 transition-colors"
                >
                    <Loader2 size={20} className={loading ? "animate-spin" : ""} />
                </button>
            </header>

            {/* Stats Grid */}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4 mb-10">
                {[
                    { label: 'Obłożenie', value: stats.occupancy + '%', icon: TrendingUp, color: 'text-blue-600', bg: 'bg-blue-50' },
                    { label: 'Goście w hotelu', value: stats.checkedInGuests, sub: 'Aktualnie zameldowani', icon: Users, color: 'text-emerald-600', bg: 'bg-emerald-50' },
                    { label: 'Zajęte łóżka', value: stats.activeBookings, sub: 'Obłożone dziś', icon: CheckCircle2, color: 'text-purple-600', bg: 'bg-purple-50' },
                    { label: 'Przyjazdy', value: stats.arrivals, sub: 'Dziś', icon: Calendar, color: 'text-indigo-600', bg: 'bg-indigo-50' },
                    { label: 'Wyjazdy', value: stats.departures, sub: 'Dziś', icon: LogOut, color: 'text-orange-600', bg: 'bg-orange-50' },
                    { label: 'Pokoje', value: stats.totalRooms, icon: Home, color: 'text-gray-600', bg: 'bg-gray-50' },
                    { label: 'Łóżka', value: stats.totalBeds, icon: LayoutList, color: 'text-pink-600', bg: 'bg-pink-50' },
                ].map((stat, i) => (
                    <div key={i} className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                        <div className={`w-10 h-10 rounded-lg ${stat.bg} ${stat.color} flex items-center justify-center mb-4`}>
                            <stat.icon size={20} />
                        </div>
                        <h3 className="text-gray-500 text-xs font-medium mb-1 uppercase tracking-wider">{stat.label}</h3>
                        <p className="text-xl font-extrabold text-gray-900">{stat.value}</p>
                        {stat.sub && <p className="text-[10px] text-gray-400 mt-1">{stat.sub}</p>}
                    </div>
                ))}
            </div>

            {/* Action Center */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* Arrivals Section */}
                <section className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 className="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <Users size={18} className="text-emerald-600" />
                        Dzisiejsze Przyjazdy
                    </h3>
                    <div className="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        {todayArrivals.length === 0 ? (
                            <p className="text-gray-400 text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                Brak przyjazdów na dzisiaj.
                            </p>
                        ) : (
                            todayArrivals.map(res => (
                                <div key={res.id} className="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-emerald-200 hover:bg-emerald-50/30 transition-all group">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center font-bold">
                                            {res.first_name?.[0]}{res.last_name?.[0]}
                                        </div>
                                        <div>
                                            <p className="font-bold text-gray-900">{res.first_name} {res.last_name}</p>
                                            <p className="text-xs text-gray-500 flex items-center gap-1">
                                                <Bed size={12} /> Łóżko {res.bed_ids?.join(', ')}
                                                <span className={`ml-2 px-1.5 py-0.5 rounded text-[10px] ${res.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'}`}>
                                                    {res.status === 'pending' ? 'Oczekująca' : 'Potwierdzona'}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button
                                            onClick={() => handleCheckIn(res)}
                                            className="bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-emerald-700 transition shadow-sm"
                                        >
                                            Zamelduj
                                        </button>
                                        <button
                                            onClick={() => window.location.href = `admin.php?page=mikroplaneta-booking-reservations&id=${res.id}`}
                                            className="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-white rounded-lg transition-colors border border-transparent hover:border-gray-200"
                                            title="Pokaż w kalendarzu"
                                        >
                                            <Calendar size={16} />
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </section>

                {/* Departures Section */}
                <section className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 className="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <LogOut size={18} className="text-orange-600" />
                        Dzisiejsze Wyjazdy
                    </h3>
                    <div className="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        {todayDepartures.length === 0 ? (
                            <p className="text-gray-400 text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                Brak wyjazdów na dzisiaj.
                            </p>
                        ) : (
                            todayDepartures.map(res => (
                                <div key={res.id} className="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-orange-200 hover:bg-orange-50/30 transition-all group">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 bg-orange-100 text-orange-700 rounded-full flex items-center justify-center font-bold">
                                            {res.first_name?.[0]}{res.last_name?.[0]}
                                        </div>
                                        <div>
                                            <p className="font-bold text-gray-900">{res.first_name} {res.last_name}</p>
                                            <p className="text-xs text-gray-500 flex items-center gap-1">
                                                <Bed size={12} /> Łóżko {res.bed_ids?.join(', ')}
                                                <span className="ml-2 px-1.5 py-0.5 rounded text-[10px] bg-blue-100 text-blue-700">
                                                    Zameldowany
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button
                                            onClick={() => handleCheckOut(res.id!)}
                                            className="bg-orange-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-orange-700 transition shadow-sm"
                                        >
                                            Wymelduj
                                        </button>
                                        <button
                                            onClick={() => window.location.href = `admin.php?page=mikroplaneta-booking-reservations&id=${res.id}`}
                                            className="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-white rounded-lg transition-colors border border-transparent hover:border-gray-200"
                                            title="Pokaż w kalendarzu"
                                        >
                                            <Calendar size={16} />
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </section>
            </div>

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
        </>
    );
};

export default DashboardContent;
