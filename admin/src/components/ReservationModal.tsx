import React, { useState, useEffect } from 'react';
import { X, Search, Save, Loader2, UserPlus, Users, Baby, Calendar as CalendarIcon, MapPin, CreditCard, Coins, BedDouble, Check, MousePointerClick } from 'lucide-react';
import { AvailabilityAPI, GuestsAPI, ReservationsAPI, PricingAPI, ExtrasAPI, Guest, Room, ExtraService, Bed } from '../services/api';
import { format, parseISO } from 'date-fns';
import { pl } from 'date-fns/locale';

interface ReservationModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    initialData?: {
        bedId?: number;
        bedIds?: number[];
        placeIds?: number[];
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
        bed_ids: [] as number[],
        room_id: 0,
        check_in: format(new Date(), 'yyyy-MM-dd'),
        check_out: format(new Date(Date.now() + 86400000), 'yyyy-MM-dd'),
        status: 'pending',
        adults: 1,
        children: 0,
        notes: '',
        total_price: 0,
        nights_price: 0 // Store nights only price separately
    });

    const [availableServices, setAvailableServices] = useState<ExtraService[]>([]);
    const [selectedServices, setSelectedServices] = useState<Record<number, number>>({});

    // Guest Creation State
    const [isCreatingGuest, setIsCreatingGuest] = useState(false);
    const [newGuestData, setNewGuestData] = useState({
        first_name: '',
        last_name: '',
        email: '',
        phone: ''
    });

    const [localBedIds, setLocalBedIds] = useState<number[]>([]);
    const [localPlaceIds, setLocalPlaceIds] = useState<number[]>([]);
    const [resolvedPricingBedIds, setResolvedPricingBedIds] = useState<number[]>([]);

    const getBedCapacity = (bed?: Partial<Bed> | null): number => {
        const explicitCapacity = Number(bed?.available_places ?? bed?.capacity ?? 0);
        if (explicitCapacity > 0) {
            return explicitCapacity;
        }

        const type = String(bed?.bed_type || 'single');
        return type === 'bunk' ? 2 : 1;
    };

    const getBedsCapacity = (bedIds: number[]): number => {
        const allBeds = rooms.flatMap(r => r.beds || []);
        return bedIds.reduce((sum, bedId) => {
            const bed = allBeds.find(b => b.id === bedId);
            return sum + getBedCapacity(bed);
        }, 0);
    };

    const resolveBedIdsForGuests = async (preferredBedIds: number[], totalGuests: number, roomId?: number): Promise<number[]> => {
        let bedIds = [...preferredBedIds];
        let bedCapacity = getBedsCapacity(bedIds);

        if (bedIds.length > 0 && bedCapacity >= totalGuests) {
            return bedIds;
        }

        if (bedIds.length === 0) {
            try {
                const options = await AvailabilityAPI.groupSearch({
                    group_size: totalGuests,
                    check_in: formData.check_in,
                    check_out: formData.check_out,
                });

                if (options.length > 0 && options[0].beds && options[0].beds.length > 0) {
                    bedIds = options[0].beds
                        .map((bed: any) => bed && bed.id ? parseInt(String(bed.id)) : null)
                        .filter((id: number | null): id is number => id !== null && !isNaN(id));
                    bedCapacity = getBedsCapacity(bedIds);
                }
            } catch (err) {
                console.error('[ReservationModal] Group search failed:', err);
            }
        }

        if (bedIds.length === 0 || bedCapacity < totalGuests) {
            try {
                const availableBeds = await AvailabilityAPI.findBeds({
                    check_in: formData.check_in,
                    check_out: formData.check_out,
                    room_id: roomId || undefined,
                });

                const filtered = availableBeds
                    .filter(bed => !bedIds.includes(bed.id as number))
                    .sort((a: any, b: any) => getBedCapacity(b) - getBedCapacity(a));

                for (const bed of filtered) {
                    const id = bed.id as number;
                    if (!Number.isInteger(id)) continue;
                    bedIds.push(id);
                    bedCapacity += getBedCapacity(bed);
                    if (bedCapacity >= totalGuests) break;
                }
            } catch (err) {
                console.error('[ReservationModal] Find beds failed:', err);
            }
        }

        return bedIds;
    };

    // Update form when initial data changes or modal opens
    useEffect(() => {
        if (isOpen) {
            const bedIds = initialData?.bedIds || (initialData?.bedId ? [initialData.bedId] : []);
            const placeIds = initialData?.placeIds || [];
            const preselectedCapacity = getBedsCapacity(bedIds);
            const defaultAdults = placeIds.length > 0
                ? Math.max(1, placeIds.length)
                : (bedIds.length > 0 ? Math.max(1, preselectedCapacity) : 1);

            setFormData(prev => ({
                ...prev,
                bed_ids: bedIds,
                room_id: initialData?.roomId || 0,
                check_in: initialData?.checkIn || format(new Date(), 'yyyy-MM-dd'),
                check_out: initialData?.checkOut || format(new Date(Date.now() + 86400000), 'yyyy-MM-dd'),
                adults: defaultAdults,
                children: 0
            }));
            setLocalBedIds(bedIds);
            setLocalPlaceIds(placeIds);
            setResolvedPricingBedIds(bedIds);
            setStep(1);
            setSelectedGuest(null);
            setSearchQuery('');
            setIsCreatingGuest(false);
            setNewGuestData({ first_name: '', last_name: '', email: '', phone: '' });
            setSelectedServices({});

            // Fetch services
            ExtrasAPI.getServices({ is_active: 1 }).then(setAvailableServices).catch(console.error);
        }
    }, [isOpen, initialData]);

    // Reset bed selection if guest count changes (to force auto-assign or re-selection)
    // Using a ref to track if this is the first load after modal open
    const isFirstLoad = React.useRef(true);
    const prevGuestCount = React.useRef<number>(formData.adults + formData.children);
    
    useEffect(() => {
        if (!isOpen) {
            isFirstLoad.current = true;
            prevGuestCount.current = formData.adults + formData.children;
            return;
        }

        if (isFirstLoad.current) {
            isFirstLoad.current = false;
            prevGuestCount.current = formData.adults + formData.children;
            return;
        }

        const currentGuestCount = formData.adults + formData.children;
        
        // Only clear beds if guest count DECREASED (need to re-select)
        if (currentGuestCount < prevGuestCount.current) {
            setLocalBedIds([]); // Clear manual selection
            setLocalPlaceIds([]);
            setFormData(prev => ({ ...prev, bed_ids: [] })); // Also clear primary selection
        }
        
        prevGuestCount.current = currentGuestCount;
    }, [formData.adults, formData.children]); // Removed isOpen to avoid double triggers

    // --- DYNAMIC PRICING CALCULATION ---
    const [pricingLoading, setPricingLoading] = useState(false);
    const [nightsCount, setNightsCount] = useState(0);

    useEffect(() => {
        if (!isOpen) return;

        const calculatePrice = async () => {
            if (!formData.check_in || !formData.check_out) {
                setFormData(f => ({ ...f, total_price: 0 }));
                setNightsCount(0);
                setResolvedPricingBedIds([]);
                return;
            }

            // Don't calculate if check-out <= check-in
            if (parseISO(formData.check_out) <= parseISO(formData.check_in)) {
                setFormData(f => ({ ...f, total_price: 0 }));
                setNightsCount(0);
                setResolvedPricingBedIds([]);
                return;
            }

            setPricingLoading(true);
            try {
                const totalGuests = Math.max(1, formData.adults + formData.children);
                const pricingBedIds = await resolveBedIdsForGuests(localBedIds, totalGuests, formData.room_id || initialData?.roomId);
                setResolvedPricingBedIds(pricingBedIds);

                const priceData = await PricingAPI.calculateGroup({
                    bed_ids: pricingBedIds,
                    check_in: formData.check_in,
                    check_out: formData.check_out,
                    adults: formData.adults,
                    children: formData.children,
                    room_id: formData.room_id
                });

                setFormData(f => ({ ...f, nights_price: priceData.total }));
                setNightsCount(priceData.nights);
            } catch (error) {
                console.error('Pricing calculation error:', error);
                setResolvedPricingBedIds([]);
            } finally {
                setPricingLoading(false);
            }
        };

        const timeoutId = setTimeout(calculatePrice, 300);
        return () => clearTimeout(timeoutId);
    }, [isOpen, formData.check_in, formData.check_out, localBedIds, formData.adults, formData.children, formData.room_id]);

    // --- AUTO-SUGGEST SERVICES BASED ON BEDS ---
    useEffect(() => {
        if (!isOpen || availableServices.length === 0) return;

        const bedCount = localBedIds.length;
        if (bedCount === 0) return;

        const newSelection = { ...selectedServices };
        let changed = false;

        availableServices.forEach(service => {
            if (service.auto_suggest_by_beds && service.id && !newSelection[service.id]) {
                newSelection[service.id] = bedCount;
                changed = true;
            }
        });

        if (changed) {
            setSelectedServices(newSelection);
        }
    }, [localBedIds.length, availableServices, isOpen]);

    // --- TOTAL PRICE CALCULATION (Nights + Extras) ---
    useEffect(() => {
        let extrasTotal = 0;
        Object.entries(selectedServices).forEach(([serviceId, qty]) => {
            const service = availableServices.find(s => s.id === parseInt(serviceId));
            if (service) {
                extrasTotal += service.price * qty;
            }
        });

        setFormData(f => ({ ...f, total_price: (f.nights_price || 0) + extrasTotal }));
    }, [formData.nights_price, selectedServices, availableServices]);

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

        const totalGuests = Math.max(1, formData.adults + formData.children);
        const currentCapacity = getBedsCapacity(localBedIds);

        // Validate capacity BEFORE proceeding
        if (localBedIds.length > 0 && currentCapacity < totalGuests) {
            alert(`Za mało miejsc w wybranych łóżkach!\n\nWybrane łóżka mają: ${currentCapacity} miejsc\nLiczba gości: ${totalGuests} osób\n\nZmień liczbę gości lub zaznacz więcej łóżek.`);
            return;
        }

        setLoading(true);
        try {
            let bedIds = await resolveBedIdsForGuests(localBedIds, totalGuests, formData.room_id || initialData?.roomId);
            let placeIds = [...localPlaceIds];
            let bedCapacity = getBedsCapacity(bedIds);

            console.log('[ReservationModal] Submit:', {
                totalGuests,
                bedIds,
                bedCapacity,
                localBedIds,
                adults: formData.adults,
                children: formData.children,
            });

            if (bedIds.length !== localBedIds.length || !bedIds.every(id => localBedIds.includes(id))) {
                placeIds = [];
            }

            if (bedCapacity < totalGuests) {
                alert(`Brak wystarczającej liczby dostępnych miejsc dla ${totalGuests} osób.\n\nZnalezione miejsca: ${bedCapacity}\nWymagane miejsca: ${totalGuests}\n\nSprawdź czy masz dodane łóżka w pokojach (Booking → Rooms & Beds).`);
                setLoading(false);
                return;
            }

            // Final validation - only warn if still not enough beds (shouldn't happen)
            if (bedCapacity < totalGuests) {
                console.warn('[ReservationModal] Still not enough capacity:', bedCapacity, '<', totalGuests);
                // Don't block - just warn and proceed
            }

            const reservationData = {
                guest_id: selectedGuest.id,
                bed_ids: bedIds,
                place_ids: (() => {
                    const manualSelectionStillMatchesBeds = placeIds.length > 0
                        && bedIds.length === localBedIds.length
                        && bedIds.every(id => localBedIds.includes(id));

                    if (!manualSelectionStillMatchesBeds || placeIds.length < totalGuests) {
                        return undefined;
                    }

                    return placeIds.slice(0, totalGuests);
                })(),
                check_in: formData.check_in,
                check_out: formData.check_out,
                status: formData.status,
                adults: formData.adults,
                children: formData.children,
                notes: formData.notes,
                total_price: formData.total_price
            };

            const reservation = await ReservationsAPI.create(reservationData);

            // Save Extras
            const extrasToSave = Object.entries(selectedServices)
                .filter(([_, qty]) => qty > 0)
                .map(([serviceId, qty]) => ({
                    service_id: parseInt(serviceId),
                    quantity: qty
                }));

            if (extrasToSave.length > 0 && reservation.id) {
                await ExtrasAPI.setReservationExtras(reservation.id, extrasToSave);
            }

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
    const selectedBed = selectedRoom?.beds?.find(b => b.id === (localBedIds[0] || 0));
    const selectedBedCount = localBedIds.length;
    const selectedCapacity = getBedsCapacity(localBedIds);
    const pricingCapacity = getBedsCapacity(resolvedPricingBedIds);
    const isDormitory = selectedRoom?.room_type === 'dormitory';
    const showBedWarning = isDormitory && (formData.adults + formData.children > selectedCapacity);
    const isAutoBedSelection = selectedBedCount === 0;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-200">
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

                <form onSubmit={handleSubmit} className="flex flex-col flex-1 overflow-hidden">
                    <div className="p-6 flex-1 overflow-y-auto">
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
                            {localBedIds.length > 1 && (
                                <div className="pt-2 border-t border-blue-100">
                                    <p className="text-xs font-bold text-blue-700 uppercase mb-1">Rezerwacja grupowa</p>
                                    <p className="text-sm text-blue-600">
                                        {localBedIds.length} łóżek: {localBedIds.map(id => {
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
                                            min={format(new Date(), 'yyyy-MM-dd')}
                                            onChange={(e) => setFormData({ ...formData, check_in: e.target.value })}
                                            className="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1">Wyjazd</label>
                                        <input
                                            type="date"
                                            value={formData.check_out}
                                            min={formData.check_in || format(new Date(), 'yyyy-MM-dd')}
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
                                                min="1"
                                                max="50"
                                                value={formData.adults}
                                                onChange={(e) => setFormData(f => ({ ...f, adults: Math.max(1, Math.min(50, parseInt(e.target.value) || 1)) }))}
                                                className="w-full text-center bg-transparent text-sm font-bold outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setFormData(f => ({ ...f, adults: Math.min(50, f.adults + 1) }))}
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
                                                min="0"
                                                max="50"
                                                value={formData.children}
                                                onChange={(e) => setFormData(f => ({ ...f, children: Math.max(0, Math.min(50, parseInt(e.target.value) || 0)) }))}
                                                className="w-full text-center bg-transparent text-sm font-bold outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setFormData(f => ({ ...f, children: Math.min(50, f.children + 1) }))}
                                                className="px-3 py-2 hover:bg-gray-100 text-gray-600 font-bold"
                                            >+</button>
                                        </div>
                                    </div>
                                </div>

                                {isAutoBedSelection ? (
                                    <div className="p-3 bg-blue-50 border border-blue-100 rounded-xl text-xs text-blue-700 flex items-center gap-2">
                                        <Users size={14} />
                                        <span>
                                            System automatycznie zarezerwuje <strong>{formData.adults + formData.children}</strong> miejsc w dostępnych pokojach.
                                            {pricingCapacity > 0 ? ` Podgląd ceny liczy obecnie ${pricingCapacity} dostępnych miejsc.` : ''}
                                        </span>
                                    </div>
                                ) : (
                                    <div className={`p-3 rounded-xl border flex items-center justify-between text-xs ${showBedWarning ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'}`}>
                                        <div className="flex items-center gap-2 font-bold">
                                            <BedDouble size={14} />
                                            <span>Wybrano: {selectedBedCount} łóżek ({selectedCapacity} miejsc) / {formData.adults + formData.children} osób</span>
                                        </div>
                                        {showBedWarning && <span className="font-black uppercase text-[10px]">Za mało miejsc!</span>}
                                        {!showBedWarning && <Check size={14} className="text-emerald-600" />}
                                    </div>
                                )}

                                {showBedWarning && !isAutoBedSelection && (
                                    <div className="p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2 text-amber-700">
                                        <div className="text-[11px] leading-relaxed">
                                            <p className="font-bold flex items-center gap-1 mb-1">
                                                <MousePointerClick size={14} /> Jak dodać więcej łóżek?
                                            </p>
                                            <p>Przytrzymaj klawisz <strong>Ctrl</strong> (lub Cmd) i kliknij na kolejne łóżka w kalendarzu, aby dodać je do tej rezerwacji. Każda osoba dorosła i dziecko wymaga osobnego miejsca.</p>
                                        </div>
                                    </div>
                                )}

                                {/* EXTRA SERVICES SECTION */}
                                {availableServices.length > 0 && (
                                    <div className="space-y-3">
                                        <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1 flex items-center gap-1">
                                            <Coins size={12} /> Usługi Dodatkowe
                                        </label>
                                        <div className="grid grid-cols-1 gap-2">
                                            {availableServices.map(service => {
                                                const isSelected = !!selectedServices[service.id!];
                                                const quantity = selectedServices[service.id!] || 0;

                                                return (
                                                    <div
                                                        key={service.id}
                                                        className={`p-3 rounded-xl border transition-all flex items-center justify-between ${isSelected ? 'bg-brand-50 border-brand-200 shadow-sm' : 'bg-white border-gray-100'}`}
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <div
                                                                onClick={() => {
                                                                    const newSelection = { ...selectedServices };
                                                                    if (isSelected) {
                                                                        delete newSelection[service.id!];
                                                                    } else {
                                                                        newSelection[service.id!] = service.pricing_type === 'per_unit' ? (localBedIds.length || 1) : 1;
                                                                    }
                                                                    setSelectedServices(newSelection);
                                                                }}
                                                                className={`w-5 h-5 rounded border flex items-center justify-center cursor-pointer transition-colors ${isSelected ? 'bg-brand-600 border-brand-600 text-white' : 'bg-gray-50 border-gray-200 text-transparent'}`}
                                                            >
                                                                <Check size={14} />
                                                            </div>
                                                            <div>
                                                                <p className="text-sm font-bold text-gray-900">{service.name}</p>
                                                                <p className="text-[10px] text-gray-500">{service.price.toFixed(2)} zł {service.pricing_type === 'per_unit' ? '/ szt.' : '/ raz'}</p>
                                                            </div>
                                                        </div>

                                                        {isSelected && service.pricing_type === 'per_unit' && (
                                                            <div className="flex bg-white border border-brand-200 rounded-lg overflow-hidden h-8">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setSelectedServices(s => ({ ...s, [service.id!]: Math.max(1, s[service.id!] - 1) }))}
                                                                    className="px-2 hover:bg-gray-50 text-gray-600 font-bold"
                                                                >-</button>
                                                                <input
                                                                    type="number"
                                                                    min="1"
                                                                    max="100"
                                                                    value={quantity}
                                                                    onChange={(e) => {
                                                                        const val = Math.max(1, Math.min(100, parseInt(e.target.value) || 1));
                                                                        setSelectedServices(s => ({ ...s, [service.id!]: val }));
                                                                    }}
                                                                    className="w-12 text-center bg-transparent text-xs font-bold outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                                />
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setSelectedServices(s => ({ ...s, [service.id!]: Math.min(100, s[service.id!] + 1) }))}
                                                                    className="px-2 hover:bg-gray-50 text-gray-600 font-bold"
                                                                >+</button>
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
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
                    <div className="p-6 border-t border-gray-100 flex gap-3 bg-gray-50/50">
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
            </div >
        </div >
    );
};

export default ReservationModal;
