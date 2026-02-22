import React, { useState, useEffect } from 'react';
import { Plus, Trash2, Edit2, Coins, Loader2, Check, Info, Power, Zap } from 'lucide-react';
import { ExtrasAPI, ExtraService } from '../services/api';

const ExtrasManager: React.FC = () => {
    const [services, setServices] = useState<ExtraService[]>([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    // Form State
    const [formData, setFormData] = useState<Partial<ExtraService>>({
        name: '',
        description: '',
        price: 0,
        pricing_type: 'per_stay',
        is_active: true,
        auto_suggest_by_beds: false
    });

    const fetchServices = async () => {
        try {
            setLoading(true);
            const data = await ExtrasAPI.getServices();
            setServices(data);
        } catch (e) {
            console.error('Failed to fetch services', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchServices();
    }, []);

    const resetForm = () => {
        setFormData({
            name: '',
            description: '',
            price: 0,
            pricing_type: 'per_stay',
            is_active: true,
            auto_suggest_by_beds: false
        });
        setEditingId(null);
        setShowForm(false);
    };

    const handleEdit = (service: ExtraService) => {
        setFormData({
            name: service.name,
            description: service.description,
            price: service.price,
            pricing_type: service.pricing_type,
            is_active: !!service.is_active,
            auto_suggest_by_beds: !!service.auto_suggest_by_beds
        });
        setEditingId(service.id!);
        setShowForm(true);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        try {
            if (editingId) {
                await ExtrasAPI.updateService(editingId, formData);
            } else {
                await ExtrasAPI.createService(formData as ExtraService);
            }
            resetForm();
            fetchServices();
        } catch (e) {
            alert('Błąd podczas zapisywania usługi.');
            console.error(e);
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Czy na pewno chcesz usunąć tę usługę? Nie wpłynie to na istniejące rezerwacje.')) return;

        try {
            await ExtrasAPI.deleteService(id);
            setServices(services.filter(s => s.id !== id));
        } catch (e) {
            alert('Nie udało się usunąć usługi.');
            console.error(e);
        }
    };

    const toggleStatus = async (service: ExtraService) => {
        try {
            const newStatus = !service.is_active;
            await ExtrasAPI.updateService(service.id!, { is_active: newStatus });
            setServices(services.map(s => s.id === service.id ? { ...s, is_active: newStatus } : s));
        } catch (e) {
            console.error(e);
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-end items-center">
                <button
                    onClick={() => { resetForm(); setShowForm(true); }}
                    className="flex items-center gap-2 bg-brand-600 text-white px-4 py-2 rounded-xl hover:bg-brand-700 transition shadow-lg shadow-brand-100"
                >
                    <Plus size={20} />
                    Dodaj Usługę
                </button>
            </div>

            {showForm && (
                <div className="bg-white p-6 rounded-3xl border border-brand-100 shadow-xl mb-6 animate-in slide-in-from-top-4 duration-300">
                    <h3 className="font-black text-xl mb-6 text-gray-900 flex items-center gap-2">
                        <Coins className="text-brand-600" size={24} />
                        {editingId ? 'Edytuj Usługę' : 'Nowa Usługa'}
                    </h3>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1.5 ml-1">Nazwa wyświetlana</label>
                                    <input
                                        type="text"
                                        className="w-full border-gray-200 bg-gray-50 rounded-xl p-3 focus:bg-white focus:ring-2 focus:ring-brand-500 transition-all outline-none"
                                        placeholder="np. Śniadanie do pokoju"
                                        value={formData.name}
                                        onChange={e => setFormData({ ...formData, name: e.target.value })}
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1.5 ml-1">Opis (dla gościa)</label>
                                    <textarea
                                        rows={3}
                                        className="w-full border-gray-200 bg-gray-50 rounded-xl p-3 focus:bg-white focus:ring-2 focus:ring-brand-500 transition-all outline-none resize-none"
                                        placeholder="Krótki opis co zawiera usługa..."
                                        value={formData.description}
                                        onChange={e => setFormData({ ...formData, description: e.target.value })}
                                    />
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1.5 ml-1">Cena (PLN)</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="w-full border-gray-200 bg-gray-50 rounded-xl p-3 focus:bg-white focus:ring-2 focus:ring-brand-500 transition-all outline-none"
                                            value={formData.price}
                                            onChange={e => setFormData({ ...formData, price: parseFloat(e.target.value) })}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1.5 ml-1">Sposób naliczania</label>
                                        <select
                                            className="w-full border-gray-200 bg-gray-50 rounded-xl p-3 focus:bg-white focus:ring-2 focus:ring-brand-500 transition-all outline-none cursor-pointer"
                                            value={formData.pricing_type}
                                            onChange={e => setFormData({ ...formData, pricing_type: e.target.value as any })}
                                        >
                                            <option value="per_stay">Za cały pobyt (raz)</option>
                                            <option value="per_unit">Za sztukę (z ilością)</option>
                                            <option value="per_person">Za osobę (nieobsługiwane jeszcze w pełni)</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="p-4 bg-gray-50 rounded-2xl space-y-3">
                                    <div className="flex items-center justify-between">
                                        <label className="flex items-center gap-2 cursor-pointer group">
                                            <div
                                                onClick={() => setFormData({ ...formData, auto_suggest_by_beds: !formData.auto_suggest_by_beds })}
                                                className={`w-5 h-5 rounded border transition-colors flex items-center justify-center ${formData.auto_suggest_by_beds ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white border-gray-300'}`}
                                            >
                                                {formData.auto_suggest_by_beds && <Check size={14} />}
                                            </div>
                                            <span className="text-sm font-bold text-gray-700 select-none">Automatyczna sugestia</span>
                                        </label>
                                        <div className="group relative">
                                            <Info size={14} className="text-gray-400" />
                                            <div className="hidden group-hover:block absolute right-0 bottom-full mb-2 w-48 p-2 bg-gray-800 text-white text-[10px] rounded shadow-xl z-10 pointer-events-none">
                                                Zaznacz, jeśli system ma sugerować tę usługę automatycznie (np. pościel przy rezerwacji łóżek).
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <label className="flex items-center gap-2 cursor-pointer border-t border-gray-100 pt-3 w-full">
                                            <div
                                                onClick={() => setFormData({ ...formData, is_active: !formData.is_active })}
                                                className={`w-5 h-5 rounded border transition-colors flex items-center justify-center ${formData.is_active ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-300'}`}
                                            >
                                                {formData.is_active && <Check size={14} />}
                                            </div>
                                            <span className="text-sm font-bold text-gray-700 select-none">Usługa aktywna</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-6 border-t border-gray-100">
                            <button type="button" onClick={resetForm} className="px-6 py-2.5 text-gray-500 font-bold hover:bg-gray-100 rounded-xl transition-all">Anuluj</button>
                            <button
                                type="submit"
                                disabled={submitting}
                                className="px-10 py-2.5 bg-brand-600 text-white rounded-xl font-bold hover:bg-brand-700 flex items-center gap-2 disabled:opacity-70 shadow-lg shadow-brand-100 transition-all hover:scale-105"
                            >
                                {submitting && <Loader2 className="animate-spin" size={18} />}
                                {submitting ? 'Zapisywanie...' : (editingId ? 'Zaktualizuj Usługę' : 'Zapisz Usługę')}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {loading ? (
                <div className="flex flex-col items-center justify-center py-24 bg-white rounded-3xl border border-gray-100 shadow-sm">
                    <Loader2 className="animate-spin text-brand-600 mb-4" size={48} />
                    <p className="text-gray-500 font-medium">Pobieranie katalogu usług...</p>
                </div>
            ) : services.length === 0 && !showForm ? (
                <div className="flex flex-col items-center justify-center p-20 bg-white rounded-3xl border-2 border-dashed border-gray-200">
                    <div className="w-24 h-24 bg-brand-50 rounded-3xl flex items-center justify-center mb-8 rotate-12 group hover:rotate-0 transition-transform duration-300">
                        <Coins className="text-brand-600" size={48} />
                    </div>
                    <h3 className="text-2xl font-black text-gray-900 mb-3 text-center">Twój katalog usług jest pusty</h3>
                    <p className="text-gray-500 mb-10 text-center max-w-sm leading-relaxed">
                        Dodaj dodatkowe usługi, takie jak śniadania, wypożyczenie roweru czy opłata za zwierzę, aby ułatwić zarządzanie i zwiększyć zyski.
                    </p>
                    <button
                        onClick={() => setShowForm(true)}
                        className="bg-brand-600 text-white px-10 py-4 rounded-2xl font-black hover:scale-105 transition-transform shadow-xl shadow-brand-200 flex items-center gap-3"
                    >
                        <Plus size={24} />
                        Stwórz Pierwszą Usługę
                    </button>
                </div>
            ) : (
                <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden pb-1 ">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-gray-50/80">
                                <th className="p-4 pl-6 text-[11px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-100">Usługa</th>
                                <th className="p-4 text-[11px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-100 text-center">Status</th>
                                <th className="p-4 text-[11px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-100">Cena / Model</th>
                                <th className="p-4 text-[11px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-100 text-center text-brand-600"><Zap size={14} className="inline mr-1" /> Auto</th>
                                <th className="p-4 pr-6 text-[11px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-100 text-right">Akcje</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {services.map(service => (
                                <tr key={service.id} className={`group hover:bg-gray-50/50 transition-colors ${!service.is_active ? 'opacity-60' : ''}`}>
                                    <td className="p-4 pl-6">
                                        <div className="flex items-center gap-3">
                                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${service.is_active ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-400'}`}>
                                                <Coins size={20} />
                                            </div>
                                            <div>
                                                <p className="font-bold text-gray-900 leading-tight">{service.name}</p>
                                                <p className="text-xs text-gray-400 truncate max-w-xs">{service.description || 'Brak opisu'}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="p-4 text-center">
                                        <button
                                            onClick={() => toggleStatus(service)}
                                            className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider transition-all border ${service.is_active
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-100 hover:bg-emerald-100'
                                                : 'bg-gray-100 text-gray-500 border-gray-200 hover:bg-gray-200'
                                                }`}
                                        >
                                            <Power size={10} />
                                            {service.is_active ? 'Aktywna' : 'Nieaktywna'}
                                        </button>
                                    </td>
                                    <td className="p-4">
                                        <div className="flex flex-col">
                                            <span className="font-black text-gray-900 text-lg">
                                                {service.price.toFixed(2)} <span className="text-xs font-bold text-gray-400">zł</span>
                                            </span>
                                            <span className="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">
                                                {service.pricing_type === 'per_stay' ? 'Za pobyt' :
                                                    service.pricing_type === 'per_unit' ? 'Za sztukę' : 'Za osobę'}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="p-4 text-center">
                                        <div className={`inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors ${service.auto_suggest_by_beds ? 'bg-brand-50 text-brand-600 border border-brand-100' : 'text-gray-300'}`}>
                                            <Zap size={16} fill={service.auto_suggest_by_beds ? "currentColor" : "none"} />
                                        </div>
                                    </td>
                                    <td className="p-4 pr-6 text-right">
                                        <div className="flex justify-end gap-2">
                                            <button
                                                onClick={() => handleEdit(service)}
                                                className="p-2 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-all"
                                                title="Edytuj"
                                            >
                                                <Edit2 size={18} />
                                            </button>
                                            <button
                                                onClick={() => service.id && handleDelete(service.id)}
                                                className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                                title="Usuń"
                                            >
                                                <Trash2 size={18} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="p-4 bg-gray-50/50 border-t border-gray-100 text-[10px] text-gray-400 font-medium flex items-center gap-2 px-6">
                        <Info size={12} />
                        Usługi z modelem "Za sztukę" pozwalają gościowi wybrać dowolną ilość (np. 3 x ręcznik). Model "Za pobyt" jest jednorazowy.
                    </div>
                </div>
            )}
        </div>
    );
};

export default ExtrasManager;
