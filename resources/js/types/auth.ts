export type User = {
    id: number;
    name: string;
    login?: string;
    email: string;
    is_active?: boolean;
    is_super_admin?: boolean;
    roles?: string[];
    permissions?: string[];
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
