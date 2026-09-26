export type CommissionRuleType = 'percentage' | 'fixed';
export type CommissionRuleScope =
    'all' | 'service' | 'product' | 'service_category' | 'product_category';
export type CommissionAccrualStatus = 'accrued' | 'settled' | 'cancelled';

export type CommissionRule = {
    id: string;
    tenant_id: string;
    unit_id: string;
    professional_id: string | null;
    service_id: string | null;
    product_id: string | null;
    category_id: string | null;
    scope: CommissionRuleScope;
    type: CommissionRuleType;
    value_rate: number;
    is_active: boolean;
    lock_version: number;
    created_at: string;
    updated_at: string;
    professional?: { id: string; name: string } | null;
    service?: { id: string; name: string } | null;
    product?: { id: string; name: string } | null;
    category?: { id: string; name: string; type?: string | null } | null;
};

export type CommissionAccrual = {
    id: string;
    tenant_id: string;
    unit_id: string;
    professional_id: string;
    sale_id: string;
    sale_item_id: string;
    item_name_snapshot: string;
    gross_amount_cents: number;
    rate_type: CommissionRuleType;
    rate_value: number;
    commission_amount_cents: number;
    status: CommissionAccrualStatus;
    settled_at: string | null;
    settlement_id: string | null;
    lock_version: number;
    created_at: string;
    updated_at: string;
    sale?: {
        id: string;
        reference_label?: string | null;
        status?: string;
    } | null;
    settlement?: {
        id: string;
        paid_at: string;
    } | null;
};

export type CommissionSettlement = {
    id: string;
    tenant_id: string;
    unit_id: string;
    professional_id: string;
    total_amount_cents: number;
    period_start: string | null;
    period_end: string | null;
    paid_at: string;
    user_id: string;
    notes: string | null;
    lock_version: number;
    created_at: string;
    updated_at: string;
    user?: {
        id: string;
        name: string;
        email: string;
    } | null;
    professional?: {
        id: string;
        name: string;
    } | null;
};

export type ProfessionalCommissionSummary = {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    pending_amount_cents: number;
    settled_amount_cents: number;
    pending_count: number;
};
