export type AppProps = {
    [key: string]: unknown;
    auth: null | {
        user: { id: number; name: string; email: string };
        roles: string[];
        permissions: string[];
    };
    flash: { success?: string };
    configuration: {
        name: string;
        real_data_approved: boolean;
        clinical_rules: boolean;
        ai_enabled: boolean;
    };
};
export type Pagination<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export type Resident = {
    id: number;
    registration_number: string;
    full_name: string;
    gender: 'L' | 'P';
    birth_date: string | null;
    is_birth_date_estimated: boolean;
    residency_status: string;
    revision: number;
    address?: string;
    rt?: string;
    rw?: string;
    household_id?: number | null;
    guardian_id?: number | null;
    family_relationship?: string | null;
};
export type Period = {
    id: number;
    month: number;
    year: number;
    reference_date: string;
    status: string;
    revision: number;
};
export type Target = {
    id: number;
    resident: Resident;
    category_code: string;
    attendance_status: string;
    is_excluded: boolean;
    visits_count: number;
    age_in_years_snapshot: number | null;
    selection_reason: string;
};
export type CalculationResult = {
    title: string;
    status: 'calculated' | 'unavailable';
    category: string | null;
    label: string;
    mapped_value: string | null;
    value: number | null;
    reason?: string;
    source: null | { title: string; url: string; section: string };
    previous?: { visit_id: number; date: string; weight_kg: string };
    minimum_gain_g?: number;
};
export type CalculationDetails = {
    mode: 'automatic' | 'manual';
    version?: string;
    reference_date?: string;
    age_months?: number | null;
    inputs: Record<string, string | number | boolean | null>;
    results?: Record<string, CalculationResult>;
    corrected_height_cm?: number | null;
    field_values?: Record<string, string>;
    reviewed_by?: number;
    reviewed_at?: string;
    gender?: 'L' | 'P';
    override_reason?: string | null;
};
export type Examination = {
    [key: string]: string | null | CalculationDetails;
    weight_state: string;
    height_state: string;
    blood_pressure_state: string;
    glucose_state: string;
    calculation_details: CalculationDetails | null;
};
export type Visit = {
    id: number;
    activity_session_id?: number | null;
    visited_at: string;
    record_status: string;
    examination: Examination;
    referral?: {
        destination: string;
        reason: string;
        follow_up_date: string | null;
        follow_up_status: string;
        follow_up_note: string | null;
    } | null;
};
export type Metrics = {
    targets: number;
    present: number;
    absent: number;
    unconfirmed: number;
    attendance_coverage: number | null;
    finalized_visits: number;
    draft_visits: number;
    weight_measured: number;
    blood_sugar_measured: number;
};
export const statusLabels: Record<string, string> = {
    active: 'Aktif',
    moved: 'Pindah',
    deceased: 'Meninggal',
    inactive: 'Diarsipkan',
    open: 'Terbuka',
    submitted: 'Diajukan',
    returned: 'Dikembalikan',
    locked: 'Dikunci',
    unconfirmed: 'Belum dikonfirmasi',
    present: 'Hadir',
    absent: 'Tidak hadir',
    draft: 'Draft',
    finalized: 'Final administratif',
    not_examined: 'Tidak diperiksa',
    measured: 'Diperiksa',
    unknown: 'Belum diketahui',
    not_applicable: 'Tidak berlaku',
    declined: 'Menolak',
};
export function periodLabel(period: Pick<Period, 'year' | 'month'>): string {
    return new Intl.DateTimeFormat('id-ID', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(period.year, period.month - 1, 1)));
}
export function localDateTime(date: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(date));
}
