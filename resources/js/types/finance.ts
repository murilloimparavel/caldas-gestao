export type FinancialObligationType = 'payable' | 'receivable';
export type FinancialObligationStatus = 'pending' | 'paid' | 'cancelled';

export interface FinancialObligation {
    id: string;
    tenant_id: string;
    unit_id: string;
    type: FinancialObligationType;
    category_id: string | null;
    supplier_id: string | null;
    customer_id: string | null;
    description: string;
    amount_cents: number;
    due_date: string;
    paid_date: string | null;
    status: FinancialObligationStatus;
    payment_method: string | null;
    notes: string | null;
    lock_version: number;
    created_at: string;
    updated_at: string;
    category?: { id: string; name: string } | null;
    supplier?: { id: string; name: string } | null;
    customer?: { id: string; name: string } | null;
}

export interface FinanceDashboardMetrics {
    current_cash_balance_cents: number;
    projected_balance_cents: number;
    payable_today_cents: number;
    receivable_today_cents: number;
    payable_month_pending_cents: number;
    receivable_month_pending_cents: number;
    paid_month_cents: number;
    received_month_cents: number;
    overdue_count: number;
    overdue_amount_cents: number;
}
