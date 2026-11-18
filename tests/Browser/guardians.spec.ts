import { expect, test } from '@playwright/test';

test('pilihan wali mengikuti keluarga dan tersimpan saat tambah atau edit warga', async ({
    page,
}, testInfo) => {
    test.setTimeout(150_000);
    const suffix = testInfo.project.name;
    const parentA = 'DEMO Wali Orang Tua A ' + suffix;
    const parentB = 'DEMO Wali Orang Tua B ' + suffix;
    const child = 'DEMO Wali Anak ' + suffix;
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    async function selectFamily(label: string) {
        const box = page.getByLabel('Keluarga', { exact: true });
        await box.click();
        await box.fill(label);
        await page
            .getByRole('option', { name: label === '' ? 'Belum dihubungkan' : label, exact: true })
            .click();
    }

    // Keluarga baru dibuat lewat form warga: centang "Kepala Keluarga" agar
    // keluarga dibuat otomatis dengan warga ini sebagai kepala keluarganya.
    async function createHead(name: string) {
        await page.goto('/masyarakat/create');
        await page.getByLabel('Nama lengkap', { exact: true }).fill(name);
        await page.getByRole('checkbox', { name: 'Kepala Keluarga', exact: true }).check();
        await page
            .getByLabel('Alasan pencatatan / perubahan', { exact: true })
            .fill('Pendaftaran kepala keluarga pengujian wali');
        await page.getByRole('button', { name: 'Simpan warga', exact: true }).click();
        await expect(page).toHaveURL(/\/masyarakat$/);
        await expect(page.getByRole('table')).toContainText(name);
    }

    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('ketua@demo.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Demo-Jeruk-2026!');
    await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await createHead(parentA);
    await createHead(parentB);
    await page.goto('/masyarakat/create');
    const guardian = page.getByLabel('Orang tua / wali', { exact: true });
    await expect(guardian).toBeDisabled();
    await expect(guardian.locator('option')).toHaveText(['Pilih keluarga terlebih dahulu']);
    await selectFamily(parentA);
    await expect(guardian).toBeEnabled();
    await expect(guardian.locator('option')).toHaveText(['Belum dihubungkan', parentA]);
    await guardian.selectOption({ label: parentA });
    const parentAId = await guardian.inputValue();
    await selectFamily(parentB);
    await expect(guardian).toHaveValue('');
    await expect(guardian.locator('option')).toHaveText(['Belum dihubungkan', parentB]);
    await guardian.selectOption({ label: parentB });
    const parentBId = await guardian.inputValue();
    await selectFamily('');
    await expect(guardian).toHaveValue('');
    await expect(guardian).toBeDisabled();

    await selectFamily(parentA);
    await guardian.selectOption({ label: parentA });
    await page.getByLabel('Nama lengkap', { exact: true }).fill(child);
    await page
        .getByLabel('Alasan pencatatan / perubahan', { exact: true })
        .fill('Simpan anak dengan wali dari keluarga yang sama');
    await page.getByRole('button', { name: 'Simpan warga', exact: true }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await page
        .getByRole('row')
        .filter({ hasText: child })
        .getByRole('link', { name: 'Detail', exact: true })
        .click();
    await expect(guardian).toHaveValue(parentAId);
    await expect(guardian.locator('option')).toHaveText(['Belum dihubungkan', parentA]);
    await selectFamily(parentB);
    await expect(guardian).toHaveValue('');
    await guardian.selectOption({ label: parentB });
    await page
        .getByLabel('Alasan pencatatan / perubahan', { exact: true })
        .fill('Pindah keluarga dan ganti wali sesuai keluarga baru');
    await page.getByRole('button', { name: 'Simpan warga', exact: true }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await page
        .getByRole('row')
        .filter({ hasText: child })
        .getByRole('link', { name: 'Detail', exact: true })
        .click();
    await expect(guardian).toHaveValue(parentBId);
    await expect(guardian.locator('option')).toHaveText(['Belum dihubungkan', parentB]);
    await page.screenshot({
        path: testInfo.outputPath('wali-sesuai-keluarga.png'),
        fullPage: true,
    });
    expect(errors).toEqual([]);
});
