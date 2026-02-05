import React, { useState, useEffect } from 'react';
import { Plus, BedDouble, Trash2, Home, LayoutGrid, Loader2 } from 'lucide-react';
import { RoomsAPI, Room } from '../services/api';

const RoomManager: React.FC = () => {
    const [rooms, setRooms] = useState<Room[]>([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    // New Room Form State
    const [newRoom, setNewRoom] = useState({
        name: '',
        type: 'private',
        floor: 1,
        bedCount: 1
    });

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

    const handleCreateRoom = async (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        try {
            // 1. Create Room
            const roomPayload = {
                name: newRoom.name,
                room_type: newRoom.type,
                floor: newRoom.floor
            };

            const createdRoom = await RoomsAPI.create(roomPayload);

            if (createdRoom && createdRoom.id) {
                // 2. Create Beds
                const bedPromises = [];
                for (let i = 0; i < newRoom.bedCount; i++) {
                    bedPromises.push(RoomsAPI.createBed(createdRoom.id, {
                        bed_number: i + 1,
                        bed_type: newRoom.type === 'dorm' ? 'bunk_bottom' : 'single',
                        is_active: true
                    }));
                }

                await Promise.all(bedPromises);

                // Success
                setNewRoom({ name: '', type: 'private', floor: 1, bedCount: 1 });
                setShowForm(false);
                fetchRooms(); // Refresh list
            }
        } catch (e) {
            alert('Błąd podczas zapisywania pokoju.');
            console.error(e);
        } finally {
            setSubmitting(false);
        }
    };

    const handleDeleteRoom = async (id: number) => {
        if (!confirm('Czy na pewno chcesz usunąć ten pokój? Usunięte zostaną również wszystkie łóżka.')) return;

        try {
            // Backend should handle cascade delete of beds (Database ON DELETE CASCADE usually)
            // Or we delete beds first. Let's assume Backend handles cleanup or returns error if not empty.
            // Our Schema Step 3: FOREIGN KEY (room_id) REFERENCES {$rooms} (id) ON DELETE CASCADE
            // So deleting room handles beds automatically!
            await RoomsAPI.delete(id);
            // Optimistic update or refresh
            setRooms(rooms.filter(r => r.id !== id));
        } catch (e) {
            alert('Nie udało się usunąć pokoju.');
            console.error(e);
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <div>
                    <h2 className="text-2xl font-bold text-gray-900">Pokoje i Łóżka</h2>
                    <p className="text-gray-500">Zarządzaj strukturą swojego obiektu.</p>
                </div>
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
                <div className="bg-white p-6 rounded-2xl border border-brand-100 shadow-lg mb-6 animate-in slide-in-from-top-4">
                    <h3 className="font-bold text-lg mb-4">Nowy Pokój</h3>
                    <form onSubmit={handleCreateRoom} className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Nazwa / Numer</label>
                                <input
                                    type="text"
                                    className="w-full border-gray-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    placeholder="np. Pokój 101"
                                    value={newRoom.name}
                                    onChange={e => setNewRoom({ ...newRoom, name: e.target.value })}
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Piętro</label>
                                <input
                                    type="number"
                                    className="w-full border-gray-300 rounded-lg shadow-sm"
                                    value={newRoom.floor}
                                    onChange={e => setNewRoom({ ...newRoom, floor: parseInt(e.target.value) })}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Typ Pokoju</label>
                                <select
                                    className="w-full border-gray-300 rounded-lg shadow-sm"
                                    value={newRoom.type}
                                    onChange={e => setNewRoom({ ...newRoom, type: e.target.value })}
                                >
                                    <option value="private">Prywatny</option>
                                    <option value="dorm">Wieloosobowy (Dorm)</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Liczba Łóżek</label>
                                <input
                                    type="number"
                                    min="1"
                                    className="w-full border-gray-300 rounded-lg shadow-sm"
                                    value={newRoom.bedCount}
                                    onChange={e => setNewRoom({ ...newRoom, bedCount: parseInt(e.target.value) })}
                                />
                            </div>
                        </div>
                        <div className="flex justify-end gap-3 pt-4">
                            <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Anuluj</button>
                            <button
                                type="submit"
                                disabled={submitting}
                                className="px-6 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 flex items-center gap-2 disabled:opacity-70"
                            >
                                {submitting && <Loader2 className="animate-spin" size={16} />}
                                {submitting ? 'Zapisywanie...' : 'Zapisz'}
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
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pb-12">
                    {rooms.map(room => (
                        <div key={room.id} className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col hover:border-brand-300 transition-colors group">
                            <div className="p-5 border-b border-gray-100 flex justify-between items-start bg-gray-50/50">
                                <div className="flex items-center gap-3">
                                    <div className={`p-2 rounded-lg ${room.room_type === 'private' ? 'bg-purple-100 text-purple-600' : 'bg-orange-100 text-orange-600'}`}>
                                        {room.room_type === 'private' ? <Home size={20} /> : <LayoutGrid size={20} />}
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-gray-900">{room.name}</h3>
                                        <span className="text-xs uppercase tracking-wider font-semibold text-gray-500 mr-2">{room.room_type === 'private' ? 'Pokój' : 'Dorm'}</span>
                                        <span className="text-xs text-gray-400">Piętro {room.floor}</span>
                                    </div>
                                </div>
                                <button
                                    onClick={() => room.id && handleDeleteRoom(room.id)}
                                    className="text-gray-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100 p-1"
                                    title="Usuń pokój"
                                >
                                    <Trash2 size={18} />
                                </button>
                            </div>
                            <div className="p-5 flex-1">
                                <h4 className="text-xs font-semibold text-gray-400 mb-3 uppercase flex justify-between">
                                    <span>Konfiguracja Łóżek</span>
                                    <span>{room.beds?.length || 0}</span>
                                </h4>
                                <div className="space-y-2 max-h-40 overflow-y-auto pr-1">
                                    {room.beds?.map(bed => (
                                        <div key={bed.id} className="flex items-center gap-2 text-sm text-gray-600 bg-gray-50/80 p-2 rounded-lg border border-gray-100">
                                            <BedDouble size={16} className={bed.is_active ? "text-brand-500" : "text-gray-300"} />
                                            <span className="font-medium text-gray-700">Łóżko {bed.bed_number}</span>
                                            <span className="text-[10px] ml-auto text-gray-400 border border-gray-200 px-1.5 py-0.5 rounded-full uppercase font-bold tracking-tight bg-white">
                                                {bed.bed_type && bed.bed_type.replace('bunk_', 'piętrowe ')}
                                            </span>
                                        </div>
                                    ))}
                                    {(!room.beds || room.beds.length === 0) && (
                                        <div className="text-center py-4 text-gray-400 italic text-sm">Brak łóżek</div>
                                    )}
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
