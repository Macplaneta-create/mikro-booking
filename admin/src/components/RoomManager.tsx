import React, { useState, useEffect } from 'react';
import { Plus, BedDouble, Trash2, Home, Loader2, Image as ImageIcon, Tv, Wifi, Coffee, Wind, Thermometer, ShowerHead, EyeOff, CheckCircle2, AlertTriangle, Edit3, Save, X } from 'lucide-react';
import { RoomsAPI, Room } from '../services/api';

const RoomManager: React.FC = () => {
    const [rooms, setRooms] = useState<Room[]>([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    // Room Form State
    const [editingRoom, setEditingRoom] = useState<Room | null>(null);
    const [formData, setFormData] = useState<Partial<Room>>({
        name: '',
        room_type: 'standard',
        floor: 1,
        description: '',
        amenities: [],
        pricing_mode: 'per_room',
        status: 'active'
    });
    const [beds, setBeds] = useState<any[]>([]);

    const getBedCapacity = (bed: any): number => {
        const type = String(bed?.bed_type || 'single');
        if (type === 'bunk') return 2;
        return 1;
    };

    const AMENITIES_OPTIONS = [
        { id: 'wifi', icon: <Wifi size={16} />, label: 'Wi-Fi' },
        { id: 'tv', icon: <Tv size={16} />, label: 'TV' },
        { id: 'bathroom', icon: <ShowerHead size={16} />, label: 'Łazienka' },
        { id: 'ac', icon: <Wind size={16} />, label: 'Klimatyzacja' },
        { id: 'coffee', icon: <Coffee size={16} />, label: 'Kawa/Herbata' },
        { id: 'heating', icon: <Thermometer size={16} />, label: 'Ogrzewanie' },
    ];

    const fetchRooms = async () => {
        try {
            setLoading(true);
            const data = await RoomsAPI.getAll();
            // Fetch beds for each room to display count and details
            // In a real app with many rooms, we might want 'include=beds' in getAll API
            // For now, let's just use what we have. If getAll doesn't return beds (it doesn't in our current Controller get_items),
            // we need to fetch them individually or update Controller.
            // Let's rely on basic info for now, and maybe fetch details on expand.
            // Actually, RoomsController::get_items does NOT attach beds. 
            // We'll update the list without beds first, or update Controller to support ?include=beds.
            // For UI/UX, showing bed count is critical. 
            // Let's try to fetch beds for each room in parallel (not efficient for 100 rooms, but ok for 10)

            const roomsWithBeds = await Promise.all(data.map(async (room) => {
                if (room.id) {
                    const beds = await RoomsAPI.getBeds(room.id);
                    return { ...room, beds };
                }
                return room;
            }));

            setRooms(roomsWithBeds);
        } catch (e) {
            console.error('Failed to fetch rooms', e);
            // alert('Nie udało się pobrać listy pokoi. Sprawdź połączenie.');
        } finally {
            setLoading(false);
        }
    };

    // Load rooms on mount
    useEffect(() => {
        fetchRooms();
    }, []);

    const handleOpenMediaLibrary = () => {
        console.log('[RoomManager] Opening media library...');
        console.log('[RoomManager] window:', typeof window !== 'undefined');
        console.log('[RoomManager] window.wp:', typeof window !== 'undefined' ? (window as any).wp : 'window not defined');
        console.log('[RoomManager] window.wp.media:', typeof window !== 'undefined' ? (window as any).wp?.media : 'undefined');
        
        // Check if WordPress media library is available
        if (typeof window === 'undefined') {
            alert('Środowisko przeglądarki nie jest dostępne.');
            return;
        }

        const wpMedia = (window as any).wp?.media;
        if (!wpMedia) {
            console.error('[RoomManager] wp.media not found! Available window.wp:', (window as any).wp);
            alert('Biblioteka mediów WordPress nie jest załadowana.\n\nSpróbuj:\n1. Odśwież stronę (Ctrl+F5)\n2. Sprawdź konsolę (F12) pod kątem błędów\n3. Wyłącz inne wtyczki i sprawdź czy jest konflikt');
            return;
        }

        const frame = wpMedia({
            title: 'Wybierz zdjęcie pokoju',
            button: {
                text: 'Użyj tego zdjęcia'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            console.log('[RoomManager] Selected attachment:', attachment);
            setFormData({
                ...formData,
                image_id: attachment.id,
                image_url: attachment.url || attachment.sizes?.full?.url || attachment.sizes?.large?.url || attachment.sizes?.medium?.url
            });
        });

        frame.open();
    };

    const handleSaveRoom = async (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        try {
            if (editingRoom && editingRoom.id) {
                // Update room basic info
                const updatePayload = {
                    name: formData.name || '',
                    description: formData.description || '',
                    image_id: formData.image_id || 0,
                    floor: formData.floor || 0,
                    room_type: formData.room_type || 'standard',
                    pricing_mode: formData.pricing_mode || 'per_room',
                    status: formData.status || 'active',
                    amenities: formData.amenities || []
                };
                
                console.log('[RoomManager] Updating room:', editingRoom.id, updatePayload);
                
                const result = await RoomsAPI.update(editingRoom.id, updatePayload);
                console.log('[RoomManager] Update result:', result);
                
                await fetchRooms();
                setEditingRoom(null);
            } else {
                // Create new room
                const roomPayload = {
                    ...formData,
                    name: formData.name || '',
                    room_type: formData.room_type || 'standard',
                    floor: formData.floor || 0,
                    pricing_mode: formData.pricing_mode || (formData.room_type === 'dormitory' ? 'per_bed' : 'per_room'),
                    status: formData.status || 'active',
                    amenities: formData.amenities || []
                } as Omit<Room, 'id'>;

                const createdRoom = await RoomsAPI.create(roomPayload);

                if (createdRoom && createdRoom.id) {
                    // Create Beds from the temporary state
                    const bedPromises = beds.map((bed, index) =>
                        RoomsAPI.createBed(createdRoom.id!, {
                            bed_number: index + 1,
                            bed_type: bed.bed_type || (formData.room_type === 'dormitory' ? 'bunk' : 'single'),
                            is_active: true
                        })
                    );
                    await Promise.all(bedPromises);
                    fetchRooms();
                }
            }
            setShowForm(false);
            resetForm();
        } catch (e: any) {
            const errorMsg = e.response?.data?.message || e.message || 'Nieznany błąd';
            alert('Błąd podczas zapisywania pokoju: ' + errorMsg);
            console.error('[RoomManager] Save error:', e);
            console.error('[RoomManager] Error details:', e.response?.data);
        } finally {
            setSubmitting(false);
        }
    };

    const resetForm = () => {
        setFormData({
            name: '',
            room_type: 'standard',
            floor: 1,
            description: '',
            amenities: [],
            pricing_mode: 'per_room',
            status: 'active'
        });
        setBeds([{ bed_type: 'single' }]); // Default one bed for new room
        setEditingRoom(null);
        setShowForm(false);
    };

    const handleAddBed = async () => {
        if (editingRoom && editingRoom.id) {
            // Instant API call for existing room
            try {
                const nextNumber = (beds.length > 0 ? Math.max(...beds.map(b => b.bed_number || 0)) : 0) + 1;
                const newBed = await RoomsAPI.createBed(editingRoom.id, {
                    bed_number: nextNumber,
                    bed_type: formData.room_type === 'dormitory' ? 'bunk' : 'single',
                    is_active: true
                });
                setBeds([...beds, newBed]);
            } catch (e) {
                alert('Nie udało się dodać łóżka.');
            }
        } else {
            // Local state for new room
            setBeds([...beds, { bed_type: 'single' }]);
        }
    };

    const handleDeleteBed = async (bedIndex: number, bedId?: number) => {
        if (editingRoom && editingRoom.id && bedId) {
            // Instant API call for existing room
            if (!confirm('Czy na pewno chcesz usunąć to łóżko?')) return;
            try {
                // Dynamically import to ensure we have BedsAPI
                const { BedsAPI } = await import('../services/api');
                await BedsAPI.delete(bedId);
                setBeds(beds.filter(b => b.id !== bedId));
            } catch (e: any) {
                const errorMsg = e.response?.data?.message || '';
                if (errorMsg.includes('foreign key') || errorMsg.includes('rezerwacj')) {
                    alert('Nie można usunąć tego łóżka, ponieważ jest ono przypisane do istniejących rezerwacji. Możesz je dezaktywować (funkcjonalność wkrótce) lub najpierw anulować rezerwacje.');
                } else {
                    alert('Nie udało się usunąć łóżka. Prawdopodobnie jest powiązane z istniejącą rezerwacją.');
                }
            }
        } else {
            // Local state for new room
            setBeds(beds.filter((_, i) => i !== bedIndex));
        }
    };

    const handleUpdateBedType = async (index: number, bedId: number | undefined, newType: string) => {
        if (editingRoom && editingRoom.id && bedId) {
            try {
                const { BedsAPI } = await import('../services/api');
                await BedsAPI.update(bedId, { bed_type: newType });
                setBeds(beds.map((b, i) => i === index ? { ...b, bed_type: newType } : b));
            } catch (e) {
                alert('Nie udało się zmienić typu łóżka.');
            }
        } else {
            setBeds(beds.map((b, i) => i === index ? { ...b, bed_type: newType } : b));
        }
    };

    const toggleAmenity = (amenityId: string) => {
        const current = formData.amenities || [];
        if (current.includes(amenityId)) {
            setFormData({ ...formData, amenities: current.filter(a => a !== amenityId) });
        } else {
            setFormData({ ...formData, amenities: [...current, amenityId] });
        }
    };

    const handleDeleteRoom = async (id: number) => {
        if (!confirm('Czy na pewno chcesz usunąć ten pokój? Usunięte zostaną również wszystkie łóżka.')) return;

        try {
            await RoomsAPI.delete(id);
            setRooms(rooms.filter(r => r.id !== id));
        } catch (e) {
            alert('Nie udało się usunąć pokoju.');
            console.error(e);
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-end items-center">
                <button
                    onClick={() => setShowForm(true)}
                    className="flex items-center gap-2 bg-brand-600 text-white px-4 py-2 rounded-xl hover:bg-brand-700 transition"
                >
                    <Plus size={20} />
                    Dodaj Pokój
                </button>
            </div>

            {/* Room Creator Modal/Form */}
            {showForm && (
                <div className="bg-white p-8 rounded-3xl border border-brand-100 shadow-xl mb-8 animate-in slide-in-from-top-4">
                    <div className="flex justify-between items-center mb-6">
                        <h3 className="font-bold text-xl">{editingRoom ? 'Edytuj Pokój' : 'Nowy Pokój'}</h3>
                        <button onClick={resetForm} className="p-2 hover:bg-gray-100 rounded-full text-gray-400">
                            <X size={20} />
                        </button>
                    </div>

                    <form onSubmit={handleSaveRoom} className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-12 gap-6">
                            {/* Left Column - Basic Info */}
                            <div className="md:col-span-8 space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-bold text-gray-700 mb-1">Nazwa / Numer</label>
                                        <input
                                            type="text"
                                            className="w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                            placeholder="np. Pokój 101"
                                            value={formData.name}
                                            onChange={e => setFormData({ ...formData, name: e.target.value })}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-bold text-gray-700 mb-1">Piętro</label>
                                        <input
                                            type="number"
                                            className="w-full border-gray-300 rounded-xl shadow-sm"
                                            value={formData.floor}
                                            onChange={e => setFormData({ ...formData, floor: parseInt(e.target.value) })}
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-1">Opis (krótki)</label>
                                    <textarea
                                        className="w-full border-gray-300 rounded-xl shadow-sm h-20"
                                        placeholder="Krótki opis pokoju dla personelu/gości..."
                                        value={formData.description || ''}
                                        onChange={e => setFormData({ ...formData, description: e.target.value })}
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">Zdjęcie Pokoju</label>
                                    <div className="flex items-center gap-4">
                                        <div className="w-24 h-24 rounded-2xl bg-gray-100 border border-gray-200 overflow-hidden flex items-center justify-center">
                                            {formData.image_url ? (
                                                <img src={formData.image_url} alt="Room" className="w-full h-full object-cover" />
                                            ) : (
                                                <ImageIcon size={32} className="text-gray-300" />
                                            )}
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <button
                                                type="button"
                                                onClick={handleOpenMediaLibrary}
                                                className="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 flex items-center gap-2 transition-colors"
                                            >
                                                <ImageIcon size={16} />
                                                {formData.image_url ? 'Zmień Zdjęcie' : 'Dodaj Zdjęcie'}
                                            </button>
                                            {formData.image_url && (
                                                <button
                                                    type="button"
                                                    onClick={() => setFormData({ ...formData, image_id: 0, image_url: '' })}
                                                    className="text-xs text-red-500 font-bold hover:underline text-left px-1"
                                                >
                                                    Usuń zdjęcie
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">Udogodnienia</label>
                                    <div className="flex flex-wrap gap-2">
                                        {AMENITIES_OPTIONS.map(opt => (
                                            <button
                                                key={opt.id}
                                                type="button"
                                                onClick={() => toggleAmenity(opt.id)}
                                                className={`flex items-center gap-2 px-3 py-2 rounded-xl border transition-all ${formData.amenities?.includes(opt.id)
                                                    ? 'bg-brand-50 border-brand-200 text-brand-700 font-medium'
                                                    : 'bg-white border-gray-200 text-gray-500 hover:border-brand-200'
                                                    }`}
                                            >
                                                {opt.icon}
                                                <span className="text-xs">{opt.label}</span>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* Right Column - Status & Type */}
                            <div className="md:col-span-4 space-y-4 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">Status Operacyjny</label>
                                    <div className="space-y-2">
                                        {[
                                            { id: 'active', label: 'Aktywny', icon: <CheckCircle2 size={16} />, color: 'text-emerald-600' },
                                            { id: 'maintenance', label: 'Niedostępny (Remont/Awar)', icon: <AlertTriangle size={16} />, color: 'text-orange-600' },
                                            { id: 'inactive', label: 'Wyłączony', icon: <EyeOff size={16} />, color: 'text-gray-600' }
                                        ].map(s => (
                                            <label key={s.id} className={`flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all ${formData.status === s.id ? 'bg-white border-brand-200 shadow-sm' : 'border-transparent'}`}>
                                                <input
                                                    type="radio"
                                                    name="status"
                                                    className="text-brand-600 focus:ring-brand-500"
                                                    checked={formData.status === s.id}
                                                    onChange={() => setFormData({ ...formData, status: s.id as any })}
                                                />
                                                <div className={`flex items-center gap-2 text-sm font-medium ${s.color}`}>
                                                    {s.icon}
                                                    {s.label}
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-1">Typ Pokoju</label>
                                    <select
                                        className="w-full border-gray-300 rounded-xl shadow-sm"
                                        value={formData.room_type}
                                        onChange={e => setFormData({ ...formData, room_type: e.target.value })}
                                    >
                                        <option value="standard">Standardowy</option>
                                        <option value="deluxe">Deluxe</option>
                                        <option value="studio">Studio</option>
                                        <option value="suite">Apartament (Suite)</option>
                                        <option value="cabin">Domek (Cabin)</option>
                                        <option value="dormitory">Wieloosobowy (Dorm)</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-2">Model Rozliczeń</label>
                                    <div className="grid grid-cols-2 gap-2">
                                        {[
                                            { id: 'per_room', label: 'Za pokój', hint: 'Cena całego pokoju' },
                                            { id: 'per_bed', label: 'Za łóżko', hint: 'Suma wybranych łóżek' }
                                        ].map(m => (
                                            <button
                                                key={m.id}
                                                type="button"
                                                onClick={() => setFormData({ ...formData, pricing_mode: m.id as any })}
                                                className={`flex flex-col items-center justify-center p-3 rounded-xl border transition-all ${formData.pricing_mode === m.id
                                                    ? 'bg-brand-50 border-brand-200 text-brand-700 font-bold'
                                                    : 'bg-white border-gray-200 text-gray-400 hover:border-brand-100'
                                                    }`}
                                            >
                                                <span className="text-sm">{m.label}</span>
                                                <span className="text-[10px] font-normal opacity-70">{m.hint}</span>
                                            </button>
                                        ))}
                                    </div>
                                    {formData.room_type === 'dormitory' && formData.pricing_mode === 'per_room' && (
                                        <div className="mt-2 flex items-start gap-2 p-2 bg-orange-50 rounded-lg text-orange-700 text-[10px] leading-tight">
                                            <AlertTriangle size={12} className="shrink-0 mt-0.5" />
                                            <span>Dla pokoi wieloosobowych zalecany jest model "Za łóżko".</span>
                                        </div>
                                    )}
                                </div>

                                <div>
                                    <div className="flex justify-between items-center mb-4">
                                        <label className="block text-sm font-bold text-gray-700">Łóżka w tym pokoju</label>
                                        <button
                                            type="button"
                                            onClick={handleAddBed}
                                            className="text-xs bg-brand-50 text-brand-600 px-2 py-1 rounded-lg font-bold hover:bg-brand-100 transition flex items-center gap-1"
                                        >
                                            <Plus size={14} /> Dodaj
                                        </button>
                                    </div>
                                    <div className="space-y-2 max-h-48 overflow-y-auto pr-2 custom-scrollbar">
                                        {beds.length === 0 && (
                                            <p className="text-gray-400 text-xs italic text-center py-4">Brak łóżek. Dodaj przynajmniej jedno.</p>
                                        )}
                                        {beds.map((bed, index) => (
                                            <div key={bed.id || index} className="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-100 shadow-sm">
                                                <div className="flex items-center gap-3 flex-1">
                                                    <div className="w-8 h-8 rounded-full bg-brand-50 flex items-center justify-center text-brand-600 flex-shrink-0">
                                                        <BedDouble size={14} />
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex justify-between items-center mb-1">
                                                            <span className="text-sm font-bold text-gray-800 truncate">
                                                                {bed.bed_number ? `Łóżko #${parseInt(bed.bed_number)}` : `Nowe łóżko ${index + 1}`}
                                                            </span>
                                                        </div>
                                                        <select
                                                            className="text-[10px] text-brand-600 uppercase font-black bg-transparent border-none p-0 focus:ring-0 cursor-pointer hover:text-brand-700"
                                                            value={bed.bed_type || 'single'}
                                                            onChange={(e) => handleUpdateBedType(index, bed.id, e.target.value)}
                                                        >
                                                            <option value="single">Pojedyncze</option>
                                                            <option value="double">Podwójne</option>
                                                            <option value="bunk">Piętrowe</option>
                                                        </select>
                                                        {bed.bed_type && (
                                                            <p className="text-[10px] text-gray-500 mt-0.5 capitalize">
                                                                {bed.bed_type === 'single' ? 'Pojedyncze' : 
                                                                 bed.bed_type === 'double' ? 'Podwójne' : 
                                                                 bed.bed_type === 'bunk' ? 'Piętrowe' : bed.bed_type}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDeleteBed(index, bed.id)}
                                                    className="p-1.5 text-gray-300 hover:text-red-500 transition-colors ml-2"
                                                >
                                                    <Trash2 size={16} />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-6 border-t border-gray-100">
                            <button type="button" onClick={resetForm} className="px-6 py-2 text-gray-600 hover:bg-gray-100 rounded-xl font-medium transition">Anuluj</button>
                            <button
                                type="submit"
                                disabled={submitting}
                                className="px-10 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 flex items-center gap-2 disabled:opacity-70 font-bold shadow-lg shadow-brand-100"
                            >
                                {submitting ? <Loader2 className="animate-spin" size={18} /> : (editingRoom ? <Save size={18} /> : <Plus size={18} />)}
                                {submitting ? 'Zapisywanie...' : (editingRoom ? 'Zapisz Zmiany' : 'Utwórz Pokój')}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Loading State */}
            {loading && (
                <div className="flex justify-center py-20">
                    <Loader2 className="animate-spin text-brand-600" size={40} />
                </div>
            )}

            {/* Room List or Empty State */}
            {!loading && rooms.length === 0 && !showForm ? (
                <div className="flex flex-col items-center justify-center p-20 bg-white rounded-3xl border-2 border-dashed border-gray-200">
                    <div className="w-20 h-20 bg-brand-50 rounded-full flex items-center justify-center mb-6">
                        <Home className="text-brand-600" size={40} />
                    </div>
                    <h3 className="text-xl font-bold text-gray-900 mb-2">Brak pokoi w bazie</h3>
                    <p className="text-gray-500 mb-8 text-center max-w-sm">
                        Zacznij od dodania pierwszego pokoju lub dormu, aby móc zacząć przyjmować rezerwacje.
                    </p>
                    <button
                        onClick={() => setShowForm(true)}
                        className="bg-brand-600 text-white px-8 py-3 rounded-2xl font-bold hover:scale-105 transition-transform shadow-lg shadow-brand-200"
                    >
                        Dodaj Pierwszy Pokój
                    </button>
                </div>
            ) : null}

            {!loading && rooms.length > 0 && (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 pb-12">
                    {rooms.map(room => (
                        <div key={room.id} className={`bg-white rounded-3xl border shadow-sm overflow-hidden flex flex-col hover:shadow-md transition-all group ${room.status === 'maintenance' ? 'border-orange-200 opacity-90' : 'border-gray-200'}`}>
                            {/* Room Header / Image */}
                            <div className="relative h-44 bg-gray-200">
                                {room.image_url ? (
                                    <img src={room.image_url} alt={room.name} className="w-full h-full object-cover" />
                                ) : (
                                    <div className="w-full h-full flex flex-col items-center justify-center text-gray-400 bg-gray-100">
                                        <ImageIcon size={40} strokeWidth={1} />
                                        <span className="text-[10px] uppercase tracking-widest mt-2 font-bold opacity-60">Brak zdjęcia</span>
                                    </div>
                                )}

                                {/* Status Badge */}
                                <div className="absolute top-4 left-4">
                                    {room.status === 'active' ? (
                                        <span className="px-2 py-1 bg-emerald-500 text-white text-[10px] font-bold rounded-lg shadow-lg flex items-center gap-1">
                                            <CheckCircle2 size={10} /> AKTYWNY
                                        </span>
                                    ) : room.status === 'maintenance' ? (
                                        <span className="px-2 py-1 bg-orange-500 text-white text-[10px] font-bold rounded-lg shadow-lg flex items-center gap-1">
                                            <AlertTriangle size={10} /> REMONT
                                        </span>
                                    ) : (
                                        <span className="px-2 py-1 bg-gray-500 text-white text-[10px] font-bold rounded-lg shadow-lg flex items-center gap-1">
                                            <EyeOff size={10} /> WYŁĄCZONY
                                        </span>
                                    )}
                                </div>

                                {/* Room Type Label */}
                                <div className="absolute top-4 right-4 px-2 py-1 bg-white/90 backdrop-blur-sm text-gray-700 text-[10px] font-bold rounded-lg shadow-sm border border-white/20 capitalize">
                                    {room.room_type}
                                </div>

                                {/* Quick Actions */}
                                <div className="absolute bottom-4 right-4 flex gap-2">
                                    <button
                                        onClick={() => {
                                            setEditingRoom(room);
                                            setFormData({ ...room });
                                            setBeds(room.beds || []);
                                            setShowForm(true);
                                        }}
                                        className="p-2 bg-white text-gray-600 rounded-xl shadow-lg hover:text-brand-600 transition-colors"
                                        title="Edytuj szczegóły"
                                    >
                                        <Edit3 size={18} />
                                    </button>
                                    <button
                                        onClick={() => room.id && handleDeleteRoom(room.id)}
                                        className="p-2 bg-white text-gray-600 rounded-xl shadow-lg hover:text-red-500 transition-colors"
                                        title="Usuń pokój"
                                    >
                                        <Trash2 size={18} />
                                    </button>
                                </div>
                            </div>

                            {/* Room Info */}
                            <div className="p-6 flex-1 flex flex-col">
                                <div className="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 className="text-xl font-black text-gray-900 leading-none mb-1">{room.name}</h3>
                                        <span className="text-xs text-gray-400 font-bold uppercase tracking-tighter">Piętro {room.floor}</span>
                                    </div>
                                    <div className="flex -space-x-1">
                                        {room.beds?.slice(0, 4).map((_, i) => (
                                            <div key={i} className="w-6 h-6 rounded-full border-2 border-white bg-brand-100 flex items-center justify-center text-brand-600">
                                                <BedDouble size={10} />
                                            </div>
                                        ))}
                                        {(room.beds?.length || 0) > 4 && (
                                            <div className="w-6 h-6 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[8px] font-bold text-gray-500">
                                                +{(room.beds?.length || 0) - 4}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {room.description && (
                                    <p className="text-sm text-gray-500 line-clamp-2 italic mb-4">
                                        "{room.description}"
                                    </p>
                                )}

                                {/* Amenities Icons */}
                                {room.amenities && room.amenities.length > 0 && (
                                    <div className="flex flex-wrap gap-2 mb-4">
                                        {room.amenities.map(slug => {
                                            const opt = AMENITIES_OPTIONS.find(o => o.id === slug);
                                            return opt ? (
                                                <div key={slug} className="p-2 bg-gray-50 text-gray-400 rounded-lg hover:text-brand-500 transition-colors" title={opt.label}>
                                                    {opt.icon}
                                                </div>
                                            ) : null;
                                        })}
                                    </div>
                                )}

                                <div className="mt-auto pt-4 border-t border-gray-50 flex justify-between items-center text-xs">
                                    <div className="flex items-center gap-3 text-gray-400">
                                        <BedDouble size={14} />
                                        <span>Łóżek: <strong>{room.beds?.length || 0}</strong></span>
                                        <span>Miejsc: <strong>{(room.beds || []).reduce((sum, bed) => sum + getBedCapacity(bed), 0)}</strong></span>
                                    </div>
                                    <span className={`font-bold transition-colors ${room.status === 'maintenance' ? 'text-orange-500' : 'text-emerald-500'}`}>
                                        {room.status === 'active' ? 'Obsługa możliwa' : 'Przerwa techniczna'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ))}


                    {/* 'Add More' card */}
                    <button
                        onClick={() => setShowForm(true)}
                        className="group bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200 p-8 flex flex-col items-center justify-center gap-3 hover:border-brand-400 hover:bg-brand-50/30 transition-all min-h-[300px]"
                    >
                        <div className="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform border border-gray-100 text-gray-400 group-hover:text-brand-600">
                            <Plus size={24} />
                        </div>
                        <p className="font-bold text-gray-500 group-hover:text-brand-700">Dodaj Następny Pokój</p>
                    </button>
                </div>
            )}
        </div>
    );
};

export default RoomManager;
