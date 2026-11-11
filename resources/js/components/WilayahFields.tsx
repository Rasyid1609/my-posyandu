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
        if (isFreeTextSelection(selection)) {
            onChange({
                province: '',
                city: '',
                district: '',
                provinceName: selection.freeText,
                cityName: '',
                districtName: '',
            });
        } else {
            onChange({
                province: selection.value,
                city: '',
                district: '',
                provinceName: selection.searchText ?? '',
                cityName: '',
                districtName: '',
            });
        }
    };

    const changeCity = (selection: SearchSelection) => {
        const base = { province: value.province, provinceName: value.provinceName };
        if (isFreeTextSelection(selection)) {
            onChange({
                ...base,
                city: '',
                district: '',
                cityName: selection.freeText,
                districtName: '',
            });
        } else {
            onChange({
                ...base,
                city: selection.value,
                district: '',
                cityName: selection.searchText ?? '',
                districtName: '',
            });
        }
    };

    const changeDistrict = (selection: SearchSelection) => {
        if (isFreeTextSelection(selection)) {
            onChange({ ...value, district: '', districtName: selection.freeText });
        } else {
            onChange({
                ...value,
                district: selection.value,
                districtName: selection.searchText ?? '',
            });
        }
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
                emptyHint="Nama provinsi tidak ada di data"
                allowFreeText
                onSelect={changeProvince}
            />
            <SearchCombobox
                label="Kota / Kabupaten"
                error={errors?.city}
                disabled={disabled || !provinceName}
                options={cityChoices}
                selectedLabel={cityName}
                placeholder="Cari kota / kabupaten…"
                emptyHint="Nama kota/kabupaten tidak ada di data"
                allowFreeText
                hint={
                    provinceName
                        ? province
                            ? undefined
                            : 'Provinsi tidak ada di data; ketik nama kota/kabupaten secara bebas.'
                        : 'Pilih provinsi terlebih dahulu.'
                }
                onSelect={changeCity}
            />
            <SearchCombobox
                label="Kecamatan"
                error={errors?.district}
                disabled={disabled || !cityName}
                options={districtChoices}
                selectedLabel={districtName}
                placeholder="Cari kecamatan…"
                emptyHint="Nama kecamatan tidak ada di data"
                allowFreeText
                hint={
                    cityName
                        ? city
                            ? districtOptions.length
                                ? undefined
                                : 'Data kecamatan belum tersedia; ketik nama kecamatan secara bebas.'
                            : 'Kota/kabupaten tidak ada di data; ketik nama kecamatan secara bebas.'
                        : 'Pilih kota / kabupaten terlebih dahulu.'
                }
                onSelect={changeDistrict}
            />
        </>
    );
}
