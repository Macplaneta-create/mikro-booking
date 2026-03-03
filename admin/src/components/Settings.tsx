import React, { useState, useEffect } from 'react';
import { Calendar, Clock, Save, AlertCircle, Building2, Mail, Globe, Shield } from 'lucide-react';
import { SettingsAPI, RoomsAPI, type Room } from '../services/api';

interface Page {
    id: number;
    title: {
        rendered: string;
    };
}

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
    // Payment settings
    deposit_enabled: boolean;
    deposit_percent: number;
    payment_account: string;
    payment_bank_name: string;
    payment_additional_info: string;
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
    // GDPR/RODO settings
    privacy_policy_page_id: number;
    terms_page_id: number;
    // Backup settings
    backup_email: string;
    backup_email_enabled: boolean;
    backup_email_time: string;
}

interface EmailTemplate {
    key: string;
    label: string;
    subject: string;
    body: string;
    default_subject: string;
    default_body: string;
}

interface NotificationLogEntry {
    id: number;
    template_name: string;
    status: 'sent' | 'failed' | 'pending';
    sent_at: string | null;
    created_at: string;
    error_message?: string | null;
    reservation_id?: number | null;
    guest_id: number;
    first_name?: string;
    last_name?: string;
    email?: string;
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
        privacy_policy_page_id: 0,
        terms_page_id: 0,
        // Payment settings
        deposit_enabled: false,
        deposit_percent: 30,
        payment_account: '',
        payment_bank_name: '',
        payment_additional_info: '',
        // Backup settings
        backup_email: '',
        backup_email_enabled: false,
        backup_email_time: '08:00',
    });
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [loading, setLoading] = useState(true);
    const [rooms, setRooms] = useState<Room[]>([]);
    const [roomsLoading, setRoomsLoading] = useState(false);
    const [selectedRoomId, setSelectedRoomId] = useState<number>(0);
    const [shortcodeButtonLabel, setShortcodeButtonLabel] = useState('Rezerwuj');
    const [emailTemplates, setEmailTemplates] = useState<EmailTemplate[]>([]);
    const [templatePlaceholders, setTemplatePlaceholders] = useState<string[]>([]);
    const [selectedTemplateKey, setSelectedTemplateKey] = useState<string>('reservation_confirmation');
    const [templateSaving, setTemplateSaving] = useState(false);
    const [templateSaved, setTemplateSaved] = useState(false);
    const [notificationLog, setNotificationLog] = useState<NotificationLogEntry[]>([]);
    const [notificationLogLoading, setNotificationLogLoading] = useState(false);
    const [testEmail, setTestEmail] = useState('');
    const [sendingTestEmail, setSendingTestEmail] = useState(false);
    const [pages, setPages] = useState<Page[]>([]);

    // Load settings on mount
    useEffect(() => {
        loadSettings();
        loadRooms();
        loadEmailTemplates();
        loadNotificationLog();
        loadPages();
    }, []);

    const loadSettings = async () => {
        try {
            setLoading(true);
            const data = await SettingsAPI.getAll();
            setSettings({
                ...data,
                deposit_enabled: (data as any).deposit_enabled ?? false,
                deposit_percent: (data as any).deposit_percent ?? 30,
                payment_account: (data as any).payment_account ?? '',
                payment_bank_name: (data as any).payment_bank_name ?? '',
                payment_additional_info: (data as any).payment_additional_info ?? '',
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
                privacy_policy_page_id: data.privacy_policy_page_id ?? 0,
                terms_page_id: data.terms_page_id ?? 0,
                backup_email: data.backup_email ?? '',
                backup_email_enabled: data.backup_email_enabled ?? false,
                backup_email_time: data.backup_email_time ?? '08:00',
            });
            
            // Load GDPR settings separately
            const gdprResponse = await fetch('/wp-json/mikroplaneta/v1/settings/gdpr', {
                headers: {
                    'X-WP-Nonce': window.mikroplanetaBooking?.nonce || '',
                },
            });
            if (gdprResponse.ok) {
                const gdprData = await gdprResponse.json();
                setSettings(prev => ({
                    ...prev,
                    privacy_policy_page_id: gdprData.data?.privacy_policy_page_id || prev.privacy_policy_page_id,
                    terms_page_id: gdprData.data?.terms_page_id || prev.terms_page_id,
                }));
            }
        } catch (error) {
            console.error('Failed to load settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadRooms = async () => {
        try {
            setRoomsLoading(true);
            const data = await RoomsAPI.getAll({ status: 'active' });
            setRooms(data);
            if (data.length > 0) {
                setSelectedRoomId(prev => (prev > 0 ? prev : (data[0].id || 0)));
            }
        } catch (error) {
            console.error('Failed to load rooms for shortcode generator:', error);
        } finally {
            setRoomsLoading(false);
        }
    };

    const loadEmailTemplates = async () => {
        try {
            const data = await SettingsAPI.getEmailTemplates();
            setEmailTemplates(data.templates);
            setTemplatePlaceholders(data.placeholders || []);
            if (data.templates.length > 0 && !data.templates.find(t => t.key === selectedTemplateKey)) {
                setSelectedTemplateKey(data.templates[0].key);
            }
        } catch (error) {
            console.error('Failed to load email templates:', error);
        }
    };

    const loadPages = async () => {
        try {
            const response = await fetch('/wp-json/wp/v2/pages?per_page=100&status=publish', {
                headers: {
                    'X-WP-Nonce': window.mikroplanetaBooking?.nonce || '',
                },
            });
            if (response.ok) {
                const data = await response.json();
                setPages(data);
            }
        } catch (err) {
            console.error('Failed to load pages:', err);
        }
    };

    const loadNotificationLog = async () => {
        try {
            setNotificationLogLoading(true);
            const rows = await SettingsAPI.getNotificationsLog(50);
            setNotificationLog(rows);
        } catch (error) {
            console.error('Failed to load notifications log:', error);
        } finally {
            setNotificationLogLoading(false);
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
            
            // Save GDPR settings separately
            await fetch('/wp-json/mikroplaneta/v1/settings/gdpr', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.mikroplanetaBooking?.nonce || '',
                },
                body: JSON.stringify({
                    privacy_policy_page_id: settings.privacy_policy_page_id,
                    terms_page_id: settings.terms_page_id,
                }),
            });
            
            setSaved(true);
            setTimeout(() => setSaved(false), 3000);
        } catch (error) {
            console.error('Failed to save settings:', error);
            alert('Błąd przy zapisywaniu ustawień');
        } finally {
            setSaving(false);
        }
    };

    const generatedShortcode = (() => {
        if (!selectedRoomId) {
            return '[mikroplaneta_booking]';
        }

        const trimmedLabel = shortcodeButtonLabel.trim();

        if (trimmedLabel && trimmedLabel !== 'Rezerwuj') {
            return `[mikroplaneta_room_card room_id="${selectedRoomId}" button_label="${trimmedLabel}"]`;
        }
        return `[mikroplaneta_room_card room_id="${selectedRoomId}"]`;
    })();

    const selectedTemplate = emailTemplates.find(t => t.key === selectedTemplateKey) || emailTemplates[0] || null;

    const updateSelectedTemplate = (patch: Partial<EmailTemplate>) => {
        if (!selectedTemplate) return;
        setEmailTemplates(prev => prev.map(t => t.key === selectedTemplate.key ? { ...t, ...patch } : t));
    };

    const saveEmailTemplates = async () => {
        try {
            setTemplateSaving(true);
            await SettingsAPI.updateEmailTemplates(
                emailTemplates.map(t => ({ key: t.key, subject: t.subject, body: t.body }))
            );
            setTemplateSaved(true);
            setTimeout(() => setTemplateSaved(false), 3000);
            await loadEmailTemplates();
        } catch (error) {
            console.error('Failed to save email templates:', error);
            alert('Błąd przy zapisywaniu szablonów email');
        } finally {
            setTemplateSaving(false);
        }
    };

    const sendTestEmail = async () => {
        if (!selectedTemplate) return;
        const to = testEmail.trim();
        if (!to) {
            alert('Podaj adres email do testu.');
            return;
        }
        try {
            setSendingTestEmail(true);
            await SettingsAPI.sendTestEmail(selectedTemplate.key, to);
            alert('Wysłano testową wiadomość.');
        } catch (error) {
            console.error('Failed to send test email:', error);
            alert('Nie udało się wysłać maila testowego.');
        } finally {
            setSendingTestEmail(false);
        }
    };

    const previewHtml = (template: EmailTemplate | null): string => {
        if (!template) return '';
        const replacements: Record<string, string> = {
            '{{guest_name}}': 'Jan Kowalski',
            '{{guest_email}}': 'jan@example.com',
            '{{reservation_id}}': '1001',
            '{{check_in}}': '10.03.2026',
            '{{check_out}}': '12.03.2026',
            '{{nights}}': '2',
            '{{total_price}}': '799.00',
            '{{hotel_name}}': settings.hotel_name || 'Mój Hotel',
            '{{home_url}}': window.location.origin,
            '{{reason}}': 'Przykładowy powód',
        };
        return Object.entries(replacements).reduce(
            (acc, [token, value]) => acc.split(token).join(value),
            template.body || ''
        );
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

            {/* GDPR/RODO Section */}
            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <Shield className="text-brand-600" size={20} />
                    RODO / GDPR
                </h3>
                <p className="text-gray-600 mb-6 text-sm">
                    Skonfiguruj strony z polityką prywatności i regulaminem dla formularzy rezerwacji.
                </p>

                {loading ? (
                    <p className="text-gray-500">Ładowanie ustawień...</p>
                ) : (
                    <div className="space-y-4">
                        {/* Privacy Policy Page */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Strona z Polityką Prywatności
                            </label>
                            <select
                                value={settings.privacy_policy_page_id || ''}
                                onChange={(e) => setSettings({ ...settings, privacy_policy_page_id: parseInt(e.target.value) || 0 })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            >
                                <option value="">-- Wybierz stronę --</option>
                                {pages.map((page) => (
                                    <option key={page.id} value={page.id}>
                                        {page.title.rendered}
                                    </option>
                                ))}
                            </select>
                            <p className="text-xs text-gray-500 mt-1">
                                Ta strona będzie linkowana w formularzu rezerwacji jako "Polityka prywatności".
                            </p>
                        </div>

                        {/* Terms Page */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Strona z Regulaminem
                            </label>
                            <select
                                value={settings.terms_page_id || ''}
                                onChange={(e) => setSettings({ ...settings, terms_page_id: parseInt(e.target.value) || 0 })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            >
                                <option value="">-- Wybierz stronę --</option>
                                {pages.map((page) => (
                                    <option key={page.id} value={page.id}>
                                        {page.title.rendered}
                                    </option>
                                ))}
                            </select>
                            <p className="text-xs text-gray-500 mt-1">
                                Ta strona będzie linkowana w formularzu rezerwacji jako "Regulamin".
                            </p>
                        </div>

                        {/* Info box */}
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p className="text-sm text-blue-800">
                                <strong>Ważne:</strong> Zgodnie z RODO, użytkownicy muszą wyrazić świadomą zgodę na przetwarzanie danych. 
                                Upewnij się, że wybrane strony zawierają wszystkie wymagane informacje prawne.
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <Mail className="text-brand-600" size={20} />
                    Powiadomienia Email
                </h3>
                <p className="text-gray-600 mb-6 text-sm">
                    Edytuj wiadomości wysyłane do gości i sprawdzaj historię wysyłek.
                </p>

                <div className="grid grid-cols-1 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Typ wiadomości</label>
                        <select
                            value={selectedTemplate?.key || ''}
                            onChange={(e) => setSelectedTemplateKey(e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                        >
                            {emailTemplates.map((template) => (
                                <option key={template.key} value={template.key}>
                                    {template.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Temat wiadomości</label>
                        <input
                            type="text"
                            value={selectedTemplate?.subject || ''}
                            onChange={(e) => updateSelectedTemplate({ subject: e.target.value })}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            placeholder="Temat emaila"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Treść wiadomości (HTML)</label>
                        <textarea
                            value={selectedTemplate?.body || ''}
                            onChange={(e) => updateSelectedTemplate({ body: e.target.value })}
                            className="w-full min-h-[220px] px-4 py-3 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500 font-mono text-xs"
                            placeholder="Treść HTML wiadomości"
                        />
                    </div>

                    <div>
                        <p className="text-xs text-gray-500 mb-2">
                            Dostępne znaczniki: {templatePlaceholders.join(', ')}
                        </p>
                        <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 max-h-60 overflow-auto">
                            <div className="text-xs font-semibold text-gray-600 mb-2">Podgląd HTML (przykładowe dane)</div>
                            <div
                                className="prose prose-sm max-w-none"
                                dangerouslySetInnerHTML={{ __html: previewHtml(selectedTemplate) }}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div className="flex gap-2">
                            <input
                                type="email"
                                value={testEmail}
                                onChange={(e) => setTestEmail(e.target.value)}
                                placeholder="adres@email.pl"
                                className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            />
                            <button
                                type="button"
                                onClick={sendTestEmail}
                                disabled={sendingTestEmail || !selectedTemplate}
                                className="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-gray-200 transition disabled:opacity-50"
                            >
                                {sendingTestEmail ? 'Wysyłanie...' : 'Wyślij test'}
                            </button>
                        </div>
                        <div className="flex items-center gap-2 justify-start md:justify-end">
                            <button
                                type="button"
                                onClick={saveEmailTemplates}
                                disabled={templateSaving || emailTemplates.length === 0}
                                className="bg-brand-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-brand-700 transition disabled:opacity-50"
                            >
                                <Save className="inline-block mr-2" size={16} />
                                {templateSaving ? 'Zapisywanie...' : 'Zapisz szablony'}
                            </button>
                            {templateSaved && (
                                <span className="text-green-600 text-sm flex items-center gap-1">
                                    <AlertCircle size={16} />
                                    Zapisano
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="mt-8 pt-6 border-t border-gray-200">
                    <div className="flex items-center justify-between mb-3">
                        <h4 className="text-sm font-bold text-gray-900">Historia wysyłek</h4>
                        <button
                            type="button"
                            onClick={loadNotificationLog}
                            className="text-xs bg-white border border-gray-200 px-3 py-1.5 rounded-lg text-gray-500 hover:text-brand-600 hover:border-brand-200 transition-all shadow-sm"
                        >
                            Odśwież
                        </button>
                    </div>

                    {notificationLogLoading ? (
                        <p className="text-sm text-gray-500">Ładowanie historii...</p>
                    ) : notificationLog.length === 0 ? (
                        <p className="text-sm text-gray-500">Brak wpisów historii powiadomień.</p>
                    ) : (
                        <div className="overflow-x-auto border border-gray-200 rounded-lg">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th className="text-left p-2 font-semibold">Data</th>
                                        <th className="text-left p-2 font-semibold">Typ</th>
                                        <th className="text-left p-2 font-semibold">Odbiorca</th>
                                        <th className="text-left p-2 font-semibold">Status</th>
                                        <th className="text-left p-2 font-semibold">Rezerwacja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {notificationLog.map((row) => (
                                        <tr key={row.id} className="border-t border-gray-100">
                                            <td className="p-2 text-gray-700">{row.sent_at || row.created_at}</td>
                                            <td className="p-2 text-gray-700">{row.template_name}</td>
                                            <td className="p-2 text-gray-700">
                                                {row.first_name || ''} {row.last_name || ''} {row.email ? `(${row.email})` : ''}
                                            </td>
                                            <td className={`p-2 font-medium ${row.status === 'sent' ? 'text-green-600' : row.status === 'failed' ? 'text-red-600' : 'text-amber-600'}`}>
                                                {row.status}
                                                {row.status === 'failed' && row.error_message ? `: ${row.error_message}` : ''}
                                            </td>
                                            <td className="p-2 text-gray-700">{row.reservation_id ? `#${row.reservation_id}` : '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
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

                        {/* Payment Settings Section */}
                        <div className="mt-6 pt-6 border-t border-gray-200">
                            <h4 className="text-md font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="text-brand-600">
                                    <rect x="2" y="5" width="20" height="14" rx="2"/>
                                    <line x1="2" y1="10" x2="22" y2="10"/>
                                </svg>
                                Płatności i Zaliczka
                            </h4>

                            {/* Deposit enabled checkbox */}
                            <div className="flex items-center gap-3 py-2 mb-4">
                                <input
                                    type="checkbox"
                                    id="deposit_enabled"
                                    checked={settings.deposit_enabled}
                                    onChange={(e) =>
                                        setSettings({
                                            ...settings,
                                            deposit_enabled: e.target.checked,
                                        })
                                    }
                                    className="w-4 h-4 rounded text-brand-600 cursor-pointer"
                                />
                                <label htmlFor="deposit_enabled" className="text-sm font-medium text-gray-700 cursor-pointer">
                                    Wymagaj zaliczki na potwierdzenie rezerwacji
                                </label>
                            </div>

                            {/* Deposit percent */}
                            <div className="mb-4">
                                <label htmlFor="deposit_percent" className="block text-sm font-medium text-gray-700 mb-2">
                                    Wysokość zaliczki (%)
                                </label>
                                <input
                                    type="number"
                                    id="deposit_percent"
                                    min="0"
                                    max="100"
                                    value={settings.deposit_percent}
                                    onChange={(e) =>
                                        setSettings({
                                            ...settings,
                                            deposit_percent: Math.max(0, Math.min(100, parseInt(e.target.value) || 0)),
                                        })
                                    }
                                    className="w-full max-w-xs px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                                <p className="text-xs text-gray-500 mt-1">
                                    Procent całkowitej kwoty rezerwacji wymagany jako zaliczka (0-100%).
                                </p>
                            </div>

                            {/* Payment account */}
                            <div className="mb-4">
                                <label htmlFor="payment_account" className="block text-sm font-medium text-gray-700 mb-2">
                                    Numer konta bankowego
                                </label>
                                <input
                                    type="text"
                                    id="payment_account"
                                    value={settings.payment_account}
                                    onChange={(e) =>
                                        setSettings({
                                            ...settings,
                                            payment_account: e.target.value,
                                        })
                                    }
                                    placeholder="12 3456 7890 0000 1234 5678"
                                    className="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500 font-mono"
                                />
                                <p className="text-xs text-gray-500 mt-1">
                                    Numer konta do przelewu zaliczki. Gość zobaczy go po wysłaniu rezerwacji.
                                </p>
                            </div>

                            {/* Payment bank name */}
                            <div className="mb-4">
                                <label htmlFor="payment_bank_name" className="block text-sm font-medium text-gray-700 mb-2">
                                    Nazwa banku
                                </label>
                                <input
                                    type="text"
                                    id="payment_bank_name"
                                    value={settings.payment_bank_name}
                                    onChange={(e) =>
                                        setSettings({
                                            ...settings,
                                            payment_bank_name: e.target.value,
                                        })
                                    }
                                    placeholder="Bank Testowy"
                                    className="w-full max-w-xs px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>

                            {/* Payment additional info */}
                            <div className="mb-4">
                                <label htmlFor="payment_additional_info" className="block text-sm font-medium text-gray-700 mb-2">
                                    Dodatkowe informacje dla gościa
                                </label>
                                <textarea
                                    id="payment_additional_info"
                                    rows={3}
                                    value={settings.payment_additional_info}
                                    onChange={(e) =>
                                        setSettings({
                                            ...settings,
                                            payment_additional_info: e.target.value,
                                        })
                                    }
                                    placeholder="Np. Prosimy o podanie imienia i nazwiska w tytule przelewu."
                                    className="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                                <p className="text-xs text-gray-500 mt-1">
                                    Dodatkowe instrukcje płatności wyświetlane gościowi po rezerwacji.
                                </p>
                            </div>
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

            {/* Backup & Export Settings */}
            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg className="w-[20px] h-[20px] text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Backup & Export
                </h3>
                <p className="text-gray-600 mb-6 text-sm">
                    Skonfiguruj automatyczne kopie zapasowe i eksport danych rezerwacji.
                </p>

                {loading ? (
                    <p className="text-gray-500">Ładowanie...</p>
                ) : (
                    <form onSubmit={handleSaveSettings} className="space-y-4">
                        <div className="flex items-center gap-3 py-1">
                            <input
                                type="checkbox"
                                id="backup_email_enabled"
                                checked={settings.backup_email_enabled}
                                onChange={(e) => setSettings({ ...settings, backup_email_enabled: e.target.checked })}
                                className="w-4 h-4 rounded text-brand-600 cursor-pointer"
                            />
                            <label htmlFor="backup_email_enabled" className="text-sm font-medium text-gray-700 cursor-pointer">
                                Włącz codzienne podsumowanie rezerwacji na email
                            </label>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Email odbiorcy</label>
                                <input
                                    type="email"
                                    value={settings.backup_email}
                                    onChange={(e) => setSettings({ ...settings, backup_email: e.target.value })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                    placeholder="wlasciciel@hotel.com"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Godzina wysyłki</label>
                                <input
                                    type="time"
                                    value={settings.backup_email_time}
                                    onChange={(e) => setSettings({ ...settings, backup_email_time: e.target.value })}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                        </div>

                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p className="text-sm text-blue-800">
                                <strong>Wskazówka:</strong> Na dashboardzie znajdziesz przyciski do ręcznego eksportu:
                            </p>
                            <ul className="mt-2 space-y-1 text-sm text-blue-700">
                                <li>• <strong>CSV</strong> - eksport rezerwacji do Excela</li>
                                <li>• <strong>SQL</strong> - kopia zapasowa bazy danych</li>
                                <li>• <strong>Email</strong> - wyślij podsumowanie na żądanie</li>
                            </ul>
                        </div>

                        <div className="flex items-center gap-2 mt-6 pt-4 border-t border-gray-200">
                            <button
                                type="submit"
                                disabled={saving}
                                className="bg-brand-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-brand-700 transition disabled:opacity-50"
                            >
                                <Save className="inline-block mr-2" size={16} />
                                {saving ? 'Zapisywanie...' : 'Zapisz ustawienia backupu'}
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

                <div className="mt-6 pt-6 border-t border-gray-200">
                    <h4 className="text-sm font-bold text-gray-900 mb-3">Generator karty pokoju</h4>
                    <p className="text-xs text-gray-500 mb-3">Wybierz pokój/domek i wygeneruj shortcode z przyciskiem "Rezerwuj" otwierającym modal.</p>

                    {roomsLoading ? (
                        <p className="text-xs text-gray-500">Ładowanie pokoi...</p>
                    ) : (
                        <div className="space-y-3">
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Pokój / Domek</label>
                                <select
                                    value={selectedRoomId}
                                    onChange={(e) => setSelectedRoomId(parseInt(e.target.value, 10) || 0)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                >
                                    {rooms.length === 0 && <option value={0}>Brak aktywnych pokoi</option>}
                                    {rooms.map((room) => (
                                        <option key={room.id} value={room.id}>
                                            #{room.id} - {room.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Tekst przycisku (opcjonalnie)</label>
                                <input
                                    type="text"
                                    value={shortcodeButtonLabel}
                                    onChange={(e) => setShortcodeButtonLabel(e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                    placeholder="Rezerwuj"
                                />
                            </div>

                            <div className="bg-gray-50 p-3 rounded-xl border border-gray-200 flex items-center justify-between">
                                <code className="text-brand-700 font-bold font-mono text-sm break-all mr-3">{generatedShortcode}</code>
                                <button
                                    onClick={() => {
                                        navigator.clipboard.writeText(generatedShortcode);
                                        alert('Skopiowano shortcode!');
                                    }}
                                    className="text-xs bg-white border border-gray-200 px-3 py-1.5 rounded-lg text-gray-500 hover:text-brand-600 hover:border-brand-200 transition-all shadow-sm shrink-0"
                                >
                                    Kopiuj
                                </button>
                            </div>
                        </div>
                    )}
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

            {/* Payment Settings Migration */}
            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4">Aktualizacja Bazy Danych</h3>
                <p className="text-sm text-gray-600 mb-4">
                    Jeśli po aktualizacji pluginu brakuje ustawień płatności, kliknij przycisk poniżej aby dodać nowe opcje do bazy danych.
                </p>
                <button
                    onClick={async () => {
                        try {
                            const bookingData = (window as any).mikroplanetaBooking || {};
                            const response = await fetch(`${bookingData.apiUrl || '/wp-json/mikroplaneta/v1'}/settings/force-add-payment-options`, {
                                method: 'POST',
                                headers: {
                                    'X-WP-Nonce': bookingData.nonce || '',
                                    'Content-Type': 'application/json',
                                },
                            });
                            const data = await response.json();
                            if (data.success) {
                                alert('✅ Dodano ustawienia płatności!\n\n' + JSON.stringify(data.data, null, 2));
                            } else {
                                alert('❌ Błąd: ' + (data.message || 'Nieznany błąd'));
                            }
                        } catch (error: any) {
                            alert('❌ Błąd: ' + (error.message || 'Nieznany błąd'));
                        }
                    }}
                    className="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    Dodaj ustawienia płatności
                </button>
                <p className="text-xs text-gray-400 mt-2">Uruchom tylko raz po aktualizacji pluginu</p>
            </div>

            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm opacity-50 pointer-events-none">
                <h3 className="text-lg font-bold text-gray-900 mb-4">Integracje (Wkrótce)</h3>
                <p className="text-gray-500">Google Calendar, Booking.com, Airbnb</p>
            </div>
        </div>
    );
};

export default Settings;
