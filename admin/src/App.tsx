/**
 * Root App Component
 * 
 * Handles routing based on WordPress URL parameters
 */

import React, { useState, useEffect } from 'react';
import { LayoutDashboard, CalendarDays, BedDouble, Users, Settings as SettingsIcon } from 'lucide-react';
import RoomManager from './components/RoomManager';
import DashboardContent from './components/DashboardContent';
import CalendarView from './components/CalendarView';
import Settings from './components/Settings';
import GuestsView from './components/GuestsView';

const App: React.FC = () => {
    // Determine current view based on URL parameter 'page'
    // Format: mikroplaneta-booking-[view] or just mikroplaneta-booking (dashboard)
    const [currentView, setCurrentView] = useState('dashboard');

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || '';

        if (page.endsWith('-rooms')) {
            setCurrentView('rooms');
        } else if (page.endsWith('-reservations')) {
            setCurrentView('reservations');
        } else if (page.endsWith('-guests')) {
            setCurrentView('guests');
        } else if (page.endsWith('-settings')) {
            setCurrentView('settings');
        } else {
            setCurrentView('dashboard');
        }
    }, []);

    // Render active component
    const renderContent = () => {
        switch (currentView) {
            case 'rooms':
                return <RoomManager />;
            case 'reservations':
                return <CalendarView />;
            case 'guests':
                return <GuestsView />;
            case 'settings':
                return <Settings />;
            case 'dashboard':
            default:
                return <DashboardContent />;
        }
    };

    return (
        <div className="mikroplaneta-app min-h-screen bg-gray-50/50 p-6 md:p-8">
            <header className="mb-8 flex items-center justify-between">
                <div>
                    {/* Dynamic Header */}
                    {currentView === 'dashboard' && <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3"><LayoutDashboard className="text-brand-600" /> Dashboard</h1>}
                    {currentView === 'rooms' && <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3"><BedDouble className="text-brand-600" /> Pokoje i Łóżka</h1>}
                    {currentView === 'reservations' && <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3"><CalendarDays className="text-brand-600" /> Rezerwacje</h1>}
                    {currentView === 'guests' && <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3"><Users className="text-brand-600" /> Baza Gości</h1>}
                    {currentView === 'settings' && <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3"><SettingsIcon className="text-brand-600" /> Ustawienia</h1>}

                    <p className="text-gray-500 mt-2 ml-1">MikroPlaneta Booking System v1.0</p>
                </div>
            </header>

            <main>
                {renderContent()}
            </main>
        </div>
    );
};

export default App;
