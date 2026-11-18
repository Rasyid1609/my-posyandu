import { expect, test } from '@playwright/test';

test('wilayah provinsi, kota/kabupaten, dan kecamatan mengikuti master data', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('ketua@demo.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Demo-Jeruk-2026!');
    await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);

    await page.goto('/masyarakat/create');
    const province = page.getByLabel('Provinsi', { exact: true });
    const city = page.getByLabel('Kota / Kabupaten', { exact: true });
    const district = page.getByLabel('Kecamatan', { exact: true });

    // Provinsi di luar Madiun harus tersedia dari master data nasional.
    await province.click();
    await province.fill('Jawa Barat');
    await page.getByRole('option', { name: '32 - Jawa Barat', exact: true }).click();
    // Blur tanpa mengetik tidak boleh menghapus provinsi yang baru dipilih.
    await page.keyboard.press('Tab');
    await expect(province).toHaveValue('Jawa Barat');
    await expect(city).toBeEnabled();

    await city.click();
    await city.fill('Kota Bandung');
    await page.getByRole('option', { name: '73 - Kota Bandung', exact: true }).click();
    await page.keyboard.press('Tab');
    await expect(city).toHaveValue('Kota Bandung');
    await expect(district).toBeEnabled();

    await district.click();
    await district.fill('Coblong');
    await page.getByRole('option', { name: '02 - Coblong', exact: true }).click();
    await page.keyboard.press('Tab');
    await expect(district).toHaveValue('Coblong');

    expect(errors).toEqual([]);
});
