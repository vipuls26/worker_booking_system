import { expect, type APIRequestContext } from '@playwright/test';

const mailpitBaseUrl = process.env.PLAYWRIGHT_MAILPIT_URL || 'http://localhost:8025';

type MailpitMessageSummary = {
    ID: string;
    Subject?: string;
    To?: Array<{
        Address?: string;
    }>;
};

type MailpitMessageListResponse = {
    messages?: MailpitMessageSummary[];
};

function recipientMatches(message: MailpitMessageSummary, email: string): boolean {
    return (message.To || []).some((recipient) => recipient.Address?.toLowerCase() === email.toLowerCase());
}

function collectTextPayload(payload: unknown): string[] {
    if (typeof payload === 'string') {
        return [payload];
    }

    if (Array.isArray(payload)) {
        return payload.flatMap((item) => collectTextPayload(item));
    }

    if (payload && typeof payload === 'object') {
        return Object.values(payload as Record<string, unknown>).flatMap((value) => collectTextPayload(value));
    }

    return [];
}

function extractResetLink(messagePayload: unknown): string | null {
    const candidates = collectTextPayload(messagePayload);

    for (const candidate of candidates) {
        const match = candidate.match(/https?:\/\/[^\s"'<>]+\/reset-password\?[^\s"'<>]+/i);

        if (match) {
            return match[0].replace(/&amp;/g, '&');
        }
    }

    return null;
}

/**
 * Clear Mailpit messages when the local API supports it, so reset-email tests stay deterministic.
 */
export async function clearMailpitInbox(request: APIRequestContext): Promise<void> {
    await request.fetch(`${mailpitBaseUrl}/api/v1/messages`, {
        method: 'DELETE',
    }).catch(() => undefined);
}

/**
 * Wait for the latest password reset email and return the reset link found in the Mailpit payload.
 */
export async function waitForPasswordResetEmail(
    request: APIRequestContext,
    email: string,
    options: {
        timeoutMs?: number;
        subjectIncludes?: string;
    } = {},
): Promise<{ resetLink: string; messageId: string }> {
    const timeoutMs = options.timeoutMs ?? 15_000;
    const startedAt = Date.now();

    while (Date.now() - startedAt < timeoutMs) {
        const listResponse = await request.get(`${mailpitBaseUrl}/api/v1/messages`);

        if (listResponse.ok()) {
            const listPayload = await listResponse.json() as MailpitMessageListResponse;
            const matchingMessage = (listPayload.messages || []).find((message) => {
                const subjectMatches = options.subjectIncludes
                    ? (message.Subject || '').toLowerCase().includes(options.subjectIncludes.toLowerCase())
                    : true;

                return subjectMatches && recipientMatches(message, email);
            });

            if (matchingMessage?.ID) {
                const detailResponse = await request.get(`${mailpitBaseUrl}/api/v1/message/${matchingMessage.ID}`);

                expect(detailResponse.ok()).toBeTruthy();

                const detailPayload = await detailResponse.json();
                const resetLink = extractResetLink(detailPayload);

                if (resetLink) {
                    return {
                        resetLink,
                        messageId: matchingMessage.ID,
                    };
                }
            }
        }

        await new Promise((resolve) => setTimeout(resolve, 500));
    }

    throw new Error(`No password reset email for ${email} was found in Mailpit within ${timeoutMs}ms.`);
}
