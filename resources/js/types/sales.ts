export type SaleStatus =
    | 'draft'
    | 'open'
    | 'ready_to_bill'
    | 'finalized'
    | 'cancelled'
    | 'adjusted';

export type SaleCategoryType = 'service' | 'product' | 'mixed';
export type UniquenessScope = 'customer' | 'appointment' | 'reference' | 'none';

export type SaleCategoryOption = {
    id: string;
    name: string;
    type: SaleCategoryType;
    uniqueness_scope: UniquenessScope;
    is_active?: boolean;
    key?: string;
};

export type CustomerOption = {
    id: string;
    name: string;
    phone?: string | null;
};

export type ServiceOption = {
    id: string;
    name: string;
    price_cents: number;
    duration_minutes: number;
};

export type ProductOption = {
    id: string;
    name: string;
    price_cents: number;
    sale_price_cents?: number;
    current_stock: number;
};

export type ProfessionalOption = {
    id: string;
    name: string;
};

export type SaleItem = {
    id: string;
    tenant_id: string;
    unit_id: string;
    sale_id: string;
    item_type: 'service' | 'product' | 'custom';
    service_id: string | null;
    product_id: string | null;
    professional_id: string | null;
    name_snapshot: string;
    unit_price_cents: number;
    quantity: number;
    discount_cents: number;
    total_cents: number;
    service?: { id: string; name: string; duration_minutes?: number } | null;
    product?: { id: string; name: string } | null;
    professional?: { id: string; name: string } | null;
};

export type SaleStatusHistory = {
    id: string;
    sale_id: string;
    from_status: string | null;
    to_status: string;
    user_id: string | null;
    reason: string | null;
    created_at: string;
    user?: { id: string; name: string } | null;
};

export type AppointmentSaleLinkSummary = {
    id: string;
    appointment_id: string;
    appointment?: {
        id: string;
        starts_at: string;
        ends_at: string;
        status: string;
    } | null;
};

export type Sale = {
    id: string;
    tenant_id: string;
    unit_id: string;
    customer_id: string | null;
    sale_category_id: string;
    category_key_snapshot: string | null;
    category_name_snapshot: string | null;
    reference_label: string | null;
    open_context_key: string | null;
    status: SaleStatus;
    currency: string;
    total_amount_cents: number;
    discount_amount_cents: number;
    final_amount_cents: number;
    notes: string | null;
    lock_version: number;
    created_at: string;
    updated_at: string;
    customer?: CustomerOption | null;
    category?: SaleCategoryOption | null;
    appointment_link?: AppointmentSaleLinkSummary | null;
    items?: SaleItem[];
    status_histories?: SaleStatusHistory[];
    closing_sessions?: ClosingSession[];
};

export type SaleMetrics = {
    open_count: number;
    ready_count: number;
    today_total_cents: number;
};

export type ClosingSessionStatus =
    | 'draft'
    | 'ready'
    | 'processing'
    | 'completed'
    | 'cancelled'
    | 'failed';

export type ReceiptPayload = {
    receipt_number: string;
    issued_at: string;
    tenant: {
        id: string;
        name: string;
        slug: string;
    };
    unit: {
        id: string;
        name: string;
        timezone?: string;
    };
    closed_by: {
        id: string;
        name: string;
        email?: string;
    };
    closing_subject: string;
    customer?: {
        id: string;
        name: string;
        phone?: string | null;
        email?: string | null;
    } | null;
    currency: string;
    totals: {
        total_gross_cents: number;
        total_discount_cents: number;
        final_total_cents: number;
        sales_count: number;
    };
    sales: Array<{
        id: string;
        category_name: string;
        reference_label?: string | null;
        total_amount_cents: number;
        discount_amount_cents: number;
        final_amount_cents: number;
        items: Array<{
            id: string;
            item_type: 'service' | 'product' | 'custom';
            name: string;
            quantity: number;
            unit_price_cents: number;
            discount_cents: number;
            total_cents: number;
            professional_name?: string | null;
        }>;
    }>;
    notes?: string | null;
};

export type ClosingSession = {
    id: string;
    tenant_id: string;
    unit_id: string;
    closing_subject: string;
    currency: string;
    expected_total_cents: number;
    final_total_cents: number;
    status: ClosingSessionStatus;
    receipt_number: string | null;
    receipt_payload: ReceiptPayload | null;
    idempotency_key: string | null;
    closed_by_user_id: string | null;
    lock_version: number;
    created_at: string;
    updated_at: string;
    closed_by?: { id: string; name: string; email?: string } | null;
    sales?: Sale[];
    unit?: { id: string; name: string; timezone?: string } | null;
    tenant?: { id: string; name: string; slug?: string } | null;
};
