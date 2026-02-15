import React, { useState, useEffect } from 'react';
import { X, Search, Save, Loader2, UserPlus, Users, Baby, Calendar as CalendarIcon, MapPin, CreditCard, Coins } from 'lucide-react';
import { AvailabilityAPI, GuestsAPI, ReservationsAPI, PricingAPI, Guest, Room } from '../services/api';
import { format, parseISO } from 'date-fns';
import { pl } from 'date-fns/locale';

interface ReservationModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    initialData?: {
        bedId?: number;
        bedIds?: number[];
        checkIn?: string;
        checkOut?: string;
        roomId?: number;
    };
    rooms: Room[];
}

const ReservationModal: React.FC<ReservationModalProps> = ({ isOpen, onClose, onSuccess, initialData, rooms }) => {
    const [loading, setLoading] = useState(false);
    const [step, setStep] = useState(1); // 1: Select Guest, 2: Details

    // Form State
    const [selectedGuest, setSelectedGuest] = useState<Guest | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<Guest[]>([]);
    const [searching, setSearching] = useState(false);

    const [formData, setFormData] = useState({
        bed_id: 0,
        room_id: 0,
        check_in: format(new Date(), 'yyyy-MM-dd'),
        check_out: format(new Date(Date.now() + 86400000), 'yyyy-MM-dd'),
        status: 'pending',
        adults: 1,
        children: 0,
        notes: '',
        total_price: 0
    });

    // Guest Creation State
    const [isCreatingGuest, setIsCreatingGuest] = useState(false);
    const [newGuestData, setNewGuestData] = useState({
        first_name: '',
        last_name: '',
        email: '',
        phone: ''
    });

    // Update form when initial data changes or modal opens
    useEffect(() => {
        if (isOpen) {
            setFormData(prev => ({
                ...prev,
                bed_id: initialData?.bedId || 0,
                room_id: initialData?.roomId || 0,
                check_in: initialData?.checkIn || format(new Date(), 'yyyy-MM-dd'),
                check_out: initialData?.checkOut || format(new Date(Date.now() + 86400000), 'yyyy-MM-dd'),
                adults: initialData?.bedIds?.length || 1,
                children: 0
            }));
            setStep(1);
            setSelectedGuest(null);
            setSearchQuery('');
            setIsCreatingGuest(false);
            setNewGuestData({ first_name: '', last_name: '', email: '', phone: '' });
        }
    }, [isOpen, initialData]);

    // --- DYNAMIC PRICING CALCULATION ---
    const [pricingLoading, setPricingLoading] = useState(false);
    const [nightsCount, setNightsCount] = useState(0);

    useEffect(() => {
        if (!isOpen) return;

        const calculatePrice = async () => {
            const bedIds = initialData?.bedIds?.length
                ? initialData.bedIds
                : (formData.bed_id ? [formData.bed_id] : []);

            if (bedIds.length === 0 || !formData.check_in || !formData.check_out) {
                setFormData(f => ({ ...f, total_price: 0 }));
                setNightsCount(0);
                return;
            }

            // Don't calculate if check-out <= check-in
            if (parseISO(formData.check_out) <= parseISO(formData.check_in)) {
                setFormData(f => ({ ...f, total_price: 0 }));
                setNightsCount(0);
                return;
            }

            setPricingLoading(true);
            try {
                let total = 0;
                let nights = 0;

                for (const bid of bedIds) {
                    const priceData = await PricingAPI.calculate({
                        bed_id: bid,
                        check_in: formData.check_in,
                        check_out: formData.check_out
                    });
                    total += priceData.total;
                    nights = priceData.nights;
                }

                setFormData(f => ({ ...f, total_price: total }));
                setNightsCount(nights);
            } catch (error) {
                console.error('Pricing calculation error:', error);
            } finally {
                setPricingLoading(false);
            }
        };

        const timeoutId = setTimeout(calculatePrice, 300); // Debounce to avoid too many requests
        return () => clearTimeout(timeoutId);
    }, [isOpen, formData.check_in, formData.check_out, formData.bed_id, initialData?.bedIds]);

    // Simple guest search
    useEffect(() => {
        if (searchQuery.length > 2) {
            const delayDebounceFn = setTimeout(async () => {
                setSearching(true);
                try {
                    const results = await GuestsAPI.getAll({ search: searchQuery });
                    setSearchResults(results);
                } catch (e) {
                    console.error(e);
                } finally {
                    setSearching(false);
                }
            }, 300);
            return () => clearTimeout(delayDebounceFn);
        } else {
            setSearchResults([]);
        }
    }, [searchQuery]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedGuest) return;

        setLoading(true);
        try {
            const totalGuests = Math.max(1, formData.adults + formData.children);
            let bedIds = initialData?.bedIds && initialData.bedIds.length > 0
                ? initialData.bedIds
                : (formData.bed_id ? [formData.bed_id] : []);

            // Auto-assign beds if none were selected
            if (bedIds.length === 0) {
                const options = await AvailabilityAPI.groupSearch({
                    group_size: totalGuests,
                    check_in: formData.check_in,
                    check_out: formData.check_out,
                });

                if (options.length > 0) {
                    bedIds = options[0].beds
                        .map(bed => bed.id as number)
                        .filter((id) => Number.isInteger(id));
                } else {
                    const availableBeds = await AvailabilityAPI.findBeds({
                        check_in: formData.check_in,
                        check_out: formData.check_out,
                    });

                    bedIds = availableBeds
                        .slice(0, totalGuests)
                        .map(bed => bed.id as number)
                        .filter((id) => Number.isInteger(id));
                }

                if (bedIds.length < totalGuests) {
                    alert('Brak wystarczającej liczby dostępnych łóżek dla wybranej liczby osób.');
                    return;
                }
            }

            const reservationData = {
                guest_id: selectedGuest.id,
                bed_ids: bedIds,
                check_in: formData.check_in,
                check_out: formData.check_out,
                status: formData.status,
                adults: formData.adults,
                children: formData.children,
                notes: formData.notes,
                total_price: formData.total_price
            };

            await ReservationsAPI.create(reservationData);
            onSuccess();
            onClose();
        } catch (error: any) {
            const message = error.response?.data?.message || error.message || "Błąd podczas tworzenia rezerwacji.";
            alert(`Błąd: ${message}`);
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleCreateGuest = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newGuestData.first_name || !newGuestData.last_name || !newGuestData.email) {
            alert("Imię, nazwisko i email są wymagane.");
            return;
        }

        setLoading(true);
        try {
            const guest = await GuestsAPI.create(newGuestData);
            setSelectedGuest(guest);
            setStep(2);
            setIsCreatingGuest(false);
        } catch (error) {
            alert("Błąd podczas dodawania gościa. Sprawdź czy podany email jest unikalny.");
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    if (!isOpen) return null;

    const selectedRoom = rooms.find(r => r.id === formData.room_id);
    const selectedBed = selectedRoom?.beds?.find(b => b.id === formData.bed_id);
    const selectedBedCount = initialData?.bedIds?.length || 1;
    const isDormitory = selectedRoom?.room_type === 'dormitory';
    const showBedWarning = isDormitory && (formData.adults + formData.children > selectedBedCount);
    const isAutoBedSelection = !formData.bed_id && !(initialData?.bedIds && initialData.bedIds.length > 0);

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-200">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-100 bg-gray-50/50">
                    <div>
                        <h3 className="text-xl font-bold text-gray-900">Nowa Rezerwacja</h3>
                        <p className="text-sm text-gray-500">Krok {step} z 2: {step === 1 ? 'Wybierz Gościa' : 'Szczegóły Pobytu'}</p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 text-gray-400 hover:text-gray-600 hover:bg-white rounded-xl transition-all shadow-sm">
                        <X size={20} />
                    </button>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="p-6">
                        {/* SELECTION SUMMARY - ALWAYS VISIBLE */}
                        <div className="mb-6 p-4 bg-blue-50 border border-blue-100 rounded-2xl space-y-2 shadow-sm">
                            <div className="flex flex-wrap gap-y-3 items-center text-sm">
                                <div className="flex items-center gap-2 text-blue-700 font-bold mr-6">
                                    <CalendarIcon size={16} />
                                    <span>{format(parseISO(formData.check_in), 'd MMMM', { locale: pl })}</span>
                                    <span className="text-blue-300 mx-1">→</span>
                                    <span>{format(parseISO(formData.check_out), 'd MMMM', { locale: pl })}</span>
                                </div>
                                <div className="flex items-center gap-2 text-blue-600 font-medium">
                                    <MapPin size={16} />
                                    <span>{selectedRoom?.name}, miejsce {selectedBed?.bed_number}</span>
                                </div>
                            </div>
                            {initialData?.bedIds && initialData.bedIds.length > 1 && (
                                <div className="pt-2 border-t border-blue-100">
                                    <p className="text-xs font-bold text-blue-700 uppercase mb-1">Rezerwacja grupowa</p>
                                    <p className="text-sm text-blue-600">
                                        {initialData.bedIds.length} łóżek: {initialData.bedIds.map(id => {
                                            const bed = rooms.flatMap(r => r.beds || []).find(b => b.id === id);
                                            const room = rooms.find(r => r.beds?.some(b => b.id === id));
                                            return `${room?.name} #${bed?.bed_number}`;
                                        }).join(', ')}
                                    </p>
                                </div>
                            )}
                        </div>

                        {step === 1 ? (
                            <div className="space-y-4">
                                <label className="block text-sm font-bold text-gray-700">Wyszukaj Gościa</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                                    <input
                                        type="text"
                                        autoFocus
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        placeholder="Imię, nazwisko lub email..."
                                        className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all outline-none"
                                    />
                                    {searching && <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 animate-spin text-brand-600" size={18} />}
                                </div>

                                <div className="max-h-60 overflow-y-auto space-y-2">
                                    {searchResults.map(guest => (
                                        <div
                                            key={guest.id}
                                            onClick={() => { setSelectedGuest(guest); setStep(2); }}
                                            className="p-3 border border-gray-100 rounded-xl hover:bg-brand-50 hover:border-brand-100 cursor-pointer flex items-center justify-between transition-all group"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="w-8 h-8 bg-brand-100 text-brand-700 rounded-full flex items-center justify-center font-bold text-xs uppercase">
                                                    {guest.first_name[0]}{guest.last_name[0]}
                                                </div>
                                                <div>
                                                    <p className="font-bold text-gray-900 text-sm">{guest.first_name} {guest.last_name}</p>
                                                    <p className="text-xs text-gray-500">{guest.email}</p>
                                                </div>
                                            </div>
                                            <div className="opacity-0 group-hover:opacity-100 text-brand-600 font-bold text-xs transition-all">WYBIERZ →</div>
                                        </div>
                                    ))}
                                    {searchQuery.length > 2 && searchResults.length === 0 && !searching && !isCreatingGuest && (
                                        <div className="p-8 text-center border-2 border-dashed border-gray-100 rounded-2xl">
                                            <UserPlus size={24} className="mx-auto text-gray-300 mb-2" />
                                            <p className="text-sm text-gray-500">Nie znaleziono gościa. <br />
                                                <button
                                                    type="button"
                                                    onClick={() => setIsCreatingGuest(true)}
                                                    className="text-brand-600 font-bold hover:underline"
                                                >
                                                    Dodaj nowego →
                                                </button>
                                            </p>
                                        </div>
                                    )}

                                    {isCreatingGuest && (
                                        <div className="space-y-4 p-4 bg-gray-50 rounded-2xl border border-gray-200 animate-in fade-in slide-in-from-top-2">
                                            <div className="flex justify-between items-center mb-2">
                                                <h4 className="font-bold text-gray-900 text-sm">Nowy Gość</h4>
                                                <button type="button" onClick={() => setIsCreatingGuest(false)} className="text-xs text-gray-500 hover:text-gray-700">Anuluj</button>
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <input
                                                    placeholder="Imię"
                                                    value={newGuestData.first_name}
                                                    onChange={e => setNewGuestData({ ...newGuestData, first_name: e.target.value })}
                                                    className="p-2.5 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500"
                                                />
                                                <input
                                                    placeholder="Nazwisko"
                                                    value={newGuestData.last_name}
                                                    onChange={e => setNewGuestData({ ...newGuestData, last_name: e.target.value })}
                                                    className="p-2.5 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500"
                                                />
                                            </div>
                                            <input
                                                type="email"
                                                placeholder="Email"
                                                value={newGuestData.email}
                                                onChange={e => setNewGuestData({ ...newGuestData, email: e.target.value })}
                                                className="w-full p-2.5 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500"
                                            />
                                            <input
                                                placeholder="Telefon (opcjonalnie)"
                                                value={newGuestData.phone}
                                                onChange={e => setNewGuestData({ ...newGuestData, phone: e.target.value })}
                                                className="w-full p-2.5 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-brand-500"
                                            />
                                            <button
                                                type="button"
                                                onClick={handleCreateGuest}
                                                disabled={loading}
                                                className="w-full py-2.5 bg-brand-600 text-white rounded-xl font-bold text-sm hover:bg-brand-700 transition-all flex items-center justify-center gap-2"
                                            >
                                                {loading ? <Loader2 size={16} className="animate-spin" /> : <UserPlus size={16} />}
                                                Zapisz i kontynuuj
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-5 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                {/* Selected Guest Banner */}
                                <div className="flex items-center gap-3 p-3 bg-brand-50 rounded-xl border border-brand-100">
                                    <div className="w-10 h-10 bg-brand-600 text-white rounded-full flex items-center justify-center font-bold">
                                        {selectedGuest?.first_name[0]}{selectedGuest?.last_name[0]}
                                    </div>
                                    <div className="flex-1">
                                        <p className="font-bold text-brand-900">{selectedGuest?.first_name} {selectedGuest?.last_name}</p>
                                        <button type="button" onClick={() => setStep(1)} className="text-[10px] text-brand-600 font-bold uppercase hover:underline">Zmień gościa</button>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1">Przyjazd</label>
                                        <input
                                            type="date"
                                            value={formData.check_in}
                                            onChange={(e) => setFormData({ ...formData, check_in: e.target.value })}
                                            className="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1">Wyjazd</label>
                                        <input
                                            type="date"
                                            value={formData.check_out}
                                            onChange={(e) => setFormData({ ...formData, check_out: e.target.value })}
                                            className="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all"
                                        />
                                    </div>
                                </div>

                                {/* PAX Counts */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1 flex items-center gap-1">
                                            <Users size={12} /> Dorośli
                                        </label>
                                        <div className="flex bg-gray-50 border border-gray-200 rounded-lg overflow-hidden">
                                            <button
                                                type="button"
                                                onClick={() => setFormData(f => ({ ...f, adults: Math.max(1, f.adults - 1) }))}
                                                className="px-3 py-2 hover:bg-gray-100 text-gray-600 font-bold"
                                            >-</button>
                                            <input
                                                type="number"
                                                value={formData.adults}
                                                readOnly
                                                className="w-full text-center bg-transparent text-sm font-bold outline-none"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setFormData(f => ({ ...f, adults: f.adults + 1 }))}
                                                className="px-3 py-2 hover:bg-gray-100 text-gray-600 font-bold"
                                            >+</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1 flex items-center gap-1">
                                            <Baby size={12} /> Dzieci
                                        </label>
                                        <div className="flex bg-gray-50 border border-gray-200 rounded-lg overflow-hidden">
                                            <button
                                                type="button"
                                                onClick={() => setFormData(f => ({ ...f, children: Math.max(0, f.children - 1) }))}
                                                className="px-3 py-2 hover:bg-gray-100 text-gray-600 font-bold"
                                            >-</button>
                                            <input
                                                type="number"
                                                value={formData.children}
                                                readOnly
                                                className="w-full text-center bg-transparent text-sm font-bold outline-none"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setFormData(f => ({ ...f, children: f.children + 1 }))}
                                                className="px-3 py-2 hover:bg-gray-100 text-gray-600 font-bold"
                                            >+</button>
                                        </div>
                                    </div>
                                </div>

                                {isAutoBedSelection && (
                                    <div className="p-3 bg-blue-50 border border-blue-100 rounded-xl text-xs text-blue-700">
                                        Nie wybrano łóżek. System przydzieli automatycznie liczbę łóżek równą liczbie osób.
                                    </div>
                                )}

                                {showBedWarning && (
                                    <div className="p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2 text-amber-700">
                                        <Users size={16} className="mt-0.5 shrink-0" />
                                        <div className="text-xs">
                                            <p className="font-bold">Uwaga: Więcej osób niż łóżek</p>
                                            <p>Wybrałeś {selectedBedCount} łóżka dla {formData.adults + formData.children} osób. Dla pokoi wieloosobowych każda osoba powinna mieć osobne łóżko w kalendarzu.</p>
                                        </div>
                                    </div>
                                )}

                                <div>
                                    <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1">Notatki (opcjonalnie)</label>
                                    <textarea
                                        rows={2}
                                        value={formData.notes}
                                        onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                        className="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all resize-none"
                                        placeholder="Uwagi do rezerwacji..."
                                    />
                                </div>

                                {/* PRICE DISPLAY */}
                                <div className="mt-2 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center justify-between shadow-sm">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center text-white">
                                            <CreditCard size={20} />
                                        </div>
                                        <div>
                                            <p className="text-[11px] font-bold text-emerald-700 uppercase leading-none mb-1">Razem do zapłaty</p>
                                            <p className="text-xs text-emerald-600 font-medium">{nightsCount} {nightsCount === 1 ? 'noc' : 'noce'}</p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        {pricingLoading ? (
                                            <div className="flex items-center gap-2 text-emerald-600 font-bold">
                                                <Loader2 size={16} className="animate-spin" />
                                                Przeliczanie...
                                            </div>
                                        ) : (
                                            <div className="flex flex-col items-end">
                                                <span className="text-2xl font-black text-emerald-700 leading-none">
                                                    {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(formData.total_price)}
                                                </span>
                                                <span className="text-[10px] text-emerald-600/70 font-bold flex items-center gap-1 mt-1">
                                                    <Coins size={10} /> Średnio {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(formData.total_price / (nightsCount || 1))} / noc
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="p-6 border-t border-gray-100 flex gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 px-4 py-3 border border-gray-200 rounded-xl font-bold text-gray-600 hover:bg-gray-50 transition-all text-sm"
                        >
                            Anuluj
                        </button>
                        <button
                            type="submit"
                            disabled={loading || step === 1 || !selectedGuest}
                            className={`flex-[2] px-4 py-3 bg-brand-600 text-white rounded-xl font-bold flex items-center justify-center gap-2 hover:bg-brand-700 transition-all shadow-lg text-sm shadow-brand-200 disabled:opacity-50 disabled:cursor-not-allowed`}
                        >
                            {loading ? <Loader2 className="animate-spin" size={18} /> : <Save size={18} />}
                            {loading ? 'Zapisywanie...' : 'Zatwierdź Rezerwację'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default ReservationModal;
