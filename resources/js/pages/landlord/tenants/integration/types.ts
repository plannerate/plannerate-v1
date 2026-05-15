export type KeyValueRow = {
    key: string;
    value: string;
    enabled: boolean;
};

export type ApiPath = {
    key: string;
    path: string;
};

export type IntegrationPayload = {
    id: string;
    integration_type: string;
    api_url: string;
    auth_type: string;
    auth_bearer_mode: string;
    auth_token: string;
    auth_username: string;
    auth_password: string;
    auth_token_username: string;
    auth_token_password: string;
    auth_token_method: string;
    auth_token_path: string;
    auth_token_response_path: string;
    auth_token_username_field: string;
    auth_token_password_field: string;
    auth_token_headers: KeyValueRow[];
    auth_token_params: KeyValueRow[];
    auth_token_body: KeyValueRow[];
    is_active: boolean;
    last_sync: string | null;
    connection_headers: KeyValueRow[];
    connection_params: KeyValueRow[];
    connection_body: KeyValueRow[];
    api_paths: ApiPath[];
    api_method: string;
};

export type IntegrationTypeOption = {
    value: string;
    label: string;
};

export type IntegrationTestResult = {
    ok: boolean;
    message?: string;
    meta?: Record<string, unknown>;
    data?: unknown;
};
