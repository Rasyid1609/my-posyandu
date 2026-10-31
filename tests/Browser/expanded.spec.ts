import { expect, test } from '@playwright/test';
import { writeFile } from 'node:fs/promises';

test('keluarga → maternal → kegiatan → layanan → rumah → lima rekap → ekspor → analisis → import', async ({
    page,
}, testInfo) => {
    test.setTimeout(360_000);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('dialog', (dialog) => dialog.accept());
    const month = testInfo.project.name === 'desktop' ? '3' : '4';
    const code = month.padStart(2, '0');
    const family = 'DEMO Keluarga Layanan ' + testInfo.project.name;
    const person = 'DEMO Ibu Layanan ' + testInfo.project.name;
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('ketua@demo.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Demo-Jeruk-2026!');
    await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
    await expect(page).toHaveURL(/\/masyarakat$/);
    await page.getByRole('link', { name: 'Data keluarga & anggota' }).click();
    await page.getByLabel('Nama kepala keluarga', { exact: true }).fill(family);
    const kk = testInfo.project.name === 'desktop' ? '0123456789098765' : '0123456789098766';
    await page.getByLabel('Nomor KK (opsional)', { exact: true }).fill(kk);
    await page.getByRole('button', { name: 'Simpan keluarga', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Keluarga disimpan',
    );
    await page
        .getByRole('row')
        .filter({ hasText: family })
        .getByRole('link', { name: 'Detail keluarga', exact: true })
        .click();
    await expect(page.getByRole('heading', { name: 'Detail keluarga', exact: true })).toBeVisible();
    const familyDetailUrl = page.url();
    await expect(page.getByRole('region', { name: family, exact: true })).toContainText(kk);
    await expect(
        page.getByText('Belum ada anggota yang dihubungkan ke keluarga ini.', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('Belum ada kunjungan rumah untuk keluarga ini.', { exact: true }),
    ).toBeVisible();
    await page.goto('/masyarakat/create');
    await page.getByLabel('Nama lengkap', { exact: true }).fill(person);
    await page.getByLabel('Jenis kelamin', { exact: true }).selectOption('P');
    await page.getByLabel('Tanggal lahir', { exact: true }).fill('1995-01-01');
    await page.getByLabel('Keluarga', { exact: true }).selectOption({ label: family });
    await page
        .getByLabel('Alasan pencatatan / perubahan', { exact: true })
        .fill('Data layanan sintetis browser');
    await page.getByRole('button', { name: 'Simpan warga', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Warga berhasil ditambahkan',
    );
    await page
        .getByRole('row')
        .filter({ hasText: person })
        .getByRole('link', { name: 'Detail', exact: true })
        .click();
    await expect(page.getByText(kk, { exact: true })).toBeVisible();
    await page.getByLabel('Tanggal mulai episode', { exact: true }).fill('2026-' + code + '-01');
    await page
        .getByLabel('Sumber penetapan episode', { exact: true })
        .fill('Catatan bidan sintetis');
    await page.getByRole('button', { name: 'Tambah episode', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Episode ditambahkan',
    );
    await page.goto('/sasaran');
    await page.getByLabel('Bulan', { exact: true }).selectOption(month);
    await page.getByLabel('Tahun', { exact: true }).fill('2026');
    await page.getByRole('button', { name: 'Buat periode', exact: true }).click();
    await expect(page).toHaveURL(/\/sasaran\/\d+$/);
    const periodPath = new URL(page.url()).pathname;
    const id = periodPath.split('/').pop()!;
    await page.getByRole('button', { name: 'Bentuk sasaran otomatis', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Sasaran dibentuk',
    );
    await expect(page.getByRole('row').filter({ hasText: person })).toContainText('Dewasa');
    await page.getByText('Tambah kegiatan Posyandu', { exact: true }).click();
    await page.getByLabel('Nama kegiatan', { exact: true }).fill('DEMO Pelayanan ILP');
    await page.getByLabel('Tanggal kegiatan', { exact: true }).fill('2026-' + code + '-15');
    await page.getByRole('button', { name: 'Simpan kegiatan', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Kegiatan Posyandu ditambahkan',
    );
    await page
        .getByRole('row')
        .filter({ hasText: person })
        .getByRole('link', { name: 'Pelayanan', exact: true })
        .click();
    await page
        .getByLabel('Tanggal & waktu kunjungan (WIB)', { exact: true })
        .fill('2026-' + code + '-15T09:00');
    await page
        .getByLabel('Kegiatan terkait', { exact: true })
        .selectOption({ label: 'DEMO Pelayanan ILP · 2026-' + code + '-15' });
    await page.getByLabel('Mendapat edukasi', { exact: true }).selectOption('yes');
    await page.getByLabel('Menerima TTD', { exact: true }).selectOption('yes');
    await page.getByLabel('Dirujuk oleh petugas', { exact: true }).selectOption('yes');
    await page.getByLabel('Nama petugas pemberi hasil', { exact: true }).fill('DEMO Bidan');
    await page
        .getByLabel('Sumber hasil / dokumen pemeriksaan', { exact: true })
        .fill('Pemeriksaan sintetis');
    await page.getByLabel('Tujuan rujukan', { exact: true }).fill('DEMO Puskesmas Manguharjo');
    await page
        .getByLabel('Alasan rujukan', { exact: true })
        .fill('Verifikasi hasil petugas sintetis');
    await page.getByRole('button', { name: 'Simpan draft pengukuran', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Pengukuran tersimpan',
    );
    await page.getByRole('button', { name: 'Finalkan administratif', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText('difinalkan');
    await page.goto('/sasaran/' + id + '/rumah');
    await page.getByLabel('Keluarga kunjungan', { exact: true }).selectOption({ label: family });
    await expect(
        page.getByRole('heading', { name: 'Catat kunjungan rumah', exact: true }),
    ).toBeVisible();
    await page.getByLabel('Tanggal kunjungan rumah', { exact: true }).fill('2026-' + code + '-17');
    await page
        .getByLabel('Alasan kunjungan', { exact: true })
        .fill('Tindak lanjut layanan sintetis');
    await page.getByRole('checkbox', { name: 'DEMO Ketua', exact: true }).check();
    await page.getByRole('checkbox', { name: person, exact: true }).check();
    await page.getByLabel('Tidak mengakses pelayanan', { exact: true }).selectOption('no');
    await page.getByRole('button', { name: 'Simpan draft kunjungan rumah', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Kunjungan rumah disimpan',
    );
    await page.getByRole('button', { name: 'Finalkan kunjungan rumah', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'difinalkan administratif',
    );
    await page.screenshot({ path: testInfo.outputPath('kunjungan-rumah.png'), fullPage: true });
    await page.goto(familyDetailUrl);
    await expect(page.getByRole('region', { name: 'Anggota keluarga', exact: true })).toContainText(
        person,
    );
    await expect(
        page.getByRole('region', { name: 'Riwayat kunjungan rumah', exact: true }),
    ).toContainText('Tindak lanjut layanan sintetis');
    await expect(
        page.getByRole('region', { name: 'Riwayat kunjungan rumah', exact: true }),
    ).toContainText('Final administratif');
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true);
    await page.screenshot({ path: testInfo.outputPath('detail-keluarga.png'), fullPage: true });
    await page.getByRole('button', { name: 'Aktifkan mode gelap' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.screenshot({
        path: testInfo.outputPath('detail-keluarga-gelap.png'),
        fullPage: true,
    });
    await page.getByRole('button', { name: 'Aktifkan mode terang' }).click();
    await page
        .getByRole('region', { name: 'Anggota keluarga', exact: true })
        .getByRole('link', { name: 'Detail warga', exact: true })
        .click();
    await expect(page.getByLabel('Nama lengkap', { exact: true })).toHaveValue(person);
    await page.goto(familyDetailUrl);
    await page.getByRole('link', { name: 'Buka layanan periode', exact: true }).click();
    await expect(page.getByLabel('Keluarga kunjungan', { exact: true })).toHaveValue(
        new URL(familyDetailUrl).pathname.split('/').pop()!,
    );
    await page.goto('/rekapitulasi?period_id=' + id);
    const report = page
        .locator('section')
        .filter({ has: page.getByRole('heading', { name: 'Lima rekap pelayanan', exact: true }) });
    await page.getByLabel('Jenis rekap', { exact: true }).selectOption('maternal');
    await expect(report.getByRole('row').filter({ hasText: 'ttd_received.yes' })).toContainText(
        '1',
    );
    for (const type of ['children', 'youth', 'adults', 'maternal', 'home']) {
        await page.getByLabel('Jenis rekap', { exact: true }).selectOption(type);
        await expect(report.getByRole('table')).toBeVisible();
    }
    const xlsxEvent = page.waitForEvent('download', { timeout: 60_000 });
    await page.getByRole('link', { name: 'Unduh XLSX', exact: true }).click({ timeout: 60_000 });
    const xlsx = await xlsxEvent;
    await xlsx.saveAs(testInfo.outputPath('rekap-rumah.xlsx'));
    const pdfLink = page.getByRole('link', { name: 'Unduh PDF', exact: true });
    await expect(pdfLink).toBeVisible();
    const pdfResponse = await page.request.get((await pdfLink.getAttribute('href'))!, {
        timeout: 60_000,
    });
    expect(pdfResponse.ok()).toBe(true);
    expect(pdfResponse.headers()['content-type']).toContain('application/pdf');
    expect(pdfResponse.headers()['content-disposition']).toContain('attachment');
    const pdfBytes = await pdfResponse.body();
    expect(pdfBytes.subarray(0, 5).toString()).toBe('%PDF-');
    await writeFile(testInfo.outputPath('rekap-rumah.pdf'), pdfBytes);
    await page.getByRole('link', { name: 'Buka analisis & review', exact: true }).click();
    await page.getByLabel('Lingkup analisis', { exact: true }).selectOption('all');
    await page.getByRole('button', { name: 'Buat ringkasan lokal', exact: true }).click();
    await expect(page.getByRole('heading', { name: /Ringkasan lokal — bukan AI/ })).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Antrekan analisis AI', exact: true }),
    ).toBeDisabled();
    await page.goto('/rekapitulasi/' + id + '/import');
    await page.getByLabel('Jenis formulir historis', { exact: true }).selectOption('adults');
    await page
        .getByLabel('Nama / keterangan sumber', { exact: true })
        .fill('DEMO Historis Browser');
    await page.getByRole('button', { name: 'Buat draft untuk review', exact: true }).click();
    await expect(page.getByRole('heading', { name: /Review draft/ })).toBeVisible();
    await page.getByRole('button', { name: 'Tambah baris draft', exact: true }).click();
    await page.getByLabel('Indikator baris 1', { exact: true }).selectOption('targets');
    await page.getByLabel('Jumlah (kosong = belum diketahui) baris 1', { exact: true }).fill('12');
    await page.getByRole('button', { name: 'Simpan perubahan draft', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText(
        'Perubahan draft tersimpan',
    );
    await page.getByRole('checkbox', { name: /Saya sudah memeriksa sumber/ }).check();
    await page.getByRole('button', { name: 'Commit import terverifikasi', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText('di-commit');
    await page.getByLabel('Jenis laporan untuk sumber', { exact: true }).selectOption('adults');
    const sourceOption = page
        .getByLabel('Sumber yang ditetapkan', { exact: true })
        .locator('option')
        .filter({ hasText: 'DEMO Historis Browser' });
    await page
        .getByLabel('Sumber yang ditetapkan', { exact: true })
        .selectOption((await sourceOption.getAttribute('value'))!);
    await page
        .getByLabel('Alasan penetapan sumber', { exact: true })
        .fill('Sumber demo sudah dicocokkan');
    await page.getByRole('button', { name: 'Tetapkan sumber laporan', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Notifikasi/ })).toContainText('Sumber resmi');
    await page.goto('/rekapitulasi?period_id=' + id);
    await page.getByLabel('Jenis rekap', { exact: true }).selectOption('adults');
    await expect(page.getByText(/Sumber: import historis #/)).toBeVisible();
    await expect(report.getByRole('row').filter({ hasText: 'Sasaran unik' })).toContainText('12');
    await page.screenshot({ path: testInfo.outputPath('rekap-historis.png'), fullPage: true });
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true);
    expect(errors).toEqual([]);
});
