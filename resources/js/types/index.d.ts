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
    order_pay?: number;
    order_items?: number;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
    flash: Flash;
};
