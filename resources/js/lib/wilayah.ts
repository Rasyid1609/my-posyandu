import wilayahData from '../data/wilayah.json';

export type Kecamatan = { kode: string; nama: string };
export type KabupatenKota = { kode: string; nama: string; kecamatan?: Kecamatan[] };
export type Provinsi = { kode: string; nama: string; ibu_kota?: string; kabupaten_kota: KabupatenKota[] };

/**
 * Nilai wilayah per level. `province`/`city`/`district` berisi kode bila dipilih
 * dari data, atau string kosong bila user mengisi bebas (nama manual pada `*Name`).
 */
export type WilayahValue = {
    province: string;
    city: string;
    district: string;
    provinceName?: string;
    cityName?: string;
    districtName?: string;
};

const provinsiList = wilayahData.provinsi as Provinsi[];

export const provinsiWilayah: Provinsi[] = provinsiList;

export function findProvinsi(kode: string): Provinsi | undefined {
    return provinsiList.find((item) => item.kode === kode);
}

export function findKabupatenKota(kodeProvinsi: string, kode: string): KabupatenKota | undefined {
    return findProvinsi(kodeProvinsi)?.kabupaten_kota.find((item) => item.kode === kode);
}

export function findKecamatan(kodeProvinsi: string, kodeKota: string, kode: string): Kecamatan | undefined {
    return findKabupatenKota(kodeProvinsi, kodeKota)?.kecamatan?.find((item) => item.kode === kode);
}

/**
 * Kode wilayah NIK: 2 digit provinsi, 4 digit kab/kota, 6 digit kecamatan
 * (sebagian data lama memakai 7 digit BPS).
 * UI menampilkan dua digit kecamatan/kota, mis. "77 - Kota Madiun", "02 - Manguharjo".
 */
export function wilayahLabel(kode: string, nama: string): string {
    const digits = kode.length >= 7 ? kode.slice(4, 6) : kode.slice(-2);
    return digits + ' - ' + nama;
}

export const defaultWilayah: WilayahValue = {
    province: '35',
    city: '3577',
    district: '357702',
    provinceName: 'Jawa Timur',
    cityName: 'Kota Madiun',
    districtName: 'Manguharjo',
};

export const emptyWilayah: WilayahValue = {
    province: '',
    city: '',
    district: '',
    provinceName: '',
    cityName: '',
    districtName: '',
};

function resolveName(kode: string, freeName: string | undefined): string {
    return freeName || '';
}

/**
 * Membentuk alamat lengkap: alamat dasar + suffix wilayah yang dipilih,
 * mis. "Jl. Melati No. 3, Manguharjo, Kota Madiun, Jawa Timur".
 * Nama diambil dari kode bila cocok dengan data, atau dari teks bebas user.
 */
export function applyWilayahSuffix(baseAddress: string, value: WilayahValue): string {
    const names = [
        resolveName(value.district, value.districtName) ||
            findKecamatan(value.province, value.city, value.district)?.nama,
        resolveName(value.city, value.cityName) || findKabupatenKota(value.province, value.city)?.nama,
        resolveName(value.province, value.provinceName) || findProvinsi(value.province)?.nama,
    ].filter((name): name is string => !!name);
    const base = baseAddress.trim().replace(/,\s*$/, '');
    return [base, ...names].filter(Boolean).join(', ');
}

/**
 * Memisahkan alamat dasar dari suffix wilayah agar form edit tidak menggandakan suffix.
 * Nama yang cocok dengan data dikembalikan sebagai kode; yang tidak cocok tetap dipertahankan
 * sebagai teks bebas.
 */
export function splitWilayahSuffix(address: string): {
    base: string;
    value: WilayahValue;
    matched: boolean;
} {
    const match = address.match(/,\s*([^,]+),\s*([^,]+),\s*([^,]+)\s*$/);
    const value: WilayahValue = { ...defaultWilayah };
    if (match) {
        const [districtName, cityName, provinceName] = [match[1], match[2], match[3]];
        const province = provinsiList.find(
            (item) => item.nama.toLowerCase() === provinceName.toLowerCase(),
        );
        if (province) {
            const city = province.kabupaten_kota.find(
                (item) => item.nama.toLowerCase() === cityName.toLowerCase(),
            );
            const district = city?.kecamatan?.find(
                (item) => item.nama.toLowerCase() === districtName.toLowerCase(),
            );
            value.province = province.kode;
            value.provinceName = province.nama;
            value.city = city?.kode || '';
            value.cityName = city?.nama || cityName;
            value.district = district?.kode || '';
            value.districtName = district?.nama || districtName;
            return {
                base: address.slice(0, match.index).trim().replace(/,\s*$/, ''),
                value,
                matched: true,
            };
        }
        return {
            base: address.slice(0, match.index).trim().replace(/,\s*$/, ''),
            value: {
                ...emptyWilayah,
                provinceName: provinceName,
                cityName: cityName,
                districtName: districtName,
            },
            matched: true,
        };
    }
    return { base: address, value, matched: false };
}
