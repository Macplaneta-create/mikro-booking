import React, { useState, useEffect } from 'react';
import { Calendar, Clock, Save, AlertCircle, Building2, Mail, Globe, Shield } from 'lucide-react';
import { SettingsAPI } from '../services/api';

interface PluginSettings {
    hotel_name: string;
    check_in_time: string;
    check_out_time: string;
    currency: string;
    timezone: string;
    email_notifications: boolean;
    pending_timeout_hours: number;
    auto_expire_pending: boolean;
    require_payment_confirmation: boolean;
    multiplier_single: number;
    multiplier_double: number;
    multiplier_bunk: number;
    multiplier_children: number;
    captcha_provider: 'none' | 'recaptcha_v3' | 'hcaptcha';
    recaptcha_site_key: string;
    recaptcha_secret_key: string;
    recaptcha_min_score: number;
    hcaptcha_site_key: string;
    hcaptcha_secret_key: string;
    rate_limit_enabled: boolean;
    rate_limit_window_seconds: number;
    rate_limit_max_requests: number;
}

const Settings: React.FC = () => {
    const [licenseKey, setLicenseKey] = useState('');
    const [status, setStatus] = useState<'inactive' | 'active'>('inactive');
    const [settings, setSettings] = useState<PluginSettings>({
        hotel_name: 'Mój Hotel',
        check_in_time: '14:00',
        check_out_time: '11:00',
        currency: 'PLN',
        timezone: 'Europe/Warsaw',
        email_notifications: true,
        pending_timeout_hours: 48,
        auto_expire_pending: true,
        require_payment_confirmation: true,
        multiplier_single: 1.0,
        multiplier_double: 2.0,
        multiplier_bunk: 2.0,
        multiplier_children: 0.5,
        captcha_provider: 'recaptcha_v3',
        recaptcha_site_key: '',
        recaptcha_secret_key: '',
        recaptcha_min_score: 0.5,
        hcaptcha_site_key: '',
        hcaptcha_secret_key: '',
        rate_limit_enabled: true,
        rate_limit_window_seconds: 60,
        rate_limit_max_requests: 120,
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
            setSettings({
                ...data,
                multiplier_single: data.multiplier_single ?? 1.0,
                multiplier_double: data.multiplier_double ?? 2.0,
                multiplier_bunk: data.multiplier_bunk ?? 2.0,
                multiplier_children: data.multiplier_children ?? 0.5,
                captcha_provider: data.captcha_provider ?? 'recaptcha_v3',
                recaptcha_site_key: data.recaptcha_site_key ?? '',
                recaptcha_secret_key: data.recaptcha_secret_key ?? '',
                recaptcha_min_score: data.recaptcha_min_score ?? 0.5,
                hcaptcha_site_key: data.hcaptcha_site_key ?? '',
                hcaptcha_secret_key: data.hcaptcha_secret_key ?? '',
                rate_limit_enabled: data.rate_limit_enabled ?? true,
                rate_limit_window_seconds: data.rate_limit_window_seconds ?? 60,
                rate_limit_max_requests: data.rate_limit_max_requests ?? 120,
            });
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

            {/* Hotel Information Section */}
            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <Building2 className="text-brand-600" size={20} />
                    Informacje o Hotelu
                </h3>
                <p className="text-gray-600 mb-6 text-sm">
                    Podstawowe dane dotyczące Twojego obiektu.
                </p>

                {loading ? (
                    <p className="text-gray-500">Ładowanie ustawień...</p>
                ) : (
                    <form onSubmit={handleSaveSettings} className="space-y-4">
                        {/* Hotel Name */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Nazwa hotelu / obiektu
                            </label>
                            <input
                                type="text"
                                value={settings.hotel_name}
                                onChange={(e) => setSettings({ ...settings, hotel_name: e.target.value })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                placeholder="np. Hotel Słoneczny"
                            />
                        </div>

                        {/* Check-in / Check-out Times */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Godzina zameldowania
                                </label>
                                <input
                                    type="time"
                                    value={settings.check_in_time}
                                    onChange={(e) => setSettings({ ...settings, check_in_time: e.target.value })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Godzina wymeldowania
                                </label>
                                <input
                                    type="time"
                                    value={settings.check_out_time}
                                    onChange={(e) => setSettings({ ...settings, check_out_time: e.target.value })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                        </div>

                        {/* Currency & Timezone */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-1">
                                    <Globe size={14} />
                                    Waluta
                                </label>
                                <select
                                    value={settings.currency}
                                    onChange={(e) => setSettings({ ...settings, currency: e.target.value })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-border-500"
                                >
                                    <option value="PLN">PLN (zł)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="USD">USD ($)</option>
                                    <option value="GBP">GBP (£)</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Strefa czasowa
                                </label>
                                <select
                                    value={settings.timezone}
                                    onChange={(e) => setSettings({ ...settings, timezone: e.target.value })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                >
                                    <option value="Europe/Warsaw">Europa/Warszawa (GMT+1)</option>
                                    <option value="Europe/London">Europa/Londyn (GMT+0)</option>
                                    <option value="Europe/Berlin">Europa/Berlin (GMT+1)</option>
                                    <option value="America/New_York">Ameryka/Nowy Jork (GMT-5)</option>
                                </select>
                            </div>
                        </div>

                        {/* Email Notifications */}
                        <div className="flex items-center gap-3 py-2 border-t border-gray-200 mt-4 pt-4">
                            <input
                                type="checkbox"
                                id="email_notifications"
                                checked={settings.email_notifications}
                                onChange={(e) => setSettings({ ...settings, email_notifications: e.target.checked })}
                                className="w-4 h-4 rounded text-brand-600 cursor-pointer"
                            />
                            <label htmlFor="email_notifications" className="text-sm font-medium text-gray-700 cursor-pointer flex items-center gap-2">
                                <Mail size={16} />
                                Włącz powiadomienia email dla gości
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
                        <div className="flex items-center justify-between mt-6 pt-4 border-t border-gray-200">
                            <div className="flex items-center gap-2">
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

                            <button
                                type="button"
                                onClick={async () => {
                                    if (window.confirm('Czy na pewno chcesz teraz uruchomić sprawdzanie wygasania rezerwacji?')) {
                                        try {
                                            const res = await SettingsAPI.triggerCron();
                                            alert(res.data.message);
                                        } catch (error) {
                                            alert('Błąd podczas uruchamiania testu');
                                            console.error(error);
                                        }
                                    }
                                }}
                                className="text-xs bg-gray-50 border border-gray-200 px-3 py-2 rounded-lg text-gray-500 hover:text-brand-600 hover:border-brand-200 transition-all shadow-sm flex items-center gap-2"
                            >
                                <Clock size={14} />
                                Testuj wygasanie (Cron)
                            </button>
                        </div>
                    </form>
                )}
            </div>

            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <Shield className="text-brand-600" size={20} />
                    Bezpieczeństwo API
                </h3>
                <p className="text-gray-600 mb-6 text-sm">
                    Skonfiguruj CAPTCHA dla formularza publicznego i globalny limit żądań REST API.
                </p>

                {loading ? (
                    <p className="text-gray-500">Ładowanie...</p>
                ) : (
                    <form onSubmit={handleSaveSettings} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Provider CAPTCHA
                            </label>
                            <select
                                value={settings.captcha_provider}
                                onChange={(e) =>
                                    setSettings({
                                        ...settings,
                                        captcha_provider: e.target.value as PluginSettings['captcha_provider'],
                                    })
                                }
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            >
                                <option value="recaptcha_v3">Google reCAPTCHA v3</option>
                                <option value="hcaptcha">hCaptcha</option>
                                <option value="none">Brak CAPTCHA (tylko środowisko dev)</option>
                            </select>
                        </div>

                        {settings.captcha_provider === 'recaptcha_v3' && (
                            <div className="grid grid-cols-1 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">reCAPTCHA Site Key</label>
                                    <input
                                        type="text"
                                        value={settings.recaptcha_site_key}
                                        onChange={(e) => setSettings({ ...settings, recaptcha_site_key: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">reCAPTCHA Secret Key</label>
                                    <input
                                        type="password"
                                        value={settings.recaptcha_secret_key}
                                        onChange={(e) => setSettings({ ...settings, recaptcha_secret_key: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Minimalny score (0-1)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="1"
                                        step="0.1"
                                        value={settings.recaptcha_min_score}
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                recaptcha_min_score: Math.max(0, Math.min(1, parseFloat(e.target.value) || 0)),
                                            })
                                        }
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                    />
                                </div>
                            </div>
                        )}

                        {settings.captcha_provider === 'hcaptcha' && (
                            <div className="grid grid-cols-1 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">hCaptcha Site Key</label>
                                    <input
                                        type="text"
                                        value={settings.hcaptcha_site_key}
                                        onChange={(e) => setSettings({ ...settings, hcaptcha_site_key: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">hCaptcha Secret Key</label>
                                    <input
                                        type="password"
                                        value={settings.hcaptcha_secret_key}
                                        onChange={(e) => setSettings({ ...settings, hcaptcha_secret_key: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                    />
                                </div>
                            </div>
                        )}

                        <div className="border-t border-gray-200 pt-4 space-y-4">
                            <div className="flex items-center gap-3 py-1">
                                <input
                                    type="checkbox"
                                    id="rate_limit_enabled"
                                    checked={settings.rate_limit_enabled}
                                    onChange={(e) => setSettings({ ...settings, rate_limit_enabled: e.target.checked })}
                                    className="w-4 h-4 rounded text-brand-600 cursor-pointer"
                                />
                                <label htmlFor="rate_limit_enabled" className="text-sm font-medium text-gray-700 cursor-pointer">
                                    Włącz globalny rate limiting dla `/mikroplaneta/v1/*`
                                </label>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Okno (sekundy)</label>
                                    <input
                                        type="number"
                                        min="10"
                                        max="3600"
                                        value={settings.rate_limit_window_seconds}
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                rate_limit_window_seconds: Math.max(10, parseInt(e.target.value) || 10),
                                            })
                                        }
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Maks. żądań w oknie</label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="10000"
                                        value={settings.rate_limit_max_requests}
                                        onChange={(e) =>
                                            setSettings({
                                                ...settings,
                                                rate_limit_max_requests: Math.max(1, parseInt(e.target.value) || 1),
                                            })
                                        }
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-2 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="submit"
                                disabled={saving}
                                className="bg-brand-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-brand-700 transition disabled:opacity-50"
                            >
                                <Save className="inline-block mr-2" size={16} />
                                {saving ? 'Zapisywanie...' : 'Zapisz ustawienia bezpieczeństwa'}
                            </button>
                        </div>
                    </form>
                )}
            </div>

            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <Globe className="text-brand-600" size={20} />
                    Mnożniki cen łóżek
                </h3>
                <p className="text-gray-600 mb-6 text-sm">
                    Określ wagę cenową dla każdego typu łóżka. Cena bazowa pokoju zostanie pomnożona przez ten współczynnik.
                </p>

                {loading ? (
                    <p className="text-gray-500">Ładowanie...</p>
                ) : (
                    <form onSubmit={handleSaveSettings} className="space-y-4">
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Łóżko Pojedyncze
                                </label>
                                <input
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    value={settings.multiplier_single}
                                    onChange={(e) => setSettings({ ...settings, multiplier_single: parseFloat(e.target.value) || 1.0 })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Łóżko Podwójne
                                </label>
                                <input
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    value={settings.multiplier_double}
                                    onChange={(e) => setSettings({ ...settings, multiplier_double: parseFloat(e.target.value) || 2.0 })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Łóżko Piętrowe
                                </label>
                                <input
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    value={settings.multiplier_bunk}
                                    onChange={(e) => setSettings({ ...settings, multiplier_bunk: parseFloat(e.target.value) || 2.0 })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Dziecko (Mnożnik)
                                </label>
                                <input
                                    type="number"
                                    step="0.05"
                                    min="0"
                                    max="1.0"
                                    value={settings.multiplier_children}
                                    onChange={(e) => setSettings({ ...settings, multiplier_children: parseFloat(e.target.value) || 0.5 })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-2 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="submit"
                                disabled={saving}
                                className="bg-brand-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-brand-700 transition disabled:opacity-50"
                            >
                                <Save className="inline-block mr-2" size={16} />
                                {saving ? 'Zapisywanie...' : 'Zapisz mnożniki'}
                            </button>
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
