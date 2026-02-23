import React, { useState, useEffect } from 'react';
import { DollarSign, Plus, Trash2, Calendar, Save, Loader2, AlertCircle } from 'lucide-react';
import { RoomsAPI, PricingAPI } from '../services/api';

interface Room {
    id?: number;
    name: string;
    room_type: string;
    pricing_mode: 'per_room' | 'per_bed';
    beds?: any[];
}

interface PricingRule {
    id?: number;
    name: string | null;
    scope_type: 'room_id' | 'room_type';
    room_id: number | null;
    room_type: string | null;
    pricing_mode: 'per_room' | 'per_bed' | null;
    priority: number;
    start_date: string;
    end_date: string;
    base_price: number;
    weekend_price: number;
}

const PricingView: React.FC = () => {
    const [rooms, setRooms] = useState<Room[]>([]);
    const [pricingRules, setPricingRules] = useState<PricingRule[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [showAddForm, setShowAddForm] = useState(false);
    const [editingRule, setEditingRule] = useState<PricingRule | null>(null);

    const [newRule, setNewRule] = useState<PricingRule>({
        name: '',
        scope_type: 'room_type',
        room_id: null,
        room_type: 'dormitory',
        pricing_mode: null,
        priority: 100,
        start_date: new Date().toISOString().split('T')[0],
        end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        base_price: 100,
        weekend_price: 120,
    });

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        try {
            setLoading(true);
            const roomsData = await RoomsAPI.getAll();
            setRooms(roomsData);

            const rulesData = await PricingAPI.getAll();
            setPricingRules(rulesData);
        } catch (error) {
            console.error('Failed to fetch data:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleAddRule = async () => {
        if (newRule.scope_type === 'room_id' && (!newRule.room_id || newRule.room_id === 0)) {
            alert('Wybierz pokój dla reguły scope=Pokój');
            return;
        }

        if (newRule.scope_type === 'room_type' && !newRule.room_type) {
            alert('Wybierz typ pokoju dla reguły scope=Typ pokoju');
            return;
        }

        try {
            setSaving(true);
            const created = await PricingAPI.create(newRule);
            setPricingRules([...pricingRules, created]);

            // Reset form
            setNewRule({
                name: '',
                scope_type: 'room_type',
                room_id: null,
                room_type: 'dormitory',
                pricing_mode: null,
                priority: 100,
                start_date: new Date().toISOString().split('T')[0],
                end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                base_price: 100,
                weekend_price: 120,
            });
            setShowAddForm(false);
        } catch (error) {
            console.error('Failed to save pricing rule:', error);
            const message = (error as any)?.response?.data?.message || (error as any)?.message || 'Błąd przy zapisywaniu cennika';
            alert(`Błąd przy zapisywaniu cennika: ${message}`);
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteRule = async (id: number) => {
        if (!confirm('Czy na pewno chcesz usunąć tę regułę cenową?')) return;

        try {
            await PricingAPI.delete(id);
            setPricingRules(pricingRules.filter(r => r.id !== id));
        } catch (error) {
            console.error('Failed to delete pricing rule:', error);
            alert('Błąd przy usuwaniu reguły');
        }
    };

    const handleEditRule = (rule: PricingRule) => {
        setEditingRule(rule);
        setNewRule({
            name: rule.name || '',
            scope_type: rule.scope_type || 'room_id',
            room_id: rule.room_id ?? null,
            room_type: rule.room_type ?? 'dormitory',
            pricing_mode: rule.pricing_mode ?? null,
            priority: rule.priority ?? 100,
            start_date: rule.start_date,
            end_date: rule.end_date,
            base_price: rule.base_price,
            weekend_price: rule.weekend_price,
        });
        setShowAddForm(true);
    };

    const handleUpdateRule = async () => {
        if (!editingRule || !editingRule.id) return;

        if (newRule.scope_type === 'room_id' && (!newRule.room_id || newRule.room_id === 0)) {
            alert('Wybierz pokój dla reguły scope=Pokój');
            return;
        }

        if (newRule.scope_type === 'room_type' && !newRule.room_type) {
            alert('Wybierz typ pokoju dla reguły scope=Typ pokoju');
            return;
        }

        try {
            setSaving(true);
            await PricingAPI.update(editingRule.id, newRule);
            
            // Update local state
            setPricingRules(pricingRules.map(r => 
                r.id === editingRule.id ? { ...r, ...newRule } : r
            ));

            // Reset form
            setEditingRule(null);
            setNewRule({
                name: '',
                scope_type: 'room_type',
                room_id: null,
                room_type: 'dormitory',
                pricing_mode: null,
                priority: 100,
                start_date: new Date().toISOString().split('T')[0],
                end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                base_price: 100,
                weekend_price: 120,
            });
            setShowAddForm(false);
            alert('Zaktualizowano regułę cenową');
        } catch (error) {
            console.error('Failed to update pricing rule:', error);
            const message = (error as any)?.response?.data?.message || (error as any)?.message || 'Błąd przy aktualizacji reguły';
            alert(`Błąd przy aktualizacji reguły: ${message}`);
        } finally {
            setSaving(false);
        }
    };

    const handleCloseForm = () => {
        setShowAddForm(false);
        setEditingRule(null);
        setNewRule({
            name: '',
            scope_type: 'room_type',
            room_id: null,
            room_type: 'dormitory',
            pricing_mode: null,
            priority: 100,
            start_date: new Date().toISOString().split('T')[0],
            end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
            base_price: 100,
            weekend_price: 120,
        });
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-96">
                <Loader2 className="animate-spin text-brand-600" size={48} />
            </div>
        );
    }

    return (
        <div className="max-w-4xl">
            <div className="flex items-center justify-end mb-6">
                <button
                    onClick={() => setShowAddForm(!showAddForm)}
                    className="bg-brand-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-brand-700 transition flex items-center gap-2"
                >
                    <Plus size={18} />
                    Dodaj regułę cenową
                </button>
            </div>

            {/* Add Rule Form */}
            {showAddForm && (
                <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm mb-6 animate-in fade-in slide-in-from-top-2">
                    <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <DollarSign className="text-brand-600" size={20} />
                        Nowa reguła cenowa
                    </h3>

                    <div className="grid grid-cols-2 gap-4 mb-4">
                        {/* Scope Selection */}
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Zakres reguły
                            </label>
                            <select
                                value={newRule.scope_type}
                                onChange={(e) => setNewRule({
                                    ...newRule,
                                    scope_type: e.target.value as 'room_id' | 'room_type',
                                    room_type: e.target.value === 'room_type' ? (newRule.room_type || 'dormitory') : newRule.room_type,
                                    room_id: e.target.value === 'room_id' ? (newRule.room_id || 0) : null,
                                })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            >
                                <option value="room_type">Typ pokoju (zalecane)</option>
                                <option value="room_id">Konkretny pokój (override)</option>
                            </select>
                        </div>

                        {/* Room Type or Room Selection */}
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Nazwa reguły (opcjonalnie)
                            </label>
                            <input
                                type="text"
                                value={newRule.name || ''}
                                onChange={(e) => setNewRule({ ...newRule, name: e.target.value })}
                                placeholder="np. Wysoki sezon - Dormitory"
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            />
                        </div>

                        {/* Room Type or Room Selection */}
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {newRule.scope_type === 'room_type' ? 'Typ pokoju' : 'Pokój'}
                            </label>
                            {newRule.scope_type === 'room_type' ? (
                                <select
                                    value={newRule.room_type || 'dormitory'}
                                    onChange={(e) => setNewRule({ ...newRule, room_type: e.target.value })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                >
                                    <option value="dormitory">Dormitory</option>
                                    <option value="standard">Standard</option>
                                    <option value="deluxe">Deluxe</option>
                                    <option value="studio">Studio</option>
                                    <option value="suite">Suite</option>
                                </select>
                            ) : (
                                <select
                                    value={newRule.room_id || 0}
                                    onChange={(e) => setNewRule({ ...newRule, room_id: parseInt(e.target.value) || 0 })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                >
                                    <option value={0}>Wybierz pokój</option>
                                    {rooms.map((room) => (
                                        <option key={room.id} value={room.id}>
                                            {room.name} ({room.room_type} - {room.pricing_mode === 'per_room' ? 'Pokój' : 'Łóżko'})
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>

                        {/* Pricing Mode & Priority */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Tryb cenowy (opcjonalnie)
                            </label>
                            <select
                                value={newRule.pricing_mode || ''}
                                onChange={(e) => setNewRule({ ...newRule, pricing_mode: (e.target.value || null) as any })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            >
                                <option value="">Każdy</option>
                                <option value="per_room">Per room</option>
                                <option value="per_bed">Per bed</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Priorytet
                            </label>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                value={newRule.priority}
                                onChange={(e) => setNewRule({ ...newRule, priority: parseInt(e.target.value) || 100 })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            />
                        </div>

                        {/* Date Range */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Data od
                            </label>
                            <input
                                type="date"
                                value={newRule.start_date}
                                onChange={(e) => setNewRule({ ...newRule, start_date: e.target.value })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Data do
                            </label>
                            <input
                                type="date"
                                value={newRule.end_date}
                                onChange={(e) => setNewRule({ ...newRule, end_date: e.target.value })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            />
                        </div>

                        {/* Prices */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Cena podstawowa (pon-czw)
                            </label>
                            <div className="relative">
                                <DollarSign className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={newRule.base_price}
                                    onChange={(e) => setNewRule({ ...newRule, base_price: parseFloat(e.target.value) || 0 })}
                                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Cena weekend (pt-ndz)
                            </label>
                            <div className="relative">
                                <DollarSign className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={newRule.weekend_price}
                                    onChange={(e) => setNewRule({ ...newRule, weekend_price: parseFloat(e.target.value) || 0 })}
                                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-3 pt-4 border-t border-gray-200">
                        <button
                            onClick={editingRule ? handleUpdateRule : handleAddRule}
                            disabled={saving}
                            className="bg-brand-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-brand-700 transition disabled:opacity-50 flex items-center gap-2"
                        >
                            {saving ? <Loader2 className="animate-spin" size={16} /> : <Save size={16} />}
                            {saving ? 'Zapisywanie...' : (editingRule ? 'Aktualizuj regułę' : 'Zapisz regułę')}
                        </button>
                        <button
                            onClick={handleCloseForm}
                            className="px-6 py-2 border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-50 transition"
                        >
                            Anuluj
                        </button>
                    </div>
                </div>
            )}

            {/* Pricing Rules List */}
            <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div className="p-6 border-b border-gray-200">
                    <h3 className="text-lg font-bold text-gray-900">Aktualne reguły cenowe</h3>
                </div>

                {pricingRules.length === 0 ? (
                    <div className="p-12 text-center">
                        <DollarSign size={48} className="mx-auto text-gray-300 mb-4" />
                        <p className="text-gray-500 mb-2">Brak zdefiniowanych reguł cenowych</p>
                        <p className="text-sm text-gray-400">
                            Kliknij "Dodaj regułę cenową" aby utworzyć pierwszą regułę
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-gray-200">
                        {pricingRules.map((rule) => {
                            const room = rooms.find(r => r.id === rule.room_id);
                            const ruleTitle = rule.name || (rule.scope_type === 'room_type'
                                ? `Typ: ${rule.room_type}`
                                : (room?.name || `Pokój #${rule.room_id}`));
                            return (
                                <div key={rule.id} className="p-6 hover:bg-gray-50 transition-colors">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-2">
                                                <h4 className="text-lg font-bold text-gray-900">
                                                    {ruleTitle}
                                                </h4>
                                                <span className="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-medium rounded">
                                                    {rule.scope_type === 'room_type' ? 'room_type' : 'room_id'}
                                                </span>
                                                <span className={`px-2 py-0.5 text-xs font-medium rounded ${(rule.pricing_mode || room?.pricing_mode) === 'per_room' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'}`}>
                                                    {(rule.pricing_mode || room?.pricing_mode) === 'per_room' ? 'Cena za pokój' : 'Cena za łóżko (baza)'}
                                                </span>
                                                <span className="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-medium rounded">
                                                    Priorytet: {rule.priority}
                                                </span>
                                            </div>

                                            <div className="grid grid-cols-3 gap-4 mt-3">
                                                <div className="flex items-center gap-2 text-sm">
                                                    <Calendar size={16} className="text-gray-400" />
                                                    <span className="text-gray-600">
                                                        {new Date(rule.start_date).toLocaleDateString('pl-PL')} - {new Date(rule.end_date).toLocaleDateString('pl-PL')}
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-2 text-sm">
                                                    <DollarSign size={16} className="text-gray-400" />
                                                    <span className="text-gray-600">
                                                        Tydzień: <span className="font-bold text-gray-900">{rule.base_price.toFixed(2)} PLN</span>
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-2 text-sm">
                                                    <DollarSign size={16} className="text-gray-400" />
                                                    <span className="text-gray-600">
                                                        Weekend: <span className="font-bold text-gray-900">{rule.weekend_price.toFixed(2)} PLN</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex gap-2">
                                            <button
                                                onClick={() => handleEditRule(rule)}
                                                className="p-2 text-brand-600 hover:bg-brand-50 rounded-lg transition"
                                                title="Edytuj regułę"
                                            >
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                onClick={() => handleDeleteRule(rule.id!)}
                                                className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                                                title="Usuń regułę"
                                            >
                                                <Trash2 size={18} />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Info Box */}
            <div className="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4 flex gap-3">
                <AlertCircle className="text-blue-600 shrink-0" size={20} />
                <div className="text-sm text-blue-800">
                    <p className="font-bold mb-1">Jak to działa?</p>
                    <p>
                        Reguły mogą być globalne dla typu pokoju albo dla konkretnego pokoju.
                        System wybiera regułę o najwyższym priorytecie, a przy remisie preferuje regułę dla konkretnego pokoju.
                    </p>
                </div>
            </div>
        </div>
    );
};

export default PricingView;
