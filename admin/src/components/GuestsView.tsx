import React, { useState, useEffect } from 'react';
import { Users, Search, Mail, Phone, Calendar, ArrowRight, Loader2, UserPlus } from 'lucide-react';
import { GuestsAPI, Guest } from '../services/api';

const GuestsView: React.FC = () => {
    const [guests, setGuests] = useState<Guest[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchQuery, setSearchQuery] = useState('');
    const [stats, setStats] = useState({ total_guests: 0, returning_guests: 0 });

    useEffect(() => {
        fetchData();
        fetchStats();
    }, []);

    const fetchData = async (query = '') => {
        setLoading(true);
        try {
            const data = await GuestsAPI.getAll(query ? { search: query } : {});
            setGuests(data);
        } catch (error) {
            console.error("Failed to fetch guests", error);
        } finally {
            setLoading(false);
        }
    };

    const fetchStats = async () => {
        try {
            const data = await GuestsAPI.getStats();
            setStats(data);
        } catch (error) {
            console.error("Failed to fetch guest stats", error);
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        fetchData(searchQuery);
    };

    return (
        <div className="space-y-8">
            {/* Stats Summary */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <div className="flex items-center gap-4 mb-2">
                        <div className="p-2 bg-blue-50 text-blue-600 rounded-lg">
                            <Users size={20} />
                        </div>
                        <span className="text-gray-500 font-medium font-size-sm">Łącznie Gości</span>
                    </div>
                    <p className="text-3xl font-bold text-gray-900">{stats.total_guests}</p>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <div className="flex items-center gap-4 mb-2">
                        <div className="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                            <Calendar size={20} />
                        </div>
                        <span className="text-gray-500 font-medium font-size-sm">Powracający Goście</span>
                    </div>
                    <p className="text-3xl font-bold text-gray-900">{stats.returning_guests}</p>
                </div>
                <div className="bg-brand-600 p-6 rounded-2xl shadow-lg text-white flex flex-col justify-between">
                    <div>
                        <p className="font-bold text-lg mb-1">Poznaj swoich gości</p>
                        <p className="text-brand-100 text-sm">Automatycznie śledzimy historię pobytów i preferencje.</p>
                    </div>
                    <UserPlus size={24} className="self-end opacity-50" />
                </div>
            </div>

            {/* View Actions */}
            <div className="flex flex-col md:flex-row gap-4 items-center justify-between">
                <form onSubmit={handleSearch} className="relative w-full md:w-96">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Szukaj po nazwisku, emailu..."
                        className="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent outline-none transition-all shadow-sm"
                    />
                </form>
                <div className="flex gap-3">
                    <button className="px-4 py-2 border border-gray-200 rounded-xl text-gray-600 bg-white hover:bg-gray-50 font-medium transition-all shadow-sm">
                        Eksportuj CSV
                    </button>
                    <button className="px-4 py-2 bg-brand-600 text-white rounded-xl font-medium hover:bg-brand-700 transition-all shadow-md">
                        + Dodaj Gościa
                    </button>
                </div>
            </div>

            {/* Guests Table/List */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                {loading ? (
                    <div className="p-20 flex justify-center"><Loader2 className="animate-spin text-brand-600" /></div>
                ) : guests.length === 0 ? (
                    <div className="p-20 text-center">
                        <Users size={48} className="mx-auto text-gray-200 mb-4" />
                        <p className="text-gray-500">Nie znaleziono gości w bazie.</p>
                    </div>
                ) : (
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-gray-50 border-b border-gray-100">
                                <th className="p-4 font-semibold text-gray-600 text-sm">Gość</th>
                                <th className="p-4 font-semibold text-gray-600 text-sm">Kontakt</th>
                                <th className="p-4 font-semibold text-gray-600 text-sm">Historia</th>
                                <th className="p-4 font-semibold text-gray-600 text-sm">Ostatni Pobyt</th>
                                <th className="p-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {guests.map((guest) => (
                                <tr key={guest.id} className="border-b border-gray-50 hover:bg-gray-50/50 transition-colors group">
                                    <td className="p-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 bg-brand-50 text-brand-600 rounded-full flex items-center justify-center font-bold">
                                                {guest.first_name[0]}{guest.last_name[0]}
                                            </div>
                                            <div>
                                                <p className="font-bold text-gray-900">{guest.first_name} {guest.last_name}</p>
                                                <p className="text-xs text-brand-600 font-medium">#{guest.id}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="p-4 space-y-1">
                                        <div className="flex items-center gap-2 text-sm text-gray-600">
                                            <Mail size={14} className="text-gray-400" />
                                            {guest.email}
                                        </div>
                                        {guest.phone && (
                                            <div className="flex items-center gap-2 text-sm text-gray-600">
                                                <Phone size={14} className="text-gray-400" />
                                                {guest.phone}
                                            </div>
                                        )}
                                    </td>
                                    <td className="p-4">
                                        <div className="flex flex-col">
                                            <span className="text-sm font-bold text-gray-800">{guest.total_stays} pobytów</span>
                                            <span className="text-xs text-emerald-600 font-medium">VIP Status</span>
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        <span className="text-sm text-gray-600">{guest.last_stay_date || 'Brak danych'}</span>
                                    </td>
                                    <td className="p-4 text-right">
                                        <button className="p-2 text-gray-400 hover:text-brand-600 transition-colors opacity-0 group-hover:opacity-100">
                                            <ArrowRight size={18} />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
};

export default GuestsView;
