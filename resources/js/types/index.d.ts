export interface User {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    email_verified_at?: string;
}

export interface Flash {
    order_code?: string;
    order_name?: string;
    order_phone?: string;
    order_pay?: number;
    order_discount?: number;
    order_items?: number;
    success?: string;
    otp_sent?: boolean;
    otp_email?: string;
}

export interface Referral {
    code: string;
    referrer_name: string | null;
}

export interface EmailBonus {
    enabled: boolean;
    type: 'fixed' | 'percent';
    value: number;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
    flash: Flash;
    referral: Referral | null;
    emailBonus: EmailBonus;
    pending_reviews?: number | null;
    pending_orders?: number | null;
};
