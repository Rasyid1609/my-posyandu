import { expect, test } from '@playwright/test';

test('notifikasi mengikuti hasil penyimpanan, tema, dan navigasi', async ({ page }, testInfo) => {
    test.setTimeout(150_000);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.clock.install();
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('ketua@demo.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Demo-Jeruk-2026!');
    await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await page.getByRole('link', { name: 'Tambah warga', exact: true }).click();
    await expect(page).toHaveURL(/\/masyarakat\/create$/);
    const notification = page.getByRole('region', { name: /^Notifikasi/ });
    const toast = notification.locator('[data-sonner-toast]');
    const submit = page.getByRole('button', { name: 'Simpan warga', exact: true });
    await page
        .locator('form')
        .filter({ has: submit })
        .evaluate((form: HTMLFormElement) => {
            form.noValidate = true;
        });

    async function expectReadableText() {
        const contrasts = await toast.evaluate((element) => {
            const context = document
                .createElement('canvas')
                .getContext('2d', { willReadFrequently: true })!;
            function luminance(color: string) {
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);
                const channels = [...context.getImageData(0, 0, 1, 1).data]
                    .slice(0, 3)
                    .map((value) => value / 255)
                    .map((value) =>
                        value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4,
                    );
                return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
            }
            const background = luminance(getComputedStyle(element).backgroundColor);
            return [...element.querySelectorAll('[data-title], [data-description]')].map((text) => {
                const foreground = luminance(getComputedStyle(text).color);
                return (
                    (Math.max(foreground, background) + 0.05) /
                    (Math.min(foreground, background) + 0.05)
                );
            });
        });
        expect(contrasts).toHaveLength(2);
        expect(Math.min(...contrasts)).toBeGreaterThanOrEqual(4.5);
    }

    await submit.click();
    await expect(toast).toContainText('Data belum tersimpan');
    await expect(page.getByLabel('Nama lengkap', { exact: true })).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await page.clock.fastForward(6500);
    await expect(toast).toBeVisible();
    await page.getByRole('button', { name: 'Tutup notifikasi' }).focus();
    await page.keyboard.press('Enter');
    await expect(toast).toHaveCount(0);

    const name = 'DEMO Notifikasi ' + testInfo.project.name;
    await page.getByLabel('Nama lengkap', { exact: true }).fill(name);
    await page
        .getByLabel('Alasan pencatatan / perubahan', { exact: true })
        .fill('Pengujian notifikasi dengan data sintetis');
    await page.route('**/masyarakat', (route) =>
        route.request().method() === 'POST' ? route.abort('failed') : route.continue(),
    );
    await submit.click();
    await expect(toast).toContainText('Hasil penyimpanan belum dapat dipastikan');
    await expect(page.getByLabel('Nama lengkap', { exact: true })).toHaveValue(name);
    await expect(toast).not.toContainText('Perubahan tersimpan');
    await page.unroute('**/masyarakat');
    await page.getByRole('button', { name: 'Tutup notifikasi' }).click();
    await expect(toast).toHaveCount(0);

    await page.route('**/masyarakat', (route) =>
        route.request().method() === 'POST'
            ? route.fulfill({
                  status: 419,
                  contentType: 'application/json',
                  body: JSON.stringify({ message: 'CSRF token mismatch.' }),
              })
            : route.continue(),
    );
    await submit.click();
    await expect(toast).toContainText('Sesi telah berakhir');
    await expect(toast).toHaveAttribute('data-type', 'warning');
    await page.clock.fastForward(6500);
    await expect(toast).toBeVisible();
    await page.unroute('**/masyarakat');
    await page.getByRole('button', { name: 'Tutup notifikasi' }).click();
    await expect(toast).toHaveCount(0);

    for (const response of [
        { status: 403, title: 'Akses tidak diizinkan', type: 'error' },
        { status: 409, title: 'Data telah berubah', type: 'warning' },
        { status: 429, title: 'Terlalu banyak permintaan', type: 'warning' },
        { status: 500, title: 'Permintaan belum berhasil', type: 'error' },
    ]) {
        await page.route('**/masyarakat', (route) =>
            route.request().method() === 'POST'
                ? route.fulfill({
                      status: response.status,
                      contentType: 'application/json',
                      body: '{}',
                  })
                : route.continue(),
        );
        await submit.click();
        await expect(toast).toContainText(response.title);
        await expect(toast).toHaveAttribute('data-type', response.type);
        await expect(page.getByLabel('Nama lengkap', { exact: true })).toHaveValue(name);
        await page.unroute('**/masyarakat');
        await page.getByRole('button', { name: 'Tutup notifikasi' }).click();
        await expect(toast).toHaveCount(0);
    }

    let releaseRequest!: () => void;
    const requestGate = new Promise<void>((resolve) => {
        releaseRequest = resolve;
    });
    await page.route('**/masyarakat', async (route) => {
        if (route.request().method() === 'POST') await requestGate;
        await route.continue();
    });
    await submit.click();
    await page.clock.fastForward(600);
    await expect(toast).toContainText('Memproses perubahan');
    releaseRequest();
    await expect(toast).toContainText('Warga berhasil ditambahkan');
    await expect(toast).toHaveAttribute('data-type', 'success');
    await expect(toast).toHaveCount(1);
    await expect(page.getByRole('table')).toContainText(name);
    await page.unroute('**/masyarakat');
    await page.clock.pauseAt(await page.evaluate(() => Date.now() + 1000));
    const bounds = await toast.boundingBox();
    expect(bounds).not.toBeNull();
    expect(bounds!.x).toBeGreaterThanOrEqual(0);
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(page.viewportSize()!.width);
    const closeBounds = await page.getByRole('button', { name: 'Tutup notifikasi' }).boundingBox();
    expect(closeBounds!.x).toBeGreaterThanOrEqual(bounds!.x);
    expect(closeBounds!.x + closeBounds!.width).toBeLessThanOrEqual(bounds!.x + bounds!.width);
    await expectReadableText();
    await page.screenshot({ path: testInfo.outputPath('notifikasi-terang.png') });
    const lightBackground = await toast.evaluate(
        (element) => getComputedStyle(element).backgroundColor,
    );

    await page.getByRole('button', { name: 'Aktifkan mode gelap' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    expect(await toast.evaluate((element) => getComputedStyle(element).backgroundColor)).not.toBe(
        lightBackground,
    );
    await expectReadableText();
    await page.screenshot({ path: testInfo.outputPath('notifikasi-gelap.png') });
    await page.getByRole('heading', { name: 'Data Masyarakat', exact: true }).hover();
    await page.clock.runFor(6000);
    await expect(toast).toHaveCount(0);
    await page.clock.resume();
    if (testInfo.project.name === 'mobile')
        await page.getByRole('button', { name: 'Buka menu', exact: true }).click();
    await page
        .getByRole('navigation', { name: 'Navigasi utama' })
        .getByRole('link', { name: 'Data Sasaran', exact: true })
        .click();
    await expect(page).toHaveURL(/\/sasaran$/);
    await page.goBack();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await expect(toast).toHaveCount(0);
    expect(errors).toEqual([]);
});
