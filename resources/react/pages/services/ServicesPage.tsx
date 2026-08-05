import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import DepositWizard from './DepositWizard';
import WithdrawalWizard from './WithdrawalWizard';
import LoanApplicationWizard from './LoanApplicationWizard';
import LoanPaymentWizard from './LoanPaymentWizard';
import TransactionApproval from './TransactionApproval';

type ServiceType = 'deposit' | 'withdraw' | 'loan' | 'payment' | 'approve' | null;

export default function ServicesPage() {
    const [activeService, setActiveService] = useState<ServiceType>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['services'],
        queryFn: () => api.get('/api/services'),
    });

    const svc = data?.data || {};

    const services = [
        { key: 'deposit' as const, label: 'Deposit / Savings', icon: 'fas fa-plus-circle', color: 'emerald', desc: 'Make a deposit to savings or share capital' },
        { key: 'withdraw' as const, label: 'Withdrawal', icon: 'fas fa-minus-circle', color: 'red', desc: 'Request a withdrawal from your account' },
        { key: 'loan' as const, label: 'Loan Application', icon: 'fas fa-file-invoice-dollar', color: 'blue', desc: 'Apply for a new loan' },
        { key: 'payment' as const, label: 'Loan Payment', icon: 'fas fa-hand-holding-usd', color: 'purple', desc: 'Make a payment on an existing loan' },
    ];

    if (svc.can_manage_member_services) {
        services.push({ key: 'approve' as const, label: 'Transaction Approval', icon: 'fas fa-check-double', color: 'amber', desc: 'Review and approve pending transactions' });
    }

    const renderWizard = () => {
        switch (activeService) {
            case 'deposit': return <DepositWizard onComplete={() => setActiveService(null)} />;
            case 'withdraw': return <WithdrawalWizard onComplete={() => setActiveService(null)} />;
            case 'loan': return <LoanApplicationWizard onComplete={() => setActiveService(null)} />;
            case 'payment': return <LoanPaymentWizard onComplete={() => setActiveService(null)} />;
            case 'approve': return <TransactionApproval onComplete={() => setActiveService(null)} />;
            default: return null;
        }
    };

    const activeLabel = services.find(s => s.key === activeService)?.label ?? '';

    const colorMap: Record<string, { bg: string; icon: string; ring: string }> = {
        emerald: { bg: 'bg-emerald-100', icon: 'text-emerald-600', ring: 'ring-emerald-500' },
        red: { bg: 'bg-red-100', icon: 'text-red-600', ring: 'ring-red-500' },
        blue: { bg: 'bg-blue-100', icon: 'text-blue-600', ring: 'ring-blue-500' },
        purple: { bg: 'bg-purple-100', icon: 'text-purple-600', ring: 'ring-purple-500' },
        amber: { bg: 'bg-amber-100', icon: 'text-amber-600', ring: 'ring-amber-500' },
    };

    return (
        <div className="space-y-6">
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h1 className="text-2xl font-bold text-gray-900">Services</h1>
                <p className="mt-1 text-sm text-gray-500">Select a service to get started</p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {services.map(s => (
                    <button
                        key={s.key}
                        onClick={() => setActiveService(s.key)}
                        className="group rounded-xl border border-gray-200 bg-white p-6 text-left shadow-sm transition-all hover:shadow-lg hover:border-gray-300"
                    >
                        <div className={`mb-3 flex h-12 w-12 items-center justify-center rounded-xl ${colorMap[s.color]?.bg || 'bg-gray-100'}`}>
                            <i className={`${s.icon} text-lg ${colorMap[s.color]?.icon || 'text-gray-600'}`}></i>
                        </div>
                        <h3 className="font-semibold text-gray-900">{s.label}</h3>
                        <p className="mt-1 text-sm text-gray-500">{s.desc}</p>
                    </button>
                ))}
            </div>

            {activeService && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setActiveService(null)}>
                    <div className="w-full max-w-md rounded-2xl shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-green-500 to-emerald-600 p-6 text-white">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">{activeLabel}</h2>
                                <button onClick={() => setActiveService(null)} className="rounded-full p-1 text-white/80 hover:bg-white/20 hover:text-white">
                                    <i className="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div className="bg-white p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                            {renderWizard()}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
