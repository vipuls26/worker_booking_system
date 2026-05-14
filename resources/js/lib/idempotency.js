export function createIdempotencyKey(prefix = 'request') {
    if (globalThis.crypto?.randomUUID) {
        return `${prefix}:${globalThis.crypto.randomUUID()}`;
    }

    return `${prefix}:${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export function withIdempotencyKey(prefix, config = {}) {
    return {
        ...config,
        headers: {
            ...(config.headers || {}),
            'X-Idempotency-Key': createIdempotencyKey(prefix),
        },
    };
}
