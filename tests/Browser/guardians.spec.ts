import { expect, test } from '@playwright/test';

test('pilihan wali mengikuti keluarga dan tersimpan saat tambah atau edit warga', async ({
    page,
}, testInfo) => {
    test.setTimeout(150_000);
    const suffix = testInfo.project.name;
    const familyA = 'DEMO Wali Keluarga A ' + suffix;
    const familyB = 'DEMO Wali Keluarga B ' + suffix;
    const emptyFamily = 'DEMO Wali Keluarga Kosong ' + suffix;
    const parentA = 'DEMO Wali Orang Tua A ' + suffix;
    const parentB = 'DEMO Wali Orang Tua B ' + suffix;
    const child = 'DEMO Wali Anak ' + suffix;
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    async function addFamily(name: string) {
        await page.goto('/masyarakat/keluarga/daftar');
        await page.getByLabel('Nama kepala keluarga', { exact: true }).fill(name);
        await page.getByRole('button', { name: 'Simpan keluarga', exact: true }).click();
        await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
            'Keluarga disimpan',
        );
        await expect(page.getByRole('row').filter({ hasText: name })).toBeVisible();
        await expect(page.getByLabel('Nama kepala keluarga', { exact: true })).toHaveValue('');
    }

    async function addParent(name: string, family: string) {
        await page.goto('/masyarakat/create');
        await page.getByLabel('Nama lengkap', { exact: true }).fill(name);
        await page.getByLabel('Keluarga', { exact: true }).selectOption({ label: family });
        await page
            .getByLabel('Alasan pencatatan / perubahan', { exact: true })
            .fill('Pencatatan sintetis pengujian wali');
        await page.getByRole('button', { name: 'Simpan warga', exact: true }).click();
        await expect(page).toHaveURL(/\/masyarakat$/);
        await expect(page.getByRole('table')).toContainText(name);
    }

    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('ketua@demo.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Demo-Jeruk-2026!');
    await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await addFamily(familyA);
    await addFamily(familyB);
    await addFamily(emptyFamily);
    await addParent(parentA, familyA);
    await addParent(parentB, familyB);
    await page.goto('/masyarakat/create');
    const family = page.getByLabel('Keluarga', { exact: true });
    const guardian = page.getByLabel('Orang tua / wali', { exact: true });
    await expect(guardian).toBeDisabled();
    await expect(guardian.locator('option')).toHaveText(['Pilih keluarga terlebih dahulu']);
    await family.selectOption({ label: familyA });
    await expect(guardian).toBeEnabled();
    await expect(guardian.locator('option')).toHaveText(['Belum dihubungkan', parentA]);
    await guardian.selectOption({ label: parentA });
    const parentAId = await guardian.inputValue();
    await family.selectOption({ label: familyB });
    await expect(guardian).toHaveValue('');
    await expect(guardian.locator('option')).toHaveText(['Belum dihubungkan', parentB]);
    await guardian.selectOption({ label: parentB });
    const parentBId = await guardian.inputValue();
    await family.selectOption('');
    await expect(guardian).toHaveValue('');
    await expect(guardian).toBeDisabled();
    await family.selectOption({ label: emptyFamily });
    await expect(guardian).toBeDisabled();
    await expect(
        page.getByText('Belum ada anggota keluarga yang dapat dipilih sebagai wali.', {
            exact: true,
        }),
    ).toBeVisible();

    await family.selectOption({ label: familyA });
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
    await family.selectOption({ label: familyB });
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
