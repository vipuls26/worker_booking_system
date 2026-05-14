const AUTH_TOKEN_KEY = 'auth_token';
const AUTH_USER_KEY = 'auth_user';

function migrateLegacyValue(key) {
    const sessionValue = window.sessionStorage.getItem(key);

    if (sessionValue !== null) {
        return sessionValue;
    }

    const legacyValue = window.localStorage.getItem(key);

    if (legacyValue === null) {
        return null;
    }

    window.sessionStorage.setItem(key, legacyValue);
    window.localStorage.removeItem(key);

    return legacyValue;
}

export function getStoredAuthToken() {
    return migrateLegacyValue(AUTH_TOKEN_KEY);
}

export function getStoredAuthUser() {
    const storedUser = migrateLegacyValue(AUTH_USER_KEY);

    return storedUser ? JSON.parse(storedUser) : null;
}

export function setStoredAuthSession(token, user) {
    window.sessionStorage.setItem(AUTH_TOKEN_KEY, token);
    window.sessionStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
    window.localStorage.removeItem(AUTH_TOKEN_KEY);
    window.localStorage.removeItem(AUTH_USER_KEY);
}

export function setStoredAuthUser(user) {
    window.sessionStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
    window.localStorage.removeItem(AUTH_USER_KEY);
}

export function clearStoredAuthSession() {
    window.sessionStorage.removeItem(AUTH_TOKEN_KEY);
    window.sessionStorage.removeItem(AUTH_USER_KEY);
    window.localStorage.removeItem(AUTH_TOKEN_KEY);
    window.localStorage.removeItem(AUTH_USER_KEY);
}
