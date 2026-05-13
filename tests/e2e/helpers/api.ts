import { expect, type APIRequestContext } from '@playwright/test';

export type ApiSession = {
    token: string;
    user: {
        id: number;
        email: string;
        role: {
            slug: string;
        };
    };
};

type JsonOptions = {
    method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
    data?: unknown;
    params?: Record<string, string | number | boolean | undefined>;
    expectedStatus?: number;
};

function authHeaders(token: string): Record<string, string> {
    return {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
    };
}

/**
 * Send one authenticated JSON request and return the decoded payload.
 */
export async function apiJson<T>(
    request: APIRequestContext,
    token: string,
    path: string,
    options: JsonOptions = {},
): Promise<T> {
    const method = options.method ?? 'GET';
    const response = await request.fetch(path, {
        method,
        headers: authHeaders(token),
        data: options.data,
        params: options.params,
    });

    expect(response.status()).toBe(options.expectedStatus ?? 200);

    return (await response.json()) as T;
}

/**
 * Send one authenticated request and return the raw response when a test needs status assertions.
 */
export async function apiResponse(
    request: APIRequestContext,
    token: string,
    path: string,
    options: JsonOptions = {},
) {
    return request.fetch(path, {
        method: options.method ?? 'GET',
        headers: authHeaders(token),
        data: options.data,
        params: options.params,
    });
}
