import { chromium, type Browser, type BrowserContext, type Page, type TestInfo } from '@playwright/test';
import { testUsers, type TestUser } from './users';

export type PersistentProfileName = 'admin' | 'customer1' | 'customer2' | 'worker1';

type WindowBounds = {
    width: number;
    height: number;
    x: number;
    y: number;
};

export type PersistentWindow = {
    browser: Browser;
    context: BrowserContext;
    page: Page;
    profileName: PersistentProfileName;
    user: TestUser;
    bounds: WindowBounds;
};

const baseUrl = 'http://127.0.0.1:8000';

const profileUsers: Record<PersistentProfileName, TestUser> = {
    admin: testUsers.admin,
    customer1: testUsers.customer,
    customer2: testUsers.customerTwo,
    worker1: testUsers.worker,
};

const screenWidth = 1920;
const screenHeight = 1080;

const halfWidth = Math.floor(screenWidth / 2);
const halfHeight = Math.floor(screenHeight / 2);

const profileBounds: Record<PersistentProfileName, WindowBounds> = {
    customer1: {
        width: halfWidth,
        height: halfHeight,
        x: 0,
        y: 0,
    },

    worker1: {
        width: halfWidth,
        height: halfHeight,
        x: halfWidth,
        y: 0,
    },

    customer2: {
        width: halfWidth,
        height: halfHeight,
        x: 0,
        y: halfHeight,
    },

    admin: {
        width: halfWidth,
        height: halfHeight,
        x: halfWidth,
        y: halfHeight,
    },
};

function recordVideoDirectory(testInfo: TestInfo, profileName: PersistentProfileName): string {
    return testInfo.outputPath(`videos/${profileName}`);
}

export async function launchProfileWindow(
    profileName: PersistentProfileName,
    testInfo: TestInfo,
): Promise<PersistentWindow> {
    const bounds = profileBounds[profileName];
    const browser = await chromium.launch({
        channel: 'chromium',
        headless: false,
        slowMo: 1000,
        args: [
            `--window-size=${bounds.width},${bounds.height}`,
            `--window-position=${bounds.x},${bounds.y}`,
        ],
    });

    const context = await browser.newContext({
        baseURL: baseUrl,
        viewport: null,
        recordVideo: {
            dir: recordVideoDirectory(testInfo, profileName),
            size: {
                width: bounds.width,
                height: bounds.height,
            },
        },
    });

    const page = await context.newPage();

    return {
        browser,
        context,
        page,
        profileName,
        user: profileUsers[profileName],
        bounds,
    };
}

export async function launchProfileWindows(
    profileNames: PersistentProfileName[],
    testInfo: TestInfo,
): Promise<Record<PersistentProfileName, PersistentWindow>> {
    const windows = {} as Record<PersistentProfileName, PersistentWindow>;

    for (const profileName of profileNames) {
        windows[profileName] = await launchProfileWindow(profileName, testInfo);
    }

    return windows;
}

export async function closeProfileWindows(
    windows: Partial<Record<PersistentProfileName, PersistentWindow>>,
    testInfo: TestInfo,
): Promise<void> {
    const isFailure = testInfo.status !== testInfo.expectedStatus;

    for (const window of Object.values(windows)) {
        if (!window) {
            continue;
        }

        const screenshotPath = testInfo.outputPath(`${window.profileName}-failure.png`);
        const video = window.page.video();

        if (isFailure) {
            await window.page.screenshot({
                path: screenshotPath,
                fullPage: true,
            });
        }

        await window.context.close();

        if (isFailure) {
            await testInfo.attach(`${window.profileName}-screenshot`, {
                path: screenshotPath,
                contentType: 'image/png',
            });
        }

        if (video && isFailure) {
            const videoPath = await video.path();

            await testInfo.attach(`${window.profileName}-video`, {
                path: videoPath,
                contentType: 'video/webm',
            });
        }

        await window.browser.close();
    }
}
