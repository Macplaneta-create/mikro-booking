import React, { useState, useEffect } from 'react';
import { DollarSign, Plus, Trash2, Calendar, Save, Loader2, AlertCircle } from 'lucide-react';
import { RoomsAPI, PricingAPI } from '../services/api';

interface Room {
    id?: number;
    name: string;
    room_type: string;
    beds?: any[];
}

interface PricingRule {
    id?: number;
    room_id: number;
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

    const [newRule, setNewRule] = useState<PricingRule>({
        room_id: 0,
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
        if (newRule.room_id === 0) {
            alert('Wybierz pokój');
            return;
        }

        try {
            setSaving(true);
            const created = await PricingAPI.create(newRule);
            setPricingRules([...pricingRules, created]);

            // Reset form
            setNewRule({
                room_id: 0,
                start_date: new Date().toISOString().split('T')[0],
                end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                base_price: 100,
                weekend_price: 120,
            });
            setShowAddForm(false);
        } catch (error) {
            console.error('Failed to save pricing rule:', error);
            alert('Błąd przy zapisywaniu cennika');
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

    if (loading) {
        return (
            <div className="flex items-center justify-center h-96">
                <Loader2 className="animate-spin text-brand-600" size={48} />
            </div>
        );
    }

    return (
        <div className="max-w-4xl">
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h2 className="text-2xl font-bold text-gray-900">Cennik</h2>
                    <p className="text-gray-600 text-sm mt-1">
                        Zarządzaj cenami dla poszczególnych pokoi i okresów
                    </p>
                </div>
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
                        {/* Room Selection */}
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Pokój
                            </label>
                            <select
                                value={newRule.room_id}
                                onChange={(e) => setNewRule({ ...newRule, room_id: parseInt(e.target.value) })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            >
                                <option value={0}>Wybierz pokój</option>
                                {rooms.map((room) => (
                                    <option key={room.id} value={room.id}>
                                        {room.name} ({room.room_type})
                                    </option>
                                ))}
                            </select>
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
                            onClick={handleAddRule}
                            disabled={saving}
                            className="bg-brand-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-brand-700 transition disabled:opacity-50 flex items-center gap-2"
                        >
                            {saving ? <Loader2 className="animate-spin" size={16} /> : <Save size={16} />}
                            {saving ? 'Zapisywanie...' : 'Zapisz regułę'}
                        </button>
                        <button
                            onClick={() => setShowAddForm(false)}
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
                            return (
                                <div key={rule.id} className="p-6 hover:bg-gray-50 transition-colors">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-2">
                                                <h4 className="text-lg font-bold text-gray-900">
                                                    {room?.name || `Pokój #${rule.room_id}`}
                                                </h4>
                                                <span className="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-medium rounded">
                                                    {room?.room_type}
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

                                        <button
                                            onClick={() => handleDeleteRule(rule.id!)}
                                            className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                                            title="Usuń regułę"
                                        >
                                            <Trash2 size={18} />
                                        </button>
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
                        Każda reguła cenowa określa ceny dla konkretnego pokoju w danym okresie.
                        Jeśli cena dla danego dnia nie jest zdefiniowana, system użyje domyślnej ceny 100 PLN.
                    </p>
                </div>
            </div>
        </div>
    );
};

export default PricingView;
