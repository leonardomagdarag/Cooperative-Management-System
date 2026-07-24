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

    const colorMap: Record<string, { bg: string; icon: string }> = {
        emerald: { bg: 'bg-emerald-100 dark:bg-emerald-900/30', icon: 'text-emerald-600' },
        red: { bg: 'bg-red-100 dark:bg-red-900/30', icon: 'text-red-600' },
        blue: { bg: 'bg-blue-100 dark:bg-blue-900/30', icon: 'text-blue-600' },
        purple: { bg: 'bg-purple-100 dark:bg-purple-900/30', icon: 'text-purple-600' },
        amber: { bg: 'bg-amber-100 dark:bg-amber-900/30', icon: 'text-amber-600' },
    };

    const services = [
        { key: 'deposit' as const, label: 'Deposit', icon: 'fas fa-plus-circle', color: 'emerald', desc: 'Make a deposit' },
        { key: 'withdraw' as const, label: 'Withdrawal', icon: 'fas fa-minus-circle', color: 'red', desc: 'Request withdrawal' },
        { key: 'loan' as const, label: 'Loan Application', icon: 'fas fa-file-invoice-dollar', color: 'blue', desc: 'Apply for a loan' },
        { key: 'payment' as const, label: 'Loan Payment', icon: 'fas fa-hand-holding-usd', color: 'purple', desc: 'Pay a loan' },
    ];

    if (svc.can_manage_member_services) {
        services.push({ key: 'approve' as const, label: 'Approvals', icon: 'fas fa-check-double', color: 'amber', desc: 'Pending transactions' });
    }

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Services</h1>
                {activeService && (
                    <button onClick={() => setActiveService(null)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                        <i className="fas fa-arrow-left mr-1"></i> Back to Services
                    </button>
                )}
            </div>

            {!activeService ? (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {services.map(s => (
                        <button key={s.key} onClick={() => setActiveService(s.key)} className="group rounded-xl border border-slate-200 bg-white p-6 text-left transition-all hover:shadow-lg hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600">
                            <div className={`mb-3 flex h-12 w-12 items-center justify-center rounded-xl ${colorMap[s.color]?.bg || 'bg-slate-100'}`}>
                                <i className={`${s.icon} text-lg ${colorMap[s.color]?.icon || 'text-slate-600'}`}></i>
                            </div>
                            <h3 className="font-semibold text-slate-900 dark:text-white">{s.label}</h3>
                            <p className="mt-1 text-sm text-slate-500">{s.desc}</p>
                        </button>
                    ))}
                </div>
            ) : (
                <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                    {activeService === 'deposit' && <DepositWizard onComplete={() => setActiveService(null)} />}
                    {activeService === 'withdraw' && <WithdrawalWizard onComplete={() => setActiveService(null)} />}
                    {activeService === 'loan' && <LoanApplicationWizard onComplete={() => setActiveService(null)} />}
                    {activeService === 'payment' && <LoanPaymentWizard onComplete={() => setActiveService(null)} />}
                    {activeService === 'approve' && <TransactionApproval />}
                </div>
            )}
        </div>
    );
}
