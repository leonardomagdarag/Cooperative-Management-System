import { z } from 'zod';

export type FieldErrors = Record<string, string>;

const MAX_NAME = 100;
const MAX_EMAIL = 191;
const MIN_PHONE = 10;
const MAX_PHONE = 11;
const MAX_POSTAL = 4;
const MAX_GOV_ID = 64;
const MAX_TEXT = 255;
export const MIN_PERSON_AGE = 18;

/**
 * Character/format rules mirroring app/Support/PersonFieldRules.php. Keep the
 * two in sync -- the backend is the real gate, but the modals should not let
 * an operator reach submit with a value the server would reject.
 */
export const NAME_PATTERN = /^[\p{L}\p{M}\p{Zs}][\p{L}\p{M}\p{Zs}'’\u2013.-]*$/u;
export const TEXT_PATTERN = /^[\p{L}\p{N}\p{M}\p{Zs}.,'’&()\/#+\u2013-]+$/u;
export const PHONE_PATTERN = /^[0-9]{10,11}$/;
export const POSTAL_CODE_PATTERN = /^[0-9]{4}$/;
export const NATIONAL_ID_PATTERN = /^[0-9]{4}-?[0-9]{4}-?[0-9]{4}-?[0-9]{4}$/;
export const TIN_PATTERN = /^[0-9]{3}-?[0-9]{3}-?[0-9]{3}-?[0-9]{3}$/;
export const SSS_PATTERN = /^[0-9]{2}-?[0-9]{7}-?[0-9]$/;
export const PHILHEALTH_PATTERN = /^[0-9]{2}-?[0-9]{9}-?[0-9]$/;
export const LOCAL_ID_PATTERN = /^[A-Za-z0-9-]+$/;
export const URL_PATTERN = /^https?:\/\/[^\s/$.?#].[^\s]*$/i;

export const PERSON_TITLES = ['Chairman', 'Vice Chairman', 'Secretary', 'Treasurer', 'Loan Officer', 'Accountant', 'Member'];
export const EMPLOYMENT_TYPES = ['full-time', 'part-time', 'self-employed', 'freelancer', 'government', 'private', 'ofw', 'retired', 'unemployed', 'student', 'homemaker'];

const NAME_MSG = 'may only contain letters, spaces, apostrophes and hyphens';
const TEXT_MSG = 'may only contain letters, numbers and basic punctuation';
const PHONE_MSG = `must be ${MIN_PHONE}-${MAX_PHONE} digits using numbers only`;

const passwordRegex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{8,128}$/;

const stringMax = (max: number, msg?: string, pattern?: RegExp, patternMsg?: string) => {
    let s = z.string().max(max, msg ?? `Maximum of ${max} characters`);
    if (pattern) s = s.regex(pattern, patternMsg);
    return s.optional().or(z.literal(''));
};

const requiredString = (msg: string, max = 255) => z.string().trim().min(1, msg).max(max, `Maximum of ${max} characters`);

const optionalString = (max: number) => stringMax(max);

/** Personal names: letters only (plus spaces, apostrophes, hyphens, periods). No digits, no symbols. */
const nameString = (requiredMsg?: string, max = MAX_NAME) => {
    if (requiredMsg) return requiredString(requiredMsg, max).regex(NAME_PATTERN, `Name ${NAME_MSG}`);
    return stringMax(max, undefined, NAME_PATTERN, `Name ${NAME_MSG}`);
};

/** Free text: adds digits and the punctuation real addresses and job titles use. */
const textString = (max: number) => stringMax(max, undefined, TEXT_PATTERN, `Value ${TEXT_MSG}`);

const phoneString = (required: boolean) => {
    const base = required
        ? z.string().trim().min(1, 'Phone number is required')
        : z.string().trim();

    return base
        .max(MAX_PHONE, `Phone number must be at most ${MAX_PHONE} digits`)
        .refine(v => v === '' || PHONE_PATTERN.test(v), { message: `Phone number ${PHONE_MSG}` });
};

const govIdString = (pattern: RegExp, msg: string) =>
    stringMax(MAX_GOV_ID, undefined, pattern, msg);

const startOfToday = () => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
};

const parseDate = (value: string): Date | null => {
    const normalized = /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value;
    const ts = Date.parse(normalized);
    return Number.isNaN(ts) ? null : new Date(ts);
};

const ageOn = (dob: Date): number => {
    const now = new Date();
    let age = now.getFullYear() - dob.getFullYear();
    const monthDelta = now.getMonth() - dob.getMonth();
    if (monthDelta < 0 || (monthDelta === 0 && now.getDate() < dob.getDate())) age -= 1;
    return age;
};

/** Birth date must parse, be in the past, and put the person over the minimum age. */
const birthDateString = (requiredMsg?: string) => {
    const base = requiredMsg ? requiredString(requiredMsg) : dateOrEmpty.optional();

    return base
        .refine(v => v === '' || parseDate(v) !== null, { message: 'Enter a valid date' })
        .refine(v => v === '' || parseDate(v)!.getTime() < startOfToday(), { message: 'Birth date cannot be in the future' })
        .refine(v => v === '' || ageOn(parseDate(v)!) >= MIN_PERSON_AGE, { message: `Must be at least ${MIN_PERSON_AGE} years old` });
};

const amount = (min: number, label = 'Amount') =>
    z.coerce.number({ invalid_type_error: 'Enter a valid ' + label })
        .finite()
        .positive(`Enter a valid ${label}`)
        .min(min, `${label} must be at least ${min}`);

const dateOrEmpty = z.string().refine(v => v === '' || !isNaN(Date.parse(v)), { message: 'Enter a valid date' });

export const optionalId = z.coerce.number({ invalid_type_error: 'Select a valid option' }).int('Select a valid option').positive('Select a valid option').optional().or(z.literal(''));

export const enumFrom = (values: readonly string[], msg: string) =>
    z.string().refine(v => values.includes(v), { message: msg });

/** Strips characters the field cannot accept. Used by the modals while typing. */
export const onlyDigits = (value: string, max = MAX_PHONE) => value.replace(/\D/g, '').slice(0, max);

/** Inserts the dashes of a grouped Philippine ID (e.g. 1234567890123456 -> 1234-5678-9012-3456). */
export const formatGroupedDigits = (value: string, groups: number[]) => {
    const digits = value.replace(/\D/g, '');
    const total = groups.reduce((a, b) => a + b, 0);
    let out = '';
    let offset = 0;
    for (const size of groups) {
        if (offset > 0) out += '-';
        out += digits.slice(offset, offset + size);
        offset += size;
    }
    return out.slice(0, total + groups.length - 1);
};

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

const EMAIL_SCHEMA = z.string().trim().min(1, 'Email is required').max(MAX_EMAIL, 'Email is too long').email('Enter a valid email');

const GENDER_SCHEMA = enumFrom(['male', 'female', 'other'], 'Select a valid gender');

const CIVIL_STATUS_SCHEMA = enumFrom(['single', 'married', 'widowed', 'separated'], 'Select a valid civil status');

const TITLE_SCHEMA = enumFrom(PERSON_TITLES, 'Select a valid title');
const OPTIONAL_TITLE_SCHEMA = TITLE_SCHEMA.optional().or(z.literal(''));

const EMPLOYMENT_TYPE_SCHEMA = enumFrom(EMPLOYMENT_TYPES, 'Select a valid employment type');
const OPTIONAL_EMPLOYMENT_TYPE_SCHEMA = EMPLOYMENT_TYPE_SCHEMA.optional().or(z.literal(''));

const POSTAL_CODE_SCHEMA = stringMax(MAX_POSTAL, undefined, POSTAL_CODE_PATTERN, `Postal code must be ${MAX_POSTAL} digits`);

const FACEBOOK_LINK_SCHEMA = stringMax(MAX_TEXT, undefined, URL_PATTERN, 'Enter a valid link starting with http:// or https://');

const INCOME_SCHEMA = z.coerce.number().min(0, 'Monthly income cannot be negative').max(9999999999, 'Monthly income is too large').optional().or(z.literal(''));

const beneficiarySchema = z.object({
    first_name: nameString('First name is required'),
    last_name: nameString('Last name is required'),
    relationship: nameString('Relationship is required', 50),
});

export const memberSchema = z.object({
    first_name: nameString('First name is required'),
    mid_name: nameString(),
    last_name: nameString('Last name is required'),
    suffix: nameString(undefined, 50),
    birth_date: birthDateString('Birth date is required'),
    gender: GENDER_SCHEMA,
    civil_status: CIVIL_STATUS_SCHEMA,
    email: EMAIL_SCHEMA,
    phone_number: phoneString(true),
    password: passwordPolicy,
    password_confirmation: z.string(),
    monthly_income: INCOME_SCHEMA,
    branch_id: optionalId,
}).refine(d => d.password === d.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
});

export const memberStepSchemas: Record<string, z.ZodTypeAny> = {
    personal: z.object({
        first_name: nameString('First name is required'),
        mid_name: nameString(),
        last_name: nameString('Last name is required'),
        suffix: nameString(undefined, 50),
        birth_date: birthDateString('Birth date is required'),
        gender: GENDER_SCHEMA,
        civil_status: CIVIL_STATUS_SCHEMA,
        birthplace: textString(MAX_TEXT),
        religion: nameString(undefined, 64),
        title: OPTIONAL_TITLE_SCHEMA,
    }),
    address: z.object({
        country: textString(100),
        region: textString(100),
        province: textString(100),
        city: textString(100),
        barangay: textString(100),
        street: textString(MAX_TEXT),
        postal_code: POSTAL_CODE_SCHEMA,
    }),
    contact: z.object({
        email: EMAIL_SCHEMA,
        phone_number: phoneString(true),
        facebook_link: FACEBOOK_LINK_SCHEMA,
    }),
    ids: z.object({
        national_id: govIdString(NATIONAL_ID_PATTERN, 'National ID must be 16 digits, optionally grouped as XXXX-XXXX-XXXX-XXXX'),
        tin: govIdString(TIN_PATTERN, 'TIN must be 12 digits, optionally grouped as XXX-XXX-XXX-XXX'),
        sss: govIdString(SSS_PATTERN, 'SSS must be 10 digits, optionally grouped as XX-XXXXXXX-X'),
        philhealth: govIdString(PHILHEALTH_PATTERN, 'PhilHealth number must be 12 digits, optionally grouped as XX-XXXXXXXXX-X'),
        barangay_id: stringMax(MAX_GOV_ID, undefined, LOCAL_ID_PATTERN, 'Barangay ID may only contain letters, numbers and hyphens'),
    }),
    employment: z.object({
        employment_type: OPTIONAL_EMPLOYMENT_TYPE_SCHEMA,
        employer_name: textString(MAX_TEXT),
        position: textString(100),
        monthly_income: INCOME_SCHEMA,
        branch_id: optionalId,
    }),
    login: z.object({
        password: passwordPolicy,
        password_confirmation: z.string(),
    }).refine(d => d.password === d.password_confirmation, {
        message: 'Passwords do not match',
        path: ['password_confirmation'],
    }),
    beneficiaries: z.object({
        beneficiaries: z.array(beneficiarySchema).max(5, 'A member may have at most 5 beneficiaries'),
    }),
    agreement: z.object({
        agreement: z.boolean().refine(v => v === true, { message: 'You must accept the agreement to continue' }),
    }),
    review: z.object({}),
};

export const memberEditSchema = z.object({
    first_name: nameString('First name is required'),
    mid_name: nameString(),
    last_name: nameString('Last name is required'),
    suffix: nameString(undefined, 50),
    birth_date: birthDateString(),
    gender: GENDER_SCHEMA.optional(),
    civil_status: CIVIL_STATUS_SCHEMA.optional(),
    email: EMAIL_SCHEMA,
    phone_number: phoneString(false),
    facebook_link: FACEBOOK_LINK_SCHEMA,
    password: optionalPassword,
    branch_id: optionalId,
});

export const memberEditStepSchemas: Record<string, z.ZodTypeAny> = {
    ...memberStepSchemas,
    personal: z.object({
        first_name: nameString('First name is required'),
        mid_name: nameString(),
        last_name: nameString('Last name is required'),
        suffix: nameString(undefined, 50),
        birth_date: birthDateString(),
        gender: GENDER_SCHEMA.optional(),
        civil_status: CIVIL_STATUS_SCHEMA.optional(),
        birthplace: textString(MAX_TEXT),
        religion: nameString(undefined, 64),
        title: OPTIONAL_TITLE_SCHEMA,
    }),
};

export const officerStepSchemas: z.ZodTypeAny[] = [
    z.object({
        first_name: nameString('First name is required'),
        mid_name: nameString(),
        last_name: nameString('Last name is required'),
        birth_date: birthDateString(),
        gender: GENDER_SCHEMA.optional(),
        civil_status: CIVIL_STATUS_SCHEMA.optional(),
        title: OPTIONAL_TITLE_SCHEMA,
    }),
    z.object({
        email: EMAIL_SCHEMA,
        phone_number: phoneString(false),
        street: textString(MAX_TEXT),
        barangay: textString(100),
        city: textString(100),
        postal_code: POSTAL_CODE_SCHEMA,
    }),
    z.object({
        role: enumFrom(['admin', 'chairman', 'manager', 'accountant', 'secretary', 'loan'], 'Select a role'),
        branch_id: optionalId,
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
    first_name: nameString('First name is required'),
    mid_name: nameString(),
    last_name: nameString('Last name is required'),
    birth_date: birthDateString(),
    gender: GENDER_SCHEMA.optional(),
    civil_status: CIVIL_STATUS_SCHEMA.optional(),
    title: OPTIONAL_TITLE_SCHEMA,
    email: EMAIL_SCHEMA,
    phone_number: phoneString(false),
    street: textString(MAX_TEXT),
    barangay: textString(100),
    city: textString(100),
    postal_code: POSTAL_CODE_SCHEMA,
    role: enumFrom(['admin', 'chairman', 'manager', 'accountant', 'secretary', 'loan'], 'Select a role'),
    password: optionalPassword,
    branch_id: optionalId,
});

export const officerSchema = z.object({
    first_name: nameString('First name is required'),
    mid_name: nameString(),
    last_name: nameString('Last name is required'),
    birth_date: birthDateString(),
    gender: GENDER_SCHEMA.optional(),
    civil_status: CIVIL_STATUS_SCHEMA.optional(),
    title: OPTIONAL_TITLE_SCHEMA,
    email: EMAIL_SCHEMA,
    phone_number: phoneString(true),
    street: textString(MAX_TEXT),
    barangay: textString(100),
    city: textString(100),
    postal_code: POSTAL_CODE_SCHEMA,
    role: enumFrom(['admin', 'chairman', 'manager', 'accountant', 'secretary', 'loan'], 'Select a role'),
    password: passwordPolicy,
    password_confirmation: z.string(),
    branch_id: optionalId,
}).refine(d => d.password === d.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
});

export const depositSchema = z.object({
    amount: amount(100),
    account_type: enumFrom(['savings', 'share-capital'], 'Select a valid account type'),
    transaction_mode: enumFrom(['counter', 'online', 'bank_transfer'], 'Select a transaction mode'),
    note: optionalString(500),
    branch_id: optionalId,
});

function withdrawalAmountField(available: number, min: number, max: number) {
    const configuredMax = max > 0 ? Math.min(available, max) : available;
    const maxMessage = max > 0 && max <= available
        ? `Amount exceeds the maximum withdrawal limit of \u20b1${max.toLocaleString()}`
        : `Amount exceeds available balance of \u20b1${available.toLocaleString()}`;

    return z.coerce
        .number()
        .positive('Enter a valid amount')
        .min(min, `Minimum withdrawal amount is \u20b1${min.toLocaleString()}`)
        .max(configuredMax, maxMessage);
}

export function withdrawalSchema(available: number, min = 100, max = 0) {
    return z.object({
        amount: withdrawalAmountField(available, min, max),
        transaction_mode: enumFrom(['counter', 'online', 'bank_transfer'], 'Select a transaction mode'),
        reason: optionalString(500),
        bank_name: optionalString(100),
        account_number: optionalString(50),
        account_holder: optionalString(100),
        branch_id: optionalId,
    }).superRefine((d, ctx) => {
        if (d.transaction_mode === 'bank_transfer') {
            if (!d.bank_name) ctx.addIssue({ code: 'custom', path: ['bank_name'], message: 'Bank name is required for bank transfer' });
            if (!d.account_number) ctx.addIssue({ code: 'custom', path: ['account_number'], message: 'Account number is required for bank transfer' });
            if (!d.account_holder) ctx.addIssue({ code: 'custom', path: ['account_holder'], message: 'Account holder is required for bank transfer' });
        }
    });
}

/** Base object schema without superRefine – use .pick() for step-specific validation */
export function withdrawalBaseSchema(available: number, min = 100, max = 0) {
    return z.object({
        amount: withdrawalAmountField(available, min, max),
        transaction_mode: enumFrom(['counter', 'online', 'bank_transfer'], 'Select a transaction mode'),
        reason: optionalString(500),
        bank_name: optionalString(100),
        account_number: optionalString(50),
        account_holder: optionalString(100),
        branch_id: optionalId,
    });
}

export const loanApplicationSchema = z.object({
    loan_product_code: requiredString('Select a loan product'),
    amount: amount(1),
    term_months: z.coerce.number().int('Term must be a whole number').min(1, 'Term must be at least 1 month').max(360, 'Term cannot exceed 360 months'),
    payment_frequency: enumFrom(['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'], 'Select a payment frequency'),
    purpose: requiredString('Purpose is required', 500),
    branch_id: optionalId,
});

/** Step-2-only schema (product, amount, term, frequency) — excludes purpose which is entered on step 3 */
export const loanApplicationStep2Schema = z.object({
    loan_product_code: requiredString('Select a loan product'),
    amount: amount(1),
    term_months: z.coerce.number().int('Term must be a whole number').min(1, 'Term must be at least 1 month').max(360, 'Term cannot exceed 360 months'),
    payment_frequency: enumFrom(['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'], 'Select a payment frequency'),
});

export const loanPaymentSchema = z.object({
    loan_id: requiredString('Select a loan'),
    account_id: requiredString('Select an account'),
    amount: amount(1),
    transaction_mode: enumFrom(['counter', 'online', 'bank_transfer'], 'Select a transaction mode'),
    branch_id: optionalId,
});

export const loanEncodeSchema = z.object({
    user_id: z.coerce.number({ invalid_type_error: 'Select a member' }).min(1, 'Select a member'),
    loan_product: requiredString('Loan product is required'),
    loan_type: enumFrom(['group', 'business', 'special'], 'Select a valid loan type'),
    loan_status: enumFrom(['pending'], 'Loans must start in the pending state'),
    loan_amount: amount(1, 'Loan amount'),
    interest_rate: z.coerce.number().min(0, 'Interest rate cannot be negative').max(100, 'Interest rate cannot exceed 100%'),
    term_months: z.coerce.number().int('Term must be a whole number').min(1, 'Term must be at least 1 month').max(360, 'Term cannot exceed 360 months'),
    payment_frequency: enumFrom(['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'], 'Select a payment frequency'),
    applied_at: z.string().trim().min(1, 'Applied date is required').refine(v => !isNaN(Date.parse(v)), { message: 'Enter a valid date' }),
    branch_id: optionalId,
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
    account_id: z.union([z.string(), z.number()]).optional().transform(v => v != null ? String(v) : undefined),
    loan_id: z.union([z.string(), z.number()]).optional().transform(v => v != null ? String(v) : undefined),
    branch_id: optionalId,
}).superRefine((d, ctx) => {
    if ((d.transaction_type === 'deposit' || d.transaction_type === 'withdrawal') && !d.account_id) {
        ctx.addIssue({ code: 'custom', path: ['account_id'], message: 'Account is required for this transaction type' });
    }
    if ((d.transaction_type === 'loan_payment' || d.transaction_type === 'loan_disbursement') && !d.loan_id) {
        ctx.addIssue({ code: 'custom', path: ['loan_id'], message: 'Loan is required for this transaction type' });
    }
});

export const expenseSchema = z.object({
    category: requiredString('Category is required', 100),
    description: optionalString(255),
    amount: z.coerce.number({ invalid_type_error: 'Enter a valid amount' }).finite('Enter a valid amount').positive('Amount must be greater than 0').min(0.01, 'Amount must be at least 0.01'),
    expense_date: requiredString('Date is required').refine(v => !isNaN(Date.parse(v)), { message: 'Enter a valid date' }),
    branch_id: optionalId,
});

export const loanEditSchema = z.object({
    loan_type: enumFrom(['business', 'group', 'special'], 'Select a valid loan type'),
    loan_amount: z.coerce.number({ invalid_type_error: 'Enter a valid amount' }).finite('Enter a valid amount').positive('Amount must be greater than 0'),
    interest_rate: z.coerce.number({ invalid_type_error: 'Enter a valid interest rate' }).min(0, 'Interest rate cannot be negative').max(100, 'Interest rate cannot exceed 100%'),
    term_months: z.coerce.number().int('Term must be a whole number').min(1, 'Term must be at least 1 month').max(360, 'Term cannot exceed 360 months'),
    payment_frequency: enumFrom(['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'], 'Select a payment frequency'),
    due_date: dateOrEmpty.optional(),
    branch_id: optionalId,
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
    province: optionalString(100),
    city: z.string().trim().min(1, 'Select your city/municipality'),
    barangay: z.string().trim().min(1, 'Select your barangay'),
    street: requiredString('Street address is required'),
    zip_code: requiredString('ZIP code is required', 10),
    occupation: requiredString('Occupation is required'),
    monthly_income: requiredString('Select an income range'),
    membership_type: enumFrom(['Regular', 'Associate'], 'Select a membership type'),
    referred_by: optionalString(255),
    branch_id: optionalId,
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
        province: optionalString(100),
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
        branch_id: optionalId,
    }),
    3: z.object({
        password: passwordPolicy,
        password_confirmation: requiredString('Please confirm your password'),
        terms: z.boolean().refine(v => v === true, { message: 'You must accept the terms and conditions' }),
        privacy_acknowledged: z.boolean().refine(v => v === true, { message: 'Please acknowledge the Data Privacy Policy' }),
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
    interest_extension_penalty: z.boolean(),
    minimum_share_capital: z.coerce.number().min(0, 'Cannot be negative').optional().or(z.literal('')),
    minimum_membership_years: z.coerce.number().int('Must be a whole number').min(0).max(50, 'Cannot exceed 50 years'),
    required_co_makers: z.coerce.number().int('Must be a whole number').min(0).max(20, 'Cannot exceed 20 co-makers'),
    sort_order: z.coerce.number().int('Must be a whole number').min(0).max(9999, 'Cannot exceed 9999'),
}).superRefine((product, ctx) => {
    if (product.penalty_type === 'percent' && product.penalty_amount > 100) {
        ctx.addIssue({ code: 'custom', path: ['penalty_amount'], message: 'Percentage penalty cannot exceed 100%' });
    }
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
