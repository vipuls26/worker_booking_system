import { randomUUID } from 'node:crypto';

/**
 * Return a local YYYY-MM-DD string for a future date.
 */
export function futureDate(daysFromToday = 1): string {
    const date = new Date();
    const timezoneOffsetInMilliseconds = date.getTimezoneOffset() * 60 * 1000;

    date.setDate(date.getDate() + daysFromToday);

    return new Date(date.getTime() - timezoneOffsetInMilliseconds).toISOString().slice(0, 10);
}

/**
 * Return a local YYYY-MM-DD string for a past date.
 */
export function pastDate(daysBeforeToday = 1): string {
    return futureDate(daysBeforeToday * -1);
}

/**
 * Build a unique reference that stays readable in logs and audit trails.
 */
export function uniqueReference(prefix: string): string {
    return `${prefix}-${Date.now()}-${randomUUID().slice(0, 8)}`;
}
