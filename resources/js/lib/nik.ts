import { findProvinsi } from './wilayah';

/**
 * Hasil parsing NIK 16 digit untuk pengisian otomatis field terkait.
 * `wilayah` memakai kode+nama agar langsung terpilih di dropdown; level yang
 * tidak dapat dipetakan ke data dikosongkan agar user melengkapinya manual.
 */
export type NikParseResult = {
    gender: 'L' | 'P';
    birthDate: string;
    wilayah: {
        province: string;
        provinceName: string;
        city: string;
        cityName: string;
        district: string;
        districtName: string;
    };
};

const nikGroups = [2, 4, 6, 4];

/**
 * Menampilkan NIK terpenggal "PP-KKKK-DDMMYY-XXXX" dengan underscore pada digit
 * yang belum diisi. Hanya untuk tampilan; nilai form tetap deretan digit polos.
 */
export function formatNikDisplay(raw: string): string {
    const digits = raw.replace(/\D/g, '').slice(0, 16);
    if (!digits) return '';
    let result = '';
    let index = 0;
    nikGroups.forEach((size, groupIndex) => {
        if (groupIndex) result += '-';
        for (let position = 0; position < size; position++) {
            result += digits[index] ?? '_';
            index++;
        }
    });
    return result;
}

/**
 * Memecah NIK 16 digit (UI level saja, tidak menggantikan validasi server):
 * - Digit 1-2   : kode provinsi
 * - Digit 3-4   : kode kab/kota (01-69 kabupaten, 71-99 kota)
 * - Digit 5-6   : kode kecamatan
 * - Digit 7-12  : DDMMYY — perempuan ditulis tanggal lahir + 40
 * - Digit 13-16 : nomor urut
 * Mengembalikan null bila NIK tidak valid sehingga field lain tidak diubah.
 */
export function parseNik(nik: string): NikParseResult | null {
    if (!/^\d{16}$/.test(nik)) return null;

    const province = findProvinsi(nik.slice(0, 2));
    if (!province) return null;

    const kabKota = Number.parseInt(nik.slice(2, 4), 10);
    if (!((kabKota >= 1 && kabKota <= 69) || (kabKota >= 71 && kabKota <= 99))) return null;

    const month = Number.parseInt(nik.slice(8, 10), 10);
    if (month < 1 || month > 12) return null;

    const rawDay = Number.parseInt(nik.slice(6, 8), 10);
    const isFemale = rawDay > 40;
    const day = isFemale ? rawDay - 40 : rawDay;
    if (day < 1 || day > 31) return null;

    if (Number.parseInt(nik.slice(12, 16), 10) < 1) return null;

    const yy = Number.parseInt(nik.slice(10, 12), 10);
    const year = yy > (new Date().getFullYear() % 100) ? 1900 + yy : 2000 + yy;
    const birthDate =
        year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');

    const cityCode = nik.slice(0, 4);
    const city = province.kabupaten_kota.find((item) => item.kode === cityCode);
    const kecamatanDigits = nik.slice(4, 6);
    // Kode kecamatan pada data: 6/7 digit, dua digit kecamatan ada di posisi 5-6.
    // Pemilik kode NIK 6 digit adalah kecamatan induk (diakhiri 0 pada kode BPS).
    const kecamatanCandidates = (city?.kecamatan ?? []).filter(
        (item) => item.kode.slice(4, 6) === kecamatanDigits,
    );
    const district =
        kecamatanCandidates.find((item) => item.kode.endsWith('0')) ?? kecamatanCandidates[0];

    return {
        gender: isFemale ? 'P' : 'L',
        birthDate: birthDate,
        wilayah: {
            province: province.kode,
            provinceName: province.nama,
            city: city?.kode ?? '',
            cityName: city?.nama ?? '',
            district: district?.kode ?? '',
            districtName: district?.nama ?? '',
        },
    };
}
