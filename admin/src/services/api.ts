import axios from 'axios';

// Define the global variable type coming from wp_localize_script
declare global {
    interface Window {
        mikroplanetaBooking: {
            apiUrl: string;
            nonce: string;
            currentPage?: string;
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
    floor: number;
    room_type: string;
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
    bed_id: number;
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
    getAll: async (params?: { floor?: number; room_type?: string }) => {
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
    checkIn: async (id: number) => {
        const res = await api.post(`/reservations/${id}/checkin`);
        return res.data.data as Reservation;
    },
    confirm: async (id: number) => {
        const res = await api.post(`/reservations/${id}/confirm`);
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
    getCalendar: async (bedId: number, start: string, end: string) => {
        const res = await api.get(`/availability/calendar/${bedId}`, { params: { start_date: start, end_date: end } });
        return res.data.data;
    },
    getOccupancy: async (params: { start_date: string, end_date: string }) => {
        const res = await api.get('/availability/occupancy', { params });
        return res.data.data;
    },
    findBeds: async (params: { check_in: string, check_out: string, room_id?: number }) => {
        const res = await api.get('/availability/beds', { params });
        return res.data.data as Bed[];
    }
};

export default api;
