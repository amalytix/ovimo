export type SocialIntegration = {
    id: number;
    platform: string;
    platform_user_id: string;
    platform_username: string | null;
    profile_data?: Record<string, unknown> | null;
    scopes?: string[] | null;
    is_active: boolean;
    created_at?: string | null;
    token_expires_at?: string | null;
};
