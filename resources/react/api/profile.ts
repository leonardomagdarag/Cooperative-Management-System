import { api } from './client';

export interface ProfileData {
    user: {
        id: number;
        user_number: string;
        email: string;
        primary_role: string;
        is_officer: boolean;
        account_status: string;
        created_at: string;
    };
    personal_data: {
        title: string;
        first_name: string;
        mid_name: string;
        last_name: string;
        suffix: string;
        birth_date: string;
        birthplace: string;
        gender: string;
        education: string;
        civil_status: string;
        religion: string;
        profile_picture: string | null;
    } | null;
    address: {
        country: string;
        region: string;
        province: string;
        city_municipality: string;
        barangay: string;
        street: string;
        zip_code: string;
    } | null;
    contact: {
        phone_no: string;
        email: string;
        facebook: string;
    } | null;
    accounts: Array<{
        id: number;
        account_number: string;
        account_type: string;
        balance: number;
        status: string;
    }>;
    total_balance: number;
    loans: Array<{
        id: number;
        loan_number: string;
        loan_type: string;
        principal_amount: number;
        loan_status: string;
    }>;
    active_loans_count: number;
    beneficiaries: Array<{
        id: number;
        name: string;
        relationship: string;
        percentage: number;
    }>;
    recent_logs: Array<{
        id: number;
        description: string;
        event: string;
        created_at: string;
    }>;
    trusted_devices: Array<{
        id: number;
        device_name: string;
        ip_address: string;
        last_used_at: string;
        is_active: boolean;
    }>;
}

export async function getProfile() {
    return api.get('/api/profile');
}

export async function updateProfile(data: Record<string, any>) {
    return api.put('/api/profile', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
}
