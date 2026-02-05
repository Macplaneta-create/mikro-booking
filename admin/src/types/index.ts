export interface Bed {
    id: number;
    room_id: number;
    name: string;
    type: 'single' | 'double' | 'bunk_top' | 'bunk_bottom';
    status: 'active' | 'maintenance';
}

export interface Room {
    id: number;
    name: string;
    type: 'private' | 'dorm';
    description?: string;
    floor: number;
    status: 'active' | 'maintenance' | 'inactive';
    beds?: Bed[];
}
