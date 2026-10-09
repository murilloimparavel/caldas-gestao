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
    source_type: 'sale_item' | 'package_service' | null;
    service_id: string | null;
    package_template_id: string | null;
    customer_package_id: string | null;
    item_name_snapshot: string;
    service_name_snapshot: string | null;
    package_name_snapshot: string | null;
    gross_amount_cents: number;
    quantity: number | null;
    covered_quantity: number | null;
    commissionable_quantity: number | null;
    rate_type: CommissionRuleType;
    rate_value: number;
    commission_amount_cents: number;
    status: CommissionAccrualStatus;
    settled_at: string | null;
    settlement_id: string | null;
    lock_version: number;
    created_at: string;
    updated_at: string;
    service?: { id: string; name: string } | null;
    package_template?: { id: string; name: string } | null;
    customer_package?: { id: string; name_snapshot: string | null } | null;
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

export type PackageCommissionSummary = {
    id: string;
    name: string;
    service_count: number;
    gross_amount_cents: number;
    commission_amount_cents: number;
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
