import { z } from 'zod';

export type FieldErrors = Record<string, string>;

const MAX_NAME = 100;
const MAX_EMAIL = 191;
const MAX_PHONE = 11;

const passwordRegex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{8,128}$/;

const stringMax = (max: number, msg?: string) => z.string().max(max, msg ?? `Maximum of ${max} characters`).optional().or(z.literal(''));

const requiredString = (msg: string, max = 255) => z.string().trim().min(1, msg).max(max, `Maximum of ${max} characters`);

const optionalString = (max: number) => stringMax(max);

const amount = (min: number, label = 'Amount') =>
    z.coerce.number({ invalid_type_error: 'Enter a valid ' + label })
        .finite()
        .positive(`Enter a valid ${label}`)
        .min(min, `${label} must be at least ${min}`);

const dateOrEmpty = z.string().refine(v => v === '' || !isNaN(Date.parse(v)), { message: 'Enter a valid date' });

export const enumFrom = (values: readonly string[], msg: string) =>
    z.string().refine(v => values.includes(v), { message: msg });

export const passwordPolicy = z.string()
    .min(8, 'Password must be at least 8 characters')
    .max(128, 'Password must be at most 128 characters')
    .regex(/[A-Z]/, 'Must contain an uppercase letter')
    .regex(/[a-z]/, 'Must contain a lowercase letter')
    .regex(/[0-9]/, 'Must contain a number');

export const optionalPassword = passwordPolicy.optional().or(z.literal(''));

export function toFieldErrors(error: z.ZodError | undefined): FieldErrors {
    if (!error) return {};
    const out: FieldErrors = {};
    for (const issue of error.issues) {
        const key = issue.path.join('.');
        if (!out[key]) out[key] = issue.message;
    }
    return out;
}

export function validate(schema: z.ZodTypeAny, data: unknown): FieldErrors {
    const result = schema.safeParse(data);
    return result.success ? {} : toFieldErrors(result.error);
}

export function isValid(schema: z.ZodTypeAny, data: unknown): boolean {
    return schema.safeParse(data).success;
}

export interface PasswordStrength {
    score: number;
    label: string;
    checks: { label: string; ok: boolean }[];
}

export function passwordStrength(pw: string): PasswordStrength {
    const checks = [
        { label: 'At least 8 characters', ok: pw.length >= 8 },
        { label: 'Uppercase letter', ok: /[A-Z]/.test(pw) },
        { label: 'Lowercase letter', ok: /[a-z]/.test(pw) },
        { label: 'Number', ok: /[0-9]/.test(pw) },
    ];
    const score = checks.filter(c => c.ok).length;
    const label = pw.length === 0 ? '' : score <= 1 ? 'Weak' : score === 2 ? 'Fair' : score === 3 ? 'Good' : 'Strong';
    return { score, label, checks };
}

const beneficiarySchema = z.object({
    first_name: requiredString('First name is required', MAX_NAME),
    last_name: requiredString('Last name is required', MAX_NAME),
    relationship: requiredString('Relationship is required', 50),
});

export const memberSchema = z.object({
    first_name: requiredString('First name is required', MAX_NAME),
    mid_name: optionalString(MAX_NAME),
    last_name: requiredString('Last name is required', MAX_NAME),
    suffix: optionalString(50),
    birth_date: requiredString('Birth date is required'),
    gender: enumFrom(['male', 'female', 'other'], 'Select a valid gender'),
    civil_status: enumFrom(['single', 'married', 'widowed', 'separated'], 'Select a valid civil status'),
    email: z.string().trim().min(1, 'Email is required').max(MAX_EMAIL, 'Email is too long').email('Enter a valid email'),
    phone_number: z.string().trim().min(1, 'Phone number is required').max(MAX_PHONE, `Phone number must be at most ${MAX_PHONE} digits`).regex(/^[0-9]+$/, 'Phone number must contain only digits'),
    password: passwordPolicy,
    password_confirmation: z.string(),
    monthly_income: z.coerce.number().min(0, 'Monthly income cannot be negative').optional().or(z.literal('')),
}).refine(d => d.password === d.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
});

export const memberStepSchemas: Record<string, z.ZodTypeAny> = {
    personal: z.object({
        first_name: requiredString('First name is required', MAX_NAME),
        mid_name: optionalString(MAX_NAME),
        last_name: requiredString('Last name is required', MAX_NAME),
        suffix: optionalString(50),
        birth_date: requiredString('Birth date is required'),
        gender: enumFrom(['male', 'female', 'other'], 'Select a valid gender'),
        civil_status: enumFrom(['single', 'married', 'widowed', 'separated'], 'Select a valid civil status'),
        religion: optionalString(64),
        title: optionalString(50),
    }),
    address: z.object({
        country: optionalString(100),
        region: optionalString(100),
        province: optionalString(100),
        city: optionalString(100),
        barangay: optionalString(100),
        street: optionalString(255),
        postal_code: optionalString(20),
    }),
    contact: z.object({
        email: z.string().trim().min(1, 'Email is required').max(MAX_EMAIL, 'Email is too long').email('Enter a valid email'),
        phone_number: z.string().trim().min(1, 'Phone number is required').max(MAX_PHONE, `Phone number must be at most ${MAX_PHONE} digits`).regex(/^[0-9]+$/, 'Phone number must contain only digits'),
        facebook_link: optionalString(255),
    }),
    ids: z.object({
        national_id: optionalString(64),
        tin: optionalString(64),
        sss: optionalString(64),
        philhealth: optionalString(64),
        barangay_id: optionalString(64),
    }),
    employment: z.object({
        employment_type: optionalString(50),
        employer_name: optionalString(255),
        position: optionalString(100),
        monthly_income: z.coerce.number().min(0, 'Monthly income cannot be negative').optional().or(z.literal('')),
    }),
    login: z.object({
        password: passwordPolicy,
        password_confirmation: z.string(),
    }).refine(d => d.password === d.password_confirmation, {
        message: 'Passwords do not match',
        path: ['password_confirmation'],
    }),
    beneficiaries: z.object({
        beneficiaries: z.array(beneficiarySchema),
    }),
    agreement: z.object({
        agreement: z.boolean().refine(v => v === true, { message: 'You must accept the agreement to continue' }),
    }),
    review: z.object({}),
};

export const memberEditSchema = z.object({
    first_name: requiredString('First name is required', MAX_NAME),
    mid_name: optionalString(MAX_NAME),
    last_name: requiredString('Last name is required', MAX_NAME),
    suffix: optionalString(50),
    birth_date: dateOrEmpty.optional(),
    gender: enumFrom(['male', 'female', 'other'], 'Select a valid gender').optional(),
    civil_status: enumFrom(['single', 'married', 'widowed', 'separated'], 'Select a valid civil status').optional(),
    email: z.string().trim().min(1, 'Email is required').max(MAX_EMAIL, 'Email is too long').email('Enter a valid email'),
    phone_number: optionalString(MAX_PHONE),
    facebook_link: optionalString(255),
    password: optionalPassword,
});

export const memberEditStepSchemas: Record<string, z.ZodTypeAny> = {
    ...memberStepSchemas,
    personal: z.object({
        first_name: requiredString('First name is required', MAX_NAME),
        mid_name: optionalString(MAX_NAME),
        last_name: requiredString('Last name is required', MAX_NAME),
        suffix: optionalString(50),
        birth_date: dateOrEmpty.optional(),
        gender: enumFrom(['male', 'female', 'other'], 'Select a valid gender').optional(),
        civil_status: enumFrom(['single', 'married', 'widowed', 'separated'], 'Select a valid civil status').optional(),
        religion: optionalString(64),
        title: optionalString(50),
    }),
};

export const officerStepSchemas: z.ZodTypeAny[] = [
    z.object({
        first_name: requiredString('First name is required', MAX_NAME),
        mid_name: optionalString(MAX_NAME),
        last_name: requiredString('Last name is required', MAX_NAME),
        birth_date: dateOrEmpty.optional(),
        gender: enumFrom(['male', 'female', 'other'], 'Select a valid gender').optional(),
        civil_status: enumFrom(['single', 'married', 'widowed', 'separated'], 'Select a valid civil status').optional(),
        title: optionalString(50),
    }),
    z.object({
        email: z.string().trim().min(1, 'Email is required').max(MAX_EMAIL, 'Email is too long').email('Enter a valid email'),
        phone_number: optionalString(MAX_PHONE),
        street: optionalString(255),
        barangay: optionalString(100),
        city: optionalString(100),
        postal_code: optionalString(20),
    }),
    z.object({
        role: enumFrom(['admin', 'manager', 'accountant', 'secretary', 'loan'], 'Select a role'),
    }),
    z.object({
        password: passwordPolicy,
        password_confirmation: z.string(),
    }).refine(d => d.password === d.password_confirmation, {
        message: 'Passwords do not match',
        path: ['password_confirmation'],
    }),
];

export const officerEditSchema = z.object({
    first_name: requiredString('First name is required', MAX_NAME),
    mid_name: optionalString(MAX_NAME),
    last_name: requiredString('Last name is required', MAX_NAME),
    birth_date: dateOrEmpty.optional(),
    gender: enumFrom(['male', 'female', 'other'], 'Select a valid gender').optional(),
    civil_status: enumFrom(['single', 'married', 'widowed', 'separated'], 'Select a valid civil status').optional(),
    title: optionalString(50),
    email: z.string().trim().min(1, 'Email is required').max(MAX_EMAIL, 'Email is too long').email('Enter a valid email'),
    phone_number: z.string().trim().max(MAX_PHONE, `Phone number must be at most ${MAX_PHONE} digits`).regex(/^[0-9]+$/, 'Phone number must contain only digits').optional().or(z.literal('')),
    street: optionalString(255),
    barangay: optionalString(100),
    city: optionalString(100),
    postal_code: optionalString(20),
    role: enumFrom(['admin', 'manager', 'accountant', 'secretary', 'loan'], 'Select a role'),
    password: optionalPassword,
});

export const officerSchema = z.object({
    first_name: requiredString('First name is required', MAX_NAME),
    mid_name: optionalString(MAX_NAME),
    last_name: requiredString('Last name is required', MAX_NAME),
    birth_date: dateOrEmpty.optional(),
    gender: enumFrom(['male', 'female', 'other'], 'Select a valid gender').optional(),
    civil_status: enumFrom(['single', 'married', 'widowed', 'separated'], 'Select a valid civil status').optional(),
    title: optionalString(50),
    email: z.string().trim().min(1, 'Email is required').max(MAX_EMAIL, 'Email is too long').email('Enter a valid email'),
    phone_number: z.string().trim().min(1, 'Phone number is required').max(MAX_PHONE, `Phone number must be at most ${MAX_PHONE} digits`).regex(/^[0-9]+$/, 'Phone number must contain only digits').optional(),
    street: optionalString(255),
    barangay: optionalString(100),
    city: optionalString(100),
    postal_code: optionalString(20),
    role: enumFrom(['admin', 'manager', 'accountant', 'secretary', 'loan'], 'Select a role'),
    password: passwordPolicy,
    password_confirmation: z.string(),
}).refine(d => d.password === d.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
});

export const depositSchema = z.object({
    amount: amount(100),
    account_type: enumFrom(['savings', 'share-capital'], 'Select a valid account type'),
    transaction_mode: enumFrom(['counter', 'online'], 'Select a transaction mode'),
    note: optionalString(500),
});

export function withdrawalSchema(available: number) {
    return z.object({
        amount: z.coerce.number().positive('Enter a valid amount').min(100, 'Minimum withdrawal amount is ₱100').max(available, `Amount exceeds available balance of \u20b1${available.toLocaleString()}`),
        transaction_mode: enumFrom(['counter', 'online', 'bank_transfer'], 'Select a transaction mode'),
        reason: optionalString(500),
        bank_name: optionalString(100),
        account_number: optionalString(50),
        account_holder: optionalString(100),
    }).superRefine((d, ctx) => {
        if (d.transaction_mode === 'bank_transfer') {
            if (!d.bank_name) ctx.addIssue({ code: 'custom', path: ['bank_name'], message: 'Bank name is required for bank transfer' });
            if (!d.account_number) ctx.addIssue({ code: 'custom', path: ['account_number'], message: 'Account number is required for bank transfer' });
            if (!d.account_holder) ctx.addIssue({ code: 'custom', path: ['account_holder'], message: 'Account holder is required for bank transfer' });
        }
    });
}

export const loanApplicationSchema = z.object({
    loan_product_code: requiredString('Select a loan product'),
    amount: amount(1),
    term_months: z.coerce.number().int('Term must be a whole number').min(1, 'Term must be at least 1 month').max(360, 'Term cannot exceed 360 months'),
    payment_frequency: enumFrom(['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'], 'Select a payment frequency'),
    purpose: requiredString('Purpose is required', 500),
});

export const loanPaymentSchema = z.object({
    loan_id: requiredString('Select a loan'),
    account_id: requiredString('Select an account'),
    amount: amount(1),
    transaction_mode: enumFrom(['counter', 'online'], 'Select a transaction mode'),
});

export const loanEncodeSchema = z.object({
    user_id: z.coerce.number({ invalid_type_error: 'Select a member' }).min(1, 'Select a member'),
    loan_product: requiredString('Loan product is required'),
    loan_type: enumFrom(['group', 'business', 'special'], 'Select a valid loan type'),
    loan_status: enumFrom(['pending', 'approved', 'active', 'paid_off'], 'Select a valid loan status'),
    loan_amount: amount(1, 'Loan amount'),
    interest_rate: z.coerce.number().min(0, 'Interest rate cannot be negative').max(100, 'Interest rate cannot exceed 100%'),
    term_months: z.coerce.number().int('Term must be a whole number').min(1, 'Term must be at least 1 month').max(360, 'Term cannot exceed 360 months'),
    payment_frequency: enumFrom(['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'], 'Select a payment frequency'),
    applied_at: z.string().trim().min(1, 'Applied date is required').refine(v => !isNaN(Date.parse(v)), { message: 'Enter a valid date' }),
});

export const loanReviewSchema = z.object({
    action: enumFrom(['start_review', 'endorse', 'reject', 'interview_complete'], 'Select a review action'),
    notes: optionalString(1000),
    interview_schedule: dateOrEmpty.optional(),
});

export const transactionApprovalSchema = z.object({
    action: enumFrom(['approve', 'reject'], 'Select an action'),
    reason: optionalString(500),
}).superRefine((d, ctx) => {
    if (d.action === 'reject' && !d.reason?.trim()) {
        ctx.addIssue({ code: 'custom', path: ['reason'], message: 'Reason is required when rejecting' });
    }
});

export const ledgerSchema = z.object({
    transaction_type: enumFrom(['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'fee'], 'Select a transaction type'),
    amount: z.coerce.number().positive('Enter a valid amount').min(0.01, 'Amount must be at least 0.01'),
    note: optionalString(500),
    account_id: optionalString(50),
    loan_id: optionalString(50),
}).superRefine((d, ctx) => {
    if ((d.transaction_type === 'deposit' || d.transaction_type === 'withdrawal') && !d.account_id) {
        ctx.addIssue({ code: 'custom', path: ['account_id'], message: 'Account is required for this transaction type' });
    }
    if ((d.transaction_type === 'loan_payment' || d.transaction_type === 'loan_disbursement') && !d.loan_id) {
        ctx.addIssue({ code: 'custom', path: ['loan_id'], message: 'Loan is required for this transaction type' });
    }
});

export const announcementSchema = z.object({
    title: requiredString('Title is required'),
    content: z.string().trim().min(1, 'Content is required').max(5000, 'Content cannot exceed 5000 characters'),
    type: enumFrom(['news', 'announcement', 'event'], 'Select a valid type'),
    visibility: z.array(z.string()).min(1, 'Select at least one visibility'),
    category: optionalString(100),
    event_date: dateOrEmpty.optional(),
    event_time: optionalString(50),
    event_location: optionalString(255),
}).superRefine((d, ctx) => {
    if (d.type === 'event' && !d.event_date) {
        ctx.addIssue({ code: 'custom', path: ['event_date'], message: 'Event date is required for events' });
    }
});

export const roleSchema = z.object({
    name: requiredString('Role name is required', 60).regex(/^[a-zA-Z0-9 _-]+$/, 'Role name may only contain letters, numbers, spaces, dashes and underscores'),
});

export const registerSchema = z.object({
    first_name: requiredString('First name is required'),
    middle_name: optionalString(255),
    last_name: requiredString('Last name is required'),
    gender: enumFrom(['Male', 'Female', 'Other'], 'Select a gender'),
    birthdate: dateOrEmpty.refine(v => v !== '' && new Date(v) < new Date(), { message: 'Birthdate must be before today' }),
    age: z.coerce.number().int('Age must be a whole number').min(18, 'You must be at least 18 years old'),
    phone: requiredString('Phone number is required', 20),
    email: z.string().trim().email('Enter a valid email address').min(1, 'Email is required').max(255, 'Maximum of 255 characters'),
    region: z.string().trim().min(1, 'Select your region'),
    province: z.string().trim().min(1, 'Select your province'),
    city: z.string().trim().min(1, 'Select your city/municipality'),
    barangay: z.string().trim().min(1, 'Select your barangay'),
    street: requiredString('Street address is required'),
    zip_code: requiredString('ZIP code is required', 10),
    occupation: requiredString('Occupation is required'),
    monthly_income: requiredString('Select an income range'),
    membership_type: enumFrom(['Regular', 'Associate'], 'Select a membership type'),
    referred_by: optionalString(255),
    password: passwordPolicy,
    password_confirmation: requiredString('Please confirm your password'),
    terms: z.boolean().refine(v => v === true, { message: 'You must accept the terms and conditions' }),
}).refine(d => d.password === d.password_confirmation, { message: 'Passwords do not match', path: ['password_confirmation'] });

export const registerStepSchemas: Record<number, z.ZodTypeAny> = {
    0: z.object({
        first_name: requiredString('First name is required'),
        middle_name: optionalString(255),
        last_name: requiredString('Last name is required'),
        gender: enumFrom(['Male', 'Female', 'Other'], 'Select a gender'),
        birthdate: dateOrEmpty.refine(v => v !== '' && new Date(v) < new Date(), { message: 'Birthdate must be before today' }),
        age: z.coerce.number().int('Age must be a whole number').min(18, 'You must be at least 18 years old'),
    }),
    1: z.object({
        phone: requiredString('Phone number is required', 20),
        email: z.string().trim().email('Enter a valid email address').min(1, 'Email is required').max(255, 'Maximum of 255 characters'),
        region: z.string().trim().min(1, 'Select your region'),
        province: z.string().trim().min(1, 'Select your province'),
        city: z.string().trim().min(1, 'Select your city/municipality'),
        barangay: z.string().trim().min(1, 'Select your barangay'),
        street: requiredString('Street address is required'),
        zip_code: requiredString('ZIP code is required', 10),
    }),
    2: z.object({
        occupation: requiredString('Occupation is required'),
        monthly_income: requiredString('Select an income range'),
        membership_type: enumFrom(['Regular', 'Associate'], 'Select a membership type'),
        referred_by: optionalString(255),
    }),
    3: z.object({
        password: passwordPolicy,
        password_confirmation: requiredString('Please confirm your password'),
        terms: z.boolean().refine(v => v === true, { message: 'You must accept the terms and conditions' }),
    }).refine(d => d.password === d.password_confirmation, { message: 'Passwords do not match', path: ['password_confirmation'] }),
};

export const loanProductSchema = z.object({
    code: requiredString('Code is required', 50).regex(/^[a-zA-Z0-9_-]+$/, 'Code may only contain letters, numbers, dashes and underscores').min(2, 'Code must be at least 2 characters'),
    name: requiredString('Name is required', 120).min(3, 'Name must be at least 3 characters'),
    base_type: enumFrom(['group', 'business', 'special'], 'Select a valid base type'),
    icon: optionalString(50),
    interest_rate: z.coerce.number().min(0, 'Interest rate cannot be negative').max(100, 'Interest rate cannot exceed 100%'),
    max_amount: z.coerce.number().min(500, 'Max amount must be at least \u20b1500').max(5000000, 'Max amount cannot exceed \u20b15,000,000'),
    max_term: z.coerce.number().int('Max term must be a whole number').min(1, 'Max term must be at least 1 month').max(120, 'Max term cannot exceed 120 months'),
    minimum_share_capital: z.coerce.number().min(0, 'Cannot be negative').optional().or(z.literal('')),
    minimum_membership_years: z.coerce.number().int('Must be a whole number').min(0).max(50, 'Cannot exceed 50 years'),
    required_co_makers: z.coerce.number().int('Must be a whole number').min(0).max(20, 'Cannot exceed 20 co-makers'),
    sort_order: z.coerce.number().int('Must be a whole number').min(0).max(9999, 'Cannot exceed 9999'),
});

export const profileSchema = z.object({
    first_name: requiredString('First name is required', MAX_NAME),
    last_name: requiredString('Last name is required', MAX_NAME),
    email: z.string().trim().min(1, 'Email is required').max(MAX_EMAIL, 'Email is too long').email('Enter a valid email'),
    phone_number: optionalString(MAX_PHONE),
});

export const passwordChangeSchema = z.object({
    current_password: requiredString('Current password is required'),
    password: passwordPolicy,
    password_confirmation: z.string(),
    otp_code: z.string().trim().regex(/^\d{6}$/, 'Enter the 6-digit verification code'),
}).refine(d => d.password === d.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
});

export { passwordRegex };
