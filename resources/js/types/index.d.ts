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
    order_payment_option?: 'full' | 'deposit';
    order_prepay?: number;
    order_cod?: number;
    success?: string;
    otp_sent?: boolean;
    otp_email?: string;
}

export interface Referral {
    code: string;
    referrer_name: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
    flash: Flash;
    referral: Referral | null;
    pending_reviews?: number | null;
};
