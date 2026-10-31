import { expect, test } from '@playwright/test';

test('matriks jabatan tampil dan akun sekretaris mewarisi izin', async ({ page }, testInfo) => {
    page.on('dialog', (dialog) => dialog.accept());
    const email = 'sekretaris-' + testInfo.project.name + '@demo.test';
    const password = 'Demo-Sekretaris-2026!';
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('ketua@demo.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Demo-Jeruk-2026!');
    await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await page.goto('/pengaturan/akun');
    await expect(
        page.getByRole('heading', { name: 'Matriks izin aktif', exact: true }),
    ).toBeVisible();
    await page.getByLabel('Jabatan', { exact: true }).selectOption('bendahara');
    await expect(page.getByRole('checkbox', { name: /Melihat ringkasan agregat/ })).toBeChecked();
    await expect(page.getByRole('checkbox', { name: /Melihat ringkasan agregat/ })).toBeDisabled();
    await expect(page.getByRole('checkbox', { name: /Melihat identitas warga/ })).not.toBeChecked();
    await page.getByLabel('Jabatan', { exact: true }).selectOption('admin');
    await expect(page.getByRole('checkbox', { name: /Mencatat pengukuran mentah/ })).toBeChecked();
    await expect(
        page.getByRole('checkbox', { name: /Finalisasi administratif pengukuran/ }),
    ).not.toBeChecked();
    await page.getByLabel('Jabatan', { exact: true }).selectOption('sekretaris');
    await expect(
        page.getByRole('checkbox', { name: /Finalisasi administratif pengukuran/ }),
    ).toBeChecked();
    await expect(
        page.getByRole('checkbox', { name: /Finalisasi administratif pengukuran/ }),
    ).toBeDisabled();
    await expect(
        page.getByRole('checkbox', { name: /Mengunci dan membuka rekap operasional/ }),
    ).not.toBeChecked();
    await page
        .getByLabel('Nama petugas', { exact: true })
        .fill('DEMO Sekretaris ' + testInfo.project.name);
    await page.getByLabel('Email akun', { exact: true }).fill(email);
    await page.getByLabel('Kata sandi awal', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Simpan akun', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText('Akun dibuat');
    await page.screenshot({ path: testInfo.outputPath('matriks-izin.png'), fullPage: true });
    if (testInfo.project.name === 'mobile')
        await page.getByRole('button', { name: 'Buka menu', exact: true }).click();
    await page.getByRole('button', { name: 'Keluar', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
    await expect(page.getByRole('link', { name: 'Tambah warga', exact: true })).toBeVisible();
    const forbidden = await page.request.get('/pengaturan/akun');
    expect(forbidden.status()).toBe(403);
});
