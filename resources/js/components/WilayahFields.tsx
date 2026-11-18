import {
    findProvinsi,
    provinsiWilayah,
    wilayahLabel,
    type WilayahValue,
} from '../lib/wilayah';
import SearchCombobox, {
    isFreeTextSelection,
    type SearchOption,
    type SearchSelection,
} from './SearchCombobox';

export default function WilayahFields({
    value,
    onChange,
    disabled,
    errors,
}: {
    value: WilayahValue;
    onChange: (next: WilayahValue) => void;
    disabled?: boolean;
    errors?: { province?: string; city?: string; district?: string };
}) {
    const province = findProvinsi(value.province);
    const cityOptions = province?.kabupaten_kota ?? [];
    const city = cityOptions.find((item) => item.kode === value.city);
    const districtOptions = city?.kecamatan ?? [];
    const provinceName = value.provinceName || province?.nama || '';
    const cityName = value.cityName || city?.nama || '';
    const districtName = value.districtName || '';

    const provinceChoices: SearchOption[] = provinsiWilayah.map((item) => ({
        value: item.kode,
        label: item.kode + ' - ' + item.nama,
        searchText: item.nama,
    }));
    const cityChoices: SearchOption[] = cityOptions.map((item) => ({
        value: item.kode,
        label: wilayahLabel(item.kode, item.nama),
        searchText: item.nama,
    }));
    const districtChoices: SearchOption[] = districtOptions.map((item) => ({
        value: item.kode,
        label: wilayahLabel(item.kode, item.nama),
        searchText: item.nama,
    }));

    const changeProvince = (selection: SearchSelection) => {
        if (isFreeTextSelection(selection)) return;
        onChange({
            province: selection.value,
            city: '',
            district: '',
            provinceName: selection.searchText ?? '',
            cityName: '',
            districtName: '',
        });
    };

    const changeCity = (selection: SearchSelection) => {
        if (isFreeTextSelection(selection)) return;
        onChange({
            province: value.province,
            provinceName: value.provinceName,
            city: selection.value,
            district: '',
            cityName: selection.searchText ?? '',
            districtName: '',
        });
    };

    const changeDistrict = (selection: SearchSelection) => {
        if (isFreeTextSelection(selection)) return;
        onChange({
            ...value,
            district: selection.value,
            districtName: selection.searchText ?? '',
        });
    };

    return (
        <>
            <SearchCombobox
                label="Provinsi"
                error={errors?.province}
                disabled={disabled}
                options={provinceChoices}
                selectedLabel={provinceName}
                placeholder="Cari provinsi…"
                hint={
                    provinceName && !province
                        ? 'Provinsi tersimpan tidak ada di master data; pilih ulang provinsi.'
                        : undefined
                }
                onSelect={changeProvince}
            />
            <SearchCombobox
                label="Kota / Kabupaten"
                error={errors?.city}
                disabled={disabled || !province}
                options={cityChoices}
                selectedLabel={cityName}
                placeholder="Cari kota / kabupaten…"
                hint={
                    province
                        ? undefined
                        : 'Pilih provinsi dari master data terlebih dahulu.'
                }
                onSelect={changeCity}
            />
            <SearchCombobox
                label="Kecamatan"
                error={errors?.district}
                disabled={disabled || !city}
                options={districtChoices}
                selectedLabel={districtName}
                placeholder="Cari kecamatan…"
                hint={city ? undefined : 'Pilih kota / kabupaten terlebih dahulu.'}
                onSelect={changeDistrict}
            />
        </>
    );
}
