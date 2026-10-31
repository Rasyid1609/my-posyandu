import { expect, test, type Page } from '@playwright/test';

test('reset kata sandi mencabut dua sesi dan melindungi halaman sebelumnya', async ({
    page,
    browser,
}, testInfo) => {
    test.setTimeout(150_000);
    const suffix = testInfo.project.name + '-' + Date.now();
    const email = 'sesi-' + suffix + '@demo.test';
    const originalPassword = 'Before-Password-2026!';
    const newPassword = 'After-Password-2026!';
    const unsavedResident = 'DEMO Sesi Dicabut ' + suffix;
    const errors: string[] = [];
    const firstDevice = await browser.newContext({ baseURL: 'http://127.0.0.1:8013' });
    const secondDevice = await browser.newContext({ baseURL: 'http://127.0.0.1:8013' });
    const firstPage = await firstDevice.newPage();
    const secondPage = await secondDevice.newPage();
    for (const devicePage of [page, firstPage, secondPage]) {
        devicePage.on('pageerror', (error) => errors.push(error.message));
        devicePage.on('dialog', (dialog) => dialog.accept());
    }

    async function signIn(
        devicePage: Page,
        accountEmail: string,
        password: string,
        destination = /\/masyarakat$/,
    ) {
        await devicePage.goto('/login');
        await devicePage.getByLabel('Email', { exact: true }).fill(accountEmail);
        await devicePage.getByLabel('Kata sandi', { exact: true }).fill(password);
        await devicePage.getByRole('button', { name: 'Masuk ke ruang kerja', exact: true }).click();
        await expect(devicePage).toHaveURL(destination);
    }

    try {
        await signIn(page, 'ketua@demo.test', 'Demo-Jeruk-2026!');
        await page.goto('/pengaturan/akun');
        await page
            .getByLabel('Nama petugas', { exact: true })
            .fill('DEMO Pengujian Sesi ' + suffix);
        await page.getByLabel('Email akun', { exact: true }).fill(email);
        await page.getByLabel('Kata sandi awal', { exact: true }).fill(originalPassword);
        await page.getByRole('button', { name: 'Simpan akun', exact: true }).click();
        const accountRow = page.getByRole('row').filter({ hasText: email });
        await expect(accountRow).toBeVisible();
        await signIn(firstPage, email, originalPassword);
        await signIn(secondPage, email, originalPassword);
        await firstPage.getByRole('link', { name: 'Tambah warga', exact: true }).click();
        await firstPage.getByLabel('Nama lengkap', { exact: true }).fill(unsavedResident);
        await firstPage
            .getByLabel('Alasan pencatatan / perubahan', { exact: true })
            .fill('Pengujian sintetis penolakan sesi lama');

        await accountRow.getByRole('button', { name: 'Edit akun', exact: true }).click();
        await page.getByLabel('Kata sandi baru (opsional)', { exact: true }).fill(newPassword);
        await page.getByRole('button', { name: 'Simpan akun', exact: true }).click();
        await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
            'Akun dan permission diperbarui.',
        );
        await expect(
            page.getByRole('heading', { name: 'Buat akun pengurus', exact: true }),
        ).toBeVisible();
        await page.reload();
        await expect(page).toHaveURL(/\/pengaturan\/akun$/);

        await firstPage.getByRole('button', { name: 'Simpan warga', exact: true }).click();
        await expect(firstPage).toHaveURL(/\/login$/);
        await expect(firstPage.getByLabel('Email', { exact: true })).toBeVisible();
        const historyReload = firstPage.waitForResponse(
            (response) =>
                response.request().method() === 'GET' && response.url().endsWith('/login'),
        );
        await firstPage.goBack();
        await historyReload;
        await expect(
            firstPage.getByRole('heading', { name: 'Selamat datang kembali', exact: true }),
        ).toBeVisible();
        await expect(firstPage.getByLabel('Email', { exact: true })).toBeVisible();
        await expect(firstPage.getByLabel('Nama lengkap', { exact: true })).toHaveCount(0);
        await secondPage.getByRole('link', { name: 'Tambah warga', exact: true }).click();
        await expect(secondPage).toHaveURL(/\/login$/);

        await firstPage.getByLabel('Email', { exact: true }).fill(email);
        await firstPage.getByLabel('Kata sandi', { exact: true }).fill(originalPassword);
        await firstPage.getByRole('button', { name: 'Masuk ke ruang kerja', exact: true }).click();
        await expect(
            firstPage.getByText('Email atau kata sandi salah, atau akun tidak aktif.').first(),
        ).toBeVisible();
        await expect(firstPage).toHaveURL(/\/login$/);
        await signIn(firstPage, email, newPassword, /\/masyarakat\/create$/);
        await expect(firstPage.getByLabel('Nama lengkap', { exact: true })).toHaveValue('');
        await firstPage.goto('/masyarakat');
        await expect(firstPage.getByRole('table')).not.toContainText(unsavedResident);
        expect(errors).toEqual([]);
    } finally {
        await firstDevice.close();
        await secondDevice.close();
    }
});
