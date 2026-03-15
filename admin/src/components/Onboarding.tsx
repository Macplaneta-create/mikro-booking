import React, { useState } from 'react';
import { BuildingIcon, Clock, CheckCircle2, ChevronRight, Loader2 } from 'lucide-react';
import api from '../services/api';

interface OnboardingProps {
    onComplete: () => void;
}

const steps = [
    { id: 'basics', title: 'Hotel Name & Currency', icon: BuildingIcon },
    { id: 'operations', title: 'Check-in Rules', icon: Clock },
    { id: 'completion', title: 'Ready to Go!', icon: CheckCircle2 }
];

export const Onboarding: React.FC<OnboardingProps> = ({ onComplete }) => {
    const [currentStep, setCurrentStep] = useState(0);
    const [saving, setSaving] = useState(false);
    
    // Form state
    const [hotelName, setHotelName] = useState('My Hotel');
    const [currency, setCurrency] = useState('PLN');
    const [checkIn, setCheckIn] = useState('14:00');
    const [checkOut, setCheckOut] = useState('11:00');

    const handleNext = async () => {
        if (currentStep < steps.length - 1) {
            setCurrentStep(prev => prev + 1);
        } else {
            // Finish
            setSaving(true);
            try {
                await api.post('/settings', {
                    hotel_name: hotelName,
                    currency: currency,
                    check_in_time: checkIn,
                    check_out_time: checkOut,
                    onboarding_completed: true
                });
                onComplete();
            } catch (err) {
                console.error("Failed to save onboarding settings:", err);
                alert("Failed to save settings. Check console.");
                setSaving(false);
            }
        }
    };

    return (
        <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-gray-50/95 backdrop-blur-sm p-4">
            <div className="w-full max-w-2xl bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden flex flex-col">
                
                {/* Header / Steps progress */}
                <div className="bg-brand-50 border-b border-brand-100 p-6">
                    <div className="flex justify-between items-center mb-8">
                        <div>
                            <h2 className="text-2xl font-bold text-gray-900">Welcome to MikroPlaneta Booking</h2>
                            <p className="text-gray-500 mt-1">Let's set up your basic hotel configuration</p>
                        </div>
                    </div>
                    
                    <div className="flex items-center justify-between relative">
                        <div className="absolute left-0 right-0 top-1/2 h-0.5 bg-brand-200 -z-10 -translate-y-1/2"></div>
                        {steps.map((step, index) => {
                            const Icon = step.icon;
                            let status = 'upcoming'; // upcoming, current, completed
                            if (index < currentStep) status = 'completed';
                            else if (index === currentStep) status = 'current';

                            return (
                                <div key={step.id} className="flex flex-col items-center bg-brand-50 px-2 relative z-10">
                                    <div className={`
                                        w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm
                                        transition-colors duration-300 border-2
                                        ${status === 'completed' ? 'bg-brand-500 border-brand-500 text-white' : ''}
                                        ${status === 'current' ? 'bg-white border-brand-500 text-brand-600 shadow-md' : ''}
                                        ${status === 'upcoming' ? 'bg-white border-gray-200 text-gray-400' : ''}
                                    `}>
                                        {status === 'completed' ? <CheckCircle2 size={20} /> : <Icon size={20} />}
                                    </div>
                                    <span className={`text-xs mt-2 font-medium ${status === 'upcoming' ? 'text-gray-400' : 'text-gray-900'}`}>{step.title}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Content Area */}
                <div className="p-8 pb-6 flex-grow">
                    
                    {currentStep === 0 && (
                        <div className="animate-in fade-in slide-in-from-right-4 duration-500">
                            <h3 className="text-xl font-bold text-gray-900 mb-6">Basic Information</h3>
                            
                            <div className="space-y-5">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Hotel/Property Name</label>
                                    <input 
                                        type="text" 
                                        value={hotelName}
                                        onChange={e => setHotelName(e.target.value)}
                                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                        placeholder="E.g. The Grand Alpine Hotel"
                                    />
                                    <p className="text-xs text-gray-500 mt-1">This will be displayed in emails and receipts.</p>
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Primary Currency</label>
                                    <select 
                                        value={currency}
                                        onChange={e => setCurrency(e.target.value)}
                                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    >
                                        <option value="PLN">PLN - Polish Złoty</option>
                                        <option value="EUR">EUR - Euro</option>
                                        <option value="USD">USD - US Dollar</option>
                                        <option value="GBP">GBP - British Pound</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    )}

                    {currentStep === 1 && (
                        <div className="animate-in fade-in slide-in-from-right-4 duration-500">
                            <h3 className="text-xl font-bold text-gray-900 mb-6">Operations Details</h3>
                            
                            <div className="grid grid-cols-2 gap-5">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Standard Check-in Time</label>
                                    <input 
                                        type="time" 
                                        value={checkIn}
                                        onChange={e => setCheckIn(e.target.value)}
                                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    />
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Standard Check-out Time</label>
                                    <input 
                                        type="time" 
                                        value={checkOut}
                                        onChange={e => setCheckOut(e.target.value)}
                                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    />
                                </div>
                            </div>
                            <div className="mt-6 bg-blue-50 text-blue-800 rounded-lg p-4 text-sm flex items-start gap-3 border border-blue-100">
                                <Clock className="w-5 h-5 flex-shrink-0 text-blue-600 mt-0.5" />
                                <div>
                                    <p className="font-semibold mb-1">Did you know?</p>
                                    <p>These hours will be automatically included in reservation confirmation emails sent to your guests.</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {currentStep === 2 && (
                        <div className="animate-in fade-in slide-in-from-right-4 duration-500 text-center py-6">
                            <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 text-green-600">
                                <CheckCircle2 size={40} />
                            </div>
                            <h3 className="text-2xl font-bold text-gray-900 mb-3">You're All Set!</h3>
                            <p className="text-gray-500 max-w-sm mx-auto mb-6">
                                The basic configuration is saved. Next, you should add your first rooms and set up their beds to start taking reservations.
                            </p>
                        </div>
                    )}

                </div>

                {/* Footer / Controls */}
                <div className="border-t border-gray-100 bg-gray-50 px-8 py-5 flex justify-between items-center rounded-b-2xl">
                    <button 
                        onClick={() => setCurrentStep(prev => prev - 1)}
                        className={`text-sm font-medium text-gray-500 hover:text-gray-900 px-4 py-2 opacity-100 transition-opacity ${currentStep === 0 || saving ? 'opacity-0 pointer-events-none' : ''}`}
                    >
                        Back
                    </button>
                    
                    <button 
                        onClick={handleNext}
                        disabled={saving}
                        className="inline-flex items-center gap-2 px-6 py-2.5 bg-brand-600 hover:bg-brand-700 disabled:opacity-70 text-white text-sm font-medium rounded-lg shadow-sm transition-all focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                    >
                        {saving ? (
                            <><Loader2 size={18} className="animate-spin" /> Saving...</>
                        ) : currentStep === steps.length - 1 ? (
                            <>Complete Setup <CheckCircle2 size={18} /></>
                        ) : (
                            <>Continue <ChevronRight size={18} /></>
                        )}
                    </button>
                </div>
                
            </div>
        </div>
    );
};
export default Onboarding;
