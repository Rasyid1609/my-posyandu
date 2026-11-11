import { useRef, useState, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { Field } from './ui';
import {
    findProvinsi,
    provinsiWilayah,
    wilayahLabel,
    type WilayahValue,
} from '../lib/wilayah';

type WilayahOption = { kode: string; nama: string };
type WilayahSelection = WilayahOption | { freeText: string };

function isFreeText(selection: WilayahSelection): selection is { freeText: string } {
    return 'freeText' in selection;
}

function normalize(text: string): string {
    return text.trim().toLowerCase();
}

function WilayahCombobox({
    label,
    hint,
    error,
    disabled,
    options,
    selectedName,
    placeholder,
    emptyHint,
    onSelect,
}: {
    label: string;
    hint?: string;
    error?: string;
    disabled?: boolean;
    options: WilayahOption[];
    selectedName: string;
    placeholder?: string;
    emptyHint?: string;
    onSelect: (selection: WilayahSelection) => void;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);

    const search = normalize(query);
    const filtered = search
        ? options.filter(
              (option) =>
                  option.nama.toLowerCase().includes(search) ||
                  wilayahLabel(option.kode, option.nama).toLowerCase().includes(search),
          )
        : options;
    const exactMatch = options.find((option) => option.nama.toLowerCase() === search);
    const canFreeText = search !== '' && !exactMatch;
    const itemCount = filtered.length + (canFreeText ? 1 : 0);
    const showList = open && itemCount > 0;
    const highlight = Math.min(active, itemCount - 1);

    const openList = () => {
        if (disabled) return;
        setQuery(selectedName);
        setActive(0);
        setOpen(true);
    };

    const select = (selection: WilayahSelection) => {
        setOpen(false);
        setQuery('');
        onSelect(selection);
    };

    const commit = () => {
        const text = query.trim();
        const matched = options.find((option) => option.nama.toLowerCase() === normalize(text));
        setOpen(false);
        setQuery('');
        if (matched) onSelect(matched);
        else onSelect({ freeText: text });
    };

    const onKeyDown = (event: ReactKeyboardEvent<HTMLInputElement>) => {
        if (!open) {
            if (['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) {
                event.preventDefault();
                openList();
            }
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => Math.min(index + 1, itemCount - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (highlight < filtered.length && filtered[highlight]) select(filtered[highlight]);
            else if (canFreeText) select({ freeText: query.trim() });
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            setQuery('');
        }
    };

    return (
        <Field label={label} hint={hint} error={error}>
            <div
                className="wilayah-combobox"
                ref={containerRef}
                onBlur={(event) => {
                    if (!containerRef.current?.contains(event.relatedTarget as Node)) commit();
                }}
            >
                <input
                    type="text"
                    role="combobox"
                    aria-expanded={showList}
                    aria-autocomplete="list"
                    autoComplete="off"
                    disabled={disabled}
                    placeholder={placeholder}
                    value={open ? query : selectedName}
                    onFocus={openList}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setActive(0);
                        setOpen(true);
                    }}
                    onKeyDown={onKeyDown}
                />
                {showList && (
                    <ul className="wilayah-combobox-list" role="listbox">
                        {filtered.map((option, index) => (
                            <li key={option.kode} role="option" aria-selected={index === highlight}>
                                <button
                                    type="button"
                                    className={index === highlight ? 'active' : undefined}
                                    tabIndex={-1}
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => select(option)}
                                >
                                    {wilayahLabel(option.kode, option.nama)}
                                </button>
                            </li>
                        ))}
                        {canFreeText && (
                            <li
                                key="free-text"
                                role="option" aria-selected={highlight === filtered.length}>
                                <button
                                    type="button"
                                    className={highlight === filtered.length ? 'active' : undefined}
                                    tabIndex={-1}
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => select({ freeText: query.trim() })}
                                >
                                    Gunakan “{query.trim()}”
                                    {emptyHint && <small>{emptyHint}</small>}
                                </button>
                            </li>
                        )}
                    </ul>
                )}
            </div>
        </Field>
    );
}

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

    const changeProvince = (selection: WilayahSelection) => {
        if (isFreeText(selection)) {
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
                province: selection.kode,
                city: '',
                district: '',
                provinceName: selection.nama,
                cityName: '',
                districtName: '',
            });
        }
    };

    const changeCity = (selection: WilayahSelection) => {
        const base = { province: value.province, provinceName: value.provinceName };
        if (isFreeText(selection)) {
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
                city: selection.kode,
                district: '',
                cityName: selection.nama,
                districtName: '',
            });
        }
    };

    const changeDistrict = (selection: WilayahSelection) => {
        if (isFreeText(selection)) {
            onChange({ ...value, district: '', districtName: selection.freeText });
        } else {
            onChange({ ...value, district: selection.kode, districtName: selection.nama });
        }
    };

    return (
        <>
            <WilayahCombobox
                label="Provinsi"
                error={errors?.province}
                disabled={disabled}
                options={provinsiWilayah}
                selectedName={provinceName}
                placeholder="Cari provinsi…"
                emptyHint="Nama provinsi tidak ada di data"
                onSelect={changeProvince}
            />
            <WilayahCombobox
                label="Kota / Kabupaten"
                error={errors?.city}
                disabled={disabled || !provinceName}
                options={cityOptions}
                selectedName={cityName}
                placeholder="Cari kota / kabupaten…"
                emptyHint="Nama kota/kabupaten tidak ada di data"
                hint={
                    provinceName
                        ? province
                            ? undefined
                            : 'Provinsi tidak ada di data; ketik nama kota/kabupaten secara bebas.'
                        : 'Pilih provinsi terlebih dahulu.'
                }
                onSelect={changeCity}
            />
            <WilayahCombobox
                label="Kecamatan"
                error={errors?.district}
                disabled={disabled || !cityName}
                options={districtOptions}
                selectedName={districtName}
                placeholder="Cari kecamatan…"
                emptyHint="Nama kecamatan tidak ada di data"
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
