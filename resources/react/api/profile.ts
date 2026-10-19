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
    employment: {
        employment_type: string;
        company: string;
        position: string;
        monthly_income: string;
        other_income_source: string;
        other_income_amount: string;
    } | null;
    member_id?: {
        national_id: string;
        tin: string;
        sss: string;
        philhealth: string;
        barangay_id: string;
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
        first_name?: string;
        last_name?: string;
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
        device_label: string;
        last_ip_address: string;
        user_agent: string | null;
        last_used_at: string | null;
        trusted_until: string | null;
        is_active: boolean;
        is_current: boolean;
    }>;
}

export async function getProfile() {
    return api.get('/api/profile');
}

export async function updateProfile(data: Record<string, any>) {
    if (data instanceof FormData) data.append('_method', 'PUT');
    return api.post('/api/profile', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
}

export async function sendEmailChangeOtp(email: string) {
    return api.post('/api/profile/email/send-otp', { email });
}
