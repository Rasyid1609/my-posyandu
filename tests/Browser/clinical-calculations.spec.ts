import { expect, test, type Page } from '@playwright/test';

test('hasil hitung mengikuti input, tersimpan, dan dikonfirmasi pada desktop serta HP', async ({
    page,
}, testInfo) => {
    test.setTimeout(240_000);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('dialog', (dialog) => dialog.accept());
    const month = testInfo.project.name === 'desktop' ? '5' : '6';
    const date = `2026-${month.padStart(2, '0')}-15T09:00`;
    const adult = `DEMO Hitung Dewasa ${testInfo.project.name}`;
    const child = `DEMO Hitung Anak ${testInfo.project.name}`;
    async function createResident(name: string, birth: string) {
        await page.goto('/masyarakat/create');
        await page.getByLabel('Nama lengkap', { exact: true }).fill(name);
        await page.getByLabel('Jenis kelamin', { exact: true }).selectOption('L');
        await page.getByLabel('Tanggal lahir', { exact: true }).fill(birth);
        await page
            .getByLabel('Alasan pencatatan / perubahan', { exact: true })
            .fill('Fixture perhitungan browser sintetis');
        await page.getByRole('button', { name: 'Simpan warga', exact: true }).click();
        await expect(page).toHaveURL(/\/masyarakat$/);
    }
    async function enterMeasurements(browserPage: Page, weight: string, height: string) {
        await browserPage.getByLabel('Tanggal & waktu kunjungan (WIB)', { exact: true }).fill(date);
        await browserPage
            .getByLabel('Status berat badan', { exact: true })
            .selectOption('measured');
        await browserPage.getByLabel('Berat badan (kg)', { exact: true }).fill(weight);
        await browserPage
            .getByLabel('Status tinggi / panjang badan', { exact: true })
            .selectOption('measured');
        await browserPage.getByLabel('Tinggi / panjang (cm)', { exact: true }).fill(height);
        await browserPage
            .getByLabel('Cara mengukur panjang / tinggi', { exact: true })
            .selectOption('standing');
    }

    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('ketua@demo.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Demo-Jeruk-2026!');
    await page.getByRole('button', { name: 'Masuk ke ruang kerja', exact: true }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await createResident(adult, '1990-01-15');
    await createResident(child, `2025-${month.padStart(2, '0')}-15`);
    await page.goto('/sasaran');
    await page.getByLabel('Bulan', { exact: true }).selectOption(month);
    await page.getByLabel('Tahun', { exact: true }).fill('2026');
    await page.getByRole('button', { name: 'Buat periode', exact: true }).click();
    await expect(page).toHaveURL(/\/sasaran\/\d+$/);
    const periodUrl = page.url();
    await page.getByRole('button', { name: 'Bentuk sasaran otomatis', exact: true }).click();
    await page
        .getByRole('row')
        .filter({ hasText: adult })
        .getByRole('link', { name: 'Pelayanan', exact: true })
        .click();
    await enterMeasurements(page, '60', '160');
    const live = page.getByRole('region', { name: 'Hasil hitung otomatis', exact: true }).first();
    const bmi = live.getByRole('article', { name: 'IMT dewasa', exact: true });
    await expect(bmi).toContainText('23,44');
    await page.getByLabel('Status tekanan darah', { exact: true }).selectOption('measured');
    await page.getByLabel('Sistolik (mmHg)', { exact: true }).fill('120');
    await page.getByLabel('Diastolik (mmHg)', { exact: true }).fill('90');
    const repeated = page.getByRole('checkbox', {
        name: 'Nilai sudah diperiksa ulang dan dirata-ratakan sesuai prosedur pengukuran.',
    });
    await repeated.check();
    await expect(live.getByRole('article', { name: 'Tekanan darah', exact: true })).toContainText(
        'Mencapai ambang',
    );
    await page.getByLabel('Diastolik (mmHg)', { exact: true }).fill('80');
    await expect(repeated).not.toBeChecked();
    await repeated.check();
    await expect(live.getByRole('article', { name: 'Tekanan darah', exact: true })).toContainText(
        'Tidak mencapai ambang',
    );
    await page.getByLabel('Status gula darah', { exact: true }).selectOption('measured');
    await page.getByLabel('Gula darah (mg/dL)', { exact: true }).fill('110');
    await page
        .getByLabel('Jenis pemeriksaan gula darah', { exact: true })
        .selectOption('fasting_plasma');
    await expect(live.getByRole('article', { name: 'Gula darah', exact: true })).toContainText(
        'Di atas rentang normal',
    );
    await page
        .getByLabel('Jenis pemeriksaan gula darah', { exact: true })
        .selectOption('random_capillary');
    await expect(live.getByRole('article', { name: 'Gula darah', exact: true })).toContainText(
        'Belum dapat dihitung',
    );
    await expect(bmi).toContainText('Berat badan lebih');
    await expect(page.getByLabel('Status IMT · otomatis', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Status IMT · otomatis', { exact: true })).toHaveValue(
        'overweight',
    );
    await page.getByLabel('Berat badan (kg)', { exact: true }).fill('50');
    await expect(bmi).toContainText('19,53');
    await expect(page.getByLabel('Status IMT · otomatis', { exact: true })).toHaveValue('normal');
    await page.getByLabel('Status berat badan', { exact: true }).selectOption('declined');
    await expect(live).toContainText('Belum dapat dihitung');
    await expect(live.getByText('19,53', { exact: false })).toHaveCount(0);
    await page.getByLabel('Status berat badan', { exact: true }).selectOption('measured');
    await page.getByLabel('Berat badan (kg)', { exact: true }).fill('60');
    await expect(bmi).toContainText('23,44');
    await page.getByRole('button', { name: 'Simpan draft pengukuran', exact: true }).click();
    const history = page.locator('.visit-card').first();
    await expect(history).toContainText('60.00');
    await history.getByText('Hasil pelayanan & sumber', { exact: true }).click();
    await expect(history.getByRole('article', { name: 'IMT dewasa', exact: true })).toContainText(
        '23,44',
    );
    await expect(
        history.getByRole('button', { name: 'Finalkan administratif', exact: true }),
    ).toBeDisabled();
    await history
        .getByRole('checkbox', {
            name: 'Saya sudah memeriksa angka dan hasil hitung otomatis pada kunjungan ini.',
        })
        .check();
    await history.getByRole('button', { name: 'Finalkan administratif', exact: true }).click();
    await expect(history).toContainText('Final administratif');
    await page.reload();
    await history.getByText('Hasil pelayanan & sumber', { exact: true }).click();
    await expect(history).toContainText('telah dikonfirmasi');

    await page.goto(periodUrl);
    await page
        .getByRole('row')
        .filter({ hasText: child })
        .getByRole('link', { name: 'Pelayanan', exact: true })
        .click();
    await enterMeasurements(page, '9.6', '75');
    const childLive = page
        .getByRole('region', { name: 'Hasil hitung otomatis', exact: true })
        .first();
    await expect(
        childLive.getByRole('article', { name: 'Berat badan menurut umur (BB/U)', exact: true }),
    ).toContainText('Berat badan normal');
    await expect(
        childLive.getByRole('article', {
            name: 'Panjang/tinggi menurut umur (PB/TB/U)',
            exact: true,
        }),
    ).toContainText('Normal');
    await expect(childLive).toContainText('75.7 cm');
    await expect(page.getByLabel('Tinggi / panjang (cm)', { exact: true })).toHaveValue('75');
    await expect(page.getByLabel('BB/U · otomatis', { exact: true })).toHaveValue('good');
    await page.getByRole('button', { name: 'Aktifkan mode gelap', exact: true }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true);
    await page.screenshot({
        path: testInfo.outputPath('hasil-otomatis-gelap.png'),
        fullPage: true,
    });
    await page.getByRole('button', { name: 'Simpan draft pengukuran', exact: true }).click();
    await expect(page.locator('.visit-card').first()).toContainText('75.00');
    expect(errors).toEqual([]);
});
