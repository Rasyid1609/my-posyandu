import { expect, test } from '@playwright/test';

test('login → warga → sasaran → pelayanan → rekap → review', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('dialog', (dialog) => dialog.accept());
    const isMobile = testInfo.project.name === 'mobile';
    const month = isMobile ? '2' : '1';
    const monthCode = month.padStart(2, '0');
    const name = 'DEMO Browser ' + testInfo.project.name;
    async function openMenu(label: string) {
        if (isMobile) await page.getByRole('button', { name: 'Buka menu', exact: true }).click();
        await page
            .getByRole('navigation', { name: 'Navigasi utama' })
            .getByRole('link', { name: label, exact: true })
            .click();
    }

    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).focus();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Kata sandi', { exact: true })).toBeFocused();
    await page.getByLabel('Email', { exact: true }).fill('ketua@demo.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Demo-Jeruk-2026!');
    await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await page.evaluate(() => document.fonts.ready);
    expect(
        await page.evaluate(() =>
            [...document.fonts].some((font) => font.family === 'Geist' && font.status === 'loaded'),
        ),
    ).toBe(true);
    await page.getByRole('button', { name: 'Kolom', exact: true }).click();
    await expect(page.getByRole('menu')).toBeVisible();
    await page.getByRole('menuitemcheckbox', { name: 'Nomor registrasi', exact: true }).click();
    await expect(
        page.getByRole('menuitemcheckbox', { name: 'Nomor registrasi', exact: true }),
    ).not.toBeChecked();
    await page.keyboard.press('Escape');
    await expect(
        page.getByRole('columnheader', { name: 'Nomor registrasi', exact: true }),
    ).toHaveCount(0);
    await page.getByRole('button', { name: 'Kolom', exact: true }).click();
    await page.getByRole('menuitemcheckbox', { name: 'Nomor registrasi', exact: true }).click();
    await expect(
        page.getByRole('menuitemcheckbox', { name: 'Nomor registrasi', exact: true }),
    ).toBeChecked();
    await page.screenshot({ path: testInfo.outputPath('menu-subtle.png') });
    await page.keyboard.press('Escape');
    await expect(
        page.getByRole('columnheader', { name: 'Nomor registrasi', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'Kolom', exact: true })).toBeFocused();
    await page.screenshot({ path: testInfo.outputPath('dashboard-nova.png'), fullPage: true });
    await page.getByRole('link', { name: 'Tambah warga', exact: true }).click();
    await page.getByLabel('Nama lengkap', { exact: true }).fill(name);
    await page.getByLabel('Tanggal lahir', { exact: true }).fill('1990-01-01');
    const nik = isMobile ? '0123456789012346' : '0123456789012345';
    await page.getByLabel('NIK (opsional)', { exact: true }).fill(nik);
    await page.getByLabel('Kontak telepon (opsional)', { exact: true }).fill('081234567890');
    await page
        .getByLabel('Alasan pencatatan / perubahan', { exact: true })
        .fill('Data sintetis untuk pengujian browser');
    await page.getByRole('button', { name: 'Simpan warga', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Warga berhasil ditambahkan',
    );
    await expect(page.getByRole('table')).toContainText(name);
    await page.screenshot({ path: testInfo.outputPath('masyarakat.png'), fullPage: true });
    await page
        .getByRole('row')
        .filter({ hasText: name })
        .getByRole('link', { name: /Detail/ })
        .click();
    await expect(page.getByText(nik, { exact: true })).toBeVisible();
    await expect(page.getByText('081234567890', { exact: true })).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('identitas-opsional.png'), fullPage: true });
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true);
    await page
        .getByLabel('Alasan pencatatan / perubahan', { exact: true })
        .fill('Perbarui tanpa menghapus nomor tersimpan');
    await page.getByRole('button', { name: 'Simpan warga', exact: true }).click();
    await page
        .getByRole('row')
        .filter({ hasText: name })
        .getByRole('link', { name: /Detail/ })
        .click();
    await expect(page.getByText(nik, { exact: true })).toBeVisible();
    await page.getByLabel('Hapus kontak tersimpan', { exact: true }).check();
    await page
        .getByLabel('Alasan pencatatan / perubahan', { exact: true })
        .fill('Hapus kontak sintetis melalui pilihan eksplisit');
    await page.getByRole('button', { name: 'Simpan warga', exact: true }).click();

    await openMenu('Data Sasaran');
    await page.getByLabel('Bulan', { exact: true }).selectOption(month);
    await page.getByLabel('Tahun', { exact: true }).fill('2026');
    await page.getByRole('button', { name: 'Buat periode', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Tambahkan sasaran manual' })).toBeVisible();
    const periodUrl = page.url();
    const option = page
        .getByLabel('Warga aktif', { exact: true })
        .locator('option')
        .filter({ hasText: name });
    const value = await option.getAttribute('value');
    expect(value).not.toBeNull();
    await page.getByLabel('Warga aktif', { exact: true }).selectOption(value!);
    await page
        .getByLabel('Alasan pemilihan sasaran', { exact: true })
        .fill('Sasaran demo kegiatan bulanan');
    await page.getByRole('button', { name: 'Tambahkan sasaran', exact: true }).click();
    await expect(page.getByRole('row').filter({ hasText: name })).toContainText(
        'Belum dikonfirmasi',
    );
    await page
        .getByRole('row')
        .filter({ hasText: name })
        .getByRole('link', { name: 'Pelayanan', exact: true })
        .click();

    await page
        .getByLabel('Tanggal & waktu kunjungan (WIB)', { exact: true })
        .fill('2026-' + monthCode + '-15T09:00');
    await page.getByLabel('Status berat badan', { exact: true }).selectOption('measured');
    await page.getByLabel('Berat badan (kg)', { exact: true }).fill('50.25');
    await page.getByLabel('Status gula darah', { exact: true }).selectOption('measured');
    await page.getByLabel('Gula darah (mg/dL)', { exact: true }).fill('90');
    await page.getByRole('button', { name: 'Simpan draft pengukuran', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Pengukuran tersimpan sebagai draft',
    );
    await page.getByRole('button', { name: 'Edit draft', exact: true }).click();
    await page.getByLabel('Berat badan (kg)', { exact: true }).fill('51.25');
    await page.getByRole('button', { name: 'Simpan draft pengukuran', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Draft pengukuran diperbarui',
    );
    await expect(page.locator('.visit-card')).toContainText('51.25');
    await page.getByRole('button', { name: 'Finalkan administratif', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'difinalkan secara administratif',
    );

    await openMenu('Data Rekapitulasi');
    await page
        .getByLabel('Periode laporan', { exact: true })
        .selectOption(new URL(periodUrl).pathname.split('/').pop()!);
    await expect(page).toHaveURL(
        '/rekapitulasi?period_id=' + new URL(periodUrl).pathname.split('/').pop()!,
    );
    await expect(
        page.getByRole('row').filter({ hasText: 'Hadir unik dalam sasaran' }),
    ).toContainText('1');
    await expect(page.getByRole('row').filter({ hasText: 'Cakupan kehadiran' })).toContainText(
        '100%',
    );
    await page
        .getByLabel('Alasan / catatan review', { exact: true })
        .fill('Kelengkapan administratif diperiksa');
    await page.getByRole('button', { name: 'Ajukan rekap', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Setujui & kunci', exact: true })).toBeVisible();
    await page
        .getByLabel('Alasan / catatan review', { exact: true })
        .fill('Disetujui untuk demo penguncian');
    await page.getByRole('button', { name: 'Setujui & kunci', exact: true }).click();
    await expect(page.getByText('Arsip versi terkunci', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Buka kembali dengan alasan' })).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('rekap-terkunci.png'), fullPage: true });
    await page.getByRole('button', { name: 'Aktifkan mode gelap' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.screenshot({ path: testInfo.outputPath('rekap-gelap.png'), fullPage: true });
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true);
    await page.goto('/masyarakat');
    await page.screenshot({
        path: testInfo.outputPath('dashboard-nova-gelap.png'),
        fullPage: true,
    });
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true);
    await page.goto(periodUrl);
    await expect(page.getByText('Tambahkan sasaran manual', { exact: true })).toHaveCount(0);
    if (isMobile) {
        await page.getByRole('button', { name: 'Buka menu', exact: true }).click();
        await expect(
            page.getByRole('link', { name: 'Posyandu Jeruk Ruang kerja kader' }),
        ).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('button', { name: 'Buka menu', exact: true })).toBeFocused();
        await page.getByRole('button', { name: 'Buka menu', exact: true }).click();
    }
    await page.getByRole('button', { name: 'Keluar', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goBack();
    await expect(
        page.getByRole('heading', { name: 'Selamat datang kembali', exact: true }),
    ).toBeVisible();
    expect(errors).toEqual([]);
});
