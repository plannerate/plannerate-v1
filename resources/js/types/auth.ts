export type User = {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type NotificationData = {
    title: string;
    message: string;
    notification_type: 'info' | 'success' | 'warning' | 'error';
    action_url?: string | null;
    download_url?: string | null;
    download_name?: string | null;
};

export type AppNotification = {
    id: string;
    read_at: string | null;
    data: NotificationData;
    created_at: string;
};

export type Auth = {
    user: User;
    notifications?: AppNotification[] | null;
    unread_count?: number;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
