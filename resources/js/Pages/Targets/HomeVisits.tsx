import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Layout, { usePermission } from '../../components/Layout';
import { Badge, Button, Empty, Errors, Field, Pagination } from '../../components/ui';
import { useDraftGuard } from '../../components/useDraftGuard';
import { periodLabel, type Period, type Pagination as PageData } from '../../types';
type Findings = {
    resident_id: number;
    no_service: string;
    danger_sign: string;
    tb_symptom: string;
    irregular_medication: string;
};
type Person = { id: number; full_name: string; pivot?: Findings };
type Family = { id: number; head_of_household_name: string; residents?: Person[] };
type HomeVisit = {
    id: number;
    household_id: number;
    household: Family;
    residents: Person[];
    staff: { id: number; name: string }[];
    visited_on: string;
    reason: string;
    follow_up: string | null;
    record_status: string;
};
const findingLabels: Record<string, string> = {
    no_service: 'Tidak mengakses pelayanan',
    danger_sign: 'Tanda bahaya (hasil petugas)',
    tb_symptom: 'Bergejala TBC (hasil petugas)',
    irregular_medication: 'Obat tidak teratur (TBC/HT/DM)',
};
const options: Record<string, string> = {
    unknown: 'Belum diketahui',
    yes: 'Ya',
    no: 'Tidak',
    not_applicable: 'Tidak berlaku',
    declined: 'Menolak',
};
function HomeCorrection({ period, id }: { period: Period; id: number }) {
    const form = useForm({ revision: period.revision, reason: '' });
    return (
        <details>
            <summary>Buka koreksi kunjungan rumah</summary>
            <form
                className="inline-form"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({ ...data, revision: period.revision }));
                    form.post('/sasaran/' + period.id + '/rumah/' + id + '/koreksi');
                }}
            >
                <Field label="Alasan koreksi kunjungan rumah">
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
                    Simpan versi lama kunjungan rumah
                </Button>
            </form>
        </details>
    );
}
export default function HomeVisits({
    period,
    households,
    household,
    visits,
    staff,
    targetCount,
    today,
}: {
    period: Period;
    households: Family[];
    household: Family | null;
    visits: PageData<HomeVisit>;
    staff: { id: number; name: string }[];
    targetCount: number;
    today: string;
}) {
    const canRecord = usePermission('visits.record');
    const canManage = usePermission('targets.manage');
    const canFinalize = usePermission('visits.finalize');
    const canCorrect = usePermission('visits.correct');
    const editable = ['open', 'returned'].includes(period.status);
    const [editing, setEditing] = useState<number | null>(null);
    const [search, setSearch] = useState('');
    const initial = {
        revision: period.revision,
        household_id: household?.id || 0,
        visited_on: today,
        reason: '',
        follow_up: '',
        staff_ids: [] as number[],
        residents: [] as Findings[],
    };
    const form = useForm(initial);
    const action = useForm({ revision: period.revision });
    useDraftGuard(form.isDirty, form.processing);
    const base = '/sasaran/' + period.id + '/rumah';
    return (
        <Layout
            title="Kunjungan rumah"
            description={periodLabel(period)}
            action={<Link href={'/sasaran/' + period.id}>Kembali ke sasaran</Link>}
        >
            <section className="panel">
                <h2>Sasaran keluarga</h2>
                <p>
                    {targetCount} keluarga ditetapkan. Angka keluarga, warga unik, dan kejadian
                    kunjungan dihitung terpisah.
                </p>
                {canManage && editable && (
                    <Button
                        variant="outline"
                        disabled={action.processing}
                        onClick={() => {
                            action.transform(() => ({ revision: period.revision }));
                            action.post('/sasaran/' + period.id + '/keluarga/generate');
                        }}
                    >
                        Bentuk sasaran keluarga aktif
                    </Button>
                )}
                <Errors errors={action.errors} />
            </section>
            <section className="panel">
                <h2>Pilih keluarga</h2>
                <form
                    className="filters"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(base, { q: search });
                    }}
                >
                    <Field label="Cari keluarga">
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            maxLength={100}
                        />
                    </Field>
                    <Button type="submit" variant="outline">
                        Cari
                    </Button>
                </form>
                <Field label="Keluarga kunjungan">
                    <select
                        value={household?.id || ''}
                        onChange={(e) => router.get(base, { household_id: e.target.value })}
                    >
                        <option value="">Pilih keluarga</option>
                        {household && !households.some((f) => f.id === household.id) && (
                            <option value={household.id}>{household.head_of_household_name}</option>
                        )}
                        {households.map((family) => (
                            <option key={family.id} value={family.id}>
                                {family.head_of_household_name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Link href="/masyarakat/keluarga/daftar">
                    Kelola keluarga dan hubungkan anggota melalui detail warga
                </Link>
            </section>
            {household && canRecord && editable && (
                <form
                    className="panel form-stack"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({ ...data, revision: period.revision }));
                        const opts = {
                            onSuccess: () => {
                                form.reset();
                                setEditing(null);
                            },
                        };
                        if (editing) form.put(base + '/' + editing, opts);
                        else form.post(base, opts);
                    }}
                >
                    <h2>{editing ? 'Edit draft kunjungan rumah' : 'Catat kunjungan rumah'}</h2>
                    <Errors errors={form.errors} />
                    <div className="form-grid">
                        <Field label="Tanggal kunjungan rumah">
                            <input
                                required
                                type="date"
                                max={today}
                                value={form.data.visited_on}
                                onChange={(e) => form.setData('visited_on', e.target.value)}
                            />
                        </Field>
                        <Field label="Alasan kunjungan">
                            <input
                                required
                                minLength={5}
                                maxLength={500}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                            />
                        </Field>
                        <Field label="Tindak lanjut">
                            <textarea
                                maxLength={1000}
                                value={form.data.follow_up}
                                onChange={(e) => form.setData('follow_up', e.target.value)}
                            />
                        </Field>
                    </div>
                    <fieldset>
                        <legend>Petugas (boleh lebih dari satu)</legend>
                        <div className="form-grid">
                            {staff.map((person) => (
                                <label key={person.id} className="checkbox-field">
                                    <input
                                        type="checkbox"
                                        checked={form.data.staff_ids.includes(person.id)}
                                        onChange={(e) =>
                                            form.setData(
                                                'staff_ids',
                                                e.target.checked
                                                    ? [...form.data.staff_ids, person.id]
                                                    : form.data.staff_ids.filter(
                                                          (id) => id !== person.id,
                                                      ),
                                            )
                                        }
                                    />
                                    {person.name}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>Warga yang benar-benar dikunjungi</legend>
                        {household.residents?.map((person) => {
                            const row = form.data.residents.find(
                                (r) => r.resident_id === person.id,
                            );
                            return (
                                <article className="visit-card" key={person.id}>
                                    <label className="checkbox-field">
                                        <input
                                            type="checkbox"
                                            checked={!!row}
                                            onChange={(e) =>
                                                form.setData(
                                                    'residents',
                                                    e.target.checked
                                                        ? [
                                                              ...form.data.residents,
                                                              {
                                                                  resident_id: person.id,
                                                                  no_service: 'unknown',
                                                                  danger_sign: 'unknown',
                                                                  tb_symptom: 'unknown',
                                                                  irregular_medication: 'unknown',
                                                              },
                                                          ]
                                                        : form.data.residents.filter(
                                                              (r) => r.resident_id !== person.id,
                                                          ),
                                                )
                                            }
                                        />
                                        {person.full_name}
                                    </label>
                                    {row && (
                                        <div className="form-grid">
                                            {Object.entries(findingLabels).map(([key, label]) => (
                                                <Field key={key} label={label}>
                                                    <select
                                                        value={row[key as keyof Findings]}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'residents',
                                                                form.data.residents.map((r) =>
                                                                    r.resident_id === person.id
                                                                        ? {
                                                                              ...r,
                                                                              [key]: e.target.value,
                                                                          }
                                                                        : r,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        {Object.entries(options).map(
                                                            ([key, value]) => (
                                                                <option key={key} value={key}>
                                                                    {value}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </Field>
                                            ))}
                                        </div>
                                    )}
                                </article>
                            );
                        })}
                    </fieldset>
                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Simpan draft kunjungan rumah
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
                <h2>Riwayat kunjungan rumah</h2>
                {visits.data.length ? (
                    visits.data.map((visit) => (
                        <article className="visit-card" key={visit.id}>
                            <div className="section-heading">
                                <h3>
                                    {visit.household.head_of_household_name} · {visit.visited_on}
                                </h3>
                                <Badge muted={visit.record_status === 'draft'}>
                                    {visit.record_status === 'draft'
                                        ? 'Draft'
                                        : 'Final administratif'}
                                </Badge>
                            </div>
                            <p>
                                {visit.reason} · Tindak lanjut: {visit.follow_up || '—'}
                            </p>
                            <p>Petugas: {visit.staff.map((p) => p.name).join(', ')}</p>
                            {visit.residents.map((person) => (
                                <details key={person.id}>
                                    <summary>{person.full_name}</summary>
                                    {Object.entries(findingLabels).map(([key, label]) => (
                                        <p key={key}>
                                            {label}:{' '}
                                            {
                                                options[
                                                    String(
                                                        person.pivot?.[key as keyof Findings] ||
                                                            'unknown',
                                                    )
                                                ]
                                            }
                                        </p>
                                    ))}
                                </details>
                            ))}
                            {visit.record_status === 'draft' && editable && (
                                <div className="flex flex-wrap gap-3">
                                    {canRecord &&
                                        (household?.id === visit.household_id ? (
                                            <Button
                                                variant="outline"
                                                onClick={() => {
                                                    form.setData({
                                                        revision: period.revision,
                                                        household_id: visit.household_id,
                                                        visited_on: visit.visited_on.slice(0, 10),
                                                        reason: visit.reason,
                                                        follow_up: visit.follow_up || '',
                                                        staff_ids: visit.staff.map((p) => p.id),
                                                        residents: visit.residents.map((p) => ({
                                                            resident_id: p.id,
                                                            no_service:
                                                                p.pivot?.no_service || 'unknown',
                                                            danger_sign:
                                                                p.pivot?.danger_sign || 'unknown',
                                                            tb_symptom:
                                                                p.pivot?.tb_symptom || 'unknown',
                                                            irregular_medication:
                                                                p.pivot?.irregular_medication ||
                                                                'unknown',
                                                        })),
                                                    });
                                                    setEditing(visit.id);
                                                    window.scrollTo({ top: 0, behavior: 'smooth' });
                                                }}
                                            >
                                                Edit draft rumah
                                            </Button>
                                        ) : (
                                            <Link
                                                href={base + '?household_id=' + visit.household_id}
                                            >
                                                Pilih keluarga untuk edit
                                            </Link>
                                        ))}
                                    {canFinalize && (
                                        <Button
                                            disabled={action.processing}
                                            onClick={() => {
                                                if (
                                                    !window.confirm(
                                                        'Finalkan administratif kunjungan rumah ini?',
                                                    )
                                                )
                                                    return;
                                                action.transform(() => ({
                                                    revision: period.revision,
                                                }));
                                                action.post(base + '/' + visit.id + '/finalisasi');
                                            }}
                                        >
                                            Finalkan kunjungan rumah
                                        </Button>
                                    )}
                                </div>
                            )}
                            {canCorrect && editable && visit.record_status === 'finalized' && (
                                <HomeCorrection period={period} id={visit.id} />
                            )}
                        </article>
                    ))
                ) : (
                    <Empty>Belum ada kunjungan rumah.</Empty>
                )}
                <Pagination data={visits} />
            </section>
        </Layout>
    );
}
