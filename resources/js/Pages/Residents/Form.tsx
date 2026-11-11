import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import MaternalEpisodes from '../../components/MaternalEpisodes';
import WilayahFields from '../../components/WilayahFields';
import SearchCombobox, { isFreeTextSelection } from '../../components/SearchCombobox';
import Layout, { usePermission } from '../../components/Layout';
import { Badge, Button, Errors, Field } from '../../components/ui';
import { useDraftGuard } from '../../components/useDraftGuard';
import { localDateTime, statusLabels, type Resident, type Visit } from '../../types';
import { formatNikDisplay, parseNik } from '../../lib/nik';
import {
    applyWilayahSuffix,
    defaultWilayah,
    splitWilayahSuffix,
    type WilayahValue,
} from '../../lib/wilayah';

export default function Form({
    resident,
    identityDetails,
    history,
    visits,
    today,
    households,
    guardians,
    episodes,
}: {
    resident: Resident | null;
    identityDetails: {
        national_id_number: string | null;
        phone_number: string | null;
        family_card_number: string | null;
    } | null;
    history: { status: string; effective_date: string; reason: string }[];
    visits: Visit[];
    today: string;
    households: {
        id: number;
        head_of_household_name: string;
        address?: string | null;
        rt?: string | null;
        rw?: string | null;
        head_phone?: string | null;
        family_card_display?: string | null;
    }[];
    guardians: { id: number; full_name: string; household_id: number }[];
    episodes: {
        id: number;
        episode_type: string;
        start_date: string;
        end_date: string | null;
        source: string;
    }[];
}) {
    const canManage = usePermission('residents.manage');
    const initialAddress = splitWilayahSuffix(resident?.address || '');
    const [wilayah, setWilayah] = useState<WilayahValue>(
        resident ? initialAddress.value : defaultWilayah,
    );
    const initialGuardian = guardians.find(
        (person) =>
            person.id === resident?.guardian_id && person.household_id === resident?.household_id,
    );
    const form = useForm({
        full_name: resident?.full_name || '',
        gender: resident?.gender || 'L',
        national_id_number: '',
        phone_number: '',
        clear_national_id: false,
        clear_phone: false,
        birth_date: resident?.birth_date?.slice(0, 10) || '',
        is_birth_date_estimated: resident?.is_birth_date_estimated || false,
        household_id: String(resident?.household_id || ''),
        guardian_id: String(initialGuardian?.id || ''),
        family_relationship: resident?.family_relationship || '',
        is_head_of_household: false,
        family_card_number: '',
        address: initialAddress.base,
        rt: resident?.rt || '',
        rw: resident?.rw || '',
        residency_status: resident?.residency_status || 'active',
        effective_date: today,
        reason: '',
        revision: resident?.revision || 1,
    });
    const familyGuardians = form.data.household_id
        ? guardians.filter((person) => person.household_id === Number(form.data.household_id))
        : [];
    const guardianHint =
        resident?.guardian_id && !initialGuardian && !form.data.guardian_id
            ? 'Wali sebelumnya tidak sesuai keluarga. Pilih ulang wali jika diperlukan.'
            : !form.data.household_id
              ? 'Pilih keluarga terlebih dahulu.'
              : !familyGuardians.length
                ? 'Belum ada anggota keluarga yang dapat dipilih sebagai wali.'
                : 'Pilihan wali berasal dari anggota keluarga yang dipilih.';
    useDraftGuard(form.isDirty, form.processing);

    // Satu arah: NIK → field terkait. Perubahan tanggal lahir/jenis kelamin/wilayah
    // tidak mengubah NIK.
    const handleNikChange = (digits: string) => {
        form.setData('national_id_number', digits);
        const parsed = parseNik(digits);
        if (parsed) {
            form.setData((data) => ({
                ...data,
                gender: parsed.gender,
                birth_date: parsed.birthDate,
            }));
            setWilayah((current) => ({
                ...current,
                ...parsed.wilayah,
            }));
        }
    };

    // Menghubungkan warga ke keluarga, lalu mengisi alamat & kontak dari data
    // yang tercatat atas kepala keluarganya (level UI; anggota bebas mengubahnya).
    const applyHouseholdLink = (value: string) => {
        form.setData({ ...form.data, household_id: value, guardian_id: '' });
        form.clearErrors('household_id', 'guardian_id');
        const family = households.find((item) => String(item.id) === value);
        if (!family) return;
        const parsed = splitWilayahSuffix(family.address || '');
        form.setData((data) => ({
            ...data,
            address: parsed.base,
            rt: family.rt || data.rt,
            rw: family.rw || data.rw,
            phone_number: family.head_phone || data.phone_number,
        }));
        if (parsed.matched) setWilayah(parsed.value);
    };

    // Tampilan No KK: masukan kepala keluarga memakai mask; anggota melihat
    // nomor KK keluarga terpilih (penuh bila berwenang, tersamar bila tidak).
    const selectedFamilyCard =
        households.find((family) => String(family.id) === form.data.household_id)
            ?.family_card_display || '';
    const familyCardValue = form.data.is_head_of_household
        ? formatNikDisplay(form.data.family_card_number)
        : /^\d{16}$/.test(selectedFamilyCard)
          ? formatNikDisplay(selectedFamilyCard)
          : selectedFamilyCard;

    return (
        <Layout
            title={resident ? 'Detail warga' : 'Tambah warga'}
            description="Simpan identitas sekali. Pengukuran dan pelayanan dicatat melalui Data Sasaran."
            action={<Link href="/masyarakat">Kembali ke daftar</Link>}
        >
            <form
                className="form-stack"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (
                        resident &&
                        form.data.residency_status !== resident.residency_status &&
                        !window.confirm('Simpan perubahan status warga beserta riwayatnya?')
                    )
                        return;
                    form.transform(
                        ({
                            national_id_number,
                            phone_number,
                            clear_national_id,
                            clear_phone,
                            address,
                            family_card_number,
                            ...data
                        }) => ({
                            ...data,
                            address: applyWilayahSuffix(address, wilayah),
                            // KK hanya dikirim saat mendaftarkan kepala keluarga
                            // (menempel pada keluarga baru, bukan pada warga).
                            ...(data.is_head_of_household && family_card_number
                                ? { family_card_number }
                                : {}),
                            ...(clear_national_id
                                ? { national_id_number: null }
                                : national_id_number
                                  ? { national_id_number }
                                  : {}),
                            ...(clear_phone
                                ? { phone_number: null }
                                : phone_number
                                  ? { phone_number }
                                  : {}),
                        }),
                    );
                    const options = {
                        onSuccess: () => {
                            form.setDefaults();
                            form.setData('is_head_of_household', false);
                        },
                    };
                    if (resident) form.put('/masyarakat/' + resident.id, options);
                    else form.post('/masyarakat', options);
                }}
            >
                <Errors errors={form.errors} />
                <section className="panel">
                    <div className="section-heading">
                        <h2>01 · Identitas dasar</h2>
                        {resident && <Badge muted>{resident.registration_number}</Badge>}
                    </div>
                    <fieldset disabled={!canManage} className="form-grid">
                        <Field label="Nama lengkap" error={form.errors.full_name}>
                            <input
                                required
                                maxLength={150}
                                value={form.data.full_name}
                                onChange={(e) => form.setData('full_name', e.target.value)}
                                autoComplete="off"
                            />
                        </Field>
                        <div>
                            <Field
                                label="NIK (opsional)"
                                error={form.errors.national_id_number}
                                hint={
                                    resident
                                        ? 'Isi untuk mengganti. Kosongkan untuk mempertahankan nomor tersimpan.'
                                        : 'NIK bersifat opsional dan tidak menggantikan nomor registrasi.'
                                }
                            >
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    className="mono"
                                    disabled={form.data.clear_national_id}
                                    value={formatNikDisplay(form.data.national_id_number)}
                                    onChange={(e) =>
                                        handleNikChange(
                                            e.target.value.replace(/\D/g, '').slice(0, 16),
                                        )
                                    }
                                    onKeyDown={(e) => {
                                        // Backspace di posisi tanda hubung/underscore: hapus
                                        // digit sebelum kursor, karena karakter mask akan
                                        // di-render ulang dan tanpa ini input terasa macet.
                                        if (e.key !== 'Backspace') return;
                                        const mask = formatNikDisplay(form.data.national_id_number);
                                        const caret = e.currentTarget.selectionStart ?? 0;
                                        if (caret < 1 || !'-_'.includes(mask[caret - 1])) return;
                                        e.preventDefault();
                                        const digitsBeforeCaret = mask
                                            .slice(0, caret - 1)
                                            .replace(/\D/g, '');
                                        handleNikChange(digitsBeforeCaret.slice(0, -1));
                                    }}
                                />
                            </Field>
                            {resident && (
                                <label className="checkbox-field">
                                    <input
                                        type="checkbox"
                                        checked={form.data.clear_national_id}
                                        onChange={(e) =>
                                            form.setData('clear_national_id', e.target.checked)
                                        }
                                    />
                                    Hapus NIK tersimpan
                                </label>
                            )}
                        </div>
                        <Field label="Jenis kelamin" error={form.errors.gender}>
                            <select
                                value={form.data.gender}
                                onChange={(e) =>
                                    form.setData('gender', e.target.value as 'L' | 'P')
                                }
                            >
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </Field>
                        <div>
                            <Field
                                label="Tanggal lahir"
                                hint="Kosongkan jika belum diketahui. Jangan membuat tanggal pengganti."
                                error={form.errors.birth_date}
                            >
                                <input
                                    type="date"
                                    max={today}
                                    value={form.data.birth_date}
                                    onChange={(e) => form.setData('birth_date', e.target.value)}
                                />
                            </Field>
                            <label
                                className="checkbox-field"
                                style={{ justifyContent: 'flex-end' }}
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.is_birth_date_estimated}
                                    onChange={(e) =>
                                        form.setData('is_birth_date_estimated', e.target.checked)
                                    }
                                />
                                Tanggal lahir merupakan estimasi
                            </label>
                        </div>
                    </fieldset>
                </section>
                <section className="panel">
                    <h2>02 · Keluarga</h2>
                    {!resident && (
                        <label className="checkbox-field">
                            <input
                                type="checkbox"
                                checked={form.data.is_head_of_household}
                                onChange={(e) => {
                                    const checked = e.target.checked;
                                    form.setData((data) => ({
                                        ...data,
                                        is_head_of_household: checked,
                                        household_id: '',
                                        guardian_id: '',
                                        family_relationship: checked ? 'Kepala Keluarga' : '',
                                    }));
                                    form.clearErrors('household_id', 'guardian_id');
                                }}
                            />
                            Kepala Keluarga
                        </label>
                    )}
                    <div className="form-grid">
                        <SearchCombobox
                            label="Keluarga"
                            error={form.errors.household_id}
                            disabled={!canManage || form.data.is_head_of_household}
                            options={[
                                { value: '', label: 'Belum dihubungkan' },
                                ...households.map((family) => ({
                                    value: String(family.id),
                                    label: family.head_of_household_name,
                                })),
                            ]}
                            selectedLabel={
                                households.find(
                                    (family) => String(family.id) === form.data.household_id,
                                )?.head_of_household_name || ''
                            }
                            placeholder="Cari keluarga…"
                            onSelect={(selection) => {
                                if (isFreeTextSelection(selection)) return;
                                applyHouseholdLink(selection.value);
                            }}
                        />
                        <Field
                            label="Orang tua / wali"
                            error={form.errors.guardian_id}
                            hint={guardianHint}
                        >
                            <select
                                disabled={
                                    !canManage ||
                                    form.data.is_head_of_household ||
                                    !form.data.household_id ||
                                    !familyGuardians.length
                                }
                                value={form.data.guardian_id}
                                onChange={(e) => form.setData('guardian_id', e.target.value)}
                            >
                                <option value="">
                                    {form.data.household_id
                                        ? 'Belum dihubungkan'
                                        : 'Pilih keluarga terlebih dahulu'}
                                </option>
                                {familyGuardians.map((person) => (
                                    <option key={person.id} value={person.id}>
                                        {person.full_name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <div>
                            <Field
                                label="No KK (opsional)"
                                error={form.errors.family_card_number}
                                hint={
                                    form.data.is_head_of_household
                                        ? '16 digit jika tersedia; tercatat pada keluarga baru.'
                                        : 'Nomor KK dicatat satu kali pada keluarga.'
                                }
                            >
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    className="mono"
                                    disabled={!form.data.is_head_of_household}
                                    value={familyCardValue}
                                    onChange={(e) =>
                                        form.setData(
                                            'family_card_number',
                                            e.target.value.replace(/\D/g, '').slice(0, 16),
                                        )
                                    }
                                    onKeyDown={(e) => {
                                        // Backspace di posisi mask: hapus digit sebelum
                                        // kursor, sama seperti pada field NIK.
                                        if (e.key !== 'Backspace') return;
                                        const mask = formatNikDisplay(form.data.family_card_number);
                                        const caret = e.currentTarget.selectionStart ?? 0;
                                        if (caret < 1 || !'-_'.includes(mask[caret - 1])) {
                                            return;
                                        }
                                        e.preventDefault();
                                        const digitsBeforeCaret = mask
                                            .slice(0, caret - 1)
                                            .replace(/\D/g, '');
                                        form.setData(
                                            'family_card_number',
                                            digitsBeforeCaret.slice(0, -1),
                                        );
                                    }}
                                />
                            </Field>
                        </div>
                        <Field label="Hubungan dalam keluarga">
                            <input
                                disabled={!canManage || form.data.is_head_of_household}
                                maxLength={30}
                                value={form.data.family_relationship}
                                onChange={(e) =>
                                    form.setData('family_relationship', e.target.value)
                                }
                            />
                        </Field>
                    </div>
                    <p className="helper-note">
                        <Link href="/masyarakat/keluarga/daftar">Kelola keluarga dan nomor KK</Link>{' '}
                        · Kelompok usia memakai konfigurasi sementara berversi.
                    </p>
                </section>
                <section className="panel">
                    <h2>03 · Alamat dan status kependudukan</h2>
                    <fieldset disabled={!canManage} className="form-grid">
                        <Field label="Alamat" error={form.errors.address}>
                            <textarea
                                maxLength={1000}
                                value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)}
                            />
                        </Field>
                        <Field
                            label="Alasan pencatatan / perubahan"
                            error={form.errors.reason}
                            hint="Wajib untuk jejak audit. Hindari menuliskan data kesehatan sensitif."
                        >
                            <textarea
                                required
                                minLength={5}
                                maxLength={500}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                            />
                        </Field>
                        <WilayahFields
                            value={wilayah}
                            onChange={setWilayah}
                            disabled={!canManage}
                        />
                        <div className="form-grid">
                            <Field label="RT">
                                <input
                                    maxLength={10}
                                    value={form.data.rt}
                                    onChange={(e) => form.setData('rt', e.target.value)}
                                />
                            </Field>
                            <Field label="RW">
                                <input
                                    maxLength={10}
                                    value={form.data.rw}
                                    onChange={(e) => form.setData('rw', e.target.value)}
                                />
                            </Field>
                        </div>
                        <Field label="Status warga" error={form.errors.residency_status}>
                            <select
                                value={form.data.residency_status}
                                onChange={(e) => form.setData('residency_status', e.target.value)}
                            >
                                {['active', 'moved', 'deceased', 'inactive'].map((value) => (
                                    <option key={value} value={value}>
                                        {statusLabels[value]}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Tanggal efektif" error={form.errors.effective_date}>
                            <input
                                required
                                type="date"
                                max={today}
                                value={form.data.effective_date}
                                onChange={(e) => form.setData('effective_date', e.target.value)}
                            />
                        </Field>
                    </fieldset>
                    <p className="helper-note">
                        Pilih “Diarsipkan” untuk menonaktifkan warga. Riwayat kunjungan dan laporan
                        terkunci tetap tersedia.
                    </p>
                </section>
                <section className="panel">
                    <h2>Kontak</h2>
                    <p className="subtle">Boleh kosong.</p>
                    {resident && (
                        <dl>
                            <dt>NIK tersimpan</dt>
                            <dd className="mono">
                                {identityDetails?.national_id_number || 'Belum diisi'}
                            </dd>
                            <dt>Kontak tersimpan</dt>
                            <dd className="mono">
                                {identityDetails?.phone_number || 'Belum diisi'}
                            </dd>
                            <dt>Nomor KK keluarga</dt>
                            <dd className="mono">
                                {identityDetails?.family_card_number ||
                                    'Belum diisi / belum dihubungkan'}
                            </dd>
                        </dl>
                    )}
                    <fieldset disabled={!canManage} className="form-grid">
                        <div>
                            <Field
                                label="Kontak telepon (opsional)"
                                error={form.errors.phone_number}
                                hint={
                                    resident
                                        ? 'Isi untuk mengganti; kosong mempertahankan kontak tersimpan.'
                                        : 'Nomor warga atau orang tua/wali yang dapat dihubungi.'
                                }
                            >
                                <input
                                    type="tel"
                                    autoComplete="off"
                                    maxLength={30}
                                    disabled={form.data.clear_phone}
                                    value={form.data.phone_number}
                                    onChange={(e) => form.setData('phone_number', e.target.value)}
                                />
                            </Field>
                            {resident && (
                                <label className="checkbox-field">
                                    <input
                                        type="checkbox"
                                        checked={form.data.clear_phone}
                                        onChange={(e) =>
                                            form.setData('clear_phone', e.target.checked)
                                        }
                                    />
                                    Hapus kontak tersimpan
                                </label>
                            )}
                        </div>
                    </fieldset>
                    <p className="helper-note">
                        Nomor lengkap hanya terlihat bagi akun dengan izin khusus; akun lainnya
                        melihat nomor tersamar.
                    </p>
                </section>
                {canManage && (
                    <div className="form-actions">
                        <Link href="/masyarakat">Batal</Link>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Menyimpan…' : 'Simpan warga'}
                        </Button>
                    </div>
                )}
            </form>
            {resident?.gender === 'P' && (
                <MaternalEpisodes
                    resident={resident}
                    episodes={episodes}
                    today={today}
                    canManage={canManage}
                />
            )}
            {!!history.length && (
                <section className="panel">
                    <h2>Riwayat status</h2>
                    <div className="table-scroll" tabIndex={0}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Efektif</th>
                                    <th>Status</th>
                                    <th>Alasan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.map((row, index) => (
                                    <tr key={index}>
                                        <td>{row.effective_date}</td>
                                        <td>{statusLabels[row.status]}</td>
                                        <td>{row.reason}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
            {!!visits.length && (
                <section className="panel">
                    <h2>Riwayat kunjungan terbaru</h2>
                    {visits.map((visit) => (
                        <div className="history-row" key={visit.id}>
                            <span>{localDateTime(visit.visited_at)}</span>
                            <Badge muted>{statusLabels[visit.record_status]}</Badge>
                        </div>
                    ))}
                </section>
            )}
        </Layout>
    );
}
