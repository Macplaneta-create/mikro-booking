import React, { useState } from 'react';
import { Calendar } from 'lucide-react';

const Settings: React.FC = () => {
    const [licenseKey, setLicenseKey] = useState('');
    const [status, setStatus] = useState<'inactive' | 'active'>('inactive');

    const handleActivate = (e: React.FormEvent) => {
        e.preventDefault();
        // Placeholder for activation logic
        if (licenseKey.startsWith('mikro-')) {
            setStatus('active');
            alert('Licencja aktywowana pomyślnie!');
        } else {
            alert('Nieprawidłowy klucz licencyjny.');
        }
    };

    return (
        <div className="max-w-2xl">
            <h2 className="text-2xl font-bold text-gray-900 mb-6">Ustawienia & Licencja</h2>

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
