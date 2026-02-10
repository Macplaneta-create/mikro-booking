import React, { useState, useEffect } from 'react';
import { Calendar, Clock, Save, AlertCircle } from 'lucide-react';
import { SettingsAPI } from '../services/api';

interface PluginSettings {
    pending_timeout_hours: number;
    auto_expire_pending: boolean;
    require_payment_confirmation: boolean;
}

const Settings: React.FC = () => {
    const [licenseKey, setLicenseKey] = useState('');
    const [status, setStatus] = useState<'inactive' | 'active'>('inactive');
    const [settings, setSettings] = useState<PluginSettings>({
        pending_timeout_hours: 48,
        auto_expire_pending: true,
        require_payment_confirmation: true,
    });
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [loading, setLoading] = useState(true);

    // Load settings on mount
    useEffect(() => {
        loadSettings();
    }, []);

    const loadSettings = async () => {
        try {
            setLoading(true);
            const data = await SettingsAPI.getAll();
            setSettings(data);
        } catch (error) {
            console.error('Failed to load settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleActivate = (e: React.FormEvent) => {
        e.preventDefault();
        if (licenseKey.startsWith('mikro-')) {
            setStatus('active');
            alert('Licencja aktywowana pomyślnie!');
        } else {
            alert('Nieprawidłowy klucz licencyjny.');
        }
    };

    const handleSaveSettings = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            setSaving(true);
            await SettingsAPI.update(settings);
            setSaved(true);
            setTimeout(() => setSaved(false), 3000);
        } catch (error) {
            console.error('Failed to save settings:', error);
            alert('Błąd przy zapisywaniu ustawień');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="max-w-2xl">
            <h2 className="text-2xl font-bold text-gray-900 mb-6">Ustawienia & Licencja</h2>

            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <Clock className="text-brand-600" size={20} />
                    Przepływ Rezerwacji
                </h3>
                <p className="text-gray-600 mb-6 text-sm">
                    Skonfiguruj zachowanie rezerwacji w stanie oczekiwania na potwierdzenie.
                </p>

                {loading ? (
                    <p className="text-gray-500">Ładowanie ustawień...</p>
                ) : (
                    <form onSubmit={handleSaveSettings} className="space-y-4">
                        {/* Pending Timeout */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Czas oczekiwania na potwierdzenie (godziny)
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="168"
                                value={settings.pending_timeout_hours}
                                onChange={(e) =>
                                    setSettings({
                                        ...settings,
                                        pending_timeout_hours: Math.max(1, parseInt(e.target.value) || 1),
                                    })
                                }
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            />
                            <p className="text-xs text-gray-500 mt-1">
                                Po tym czasie rezerwacja przejdzie automatycznie w stan anulowana (jeśli opcja poniżej jest aktywna).
                            </p>
                        </div>

                        {/* Auto-expire checkbox */}
                        <div className="flex items-center gap-3 py-2">
                            <input
                                type="checkbox"
                                id="auto_expire"
                                checked={settings.auto_expire_pending}
                                onChange={(e) =>
                                    setSettings({
                                        ...settings,
                                        auto_expire_pending: e.target.checked,
                                    })
                                }
                                className="w-4 h-4 rounded text-brand-600 cursor-pointer"
                            />
                            <label htmlFor="auto_expire" className="text-sm font-medium text-gray-700 cursor-pointer">
                                Automatycznie anuluj rezerwacje po upłynięciu czasu
                            </label>
                        </div>

                        {/* Payment confirmation checkbox */}
                        <div className="flex items-center gap-3 py-2">
                            <input
                                type="checkbox"
                                id="require_payment"
                                checked={settings.require_payment_confirmation}
                                onChange={(e) =>
                                    setSettings({
                                        ...settings,
                                        require_payment_confirmation: e.target.checked,
                                    })
                                }
                                className="w-4 h-4 rounded text-brand-600 cursor-pointer"
                            />
                            <label htmlFor="require_payment" className="text-sm font-medium text-gray-700 cursor-pointer">
                                Wymagaj potwierdzenia płatności przed finalizacją rezerwacji
                            </label>
                        </div>

                        {/* Save button */}
                        <div className="flex items-center gap-2 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="submit"
                                disabled={saving}
                                className="bg-brand-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-brand-700 transition disabled:opacity-50"
                            >
                                <Save className="inline-block mr-2" size={16} />
                                {saving ? 'Zapisywanie...' : 'Zapisz ustawienia'}
                            </button>
                            {saved && (
                                <span className="text-green-600 text-sm flex items-center gap-1">
                                    <AlertCircle size={16} />
                                    Zapisano pomyślnie
                                </span>
                            )}
                        </div>
                    </form>
                )}
            </div>

            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <Calendar className="text-brand-600" size={20} />
                    Wyświetlanie na stronie
                </h3>
                <p className="text-gray-600 mb-4 text-sm">
                    Aby wyświetlić widget rezerwacji na swojej stronie, użyj poniższego shortcode'u w edytorze WordPress.
                </p>
                <div className="bg-gray-50 p-4 rounded-xl border border-gray-200 flex items-center justify-between group">
                    <code className="text-brand-700 font-bold font-mono text-lg">[mikroplaneta_booking]</code>
                    <button
                        onClick={() => {
                            navigator.clipboard.writeText('[mikroplaneta_booking]');
                            alert('Skopiowano do schowka!');
                        }}
                        className="text-xs bg-white border border-gray-200 px-3 py-1.5 rounded-lg text-gray-500 hover:text-brand-600 hover:border-brand-200 transition-all shadow-sm"
                    >
                        Kopiuj
                    </button>
                </div>
                <div className="mt-4 flex gap-4 text-xs text-gray-400">
                    <div className="flex items-center gap-1">
                        <div className="w-1.5 h-1.5 rounded-full bg-brand-400"></div>
                        Obsługuje Gutenberg
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="w-1.5 h-1.5 rounded-full bg-brand-400"></div>
                        Obsługuje Elementor
                    </div>
                </div>
            </div>

            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4">Aktywacja Produktu</h3>
                <div className="flex items-center gap-4 mb-6">
                    <div className={`w-3 h-3 rounded-full ${status === 'active' ? 'bg-green-500' : 'bg-red-500'}`}></div>
                    <span className="font-medium text-gray-700">Status: {status === 'active' ? 'Aktywna' : 'Wersja Testowa / Nieaktywna'}</span>
                </div>

                <form onSubmit={handleActivate} className="flex gap-4">
                    <input
                        type="text"
                        value={licenseKey}
                        onChange={(e) => setLicenseKey(e.target.value)}
                        placeholder="Wklej klucz licencyjny (np. mikro-XXXX)"
                        className="flex-1 border-gray-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    />
                    <button type="submit" className="bg-brand-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-brand-700 transition">
                        Aktywuj
                    </button>
                </form>
                <p className="text-xs text-gray-400 mt-2">Klucz otrzymasz po zakupie na mikroplaneta.pl</p>
            </div>

            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm opacity-50 pointer-events-none">
                <h3 className="text-lg font-bold text-gray-900 mb-4">Integracje (Wkrótce)</h3>
                <p className="text-gray-500">Google Calendar, Booking.com, Airbnb</p>
            </div>
        </div>
    );
};

export default Settings;
