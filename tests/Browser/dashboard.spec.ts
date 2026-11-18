import { expect, test } from '@playwright/test';

test('dashboard mengarahkan kader ke tindak lanjut dan pergantian periode', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const isMobile = testInfo.project.name === 'mobile';
    const month = isMobile ? '8' : '7';
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('ketua@demo.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Demo-Jeruk-2026!');
    await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await page.goto('/dashboard');
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
    await page.goto('/sasaran');
    await page.getByLabel('Bulan', { exact: true }).selectOption(month);
    await page.getByLabel('Tahun', { exact: true }).fill('2026');
    await page.getByRole('button', { name: 'Buat periode', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Tambahkan sasaran manual', exact: true })).toBeVisible();
    const periodId = page.url().split('/').pop()!;
    const resident = page.getByLabel('Warga aktif', { exact: true }).locator('option').filter({ hasText: 'DEMO Warga Satu' });
    await page.getByLabel('Warga aktif', { exact: true }).selectOption((await resident.getAttribute('value'))!);
    await page.getByLabel('Alasan pemilihan sasaran', { exact: true }).fill('Sasaran sintetis uji dashboard');
    await page.getByRole('button', { name: 'Tambahkan sasaran', exact: true }).click();
    await expect(page.getByRole('table')).toContainText('DEMO Warga Satu');

    if (isMobile) await page.getByRole('button', { name: 'Buka menu', exact: true }).click();
    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Dashboard', exact: true }).click();
    await page.getByLabel('Pilih periode', { exact: true }).selectOption(periodId);
    await expect(page.getByRole('region', { name: 'Daftar tindak lanjut', exact: true })).toContainText('DEMO Warga Satu');
    await expect(page.getByRole('region', { name: 'Daftar tindak lanjut', exact: true })).toContainText('Belum dikonfirmasi');
    await page.screenshot({ path: testInfo.outputPath('dashboard-light.png'), fullPage: true });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    await page.getByRole('link', { name: 'Buka sasaran', exact: true }).click();
    await expect(page).toHaveURL(new RegExp('/sasaran/' + periodId + '\\?target='));
    await expect(page.getByText('Menampilkan warga yang dipilih dari dashboard.', { exact: false })).toBeVisible();
    await page.goto('/dashboard?period=' + periodId + '&task=drafts');
    await expect(page.getByText('Tidak ada tindak lanjut pada kategori ini.', { exact: true })).toBeVisible();
    await page.getByRole('link', { name: /Rujukan tertunda/ }).click();
    await expect(page).toHaveURL(/task=referrals/);
    await expect(page.getByText('Tidak ada tindak lanjut pada kategori ini.', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Aktifkan mode gelap', exact: true }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.screenshot({ path: testInfo.outputPath('dashboard-dark.png'), fullPage: true });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(errors).toEqual([]);
});
