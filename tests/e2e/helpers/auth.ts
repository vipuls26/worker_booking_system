import { expect, type APIRequestContext, type Page } from '@playwright/test';
import { testUsers, type TestUser } from './users';

type LoginResult = {
    token: string;
    user: {
        id: number;
        email: string;
        role: {
            slug: string;
        };
    };
};

type LoginOptions = {
    expectedPath?: string;
};

type CredentialLoginOptions = LoginOptions & {
    email: string;
    password: string;
};

/**
 * Sign in through the real login form so browser tests match the live user flow.
 */
export async function login(page: Page, user: TestUser = testUsers.customer, options: LoginOptions = {}): Promise<void> {
    await loginWithCredentials(page, {
        email: user.email,
        password: user.password,
        expectedPath: options.expectedPath ?? user.dashboardPath,
    });
}

/**
 * Sign in through the real login form with explicit credentials.
 */
export async function loginWithCredentials(page: Page, options: CredentialLoginOptions): Promise<void> {
    if (!options.email) {
        throw new Error('The login helper did not receive an email address.');
    }

    if (!options.password) {
        throw new Error('The login helper did not receive a password.');
    }

    await page.goto('/login');
    await page.getByTestId('login-email').fill(options.email);
    await page.getByTestId('login-password').fill(options.password);
    await page.getByTestId('login-submit').click();

    const expectedPath = options.expectedPath;
    await expect(page).toHaveURL(new RegExp(`${expectedPath.replaceAll('/', '\\/')}$`));
}

/**
 * Sign out from the shared dashboard toolbar.
 */
export async function logout(page: Page): Promise<void> {
    const logoutButton = page.getByTestId('dashboard-logout-button').or(page.getByTestId('admin-logout-button'));

    await logoutButton.click();
    await expect(page).toHaveURL(/\/login$/);
}

/**
 * Read the current bearer token from browser storage for mixed UI and API tests.
 */
export async function getAuthToken(page: Page): Promise<string> {
    const token = await page.evaluate(() => localStorage.getItem('auth_token'));

    if (!token) {
        throw new Error('No auth token found in localStorage.');
    }

    return token;
}

/**
 * Log in through the API so backend-heavy E2E checks can stay fast and deterministic.
 */
export async function loginByApi(request: APIRequestContext, user: TestUser): Promise<LoginResult> {
    const response = await request.post('/api/auth/login', {
        data: {
            email: user.email,
            password: user.password,
        },
    });

    expect(response.ok()).toBeTruthy();

    const payload = (await response.json()) as {
        data: LoginResult;
    };

    return payload.data;
}
