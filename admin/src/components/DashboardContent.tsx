import React, { useEffect, useState } from 'react';
import { TrendingUp, Users, LogOut, CheckCircle2, Calendar, Bed, AlertCircle, Loader2, Home, LayoutList, Bell, Clock } from 'lucide-react';
import { ReservationsAPI, DashboardAPI, Reservation } from '../services/api';

interface DashboardStats {
    arrivals: number;
    departures: number;
    occupancy: number;
    revenue: string;
    totalRooms: number;
    totalBeds: number;
    activeBookings: number;
    checkedInGuests: number;
    pendingReservations: number;
    recentReservations: Array<{
        id: number;
        first_name: string;
        last_name: string;
        check_in: string;
        check_out: string;
        adults: number;
        children: number;
        status: string;
        bed_ids: number[];
        total_price: number;
        created_at: string;
    }>;
}

const DashboardContent: React.FC = () => {
    const [loading, setLoading] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [stats, setStats] = useState<DashboardStats>({
        arrivals: 0,
        departures: 0,
        occupancy: 0,
        revenue: '0 zł',
        totalRooms: 0,
        totalBeds: 0,
        activeBookings: 0,
        checkedInGuests: 0,
        pendingReservations: 0,
        recentReservations: []
    });
    const [todayArrivals, setTodayArrivals] = useState<Reservation[]>([]);
    const [todayDepartures, setTodayDepartures] = useState<Reservation[]>([]);
    const [lastUpdate, setLastUpdate] = useState<Date>(new Date());
    const [refreshError, setRefreshError] = useState(false);

    const fetchDashboardData = async (isRefresh = false) => {
        try {
            if (isRefresh) {
                setIsRefreshing(true);
                setRefreshError(false);
            } else {
                setLoading(true);
            }
            
            const today = new Date().toISOString().split('T')[0];
            
            // Fetch all data in parallel with timeout
            const [dashboardStats, arrivals, departures] = await Promise.allSettled([
                DashboardAPI.getStats(),
                ReservationsAPI.getAll({
                    check_in_from: today,
                    check_in_to: today,
                    status: ['pending', 'confirmed']
                }),
                ReservationsAPI.getAll({
                    check_out_from: today,
                    check_out_to: today,
                    status: ['checked_in']
                })
            ]);

            // Handle dashboard stats
            if (dashboardStats.status === 'fulfilled') {
                setStats({
                    arrivals: dashboardStats.value.arrivals_today || 0,
                    departures: dashboardStats.value.departures_today || 0,
                    occupancy: dashboardStats.value.occupancy_rate || 0,
                    revenue: '0 zł',
                    totalRooms: dashboardStats.value.total_rooms || 0,
                    totalBeds: dashboardStats.value.total_beds || 0,
                    activeBookings: dashboardStats.value.active_bookings || 0,
                    checkedInGuests: dashboardStats.value.checked_in_guests || 0,
                    pendingReservations: dashboardStats.value.pending_reservations || 0,
                    recentReservations: dashboardStats.value.recent_reservations || []
                });
            }

            // Handle arrivals
            if (arrivals.status === 'fulfilled') {
                setTodayArrivals(arrivals.value);
            }

            // Handle departures
            if (departures.status === 'fulfilled') {
                setTodayDepartures(departures.value);
            }

            setLastUpdate(new Date());

        } catch (error) {
            console.error("Dashboard fetch error:", error);
            if (isRefresh) {
                setRefreshError(true);
            }
        } finally {
            setLoading(false);
            setIsRefreshing(false);
        }
    };

    // Initial load
    useEffect(() => {
        fetchDashboardData(false);
    }, []);

    // Auto-refresh every 30 seconds (silent)
    useEffect(() => {
        const interval = setInterval(() => {
            fetchDashboardData(true);
        }, 30000);
        return () => clearInterval(interval);
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
            fetchDashboardData(true); // Refresh data
        } catch (error) {
            alert('Błąd podczas wymeldowania');
        }
    };

    if (loading) {
        return (
            <div className="p-20 flex flex-col items-center justify-center gap-4">
                <Loader2 className="animate-spin text-brand-600 w-12 h-12" />
                <p className="text-gray-500 text-sm">Ładowanie dashboardu...</p>
                <p className="text-gray-400 text-xs">Jeśli to trwa zbyt długo, sprawdź połączenie z bazą danych</p>
            </div>
        );
    }

    return (
        <>
            <header className="flex justify-between items-center mb-8">
                <div>
                    <h2 className="text-2xl font-bold text-gray-900">Witaj, Recepcjonisto!</h2>
                    <p className="text-gray-500 text-sm italic">Oto co dzieje się dzisiaj w hotelu.</p>
                </div>
                <div className="flex items-center gap-3">
                    {refreshError && (
                        <span className="text-xs text-red-500 flex items-center gap-1">
                            <AlertCircle size={12} /> Błąd odświeżania
                        </span>
                    )}
                    <span className="text-xs text-gray-400 flex items-center gap-1">
                        <Clock size={12} />
                        {lastUpdate.toLocaleTimeString('pl-PL')}
                    </span>
                    <button
                        onClick={() => fetchDashboardData(true)}
                        className={`p-2 rounded-lg transition-colors ${
                            isRefreshing 
                                ? 'text-brand-600 bg-brand-50' 
                                : 'text-gray-400 hover:text-brand-600 hover:bg-gray-50'
                        }`}
                        title="Odśwież dane"
                    >
                        <Loader2 size={20} className={isRefreshing ? "animate-spin" : ""} />
                    </button>
                </div>
            </header>

            {/* Stats Grid */}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-8 gap-4 mb-10">
                {[
                    { label: 'Obłożenie', value: stats.occupancy + '%', icon: TrendingUp, color: 'text-blue-600', bg: 'bg-blue-50' },
                    { label: 'Goście w hotelu', value: stats.checkedInGuests, sub: 'Aktualnie zameldowani', icon: Users, color: 'text-emerald-600', bg: 'bg-emerald-50' },
                    { label: 'Zajęte łóżka', value: stats.activeBookings, sub: 'Obłożone dziś', icon: CheckCircle2, color: 'text-purple-600', bg: 'bg-purple-50' },
                    { label: 'Przyjazdy', value: stats.arrivals, sub: 'Dziś', icon: Calendar, color: 'text-indigo-600', bg: 'bg-indigo-50' },
                    { label: 'Wyjazdy', value: stats.departures, sub: 'Dziś', icon: LogOut, color: 'text-orange-600', bg: 'bg-orange-50' },
                    { label: 'Oczekujące', value: stats.pendingReservations, sub: 'Do potwierdzenia', icon: Bell, color: stats.pendingReservations > 0 ? 'text-red-600' : 'text-gray-600', bg: stats.pendingReservations > 0 ? 'bg-red-50' : 'bg-gray-50', badge: true },
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

            {/* Recent Reservations Section */}
            <section className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-8">
                <h3 className="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <Clock size={18} className="text-brand-600" />
                    Ostatnie Rezerwacje
                </h3>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gość</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-out</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Goście</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Łóżka</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cena</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akcje</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {stats.recentReservations.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-gray-400">
                                        Brak ostatnich rezerwacji
                                    </td>
                                </tr>
                            ) : (
                                stats.recentReservations.map((res) => (
                                    <tr key={res.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-gray-900">{res.first_name} {res.last_name}</div>
                                        </td>
                                        <td className="px-4 py-3 text-gray-600">{res.check_in}</td>
                                        <td className="px-4 py-3 text-gray-600">{res.check_out}</td>
                                        <td className="px-4 py-3 text-gray-600">
                                            {res.adults} + {res.children} dzieci
                                        </td>
                                        <td className="px-4 py-3 text-gray-600">
                                            {res.bed_ids?.length > 0 ? `#${res.bed_ids.join(', ')}` : '-'}
                                        </td>
                                        <td className="px-4 py-3 font-medium text-gray-900">
                                            {res.total_price} zł
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`px-2 py-1 rounded text-xs font-medium ${
                                                res.status === 'pending' ? 'bg-amber-100 text-amber-700' :
                                                res.status === 'confirmed' ? 'bg-green-100 text-green-700' :
                                                res.status === 'checked_in' ? 'bg-blue-100 text-blue-700' :
                                                'bg-gray-100 text-gray-700'
                                            }`}>
                                                {res.status === 'pending' ? 'Oczekująca' :
                                                 res.status === 'confirmed' ? 'Potwierdzona' :
                                                 res.status === 'checked_in' ? 'Zameldowana' :
                                                 res.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <a
                                                href={`admin.php?page=mikroplaneta-booking-reservations&id=${res.id}`}
                                                className="text-brand-600 hover:text-brand-700 text-xs font-medium"
                                            >
                                                Zobacz →
                                            </a>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
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

            {/* Backup & Export Section */}
            <section className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mt-8">
                <h3 className="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <svg className="w-[18px] h-[18px] text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Backup & Export
                </h3>
                <p className="text-sm text-gray-600 mb-4">
                    Eksportuj dane rezerwacji i utwórz kopię zapasową bazy danych.
                </p>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button
                        onClick={() => {
                            const nonce = (window as any).mikroplanetaBooking?.nonce || '';
                            const apiBase = ((window as any).mikroplanetaBooking?.apiUrl || '/wp-json/mikroplaneta/v1').replace(/\/+$/, '');
                            const url = `${apiBase}/backup/export/csv?_wpnonce=${encodeURIComponent(nonce)}`;
                            window.open(url, '_blank');
                        }}
                        className="p-4 rounded-xl bg-brand-50 border border-brand-100 text-left hover:border-brand-300 hover:bg-brand-100 transition-all group"
                    >
                        <svg className="w-8 h-8 text-brand-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p className="font-bold text-sm text-gray-900">Eksport CSV</p>
                        <p className="text-xs text-gray-500">Pobierz rezerwacje do Excela</p>
                    </button>

                    <button
                        onClick={() => {
                            const nonce = (window as any).mikroplanetaBooking?.nonce || '';
                            const apiBase = ((window as any).mikroplanetaBooking?.apiUrl || '/wp-json/mikroplaneta/v1').replace(/\/+$/, '');
                            const url = `${apiBase}/backup/export/sql?_wpnonce=${encodeURIComponent(nonce)}&only_hotel=1`;
                            window.open(url, '_blank');
                        }}
                        className="p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-left hover:border-emerald-300 hover:bg-emerald-100 transition-all group"
                    >
                        <svg className="w-8 h-8 text-emerald-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                        <p className="font-bold text-sm text-gray-900">Backup Bazy</p>
                        <p className="text-xs text-gray-500">Pobierz kopię bazy SQL</p>
                    </button>

                    <button
                        onClick={async () => {
                            if (!confirm('Czy na pewno chcesz wysłać podsumowanie rezerwacji na email?')) return;
                            
                            const nonce = (window as any).mikroplanetaBooking?.nonce || '';
                            const apiBase = ((window as any).mikroplanetaBooking?.apiUrl || '/wp-json/mikroplaneta/v1').replace(/\/+$/, '');
                            const url = `${apiBase}/backup/send-daily?_wpnonce=${encodeURIComponent(nonce)}`;
                            
                            try {
                                const response = await fetch(url, { method: 'POST' });
                                const data = await response.json();
                                const successMessage = data?.data?.message || data?.message || 'Email wysłany pomyślnie';
                                const errorMessage = data?.message || data?.data?.message || 'Wystąpił błąd';

                                if (response.ok && data?.success) {
                                    alert('✅ ' + successMessage);
                                } else {
                                    alert('❌ ' + errorMessage);
                                }
                            } catch (error) {
                                alert('❌ Nie udało się wysłać emaila');
                            }
                        }}
                        className="p-4 rounded-xl bg-purple-50 border border-purple-100 text-left hover:border-purple-300 hover:bg-purple-100 transition-all group"
                    >
                        <svg className="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <p className="font-bold text-sm text-gray-900">Test Email</p>
                        <p className="text-xs text-gray-500">Wyślij podsumowanie na email</p>
                    </button>
                </div>
                <p className="text-xs text-amber-700 mt-3">
                    Test Email wymaga poprawnie skonfigurowanej wysyłki maili na serwerze (SMTP lub inny transport WordPress).
                </p>
                <p className="text-xs text-gray-500 mt-4">
                    ℹ️ Więcej ustawień backupu znajdziesz w <a href="admin.php?page=mikroplaneta-booking-settings" className="text-brand-600 hover:underline">Ustawieniach</a>.
                </p>
            </section>
        </>
    );
};

export default DashboardContent;
