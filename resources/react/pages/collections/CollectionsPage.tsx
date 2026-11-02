import React, { useState, useEffect } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import ModalTabs from '../../components/ui/ModalTabs';
import MyRouteTab from './MyRouteTab';
import ApprovalsQueueTab from './ApprovalsQueueTab';
import RunHistoryTab from './RunHistoryTab';
import PageHeader from '../../components/ui/PageHeader';

export default function CollectionsPage() {
    const { user } = useAuth();
    
    // Reviewers need queue access even when they cannot browse collection history.
    const isOfficer = user?.permissions?.includes('collections.create');
    const isApprover = user?.permissions?.includes('collections.approve') || user?.permissions?.includes('oversight.approve');
    const canReview = user?.permissions?.includes('collections.review');
    const canViewAll = user?.permissions?.includes('collections.view-all');

    const tabs = [];
    
    if (isOfficer) {
        tabs.push({ key: 'my-route', id: 'my-route', label: 'My Route' });
    }
    
    if (isApprover || canReview) {
        tabs.push({ key: 'approvals', id: 'approvals', label: 'Approvals Queue' });
    }
    
    if (isOfficer || canViewAll) {
        tabs.push({ key: 'history', id: 'history', label: 'Run History' });
    }

    const [activeTab, setActiveTab] = useState(tabs[0]?.id || '');

    if (tabs.length === 0) {
        return <div className="p-6">You do not have permission to view this page.</div>;
    }

    return (
        <div className="p-6 max-w-6xl mx-auto">
            <PageHeader className="mb-6" title="Field Collections" description="Manage collection routes, review submissions, and track completed runs." icon="fas fa-route" />

            <ModalTabs
                tabs={tabs}
                activeTab={activeTab}
                onChange={setActiveTab}
            />

            <div className="mt-6 bg-white rounded-xl shadow-sm border border-slate-200 min-h-[500px]">
                {activeTab === 'my-route' && <MyRouteTab />}
                {activeTab === 'approvals' && <ApprovalsQueueTab />}
                {activeTab === 'history' && <RunHistoryTab canViewAll={canViewAll} />}
            </div>
        </div>
    );
}
