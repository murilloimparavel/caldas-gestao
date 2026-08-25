export type User = {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
};

export type Auth = {
    user: User | null;
    permissions: string[];
    entitlements: Entitlement[];
};

export type Entitlement = {
    id: string;
    key: string;
    status: 'trial' | 'active' | 'grace' | 'suspended' | 'expired' | 'revoked';
    quantity: number | null;
    source: 'plan' | 'trial' | 'manual' | 'integration';
    starts_at: string;
    ends_at: string | null;
    config: Record<string, unknown>;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
