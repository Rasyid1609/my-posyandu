import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Activities, { type Activity } from '../../components/Activities';
const categoryLabels: Record<string, string> = {
    infant: 'Bayi',
    toddler: 'Balita',
    preschool: 'Prasekolah',
    school: 'Usia sekolah',
    adolescent: 'Remaja',
    adult: 'Dewasa',
    elderly: 'Lansia',
    general: 'Usia belum diketahui',
};
import Layout, { usePermission } from '../../components/Layout';
import { Badge, Button, Empty, Errors, Field, Pagination } from '../../components/ui';
import {
    periodLabel,
    statusLabels,
    type Period,
    type Target,
    type Resident,
    type Pagination as PageData,
} from '../../types';

function TargetRow({ target, period }: { target: Target; period: Period }) {
    const canManage = usePermission('targets.manage');
    const canView = usePermission('visits.view');
    const form = useForm({
        revision: period.revision,
        attendance_status: target.attendance_status,
        reason: '',
    });
    const editable = ['open', 'returned'].includes(period.status) && !target.is_excluded;
    return (
        <tr>
            <td>
                <strong>{target.resident.full_name}</strong>
                <small>{target.resident.registration_number}</small>
            </td>
            <td>
                {target.age_in_years_snapshot === null
                    ? 'Belum diketahui / estimasi'
                    : target.age_in_years_snapshot + ' tahun'}
                <small>{categoryLabels[target.category_code]}</small>
            </td>
            <td>
                <Badge muted={target.attendance_status !== 'present'}>
                    {target.is_excluded ? 'Dikeluarkan' : statusLabels[target.attendance_status]}
                </Badge>
            </td>
            <td>{target.visits_count}</td>
            <td>
                <div className="row-actions">
                    {!target.is_excluded && canView && (
                        <Link
                            href={'/sasaran/' + period.id + '/anggota/' + target.id + '/kunjungan'}
                        >
                            Pelayanan
                        </Link>
                    )}
                    {canManage && editable && (
                        <details>
                            <summary>Kelola kehadiran</summary>
                            <form
                                className="inline-form"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.transform((data) => ({
                                        ...data,
                                        revision: period.revision,
                                    }));
                                    form.patch('/sasaran/' + period.id + '/anggota/' + target.id, {
                                        onSuccess: () => form.reset('reason'),
                                    });
                                }}
                            >
                                <Field label="Kehadiran">
                                    <select
                                        value={form.data.attendance_status}
                                        onChange={(e) =>
                                            form.setData('attendance_status', e.target.value)
                                        }
                                    >
                                        {['unconfirmed', 'present', 'absent'].map((status) => (
                                            <option key={status} value={status}>
                                                {statusLabels[status]}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Alasan perubahan">
                                    <input
                                        required
                                        minLength={5}
                                        maxLength={500}
                                        value={form.data.reason}
                                        onChange={(e) => form.setData('reason', e.target.value)}
                                    />
                                </Field>
                                <Errors errors={form.errors} />
                                <Button type="submit" disabled={form.processing}>
                                    Simpan kehadiran
                                </Button>
                                <Button
                                    variant="outline"
                                    disabled={form.processing || form.data.reason.trim().length < 5}
                                    onClick={() => {
                                        if (
                                            !window.confirm(
                                                'Perbarui usia/status maternal snapshot dari data warga dengan aturan periode? Rekap periode terbuka dapat berubah.',
                                            )
                                        )
                                            return;
                                        form.transform((data) => ({
                                            ...data,
                                            revision: period.revision,
                                        }));
                                        form.post(
                                            '/sasaran/' +
                                                period.id +
                                                '/anggota/' +
                                                target.id +
                                                '/snapshot',
                                        );
                                    }}
                                >
                                    Perbarui snapshot sasaran
                                </Button>
                                {target.visits_count === 0 && (
                                    <Button
                                        variant="outline"
                                        disabled={
                                            form.processing || form.data.reason.trim().length < 5
                                        }
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    'Keluarkan warga dari sasaran periode ini dengan alasan yang ditulis?',
                                                )
                                            )
                                                form.transform((data) => ({
                                                    ...data,
                                                    revision: period.revision,
                                                }));
                                            form.delete(
                                                '/sasaran/' + period.id + '/anggota/' + target.id,
                                            );
                                        }}
                                    >
                                        Keluarkan sasaran
                                    </Button>
                                )}
                            </form>
                        </details>
                    )}
                </div>
            </td>
        </tr>
    );
}
export default function Show({
    period,
    targets,
    candidates,
    search,
    activities,
}: {
    period: Period;
    targets: PageData<Target>;
    candidates: Resident[];
    search: string;
    activities: Activity[];
}) {
    const canManage = usePermission('targets.manage');
    const generate = useForm({ revision: period.revision });
    const [q, setQ] = useState(search);
    const form = useForm({ resident_id: '', reason: '', revision: period.revision });
    const editable = ['open', 'returned'].includes(period.status);
    return (
        <Layout
            title="Data Sasaran"
            description={
                periodLabel(period) + ' · Tanggal acuan ' + period.reference_date.slice(0, 10)
            }
            action={<Link href="/sasaran">Semua periode</Link>}
        >
            <div className="flex flex-wrap gap-4">
                <Link href={'/sasaran/' + period.id + '/rumah'}>Kunjungan rumah</Link>
                {canManage && editable && (
                    <Button
                        variant="outline"
                        disabled={generate.processing}
                        onClick={() => {
                            generate.transform(() => ({ revision: period.revision }));
                            generate.post('/sasaran/' + period.id + '/generate');
                        }}
                    >
                        Bentuk sasaran otomatis
                    </Button>
                )}
                <Errors errors={generate.errors} />
            </div>
            <div className="period-heading">
                <h2>{periodLabel(period)}</h2>
                <Badge>{statusLabels[period.status]}</Badge>
                <span>Revisi {period.revision}</span>
                <Link href={'/rekapitulasi?period_id=' + period.id}>Lihat rekap →</Link>
            </div>
            {!editable && (
                <div className="info-box">
                    Periode {statusLabels[period.status].toLowerCase()}. Sumber hanya dapat diubah
                    setelah dikembalikan atau dibuka kembali melalui review.
                </div>
            )}
            {canManage && editable && (
                <section className="panel">
                    <h2>Tambahkan sasaran manual</h2>
                    <p className="subtle">
                        Kelompok usia memakai versi sementara periode ini. Penambahan ulang tidak
                        menggandakan sasaran.
                    </p>
                    <form
                        className="filters"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get('/sasaran/' + period.id, { q }, { preserveState: true });
                        }}
                    >
                        <input
                            aria-label="Cari calon sasaran"
                            placeholder="Cari warga aktif…"
                            maxLength={100}
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                        <Button type="submit" variant="outline">
                            Cari warga
                        </Button>
                    </form>
                    <form
                        className="form-grid"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((data) => ({ ...data, revision: period.revision }));
                            form.post('/sasaran/' + period.id + '/anggota', {
                                onSuccess: () => form.reset('resident_id', 'reason'),
                            });
                        }}
                    >
                        <Field label="Warga aktif" error={form.errors.resident_id}>
                            <select
                                required
                                value={form.data.resident_id}
                                onChange={(e) => form.setData('resident_id', e.target.value)}
                            >
                                <option value="">Pilih warga (maksimal 30 hasil pencarian)</option>
                                {candidates.map((resident) => (
                                    <option key={resident.id} value={resident.id}>
                                        {resident.full_name} · {resident.registration_number}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Alasan pemilihan sasaran" error={form.errors.reason}>
                            <input
                                required
                                minLength={5}
                                maxLength={500}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                            />
                        </Field>
                        <div>
                            <Button type="submit" disabled={form.processing}>
                                Tambahkan sasaran
                            </Button>
                        </div>
                        <Errors errors={form.errors} />
                    </form>
                </section>
            )}
            <Activities period={period} activities={activities} />
            <section className="panel">
                <h2>Daftar sasaran & kehadiran</h2>
                {targets.data.length ? (
                    <div className="table-scroll" tabIndex={0}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Warga</th>
                                    <th>Snapshot pada tanggal acuan</th>
                                    <th>Kehadiran</th>
                                    <th>Kunjungan</th>
                                    <th>Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {targets.data.map((target) => (
                                    <TargetRow key={target.id} target={target} period={period} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty>Belum ada sasaran pada periode ini.</Empty>
                )}
                <Pagination data={targets} />
            </section>
        </Layout>
    );
}
