const AUTH_TOKEN_KEY = 'auth_token';
const AUTH_USER_KEY = 'auth_user';
const AUTH_NOTICE_KEY = 'auth_notice';

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

function hasPersistentAuthSession() {
    return window.localStorage.getItem(AUTH_TOKEN_KEY) !== null;
}

function writeAuthSession(storage, token, user) {
    storage.setItem(AUTH_TOKEN_KEY, token);
    storage.setItem(AUTH_USER_KEY, JSON.stringify(user));
}

export function setStoredAuthSession(token, user, remember = false) {
    const targetStorage = remember ? window.localStorage : window.sessionStorage;
    const staleStorage = remember ? window.sessionStorage : window.localStorage;

    writeAuthSession(targetStorage, token, user);
    staleStorage.removeItem(AUTH_TOKEN_KEY);
    staleStorage.removeItem(AUTH_USER_KEY);
}

export function setStoredAuthUser(user) {
    const targetStorage = hasPersistentAuthSession() ? window.localStorage : window.sessionStorage;

    targetStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
}

export function setStoredAuthNotice(message) {
    window.sessionStorage.setItem(AUTH_NOTICE_KEY, message);
}

export function pullStoredAuthNotice() {
    const message = window.sessionStorage.getItem(AUTH_NOTICE_KEY);

    if (message === null) {
        return null;
    }

    window.sessionStorage.removeItem(AUTH_NOTICE_KEY);

    return message;
}

export function clearStoredAuthSession() {
    window.sessionStorage.removeItem(AUTH_TOKEN_KEY);
    window.sessionStorage.removeItem(AUTH_USER_KEY);
    window.localStorage.removeItem(AUTH_TOKEN_KEY);
    window.localStorage.removeItem(AUTH_USER_KEY);
}
