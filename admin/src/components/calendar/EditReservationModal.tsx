/**
 * EditReservationModal
 *
 * Allows editing reservation details:
 * - Guest count (adults/children)
 * - Bed assignment
 * - Extra services
 * - Notes
 * - Dates (with availability check)
 */

import React, { useState, useEffect } from 'react';
import { X, Save, Loader2, Users, BedDouble, Calendar, Euro, Plus, Minus, AlertCircle, CheckCircle2 } from 'lucide-react';
import { ReservationsAPI, PricingAPI, ExtrasAPI, AvailabilityAPI, Reservation, ExtraService, Room } from '../../services/api';

interface EditReservationModalProps {
    reservation: Reservation;
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    rooms: Room[];
}

const EditReservationModal: React.FC<EditReservationModalProps> = ({
    reservation,
    isOpen,
    onClose,
    onSuccess,
    rooms,
}) => {
    const [loading, setLoading] = useState(false);

    const getBedCapacity = (bed: any): number => {
        const type = String(bed?.bed_type || 'single');
        if (type === 'bunk') return 2;
        return 1;
    };

    const getBedsCapacity = (bedIds: number[]): number => {
        return bedIds.reduce((sum, bedId) => {
            const bed = allBeds.find(b => b.id === bedId);
            return sum + getBedCapacity(bed);
        }, 0);
    };

    // Form state
    const [formData, setFormData] = useState({
        adults: reservation.adults || 1,
        children: reservation.children || 0,
        bed_ids: reservation.bed_ids || [],
        check_in: reservation.check_in,
        check_out: reservation.check_out,
        notes: reservation.notes || '',
    });

    const [availableServices, setAvailableServices] = useState<ExtraService[]>([]);
    const [selectedServices, setSelectedServices] = useState<Record<number, number>>({});
    const [priceData, setPriceData] = useState<{ total: number; nights: number; details: any[]; services_total?: number } | null>(null);

    // Re-sync modal form when editing a different reservation or re-opening.
    useEffect(() => {
        if (!isOpen) return;

        setFormData({
            adults: reservation.adults || 1,
            children: reservation.children || 0,
            bed_ids: reservation.bed_ids || [],
            check_in: reservation.check_in,
            check_out: reservation.check_out,
            notes: reservation.notes || '',
        });
        setPriceData(null);
        setSelectedServices({});
    }, [isOpen, reservation]);

    // Calculate price when dates, guest count, or services change
    useEffect(() => {
        const calculatePrice = async () => {
            if (!formData.bed_ids.length || !formData.check_in || !formData.check_out) {
                setPriceData(null);
                return;
            }

            try {
                // Calculate base price (beds + nights)
                const result = await PricingAPI.calculateGroup({
                    bed_ids: formData.bed_ids,
                    check_in: formData.check_in,
                    check_out: formData.check_out,
                    adults: formData.adults,
                    children: formData.children,
                });

                // Calculate services price
                let servicesTotal = 0;
                if (Object.keys(selectedServices).length > 0) {
                    for (const [serviceId, quantity] of Object.entries(selectedServices)) {
                        const service = availableServices.find(s => s.id === parseInt(serviceId));
                        if (service) {
                            servicesTotal += service.price * quantity;
                        }
                    }
                }

                setPriceData({
                    ...result,
                    services_total: servicesTotal,
                });
            } catch (error) {
                console.error('Failed to calculate price:', error);
            }
        };

        calculatePrice();
    }, [formData.bed_ids, formData.check_in, formData.check_out, formData.adults, formData.children, selectedServices, availableServices]);

    // Load extra services on mount
    useEffect(() => {
        if (isOpen) {
            ExtrasAPI.getServices({ is_active: 1 }).then(setAvailableServices).catch(console.error);
            
            // Load current extras
            if (reservation.id) {
                ExtrasAPI.getReservationExtras(reservation.id).then((extras: any[]) => {
                    setSelectedServices(extras.reduce((acc, e) => ({
                        ...acc,
                        [e.service_id]: e.quantity
                    }), {} as Record<number, number>));
                }).catch(console.error);
            }
        }
    }, [isOpen, reservation.id]);

    if (!isOpen) return null;

    const handleSave = async () => {
        const totalGuests = formData.adults + formData.children;
        let bedIds = [...formData.bed_ids];
        let currentCapacity = getBedsCapacity(bedIds);

        // If capacity is too low, auto-assign additional beds
        if (totalGuests > currentCapacity) {
            const capacityNeeded = totalGuests - currentCapacity;
            
            try {
                // Find available beds (excluding already selected ones)
                const availableBeds = await AvailabilityAPI.findBeds({
                    check_in: formData.check_in,
                    check_out: formData.check_out,
                });

                console.log('[EditReservationModal] Available beds:', availableBeds);
                console.log('[EditReservationModal] Current bedIds:', bedIds);

                // Filter out already selected beds
                const additionalBeds: any[] = availableBeds
                    .filter((bed: any) => !bedIds.includes(bed.id as number))
                    .sort((a: any, b: any) => getBedCapacity(b) - getBedCapacity(a));

                console.log('[EditReservationModal] Additional beds:', additionalBeds);

                const picked: number[] = [];
                let gainedCapacity = 0;
                for (const bed of additionalBeds) {
                    const bid = bed.id as number;
                    if (!Number.isInteger(bid)) continue;
                    picked.push(bid);
                    gainedCapacity += getBedCapacity(bed);
                    if (gainedCapacity >= capacityNeeded) {
                        break;
                    }
                }

                if (gainedCapacity < capacityNeeded) {
                    alert(`Brak wystarczającej liczby wolnych miejsc.\n\nPotrzeba dodatkowo: ${capacityNeeded}\nZnaleziono: ${gainedCapacity}\n\nSpróbuj zmienić daty lub zmniejszyć liczbę osób.`);
                    return;
                }

                // Add additional beds
                bedIds = [
                    ...bedIds,
                    ...picked
                ];
                currentCapacity = getBedsCapacity(bedIds);

                console.log('[EditReservationModal] New bedIds:', bedIds);
            } catch (err) {
                console.error('[EditReservationModal] Failed to find additional beds:', err);
                alert('Nie udało się znaleźć dodatkowych łóżek. Spróbuj ponownie.');
                return;
            }
        }

        setLoading(true);
        try {
            const nextTotalPrice = priceData
                ? priceData.total + (priceData.services_total || 0)
                : (reservation.total_price || 0);

            // Update reservation with new bed count
            await ReservationsAPI.update(reservation.id!, {
                adults: formData.adults,
                children: formData.children,
                bed_ids: bedIds,
                check_in: formData.check_in,
                check_out: formData.check_out,
                notes: formData.notes,
                total_price: nextTotalPrice,
            });

            // Update extras
            if (reservation.id) {
                const extrasPayload = Object.entries(selectedServices).map(([serviceId, quantity]) => ({
                    service_id: parseInt(serviceId),
                    quantity: quantity,
                }));
                await ExtrasAPI.setReservationExtras(reservation.id, extrasPayload);
            }

            onSuccess();
            onClose();
        } catch (error: any) {
            const errorMsg = error.response?.data?.message || error.message || 'Nieznany błąd';
            alert('Błąd podczas zapisywania zmian: ' + errorMsg);
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleServiceToggle = (serviceId: number) => {
        setSelectedServices(prev => {
            const current = { ...prev };
            if (current[serviceId]) {
                delete current[serviceId];
            } else {
                current[serviceId] = 1;
            }
            return current;
        });
    };

    const handleServiceQuantityChange = (serviceId: number, delta: number) => {
        setSelectedServices(prev => {
            const current = { ...prev };
            const newQty = (current[serviceId] || 0) + delta;
            if (newQty <= 0) {
                delete current[serviceId];
            } else {
                current[serviceId] = newQty;
            }
            return current;
        });
    };

    // Get all beds from all rooms for selection
    const allBeds = rooms
        .filter((r: Room) => r.beds && r.beds.length > 0)
        .flatMap((room: Room) => room.beds!.map((bed: any) => ({
            ...bed,
            room_name: room.name,
            room_id: room.id,
        })));

    const selectedBedsCount = formData.bed_ids.length;
    const selectedBedsCapacity = getBedsCapacity(formData.bed_ids);
    const totalGuests = formData.adults + formData.children;
    const canSave = selectedBedsCapacity >= totalGuests;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-200 max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-100 bg-gray-50/50 sticky top-0 bg-white z-10">
                    <div>
                        <h3 className="text-xl font-black text-gray-900">Edytuj rezerwację #{reservation.id}</h3>
                        <p className="text-xs text-gray-500 mt-1">Zmień daty, gości, łóżka i usługi</p>
                    </div>
                    <button onClick={() => onClose()} className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-all">
                        <X size={20} />
                    </button>
                </div>

                {/* Content */}
                <div className="p-6 space-y-6">
                    {/* Dates */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
                                <Calendar size={16} className="text-brand-600" /> Przyjazd
                            </label>
                            <input
                                type="date"
                                className="w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                value={formData.check_in}
                                onChange={e => setFormData({ ...formData, check_in: e.target.value })}
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
                                <Calendar size={16} className="text-brand-600" /> Wyjazd
                            </label>
                            <input
                                type="date"
                                className="w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                value={formData.check_out}
                                onChange={e => setFormData({ ...formData, check_out: e.target.value })}
                                min={formData.check_in}
                            />
                        </div>
                    </div>

                    {/* Guest Count */}
                    <div className="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                        <h4 className="text-sm font-black text-gray-700 mb-3 flex items-center gap-2">
                            <Users size={16} /> Liczba gości
                        </h4>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-bold text-gray-600 mb-2">Dorośli (12+)</label>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setFormData({ ...formData, adults: Math.max(1, formData.adults - 1) })}
                                        className="p-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                                    >
                                        <Minus size={16} />
                                    </button>
                                    <div className="flex-1 text-center">
                                        <span className="text-xl font-black text-gray-900">{formData.adults}</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setFormData({ ...formData, adults: formData.adults + 1 })}
                                        className="p-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                                    >
                                        <Plus size={16} />
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-gray-600 mb-2">Dzieci (0-11)</label>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setFormData({ ...formData, children: Math.max(0, formData.children - 1) })}
                                        className="p-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                                    >
                                        <Minus size={16} />
                                    </button>
                                    <div className="flex-1 text-center">
                                        <span className="text-xl font-black text-gray-900">{formData.children}</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setFormData({ ...formData, children: formData.children + 1 })}
                                        className="p-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                                    >
                                        <Plus size={16} />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div className="mt-3 flex items-center gap-2 text-sm">
                            <BedDouble size={14} className="text-gray-400" />
                            <span className="text-gray-600">
                                Miejsca: <strong className={selectedBedsCapacity >= totalGuests ? 'text-green-600' : 'text-red-600'}>
                                    {selectedBedsCapacity} dostępnych
                                </strong>
                            </span>
                            <span className="text-gray-400">|</span>
                            <Users size={14} className="text-gray-400" />
                            <span className="text-gray-600">
                                Goście: <strong>{totalGuests} osób</strong>
                            </span>
                        </div>
                        {selectedBedsCapacity < totalGuests && (
                            <div className="mt-2 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-bold flex items-start gap-2">
                                <AlertCircle size={18} className="shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-black mb-1">⚠️ Za mało miejsc!</p>
                                    <p className="text-xs opacity-90">
                                        Masz {selectedBedsCapacity} miejsc dla {totalGuests} osób. 
                                        Aby zapisać tę zmianę, musisz najpierw anulować tę rezerwację i utworzyć nową z większą liczbą miejsc.
                                    </p>
                                </div>
                            </div>
                        )}
                        {selectedBedsCapacity > totalGuests && (
                            <div className="mt-2 p-2 bg-amber-50 border border-amber-200 rounded-lg text-amber-700 text-xs font-bold">
                                ℹ️ Masz {selectedBedsCapacity} miejsc dla {totalGuests} osób - jedno lub więcej miejsc będzie pustych
                            </div>
                        )}
                    </div>

                    {/* Current Beds Info (Read-only) */}
                    <div>
                        <h4 className="text-sm font-black text-gray-700 mb-3 flex items-center gap-2">
                            <BedDouble size={16} /> Przypisane łóżka ({selectedBedsCount})
                        </h4>
                        <div className="p-4 bg-gray-50 rounded-xl border border-gray-200">
                            {formData.bed_ids.length === 0 ? (
                                <p className="text-sm text-gray-500 italic">Brak przypisanych łóżek</p>
                            ) : (
                                <div className="space-y-2">
                                    {formData.bed_ids.map((bedId, index) => {
                                        const bed = allBeds.find(b => b.id === bedId);
                                        return bed ? (
                                            <div key={bedId} className="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-200 shadow-sm">
                                                <div className="w-10 h-10 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center font-black text-sm flex-shrink-0">
                                                    {index + 1}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-bold text-gray-900 truncate">{bed.room_name}</p>
                                                    <p className="text-xs text-gray-500">
                                                        Łóżko <strong className="text-gray-700">#{bed.bed_number}</strong>
                                                        {bed.bed_type && (
                                                            <>
                                                                {' • '}
                                                                <span className="capitalize text-gray-600">
                                                                    {bed.bed_type === 'single' ? 'Pojedyncze' : 
                                                                     bed.bed_type === 'double' ? 'Podwójne' : 
                                                                     bed.bed_type === 'bunk' ? 'Piętrowe' : bed.bed_type}
                                                                </span>
                                                            </>
                                                        )}
                                                    </p>
                                                </div>
                                                <CheckCircle2 size={18} className="text-green-600 flex-shrink-0" />
                                            </div>
                                        ) : (
                                            <div key={bedId} className="text-sm text-gray-400 italic p-3 bg-white rounded-lg border border-gray-200">
                                                Łóżko #{bedId} (brak danych)
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                            <div className="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 text-xs">
                                <strong>ℹ️ Informacja:</strong> Aby zmienić przypisane łóżka, skontaktuj się z administratorem lub anuluj rezerwację i utwórz nową.
                            </div>
                        </div>
                    </div>

                    {/* Extra Services */}
                    <div>
                        <h4 className="text-sm font-black text-gray-700 mb-3 flex items-center gap-2">
                            <Euro size={16} /> Usługi dodatkowe
                        </h4>
                        <div className="space-y-2">
                            {availableServices.map(service => (
                                <div
                                    key={service.id}
                                    className="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-xl"
                                >
                                    <div className="flex items-center gap-3 flex-1">
                                        <input
                                            type="checkbox"
                                            checked={!!selectedServices[service.id!]}
                                            onChange={() => handleServiceToggle(service.id!)}
                                            className="text-brand-600 focus:ring-brand-500"
                                        />
                                        <div className="flex-1">
                                            <p className="text-sm font-bold text-gray-900">{service.name}</p>
                                            <p className="text-xs text-gray-500">
                                                {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(service.price)}
                                                {' '}{service.pricing_type === 'per_person' ? '/osoba' : service.pricing_type === 'per_unit' ? '/sztuka' : '/pobyt'}
                                            </p>
                                        </div>
                                    </div>
                                    {selectedServices[service.id!] && (
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => handleServiceQuantityChange(service.id!, -1)}
                                                className="p-1 bg-gray-100 hover:bg-gray-200 rounded"
                                            >
                                                <Minus size={14} />
                                            </button>
                                            <span className="text-sm font-bold w-6 text-center">
                                                {selectedServices[service.id!]}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => handleServiceQuantityChange(service.id!, 1)}
                                                className="p-1 bg-gray-100 hover:bg-gray-200 rounded"
                                            >
                                                <Plus size={14} />
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Notes */}
                    <div>
                        <label className="block text-sm font-bold text-gray-700 mb-2">Notatki</label>
                        <textarea
                            className="w-full border-gray-300 rounded-xl shadow-sm h-20"
                            value={formData.notes}
                            onChange={e => setFormData({ ...formData, notes: e.target.value })}
                            placeholder="Dodatkowe informacje..."
                        />
                    </div>

                    {/* Price Summary */}
                    {priceData && (
                        <div className="p-4 bg-gradient-to-r from-brand-50 to-purple-50 rounded-2xl border border-brand-100">
                            <p className="text-xs font-bold text-gray-600 mb-3">Podsumowanie kosztów</p>
                            
                            {/* Base Price */}
                            <div className="flex justify-between items-center mb-2 pb-2 border-b border-brand-100">
                                <div>
                                    <p className="text-sm font-bold text-gray-900">Noclegi</p>
                                    <p className="text-xs text-gray-500">{priceData.nights} noclegów × {selectedBedsCapacity} miejsc</p>
                                </div>
                                <p className="text-lg font-black text-gray-900">
                                    {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(priceData.total)}
                                </p>
                            </div>
                            
                            {/* Services */}
                            {Object.keys(selectedServices).length > 0 && (
                                <div className="space-y-1 mb-2 pb-2 border-b border-brand-100">
                                    <p className="text-xs font-bold text-gray-600 mb-1">Usługi dodatkowe</p>
                                    {Object.entries(selectedServices).map(([serviceId, quantity]) => {
                                        const service = availableServices.find(s => s.id === parseInt(serviceId));
                                        if (!service) return null;
                                        return (
                                            <div key={serviceId} className="flex justify-between items-center text-sm">
                                                <span className="text-gray-600">
                                                    {service.name} × {quantity}
                                                </span>
                                                <span className="font-bold text-gray-900">
                                                    {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(service.price * quantity)}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                            
                            {/* Total */}
                            <div className="flex justify-between items-center pt-2">
                                <div>
                                    <p className="text-xs font-bold text-gray-600 mb-1">Łącznie do zapłaty</p>
                                    <p className="text-[10px] text-gray-500">Pobyt + Usługi</p>
                                </div>
                                <p className="text-2xl font-black text-brand-700">
                                    {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(
                                        priceData.total + (priceData.services_total || 0)
                                    )}
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                {/* Actions */}
                <div className="p-6 border-t border-gray-100 bg-gray-50/50 flex gap-3 sticky bottom-0">
                    <button
                        type="button"
                        onClick={() => onClose()}
                        className="flex-1 px-4 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl font-bold hover:bg-gray-50 transition-all"
                        disabled={loading}
                    >
                        Anuluj
                    </button>
                    <button
                        type="button"
                        onClick={handleSave}
                        disabled={loading || !canSave}
                        className="flex-1 px-4 py-3 bg-brand-600 text-white rounded-xl font-bold hover:bg-brand-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        {loading ? <Loader2 className="animate-spin" size={18} /> : <Save size={18} />}
                        Zapisz zmiany
                    </button>
                </div>
            </div>
        </div>
    );
};

export default EditReservationModal;
