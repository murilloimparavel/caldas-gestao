export type BookingItem = {
    id: string;
    name: string;
    status: 'active' | 'inactive';
    online_booking_enabled: boolean;
    lock_version: number;
    description?: string | null;
    duration_minutes?: number;
    price_cents?: number;
    image_url?: string | null;
    avatar_url?: string | null;
};

export type Readiness = {
    unit_enabled: boolean;
    has_active_service: boolean;
    has_active_professional: boolean;
    has_service_professional_pair: boolean;
    publishable: boolean;
};

export type BookingAppearance = {
    brand_name?: string | null;
    headline?: string | null;
    subheadline?: string | null;
    primary_color?: string | null;
    background_color?: string | null;
    cta_label?: string | null;
    font_style?: 'editorial' | 'montserrat' | 'modern' | null;
};

export type PublicSettings = {
    appearance?: BookingAppearance | null;
    template_key?: 'essential' | 'atelier-barber' | string | null;
    public_domain_id?: string | null;
    description?: string | null;
    logo_url?: string | null;
    logo_image_url?: string | null;
    cover_url?: string | null;
    cover_image_url?: string | null;
    whatsapp?: string | null;
    phone?: string | null;
    instagram?: string | null;
    facebook?: string | null;
    website?: string | null;
    brand_color?: string | null;
    accent_color?: string | null;
    booking_flow?: 'service_first' | 'professional_first';
    flow?: 'service_first' | 'professional_first';
    minimum_notice_minutes?: number | null;
    public_slug?: string | null;
    public_hours?: Record<
        string,
        { enabled?: boolean; starts_at?: string; ends_at?: string }
    > | null;
};

export type TabKey =
    | 'details'
    | 'settings'
    | 'link'
    | 'gallery'
    | 'services'
    | 'hours'
    | 'publication';

export type PublicationHistoryItem = {
    id: string;
    version: number;
    source_revision: number;
    published_at?: string | null;
    superseded_at?: string | null;
    published_by?: { name?: string | null } | null;
    preview_url?: string | null;
};

export type OnlineBookingProps = {
    template_key?: 'essential' | 'atelier-barber' | string | null;
    unit: {
        id: string;
        name: string;
        slug: string;
        online_booking_enabled: boolean;
        lock_version: number;
        address?: string | Record<string, string> | null;
        logo_image_url?: string | null;
        settings?: PublicSettings | null;
    };
    publicUrl: string | null;
    previewUrl?: string | null;
    publicDomains: { id: string; hostname: string }[];
    services: BookingItem[];
    professionals: BookingItem[];
    readiness: Readiness;
    publication?: {
        id: string;
        status: 'unpublished' | 'published';
        draft_revision: number;
        published_at?: string | null;
        unpublished_at?: string | null;
        lock_version: number;
    } | null;
    draft?: {
        revision: number;
        content?: {
            schema_version?: number;
            sections?: { key: string; enabled: boolean }[];
            appearance?: BookingAppearance | null;
        } | null;
    } | null;
    activePublication?: {
        id: string;
        version: number;
        source_revision: number;
        published_at?: string | null;
        template_key: string;
    } | null;
    publicationHistory?: PublicationHistoryItem[];
    draftDiff?: string[];
    settings?: PublicSettings | null;
    cover?: string | null;
    coverUploadUrl?: string | null;
    coverDeleteUrl?: string | null;
    gallery?: {
        id: string;
        url?: string | null;
        path?: string | null;
        alt?: string | null;
        alt_text?: string | null;
        position?: number;
    }[];
};
