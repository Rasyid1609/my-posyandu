import { Link, router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import Layout, { usePermission } from '../../components/Layout';
import { Badge, Button, Empty, Errors, Field, Pagination } from '../../components/ui';
import { Input } from '../../components/ui/input';
import { useDraftGuard } from '../../components/useDraftGuard';
import type { Pagination as PageData } from '../../types';

type Household = {
    id: number;
    head_of_household_name: string;
    family_card_display: string | null;
    address: string | null;
    rt: string | null;
    rw: string | null;
    is_active: boolean;
    revision: number;
    residents_count: number;
    detail_url: string;
};
export default function Households({
    households,
    search,
}: {
    households: PageData<Household>;
    search: string;
}) {
    const canManage = usePermission('residents.manage');
    const [editing, setEditing] = useState<number | null>(null);
    const [q, setQ] = useState(search);
    const form = useForm({
        head_of_household_name: '',
        family_card_number: '',
        clear_family_card: false,
        address: '',
        rt: '',
        rw: '',
        is_active: true,
        revision: 1,
    });
    useDraftGuard(form.isDirty, form.processing);
    const current = households.data.find((family) => family.id === editing);
    return (
        <Layout
            title="Keluarga"
            description="Keluarga baru dibuat otomatis saat mendaftarkan warga dengan centang “Kepala Keluarga” di form tambah warga. Di sini keluarga yang ada dapat diedit dan nomor KK-nya dikelola."
            action={<Link href="/masyarakat">Kembali ke masyarakat</Link>}
        >
            {canManage && editing && (
                <form
                    className="panel form-stack"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform(({ family_card_number, clear_family_card, ...data }) => ({
                            ...data,
                            ...(clear_family_card
                                ? { family_card_number: null }
                                : family_card_number
                                  ? { family_card_number }
                                  : {}),
                        }));
                        const options = {
                            onSuccess: () => {
                                form.reset();
                                setEditing(null);
                            },
                        };
                        form.put('/masyarakat/keluarga/' + editing, options);
                    }}
                >
                    <h2>Edit keluarga</h2>
                    <Errors errors={form.errors} />
                    <div className="form-grid">
                        <Field label="Nama kepala keluarga">
                            <input
                                required
                                maxLength={150}
                                value={form.data.head_of_household_name}
                                onChange={(e) =>
                                    form.setData('head_of_household_name', e.target.value)
                                }
                            />
                        </Field>
                        <div>
                            <Field
                                label="Nomor KK (opsional)"
                                error={form.errors.family_card_number}
                                hint="Isi untuk mengganti. Kosongkan untuk mempertahankan nomor tersimpan."
                            >
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    maxLength={16}
                                    pattern="[0-9]{16}"
                                    disabled={form.data.clear_family_card}
                                    value={form.data.family_card_number}
                                    onChange={(e) =>
                                        form.setData('family_card_number', e.target.value)
                                    }
                                />
                            </Field>
                            {editing && (
                                <>
                                    <p>
                                        Nomor tersimpan:{' '}
                                        <span className="mono">
                                            {current?.family_card_display || 'Belum diisi'}
                                        </span>
                                    </p>
                                    <label className="checkbox-field">
                                        <input
                                            type="checkbox"
                                            checked={form.data.clear_family_card}
                                            onChange={(e) =>
                                                form.setData('clear_family_card', e.target.checked)
                                            }
                                        />
                                        Hapus nomor KK tersimpan
                                    </label>
                                </>
                            )}
                        </div>
                        <Field label="Alamat keluarga">
                            <textarea
                                maxLength={1000}
                                value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)}
                            />
                        </Field>
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
                    <p className="helper-note">
                        Nomor KK dipakai bersama oleh anggota yang dihubungkan ke keluarga ini.
                        Nomor lengkap hanya terlihat bagi akun dengan izin khusus.
                    </p>
                    <label className="checkbox-field">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                        />
                        Keluarga aktif
                    </label>
                    <div className="flex gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Simpan keluarga
                        </Button>
                        {editing && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    form.reset();
                                    setEditing(null);
                                }}
                            >
                                Batal edit
                            </Button>
                        )}
                    </div>
                </form>
            )}
            <section className="panel">
                <h2>Daftar keluarga</h2>
                <form
                    className="filters"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/masyarakat/keluarga/daftar', { q });
                    }}
                >
                    <div className="search-input">
                        <Search aria-hidden="true" />
                        <Input
                            aria-label="Cari kepala keluarga"
                            placeholder="Cari kepala keluarga…"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            maxLength={100}
                        />
                    </div>
                    <Button type="submit" variant="outline">
                        Cari keluarga
                    </Button>
                </form>
                {households.data.length ? (
                    <div className="table-scroll" tabIndex={0}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Kepala keluarga</th>
                                    <th>Nomor KK</th>
                                    <th>Alamat</th>
                                    <th>Anggota</th>
                                    <th>Status</th>
                                    <th>Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {households.data.map((family) => (
                                    <tr key={family.id}>
                                        <td>{family.head_of_household_name}</td>
                                        <td className="mono">
                                            {family.family_card_display || 'Belum diisi'}
                                        </td>
                                        <td>
                                            {family.address || '—'}
                                            <small>
                                                RT {family.rt || '—'} / RW {family.rw || '—'}
                                            </small>
                                        </td>
                                        <td>{family.residents_count}</td>
                                        <td>
                                            <Badge muted={!family.is_active}>
                                                {family.is_active ? 'Aktif' : 'Diarsipkan'}
                                            </Badge>
                                        </td>
                                        <td>
                                            <div className="flex items-center gap-2">
                                                <Button asChild variant="outline">
                                                    <Link href={family.detail_url}>
                                                        Detail keluarga
                                                    </Link>
                                                </Button>
                                                {canManage && (
                                                    <Button
                                                        variant="outline"
                                                        onClick={() => {
                                                            if (
                                                                form.isDirty &&
                                                                !window.confirm(
                                                                    'Buang perubahan keluarga yang belum tersimpan?',
                                                                )
                                                            )
                                                                return;
                                                            form.setData({
                                                                head_of_household_name:
                                                                    family.head_of_household_name,
                                                                family_card_number: '',
                                                                clear_family_card: false,
                                                                address: family.address || '',
                                                                rt: family.rt || '',
                                                                rw: family.rw || '',
                                                                is_active: family.is_active,
                                                                revision: family.revision,
                                                            });
                                                            setEditing(family.id);
                                                            window.scrollTo({
                                                                top: 0,
                                                                behavior: 'smooth',
                                                            });
                                                        }}
                                                    >
                                                        Edit keluarga
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty>Belum ada keluarga.</Empty>
                )}
                <Pagination data={households} />
            </section>
        </Layout>
    );
}
