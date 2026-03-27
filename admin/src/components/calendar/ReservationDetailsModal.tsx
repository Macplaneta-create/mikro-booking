/**
 * ReservationDetailsModal
 *
 * Displays reservation details and action buttons (confirm, check-in, check-out, cancel).
 * Extracted from CalendarView.tsx for clarity and reusability.
 */

import React, { useState, useEffect } from 'react';
import { X, User, LogIn, LogOut, History, AlertCircle, Coins, Receipt, Check, CreditCard, CheckCircle2, Edit3, BedDouble } from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { pl } from 'date-fns/locale';
import { ReservationsAPI, ExtrasAPI, PricingAPI, Reservation, ReservationExtra, Room } from '../../services/api';
import { getStatusLabel } from './calendarUtils';
import BookingHistory from '../BookingHistory';
import EditReservationModal from './EditReservationModal';

interface ReservationDetailsModalProps {
    reservation: Reservation;
    isOpen: boolean;
    onClose: () => void;
    onRefresh: () => void;
    rooms: Room[];
}

const ReservationDetailsModal: React.FC<ReservationDetailsModalProps> = ({
    reservation,
    isOpen,
    onClose,
    onRefresh,
    rooms,
}) => {
    const [extras, setExtras] = useState<ReservationExtra[]>([]);
    const [activeTab, setActiveTab] = useState<'details' | 'history'>('details');
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [calculatedPrice, setCalculatedPrice] = useState<number | null>(null);
    const [refreshTrigger, setRefreshTrigger] = useState(0); // Force recalc on change

    useEffect(() => {
        if (isOpen && reservation.id) {
            const fetchDetails = async () => {
                try {
                    const extrasData = await ExtrasAPI.getReservationExtras(reservation.id!);
                    setExtras(extrasData);
                    
                    // Calculate current price based on beds, dates, and guest count
                    if (reservation.bed_ids && reservation.bed_ids.length > 0) {
                        const pricingResult = await PricingAPI.calculateGroup({
                            bed_ids: reservation.bed_ids,
                            check_in: reservation.check_in,
                            check_out: reservation.check_out,
                            adults: reservation.adults || 1,
                            children: reservation.children || 0,
                        });
                        setCalculatedPrice(pricingResult.total);
                    }
                } catch (error) {
                    console.error("Failed to fetch reservation details:", error);
                }
            };
            fetchDetails();
        } else {
            setExtras([]);
            setCalculatedPrice(null);
            setActiveTab('details');
        }
    }, [isOpen, reservation.id, reservation.bed_ids, reservation.check_in, reservation.check_out, reservation.adults, reservation.children, refreshTrigger]);

    const allBeds = rooms.flatMap(room => room.beds || []);

    const getBedById = (bedId: number) => {
        return allBeds.find(item => item.id === bedId);
    };

    const getBedCapacity = (bedId: number): number => {
        const bed = getBedById(bedId);
        const explicitCapacity = Number(bed?.capacity ?? 0);
        if (explicitCapacity > 0) {
            return explicitCapacity;
        }

        return String(bed?.bed_type || 'single') === 'bunk' ? 2 : 1;
    };

    const getAssignedPlaceLabels = (bedId: number): string[] => {
        const bed = getBedById(bedId);
        if (!bed?.places?.length || !reservation.place_ids?.length) {
            return [];
        }

        const placeIds = new Set(reservation.place_ids);

        return [...bed.places]
            .filter(place => place.id && placeIds.has(place.id))
            .sort((left, right) => left.place_number - right.place_number)
            .map(place => place.place_label || `Miejsce ${place.place_number}`);
    };

    const bedAssignments = (reservation.bed_ids || []).map((bedId) => {
        const bed = getBedById(bedId);
        const labels = getAssignedPlaceLabels(bedId);
        const capacity = getBedCapacity(bedId);

        return {
            bedId,
            bedNumber: bed?.bed_number || bedId,
            labels,
            capacity,
        };
    });

    const chooseBedsForGuests = (bedIds: number[], guestCount: number): number[] => {
        const sorted = [...bedIds].sort((leftId, rightId) => getBedCapacity(rightId) - getBedCapacity(leftId));
        const picked: number[] = [];
        let covered = 0;

        for (const bedId of sorted) {
            picked.push(bedId);
            covered += getBedCapacity(bedId);
            if (covered >= guestCount) {
                break;
            }
        }

        return covered >= guestCount ? picked : bedIds;
    };

    if (!isOpen) return null;

    const handleConfirm = async () => {
        const reason = window.prompt('Podaj powód lub notatkę do potwierdzenia (opcjonalnie, np. numer transakcji):', '');
        if (reason === null) return;

        try {
            await ReservationsAPI.confirm(reservation.id!, reason);
            onClose();
            onRefresh();
        } catch (error) {
            alert('Błąd podczas potwierdzania rezerwacji');
            console.error(error);
        }
    };

    const handleCheckIn = async () => {
        const totalGuests = (reservation.adults || 0) + (reservation.children || 0);
        let adjustment: any = null;

        if (totalGuests > 1) {
            const actualCount = window.prompt(`Rezerwacja na ${totalGuests} osób. Ilu gości przyjechało faktycznie?`, totalGuests.toString());
            if (actualCount === null) return;

            const newCount = parseInt(actualCount);
            if (!isNaN(newCount) && newCount < totalGuests && newCount > 0) {
                const releaseBeds = window.confirm(`Przyjechało mniej osób (${newCount} z ${totalGuests}). Czy chcesz zwolnić nadmiarowe łóżka, aby były dostępne dla innych gości?`);

                if (releaseBeds) {
                    const bedsToKeep = chooseBedsForGuests(reservation.bed_ids || [], newCount);
                    adjustment = {
                        adults: Math.min(newCount, reservation.adults || 0),
                        children: Math.max(0, newCount - (reservation.adults || 0)),
                        bed_ids: bedsToKeep,
                    };
                } else {
                    adjustment = {
                        adults: Math.min(newCount, reservation.adults || 0),
                        children: Math.max(0, newCount - (reservation.adults || 0)),
                    };
                }
            } else if (!isNaN(newCount) && newCount > totalGuests) {
                alert('Nie można zameldować więcej osób niż zarezerwowano w tym szybkim widoku. Przejdź do edycji rezerwacji.');
                return;
            }
        }

        try {
            await ReservationsAPI.checkIn(reservation.id!, adjustment);
            onClose();
            onRefresh();
        } catch (error) {
            alert('Błąd podczas zameldowania');
            console.error(error);
        }
    };

    const handleCheckOut = async () => {
        try {
            await ReservationsAPI.checkOut(reservation.id!);
            onClose();
            onRefresh();
        } catch (error) {
            alert('Błąd podczas wymeldowania');
            console.error(error);
        }
    };

    const handleCancel = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (window.confirm('Czy na pewno chcesz anulować tę rezerwację?')) {
            try {
                await ReservationsAPI.cancel(reservation.id!, 'Anulowano przez użytkownika');
                onClose();
                onRefresh();
            } catch (error) {
                alert('Błąd podczas anulowania rezerwacji');
                console.error(error);
            }
        }
    };

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-200">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-100 bg-gray-50/50">
                    <div className="flex items-center gap-3">
                        <h3 className="text-xl font-bold text-gray-900">Rezerwacja #{reservation.id}</h3>
                        <div className="flex gap-2">
                            <button
                                onClick={() => setIsEditModalOpen(true)}
                                className="p-2 rounded-lg transition-colors bg-brand-50 text-brand-600 hover:bg-brand-100"
                                title="Edytuj rezerwację"
                            >
                                <Edit3 size={18} />
                            </button>
                            <button
                                onClick={() => setActiveTab(activeTab === 'history' ? 'details' : 'history')}
                                className={`p-2 rounded-lg transition-colors ${activeTab === 'history' ? 'bg-brand-100 text-brand-700' : 'bg-white text-gray-500 hover:text-gray-900 hover:bg-gray-100'}`}
                                title="Historia zmian"
                            >
                                <History size={18} />
                            </button>
                        </div>
                    </div>
                    <button
                        onClick={() => { onClose(); setActiveTab('details'); }}
                        className="p-2 text-gray-400 hover:text-gray-600 hover:bg-white rounded-xl transition-all shadow-sm"
                    >
                        <X size={20} />
                    </button>
                </div>

                {/* Content */}
                <div className="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    {activeTab === 'history' ? (
                        <BookingHistory reservationId={reservation.id!} />
                    ) : (
                        <>
                            <div className="flex items-center gap-4 mb-4">
                                <div className="w-12 h-12 bg-brand-100 text-brand-700 rounded-full flex items-center justify-center font-bold text-lg">
                                    {reservation.first_name?.[0]}{reservation.last_name?.[0]}
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 uppercase font-bold mb-0.5 tracking-wider">Gość</p>
                                    <p className="text-xl font-black text-gray-900">{reservation.first_name} {reservation.last_name}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                <div>
                                    <p className="text-[10px] text-gray-500 uppercase font-bold mb-1 flex items-center gap-1.5">
                                        <LogIn size={12} className="text-brand-500" /> Przyjazd
                                    </p>
                                    <p className="font-bold text-gray-900">{format(parseISO(reservation.check_in), 'd MMM yyyy', { locale: pl })}</p>
                                </div>
                                <div>
                                    <p className="text-[10px] text-gray-500 uppercase font-bold mb-1 flex items-center gap-1.5">
                                        <LogOut size={12} className="text-brand-500" /> Wyjazd
                                    </p>
                                    <p className="font-bold text-gray-900">{format(parseISO(reservation.check_out), 'd MMM yyyy', { locale: pl })}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="p-3 border border-gray-100 rounded-xl">
                                    <p className="text-[10px] text-gray-500 uppercase font-bold mb-1 flex items-center gap-1.5 line-clamp-1">
                                        <User size={12} /> Dorośli
                                    </p>
                                    <p className="font-bold text-gray-900">{reservation.adults || 1}</p>
                                </div>
                                <div className="p-3 border border-gray-100 rounded-xl">
                                    <p className="text-[10px] text-gray-500 uppercase font-bold mb-1 flex items-center gap-1.5 line-clamp-1">
                                        <AlertCircle size={12} /> Dzieci
                                    </p>
                                    <p className="font-bold text-gray-900">{reservation.children || 0}</p>
                                </div>
                            </div>

                            <div className="flex items-center justify-between p-3 border border-gray-100 rounded-xl">
                                <p className="text-[10px] text-gray-500 uppercase font-bold flex items-center gap-1.5">Status</p>
                                <span className={`inline-block px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider ${reservation.status === 'confirmed' ? 'bg-green-100 text-green-700' :
                                    reservation.status === 'pending' ? 'bg-amber-100 text-amber-700' :
                                        reservation.status === 'checked_in' ? 'bg-emerald-100 text-emerald-700' :
                                            reservation.status === 'checked_out' ? 'bg-gray-100 text-gray-700' :
                                                'bg-gray-100 text-gray-700'
                                    }`}>
                                    {getStatusLabel(reservation.status || 'pending')}
                                </span>
                            </div>

                            {/* Stay Details (Nights + Beds) */}
                            <div className="p-4 bg-emerald-50 rounded-2xl border border-emerald-100 mb-4">
                                <h4 className="text-[11px] font-bold text-emerald-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                                    <BedDouble size={14} className="text-emerald-600" /> Szczegóły pobytu
                                </h4>
                                <div className="space-y-2">
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">Liczbą noclegów</span>
                                        <span className="text-sm font-bold text-gray-900">
                                            {Math.ceil((new Date(reservation.check_out).getTime() - new Date(reservation.check_in).getTime()) / (1000 * 60 * 60 * 24))} nocy
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">Liczbą łóżek</span>
                                        <span className="text-sm font-bold text-gray-900">{reservation.bed_ids?.length || 0} łóżek</span>
                                    </div>
                                    {bedAssignments.length > 0 && (
                                        <div className="pt-2 border-t border-emerald-200 space-y-2">
                                            <span className="text-sm text-gray-600">Przydział miejsc</span>
                                            <div className="space-y-1.5">
                                                {bedAssignments.map((assignment) => (
                                                    <div key={assignment.bedId} className="flex items-center justify-between gap-3 text-sm">
                                                        <span className="text-gray-700">Łóżko {assignment.bedNumber}</span>
                                                        <span className="text-right font-bold text-gray-900">
                                                            {assignment.labels.length > 0
                                                                ? assignment.labels.join(', ')
                                                                : assignment.capacity > 1
                                                                    ? `${assignment.capacity}/${assignment.capacity} miejsc`
                                                                    : 'Miejsce 1'}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    <div className="flex justify-between items-center pt-2 border-t border-emerald-200">
                                        <span className="text-sm font-bold text-emerald-700">Cena za noclegi</span>
                                        <span className="text-lg font-black text-emerald-700">
                                            {calculatedPrice !== null ? 
                                                new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(calculatedPrice) :
                                                new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(reservation.total_price || 0)
                                            }
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* EXTRA SERVICES */}
                            {extras.length > 0 && (
                                <div className="space-y-3 pt-2">
                                    <h4 className="text-[11px] font-bold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <Coins size={14} className="text-brand-500" /> Usługi Dodatkowe
                                    </h4>
                                    <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                                        {extras.map((extra) => (
                                            <div key={extra.id} className="p-3 flex items-center justify-between border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                                                        <Receipt size={16} />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-bold text-gray-900">{extra.service_name}</p>
                                                        <p className="text-[10px] text-gray-500 font-medium">
                                                            {extra.quantity} x {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(extra.unit_price)}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="text-sm font-black text-gray-900">
                                                    {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(extra.total_price)}
                                                </div>
                                            </div>
                                        ))}
                                        <div className="p-3 bg-gray-50/80 flex justify-between items-center border-t border-gray-100">
                                            <span className="text-[10px] font-bold text-gray-500 uppercase">Suma usług</span>
                                            <span className="text-sm font-black text-blue-700">
                                                {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(
                                                    extras.reduce((acc, curr) => acc + curr.total_price, 0)
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {reservation.notes && (
                                <div className="p-3 bg-amber-50 rounded-xl border border-amber-100">
                                    <p className="text-[10px] text-amber-700 uppercase font-black mb-1">Notatki rezerwacji</p>
                                    <p className="text-sm text-amber-900 font-medium">{reservation.notes}</p>
                                </div>
                            )}

                            <div className="pt-2">
                                <div className="bg-emerald-600 p-4 rounded-2xl flex items-center justify-between shadow-lg shadow-emerald-200 text-white">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                                            <CreditCard size={20} />
                                        </div>
                                        <div>
                                            <p className="text-[10px] font-bold uppercase tracking-wider opacity-80 mb-0.5">Łącznie do zapłaty</p>
                                            <p className="text-[10px] font-bold opacity-60">
                                                {extras.length > 0 ? 'Pobyt + Usługi' : 'Pobyt'}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-2xl font-black">
                                        {new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(
                                            (calculatedPrice !== null ? calculatedPrice : (reservation.total_price || 0)) +
                                            extras.reduce((acc, curr) => acc + curr.total_price, 0)
                                        )}
                                    </span>
                                </div>
                            </div>
                        </>
                    )}
                </div>

                {/* Actions */}
                <div className="p-6 border-t border-gray-100 bg-gray-50/30 flex flex-wrap gap-3">
                    {reservation.status === 'pending' && (
                        <>
                            <button
                                onClick={handleConfirm}
                                className="flex-1 min-w-[120px] px-4 py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 transition-all text-sm flex items-center justify-center gap-2 shadow-md shadow-green-100"
                            >
                                <Check size={16} />
                                Potwierdź
                            </button>
                            <button
                                onClick={handleCheckIn}
                                className="flex-1 min-w-[120px] px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all text-sm flex items-center justify-center gap-2 shadow-md shadow-emerald-100"
                            >
                                <CheckCircle2 size={16} />
                                Zamelduj
                            </button>
                        </>
                    )}
                    {reservation.status === 'confirmed' && (
                        <button
                            onClick={handleCheckIn}
                            className="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-100"
                        >
                            <CheckCircle2 size={16} />
                            Zameldowanie gości
                        </button>
                    )}
                    {reservation.status === 'checked_in' && (
                        <button
                            onClick={handleCheckOut}
                            className="flex-1 px-4 py-3 bg-gray-800 text-white rounded-xl font-bold hover:bg-black transition-all text-sm flex items-center justify-center gap-2"
                        >
                            <LogOut size={16} />
                            Wymeldowanie
                        </button>
                    )}

                    <div className="flex w-full gap-3 mt-1">
                        <button
                            onClick={handleCancel}
                            className="flex-1 px-4 py-2 text-xs font-bold text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all border border-transparent hover:border-red-100"
                        >
                            Anuluj pobyt
                        </button>
                        <button
                            onClick={onClose}
                            className="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-bold hover:bg-gray-300 transition-all text-xs"
                        >
                            Zamknij okno
                        </button>
                    </div>
                </div>
            </div>

            {/* Edit Reservation Modal */}
            <EditReservationModal
                reservation={reservation}
                isOpen={isEditModalOpen}
                onClose={() => setIsEditModalOpen(false)}
                onSuccess={() => {
                    setIsEditModalOpen(false);
                    setRefreshTrigger(prev => prev + 1); // Force price recalculation
                    onRefresh();
                }}
                rooms={rooms}
            />
        </div>
    );
};

export default ReservationDetailsModal;
