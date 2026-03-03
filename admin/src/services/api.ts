import axios from 'axios';

// Define the global variable type coming from wp_localize_script
declare global {
    interface Window {
        mikroplanetaBooking: {
            apiUrl: string;
            nonce: string;
            currentPage?: string;
            version?: string;
        };
    }
}

// Get settings from global window object (injected by WordPress)
// Fallback for local development (outside WP)
const settings = window.mikroplanetaBooking || {
    apiUrl: 'http://localhost/gorytajemnic/wp-json/mikroplaneta/v1',
    nonce: 'dev-nonce', // Won't work for real requests in dev without auth substitution
};

// Create Axios instance
const api = axios.create({
    baseURL: settings.apiUrl,
    headers: {
        'X-WP-Nonce': settings.nonce,
        'Content-Type': 'application/json',
    },
});

const sanitizePricingPayload = (data: Record<string, any>) => {
    const payload: Record<string, any> = { ...data };

    // WP REST validation rejects null for typed fields.
    Object.keys(payload).forEach((key) => {
        if (payload[key] === null || payload[key] === undefined || payload[key] === '') {
            delete payload[key];
        }
    });

    if (payload.scope_type === 'room_type') {
        delete payload.room_id;
    }

    if (payload.scope_type === 'room_id') {
        delete payload.room_type;
    }

    return payload;
};

// Response interceptor for better error handling
api.interceptors.response.use(
    (response) => {
        // Return the response directly
        // If the API returns { success: true, data: ... }, we might want to unwrap it here
        // Our Controller sends: { success: true, data: ... }
        if (response.data && response.data.success !== undefined) {
            // If success is false in body (business error handled as 200 OK sometimes? No, we use HTTP codes)
            // But let's return response.data to keep full control in components
            return response;
        }
        return response;
    },
    (error) => {
        // Handle specific error cases
        if (error.response) {
            console.error('API Error:', error.response.status, error.response.data);
            // Special handling for 403 (Nonce/Permission) or 401 (Auth)
        } else {
            console.error('Network/Client Error:', error.message);
        }
        return Promise.reject(error);
    }
);

// --- API Service Models ---

export interface Room {
    id?: number;
    name: string;
    description?: string;
    image_id?: number;
    image_url?: string;
    amenities: string[];
    floor: number;
    room_type: string;
    pricing_mode: 'per_room' | 'per_bed';
    status: 'active' | 'inactive' | 'maintenance';
    beds?: Bed[];
}

export interface Bed {
    id?: number;
    room_id: number;
    bed_number: number;
    bed_type: string;
    is_active: boolean;
}

export interface Reservation {
    id?: number;
    guest_id: number;
    bed_ids: number[];
    check_in: string; // YYYY-MM-DD
    check_out: string; // YYYY-MM-DD
    status?: string;
    total_price?: number;
    adults?: number;
    children?: number;
    notes?: string;
    first_name?: string;
    last_name?: string;
}

export interface Guest {
    id?: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    total_stays?: number;
    last_stay_date?: string;
    created_at?: string;
}

// --- API Methods ---

export const RoomsAPI = {
    getAll: async (params?: { floor?: number; room_type?: string; status?: string }) => {
        const res = await api.get('/rooms', { params });
        return res.data.data as Room[];
    },
    getOne: async (id: number) => {
        const res = await api.get(`/rooms/${id}`);
        return res.data.data as Room;
    },
    create: async (data: Omit<Room, 'id'>) => {
        const res = await api.post('/rooms', data);
        return res.data.data as Room;
    },
    update: async (id: number, data: Partial<Room>) => {
        const res = await api.put(`/rooms/${id}`, data);
        return res.data.data as Room;
    },
    delete: async (id: number) => {
        await api.delete(`/rooms/${id}`);
    },

    // Bed sub-resources
    getBeds: async (roomId: number) => {
        const res = await api.get(`/rooms/${roomId}/beds`);
        return res.data.data as Bed[];
    },
    createBed: async (roomId: number, data: Omit<Bed, 'id' | 'room_id'>) => {
        const res = await api.post(`/rooms/${roomId}/beds`, data);
        return res.data.data as Bed;
    }
};

export const BedsAPI = {
    delete: async (id: number) => {
        await api.delete(`/beds/${id}`);
    },
    update: async (id: number, data: Partial<Bed>) => {
        const res = await api.put(`/beds/${id}`, data);
        return res.data.data as Bed;
    }
};

export const ReservationsAPI = {
    getAll: async (params?: any) => {
        const res = await api.get('/reservations', { params });
        return res.data.data as Reservation[];
    },
    getOne: async (id: number) => {
        const res = await api.get(`/reservations/${id}`);
        return res.data.data as Reservation;
    },
    create: async (data: any) => {
        const res = await api.post('/reservations', data);
        return res.data.data as Reservation;
    },
    update: async (id: number, data: any) => {
        const res = await api.put(`/reservations/${id}`, data);
        return res.data.data as Reservation;
    },
    // Actions
    cancel: async (id: number, reason: string) => {
        const res = await api.post(`/reservations/${id}/cancel`, { reason });
        return res.data.data as Reservation;
    },
    checkIn: async (id: number, adjustment?: { adults?: number, children?: number, bed_ids?: number[] }) => {
        const res = await api.post(`/reservations/${id}/checkin`, adjustment);
        return res.data.data as Reservation;
    },
    confirm: async (id: number, reason?: string) => {
        const res = await api.post(`/reservations/${id}/confirm`, { reason });
        return res.data.data as Reservation;
    },
    checkOut: async (id: number) => {
        const res = await api.post(`/reservations/${id}/checkout`);
        return res.data.data as Reservation;
    }
};

export const GuestsAPI = {
    getAll: async (params?: any) => {
        const res = await api.get('/guests', { params });
        return res.data.data as Guest[];
    },
    getStats: async () => {
        const res = await api.get('/guests/stats');
        return res.data.data;
    },
    create: async (data: any) => {
        const res = await api.post('/guests', data);
        return res.data.data as Guest;
    }
};

export const AvailabilityAPI = {
    groupSearch: async (params: { group_size: number, check_in: string, check_out: string }) => {
        const res = await api.get('/availability/group-search', { params });
        return res.data.data as { type: string; room_id?: number; beds: Bed[]; score: number }[];
    },
    getCalendar: async (bedId: number, start: string, end: string) => {
        const res = await api.get(`/availability/calendar/${bedId}`, { params: { start_date: start, end_date: end } });
        return res.data.data;
    },
    getOccupancy: async (params: { start_date: string, end_date: string }) => {
        const res = await api.get('/availability/occupancy', { params });
        return res.data.data;
    },
    findBeds: async (params: { check_in: string, check_out: string, room_id?: number }) => {
        // Use public endpoint for consistency with frontend widgets
        const res = await api.get('/public/availability/beds', { params });
        return res.data.data as Bed[];
    }
};

export const DashboardAPI = {
    getStats: async () => {
        const res = await api.get('/dashboard/stats');
        return res.data.data;
    }
};

export const PricingAPI = {
    getAll: async (params?: { room_id?: number; room_type?: string; scope_type?: string; pricing_mode?: string }) => {
        const res = await api.get('/pricing', { params });
        return (res.data.data as any[]).map(rule => ({
            ...rule,
            name: rule.name || null,
            scope_type: rule.scope_type || 'room_id',
            room_id: rule.room_id ? Number(rule.room_id) : null,
            room_type: rule.room_type || null,
            pricing_mode: rule.pricing_mode || null,
            priority: Number(rule.priority ?? 100),
            base_price: Number(rule.base_price),
            weekend_price: Number(rule.weekend_price),
            weekend_from_day: Number(rule.weekend_from_day ?? 5),
            weekend_to_day: Number(rule.weekend_to_day ?? 7)
        })) as Array<{
            id: number;
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
            weekend_from_day: number;
            weekend_to_day: number;
        }>;
    },
    create: async (data: {
        name?: string | null;
        scope_type?: 'room_id' | 'room_type';
        room_id?: number | null;
        room_type?: string | null;
        pricing_mode?: 'per_room' | 'per_bed' | null;
        priority?: number;
        start_date: string;
        end_date: string;
        base_price: number;
        weekend_price: number;
        weekend_from_day?: number;
        weekend_to_day?: number;
    }) => {
        const payload = sanitizePricingPayload(data as Record<string, any>);
        const res = await api.post('/pricing', payload);
        const item = res.data.data;
        return {
            ...item,
            name: item.name || null,
            scope_type: item.scope_type || 'room_id',
            room_id: item.room_id ? Number(item.room_id) : null,
            room_type: item.room_type || null,
            pricing_mode: item.pricing_mode || null,
            priority: Number(item.priority ?? 100),
            base_price: Number(item.base_price),
            weekend_price: Number(item.weekend_price),
            weekend_from_day: Number(item.weekend_from_day ?? 5),
            weekend_to_day: Number(item.weekend_to_day ?? 7)
        };
    },
    update: async (id: number, data: {
        name?: string | null;
        scope_type?: 'room_id' | 'room_type';
        room_id?: number | null;
        room_type?: string | null;
        pricing_mode?: 'per_room' | 'per_bed' | null;
        priority?: number;
        start_date?: string;
        end_date?: string;
        base_price?: number;
        weekend_price?: number;
        weekend_from_day?: number;
        weekend_to_day?: number;
    }) => {
        const payload = sanitizePricingPayload(data as Record<string, any>);
        const res = await api.put(`/pricing/${id}`, payload);
        const item = res.data.data;
        return {
            ...item,
            name: item.name || null,
            scope_type: item.scope_type || 'room_id',
            room_id: item.room_id ? Number(item.room_id) : null,
            room_type: item.room_type || null,
            pricing_mode: item.pricing_mode || null,
            priority: Number(item.priority ?? 100),
            base_price: Number(item.base_price),
            weekend_price: Number(item.weekend_price),
            weekend_from_day: Number(item.weekend_from_day ?? 5),
            weekend_to_day: Number(item.weekend_to_day ?? 7)
        };
    },
    delete: async (id: number) => {
        const res = await api.delete(`/pricing/${id}`);
        return res.data;
    },
    calculateGroup: async (params: {
        bed_ids: number[];
        check_in: string;
        check_out: string;
        adults?: number;
        children?: number;
        room_id?: number;
    }) => {
        const res = await api.post('/pricing/calculate-group', params);
        const data = res.data.data;
        return {
            ...data,
            total: Number(data.total),
            details: (data.details || []).map((d: any) => ({
                ...d,
                price: Number(d.price)
            }))
        } as {
            total: number;
            nights: number;
            details: Array<{
                date: string;
                price: number;
                is_weekend: boolean;
                is_room_total?: boolean;
            }>;
        };
    }
};

export const SettingsAPI = {
    getAll: async () => {
        const res = await api.get('/settings');
        return res.data.data as {
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
            privacy_policy_page_id: number;
            terms_page_id: number;
            backup_email: string;
            backup_email_enabled: boolean;
            backup_email_time: string;
            backup_retention_hours: number;
            ical_retention_hours: number;
            csv_export_email: string;
            csv_export_enabled: boolean;
            csv_export_time: string;
        };
    },
    update: async (data: {
        hotel_name?: string;
        check_in_time?: string;
        check_out_time?: string;
        currency?: string;
        timezone?: string;
        email_notifications?: boolean;
        pending_timeout_hours?: number;
        auto_expire_pending?: boolean;
        require_payment_confirmation?: boolean;
        multiplier_single?: number;
        multiplier_double?: number;
        multiplier_bunk?: number;
        multiplier_children?: number;
        captcha_provider?: 'none' | 'recaptcha_v3' | 'hcaptcha';
        recaptcha_site_key?: string;
        recaptcha_secret_key?: string;
        recaptcha_min_score?: number;
        hcaptcha_site_key?: string;
        hcaptcha_secret_key?: string;
        rate_limit_enabled?: boolean;
        rate_limit_window_seconds?: number;
        rate_limit_max_requests?: number;
        backup_email?: string;
        backup_email_enabled?: boolean;
        backup_email_time?: string;
        backup_retention_hours?: number;
        ical_retention_hours?: number;
        csv_export_email?: string;
        csv_export_enabled?: boolean;
        csv_export_time?: string;
    }) => {
        const res = await api.post('/settings', data);
        return res.data.data;
    },
    triggerCron: async () => {
        const res = await api.post('/settings/trigger-cron');
        return res.data;
    },
    getEmailTemplates: async () => {
        const res = await api.get('/settings/email-templates');
        return res.data.data as {
            templates: Array<{
                key: string;
                label: string;
                subject: string;
                body: string;
                default_subject: string;
                default_body: string;
            }>;
            placeholders: string[];
        };
    },
    updateEmailTemplates: async (templates: Array<{ key: string; subject: string; body: string }>) => {
        const res = await api.post('/settings/email-templates', { templates });
        return res.data.data as {
            message: string;
            templates: {
                templates: Array<{
                    key: string;
                    label: string;
                    subject: string;
                    body: string;
                    default_subject: string;
                    default_body: string;
                }>;
                placeholders: string[];
            };
        };
    },
    getNotificationsLog: async (limit = 100) => {
        const res = await api.get('/settings/notifications-log', { params: { limit } });
        return res.data.data as Array<{
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
        }>;
    },
    sendTestEmail: async (template_key: string, to_email: string) => {
        const res = await api.post('/settings/test-email', { template_key, to_email });
        return res.data.data as { message: string };
    }
};

export const LogsAPI = {
    getByReservation: async (reservationId: number) => {
        const res = await api.get(`/logs/${reservationId}`);
        return res.data.data as Array<{
            id: number;
            reservation_id: number;
            changed_by: number;
            change_type: string;
            old_value: any;
            new_value: any;
            created_at: string;
            user_name: string;
        }>;
    }
};

export interface ExtraService {
    id?: number;
    name: string;
    description: string;
    price: number;
    pricing_type: 'per_stay' | 'per_unit' | 'per_person';
    auto_suggest_by_beds: boolean;
    is_active: boolean;
    sort_order: number;
}

export interface ReservationExtra {
    id?: number;
    reservation_id: number;
    service_id: number;
    quantity: number;
    unit_price: number;
    total_price: number;
    service_name?: string;
}

export const ExtrasAPI = {
    getServices: async (params?: any) => {
        const res = await api.get('/extras/services', { params });
        return res.data.data as ExtraService[];
    },
    createService: async (data: Partial<ExtraService>) => {
        const res = await api.post('/extras/services', data);
        return res.data.data as ExtraService;
    },
    updateService: async (id: number, data: Partial<ExtraService>) => {
        const res = await api.put(`/extras/services/${id}`, data);
        return res.data.data as ExtraService;
    },
    deleteService: async (id: number) => {
        await api.delete(`/extras/services/${id}`);
    },
    getReservationExtras: async (reservationId: number) => {
        const res = await api.get(`/reservations/${reservationId}/extras`);
        return res.data.data as ReservationExtra[];
    },
    setReservationExtras: async (reservationId: number, extras: Array<{ service_id: number; quantity: number }>) => {
        const res = await api.post(`/reservations/${reservationId}/extras`, { extras });
        return res.data.data as ReservationExtra[];
    }
};

export default api;
