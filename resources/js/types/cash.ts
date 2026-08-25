export type CashShiftStatus = 'open' | 'closed';

export type CashMovementType =
    | 'supply'
    | 'bleed'
    | 'sale_inflow'
    | 'commission_outflow'
    | 'expense_outflow';

export type CashMovement = {
    id: string;
    tenant_id: string;
    unit_id: string;
    cash_shift_id: string;
    type: CashMovementType;
    amount_cents: number;
    reason: string;
    reference_type?: string | null;
    reference_id?: string | null;
    user_id: string;
    created_at: string;
    updated_at?: string;
    user?: { id: string; name: string; email?: string } | null;
};

export type CashShift = {
    id: string;
    tenant_id: string;
    unit_id: string;
    opened_by_user_id: string;
    closed_by_user_id?: string | null;
    initial_amount_cents: number;
    expected_amount_cents: number;
    final_amount_cents?: number | null;
    difference_cents?: number | null;
    status: CashShiftStatus;
    opened_at: string;
    closed_at?: string | null;
    notes?: string | null;
    lock_version: number;
    created_at?: string;
    updated_at?: string;
    opened_by?: { id: string; name: string; email?: string } | null;
    closed_by?: { id: string; name: string; email?: string } | null;
    movements?: CashMovement[];
};

export type CashMetrics = {
    open_shifts_count: number;
    closed_today_count: number;
};
