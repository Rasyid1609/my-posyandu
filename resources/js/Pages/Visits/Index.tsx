import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Layout, { usePermission } from '../../components/Layout';
import ClinicalCalculationResults from '../../components/ClinicalCalculationResults';
import { useClinicalCalculation } from '../../components/useClinicalCalculation';
import { Badge, Button, Empty, Errors, Field, Pagination } from '../../components/ui';
import { useDraftGuard } from '../../components/useDraftGuard';
import {
    localDateTime,
    periodLabel,
    statusLabels,
    type Period,
    type Target,
    type Visit,
    type Pagination as PageData,
} from '../../types';

const groups = [
    {
        label: 'Berat badan',
        state: 'weight_state',
        fields: [{ key: 'weight_kg', label: 'Berat badan (kg)' }],
    },
    {
        label: 'Tinggi / panjang badan',
        state: 'height_state',
        fields: [{ key: 'height_cm', label: 'Tinggi / panjang (cm)' }],
    },
    {
        label: 'Tekanan darah',
        state: 'blood_pressure_state',
        fields: [
            { key: 'systolic_mmhg', label: 'Sistolik (mmHg)' },
            { key: 'diastolic_mmhg', label: 'Diastolik (mmHg)' },
        ],
    },
    {
        label: 'Gula darah',
        state: 'glucose_state',
        fields: [{ key: 'glucose_mg_dl', label: 'Gula darah (mg/dL)' }],
    },
    {
        label: 'Lingkar kepala',
        state: 'head_state',
        fields: [{ key: 'head_cm', label: 'Lingkar kepala (cm)' }],
    },
    {
        label: 'Lingkar lengan atas',
        state: 'arm_state',
        fields: [{ key: 'arm_cm', label: 'Lingkar lengan atas (cm)' }],
    },
    {
        label: 'Lingkar perut',
        state: 'waist_state',
        fields: [{ key: 'waist_cm', label: 'Lingkar perut (cm)' }],
    },
];
type ClinicalField = { label: string; options: Record<string, string>; sensitive: boolean };
const textValue = (value: unknown, fallback = '') => (typeof value === 'string' ? value : fallback);

function FinalizeVisit({ period, visit }: { period: Period; visit: Visit }) {
    const form = useForm({ revision: period.revision, calculation_reviewed: false });
    const details = visit.examination.calculation_details;
    const needsReview =
        details?.mode === 'automatic' &&
        Object.values(details.results ?? {}).some((result) => result.status === 'calculated');
    return (
        <div className="space-y-3">
            {needsReview && (
                <label className="flex items-start gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.calculation_reviewed}
                        onChange={(e) => form.setData('calculation_reviewed', e.target.checked)}
                    />
                    Saya sudah memeriksa angka dan hasil hitung otomatis pada kunjungan ini.
                </label>
            )}
            <Errors errors={form.errors} />
            <Button
                disabled={form.processing || (needsReview && !form.data.calculation_reviewed)}
                onClick={() => {
                    if (!window.confirm('Finalkan catatan ini secara administratif?')) return;
                    form.transform((data) => ({ ...data, revision: period.revision }));
                    form.post('/sasaran/' + period.id + '/kunjungan/' + visit.id + '/finalisasi');
                }}
            >
                Finalkan administratif
            </Button>
        </div>
    );
}
function Correction({ period, visit }: { period: Period; visit: Visit }) {
    const form = useForm({ revision: period.revision, reason: '' });
    return (
        <details>
            <summary>Buka koreksi final</summary>
            <form
                className="inline-form"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({ ...data, revision: period.revision }));
                    form.post('/sasaran/' + period.id + '/kunjungan/' + visit.id + '/koreksi');
                }}
            >
                <Field label="Alasan koreksi final">
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
                    Simpan versi lama & buka draft
                </Button>
            </form>
        </details>
    );
}
export default function Index({
    period,
    target,
    visits,
    localNow,
    clinicalFields,
    automaticFields,
    categoryLabels,
    activities,
}: {
    period: Period;
    target: Target;
    visits: PageData<Visit>;
    localNow: string;
    clinicalFields: Record<string, ClinicalField>;
    automaticFields: string[];
    categoryLabels: Record<string, string>;
    activities: { id: number; name: string; scheduled_on: string }[];
}) {
    const canRecord = usePermission('visits.record');
    const canFinalize = usePermission('visits.finalize');
    const canCorrect = usePermission('visits.correct');
    const [editing, setEditing] = useState<number | null>(null);
    const initial: Record<string, string> = {
        revision: String(period.revision),
        visited_at: localNow,
        assessment_mode: 'automatic',
        height_method: 'unknown',
        glucose_context: 'unknown',
        blood_pressure_confirmed: '0',
        calculation_override_reason: '',
        activity_session_id: '',
        assessor_name: '',
        assessment_source: '',
        referral_destination: '',
        referral_reason: '',
        follow_up_date: '',
        follow_up_status: '',
        follow_up_note: '',
    };
    groups.forEach((group) => {
        initial[group.state] = 'not_examined';
        group.fields.forEach((field) => {
            initial[field.key] = '';
        });
    });
    Object.keys(clinicalFields).forEach((key) => {
        initial[key] = 'not_examined';
    });
    const form = useForm<Record<string, string>>(initial);
    useDraftGuard(form.isDirty, form.processing);
    const editable = ['open', 'returned'].includes(period.status) && !target.is_excluded;
    const baseUrl = '/sasaran/' + period.id + '/anggota/' + target.id + '/kunjungan';
    const automatic = form.data.assessment_mode === 'automatic';
    const calculationKeys = [
        'visited_at',
        'height_method',
        'glucose_context',
        'blood_pressure_confirmed',
        ...groups.flatMap((group) => [group.state, ...group.fields.map((field) => field.key)]),
    ];
    const calculationInput = JSON.stringify({
        ...Object.fromEntries(calculationKeys.map((key) => [key, form.data[key]])),
        visit_id: editing ? String(editing) : '',
    });
    const calculation = useClinicalCalculation(
        '/sasaran/' + period.id + '/anggota/' + target.id + '/perhitungan',
        calculationInput,
        canRecord && editable && automatic,
    );
    return (
        <Layout
            title="Pelayanan individu"
            description={
                target.resident.full_name +
                ' · ' +
                periodLabel(period) +
                ' · ' +
                categoryLabels[target.category_code]
            }
            action={<Link href={'/sasaran/' + period.id}>Kembali ke sasaran</Link>}
        >
            <div className="info-box">
                <strong>Pengukuran dan hasil hitung.</strong> Aplikasi membantu menghitung indikator
                dengan acuan Kemenkes sesuai usia dan konteks pemeriksaan. Hasil lain tetap dicatat
                dari petugas. Finalisasi merupakan review administratif, bukan penetapan diagnosis.
            </div>
            {canRecord && editable && (
                <form
                    className="form-stack"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({ ...data, revision: String(period.revision) }));
                        const options = {
                            onSuccess: () => {
                                form.reset();
                                setEditing(null);
                            },
                        };
                        if (editing) form.put(baseUrl + '/' + editing, options);
                        else form.post(baseUrl, options);
                    }}
                >
                    <Errors errors={form.errors} />
                    <section className="panel">
                        <h2>{editing ? 'Edit draft #' + editing : 'Catat kunjungan baru'}</h2>
                        <Field
                            label="Tanggal & waktu kunjungan (WIB)"
                            error={form.errors.visited_at}
                        >
                            <input
                                type="datetime-local"
                                required
                                max={localNow}
                                value={form.data.visited_at}
                                onChange={(e) => form.setData('visited_at', e.target.value)}
                            />
                        </Field>
                        <Field label="Kegiatan terkait">
                            <select
                                value={form.data.activity_session_id}
                                onChange={(e) =>
                                    form.setData('activity_session_id', e.target.value)
                                }
                            >
                                <option value="">Kunjungan tanpa jadwal khusus</option>
                                {activities.map((activity) => (
                                    <option key={activity.id} value={activity.id}>
                                        {activity.name} · {activity.scheduled_on}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <p className="subtle">Tanggal wajib berada dalam {periodLabel(period)}.</p>
                        <Field label="Cara pengisian hasil">
                            <select
                                value={form.data.assessment_mode}
                                onChange={(e) => form.setData('assessment_mode', e.target.value)}
                            >
                                <option value="automatic">Hitung otomatis dari pengukuran</option>
                                <option value="manual">Salin hasil pemeriksaan petugas</option>
                            </select>
                        </Field>
                        {!automatic &&
                            editing &&
                            visits.data.find((visit) => visit.id === editing)?.examination
                                .calculation_details?.mode === 'automatic' && (
                                <Field
                                    label="Alasan mengganti hasil otomatis"
                                    error={form.errors.calculation_override_reason}
                                >
                                    <textarea
                                        required
                                        minLength={5}
                                        maxLength={500}
                                        value={form.data.calculation_override_reason}
                                        onChange={(e) =>
                                            form.setData(
                                                'calculation_override_reason',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            )}
                        <div className="measurement-grid">
                            {groups.map((group) => (
                                <fieldset className="measurement-card" key={group.state}>
                                    <legend>{group.label}</legend>
                                    <Field
                                        label={'Status ' + group.label.toLowerCase()}
                                        error={form.errors[group.state]}
                                    >
                                        <select
                                            value={form.data[group.state]}
                                            onChange={(e) => {
                                                const state = e.target.value;
                                                form.setData((current) => ({
                                                    ...current,
                                                    [group.state]: state,
                                                    ...Object.fromEntries(
                                                        group.fields.map((field) => [
                                                            field.key,
                                                            state === 'measured'
                                                                ? current[field.key]
                                                                : '',
                                                        ]),
                                                    ),
                                                    ...(group.state === 'blood_pressure_state'
                                                        ? { blood_pressure_confirmed: '0' }
                                                        : {}),
                                                }));
                                            }}
                                        >
                                            {[
                                                'not_examined',
                                                'measured',
                                                'unknown',
                                                'not_applicable',
                                                'declined',
                                            ].map((state) => (
                                                <option value={state} key={state}>
                                                    {statusLabels[state]}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    {form.data[group.state] === 'measured' && (
                                        <>
                                            {group.fields.map((field) => (
                                                <Field
                                                    label={field.label}
                                                    key={field.key}
                                                    error={form.errors[field.key]}
                                                >
                                                    <input
                                                        type="number"
                                                        required
                                                        min={0.01}
                                                        step="0.01"
                                                        value={form.data[field.key]}
                                                        onChange={(e) => {
                                                            const value = e.target.value;
                                                            form.setData((current) => ({
                                                                ...current,
                                                                [field.key]: value,
                                                                ...(group.state ===
                                                                'blood_pressure_state'
                                                                    ? {
                                                                          blood_pressure_confirmed:
                                                                              '0',
                                                                      }
                                                                    : {}),
                                                            }));
                                                        }}
                                                    />
                                                </Field>
                                            ))}
                                            {group.state === 'height_state' && (
                                                <Field
                                                    label="Cara mengukur panjang / tinggi"
                                                    error={form.errors.height_method}
                                                >
                                                    <select
                                                        value={form.data.height_method}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'height_method',
                                                                e.target.value,
                                                            )
                                                        }
                                                    >
                                                        <option value="unknown">
                                                            Belum dicatat
                                                        </option>
                                                        <option value="recumbent">
                                                            Terlentang (panjang badan)
                                                        </option>
                                                        <option value="standing">
                                                            Berdiri (tinggi badan)
                                                        </option>
                                                    </select>
                                                </Field>
                                            )}
                                            {group.state === 'glucose_state' && (
                                                <Field
                                                    label="Jenis pemeriksaan gula darah"
                                                    error={form.errors.glucose_context}
                                                >
                                                    <select
                                                        value={form.data.glucose_context}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'glucose_context',
                                                                e.target.value,
                                                            )
                                                        }
                                                    >
                                                        <option value="unknown">
                                                            Belum diketahui
                                                        </option>
                                                        <option value="fasting_plasma">
                                                            Plasma vena puasa (minimal 8 jam)
                                                        </option>
                                                        <option value="ttgo_2h_plasma">
                                                            Plasma vena 2 jam TTGO 75 g
                                                        </option>
                                                        <option value="random_capillary">
                                                            Darah kapiler sewaktu (glukometer)
                                                        </option>
                                                    </select>
                                                </Field>
                                            )}
                                            {group.state === 'blood_pressure_state' && (
                                                <label className="flex items-start gap-2 text-xs">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            form.data.blood_pressure_confirmed ===
                                                            '1'
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'blood_pressure_confirmed',
                                                                e.target.checked ? '1' : '0',
                                                            )
                                                        }
                                                    />
                                                    Nilai sudah diperiksa ulang dan dirata-ratakan
                                                    sesuai prosedur pengukuran.
                                                </label>
                                            )}
                                        </>
                                    )}
                                </fieldset>
                            ))}
                        </div>
                    </section>
                    {automatic && (
                        <ClinicalCalculationResults
                            details={calculation.details}
                            pending={calculation.pending}
                            failed={calculation.failed}
                        />
                    )}
                    <section className="panel">
                        <h2>Pelayanan & hasil pemeriksaan</h2>
                        <p className="subtle">
                            Kolom bertanda otomatis mengikuti perhitungan di atas. Hasil lain dan
                            pelayanan yang diberikan tetap diisi petugas. Nama serta sumber wajib
                            untuk hasil yang disalin dari petugas.
                        </p>
                        <div className="form-grid">
                            {Object.entries(clinicalFields).map(([code, field]) => {
                                const calculated = automatic && automaticFields.includes(code);
                                return (
                                    <Field
                                        key={code}
                                        label={
                                            calculated
                                                ? field.label.replace(
                                                      /\s*\(hasil petugas\)/gi,
                                                      '',
                                                  ) + ' · otomatis'
                                                : field.label
                                        }
                                        error={form.errors[code]}
                                        hint={
                                            calculated
                                                ? 'Dihitung ulang di server saat disimpan. Gunakan hasil rinci di atas untuk interpretasi.'
                                                : undefined
                                        }
                                    >
                                        <select
                                            disabled={calculated}
                                            value={
                                                calculated
                                                    ? (calculation.details?.field_values?.[code] ??
                                                      'unknown')
                                                    : form.data[code]
                                            }
                                            onChange={(e) => {
                                                form.setData(code, e.target.value);
                                                if (
                                                    code === 'referral_result' &&
                                                    e.target.value !== 'yes'
                                                )
                                                    [
                                                        'referral_destination',
                                                        'referral_reason',
                                                        'follow_up_date',
                                                        'follow_up_status',
                                                        'follow_up_note',
                                                    ].forEach((key) => form.setData(key, ''));
                                            }}
                                        >
                                            {Object.entries(field.options).map(([value, label]) => (
                                                <option key={value} value={value}>
                                                    {calculated
                                                        ? label.replace(
                                                              /\s*\(hasil petugas\)/gi,
                                                              '',
                                                          )
                                                        : label}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                );
                            })}
                        </div>
                        <div className="form-grid">
                            <Field
                                label="Nama petugas pemberi hasil"
                                error={form.errors.assessor_name}
                            >
                                <input
                                    maxLength={150}
                                    value={form.data.assessor_name}
                                    onChange={(e) => form.setData('assessor_name', e.target.value)}
                                />
                            </Field>
                            <Field
                                label="Sumber hasil / dokumen pemeriksaan"
                                error={form.errors.assessment_source}
                            >
                                <input
                                    maxLength={500}
                                    value={form.data.assessment_source}
                                    onChange={(e) =>
                                        form.setData('assessment_source', e.target.value)
                                    }
                                />
                            </Field>
                        </div>
                    </section>
                    {form.data.referral_result === 'yes' && (
                        <section className="panel">
                            <h2>Rujukan & tindak lanjut</h2>
                            <div className="form-grid">
                                {[
                                    ['referral_destination', 'Tujuan rujukan'],
                                    ['referral_reason', 'Alasan rujukan'],
                                    ['follow_up_note', 'Catatan tindak lanjut'],
                                ].map(([key, label]) => (
                                    <Field key={key} label={label}>
                                        <input
                                            required={key !== 'follow_up_note'}
                                            maxLength={key === 'referral_destination' ? 200 : 1000}
                                            value={form.data[key]}
                                            onChange={(e) => form.setData(key, e.target.value)}
                                        />
                                    </Field>
                                ))}
                                <Field label="Tanggal tindak lanjut">
                                    <input
                                        type="date"
                                        value={form.data.follow_up_date}
                                        onChange={(e) =>
                                            form.setData('follow_up_date', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Status tindak lanjut">
                                    <select
                                        value={form.data.follow_up_status}
                                        onChange={(e) =>
                                            form.setData('follow_up_status', e.target.value)
                                        }
                                    >
                                        <option value="">Belum ditetapkan</option>
                                        {Object.entries({
                                            pending: 'Menunggu',
                                            scheduled: 'Terjadwal',
                                            completed: 'Selesai',
                                            declined: 'Menolak',
                                        }).map(([key, label]) => (
                                            <option key={key} value={key}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                        </section>
                    )}
                    <div className="form-actions">
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
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Menyimpan…' : 'Simpan draft pengukuran'}
                        </Button>
                    </div>
                </form>
            )}
            <section className="panel">
                <h2>Riwayat kunjungan</h2>
                <p className="subtle">
                    Beberapa kunjungan pada satu bulan tetap dihitung sebagai satu peserta unik
                    hadir.
                </p>
                {visits.data.length ? (
                    visits.data.map((visit) => (
                        <article className="visit-card" key={visit.id}>
                            <div className="section-heading">
                                <div>
                                    <strong>Kunjungan #{visit.id}</strong>
                                    <p>{localDateTime(visit.visited_at)} WIB</p>
                                </div>
                                <Badge muted={visit.record_status === 'draft'}>
                                    {statusLabels[visit.record_status]}
                                </Badge>
                            </div>
                            <div className="visit-measurements">
                                {groups.map((group) => (
                                    <div key={group.state}>
                                        <small>{group.label}</small>
                                        <strong>
                                            {
                                                statusLabels[
                                                    textValue(
                                                        visit.examination[group.state],
                                                        'not_examined',
                                                    )
                                                ]
                                            }
                                        </strong>
                                        {visit.examination[group.state] === 'measured' &&
                                            group.fields.map((field) => (
                                                <span key={field.key}>
                                                    {field.label}:{' '}
                                                    {textValue(
                                                        visit.examination[field.key],
                                                        'Belum diisi',
                                                    )}
                                                </span>
                                            ))}
                                    </div>
                                ))}
                            </div>
                            <details>
                                <summary>Hasil pelayanan & sumber</summary>
                                {visit.examination.calculation_details?.mode === 'automatic' && (
                                    <ClinicalCalculationResults
                                        details={visit.examination.calculation_details}
                                    />
                                )}
                                {visit.examination.calculation_details?.override_reason && (
                                    <p className="subtle">
                                        Alasan mengganti hasil otomatis:{' '}
                                        {visit.examination.calculation_details.override_reason}
                                    </p>
                                )}
                                <dl>
                                    {Object.entries(clinicalFields).map(([code, field]) => {
                                        const calculated =
                                            visit.examination.calculation_details?.mode ===
                                                'automatic' && automaticFields.includes(code);
                                        const label =
                                            field.options[
                                                textValue(visit.examination[code], 'not_examined')
                                            ];
                                        return (
                                            <div className="history-row" key={code}>
                                                <dt>
                                                    {calculated
                                                        ? field.label.replace(
                                                              /\s*\(hasil petugas\)/gi,
                                                              '',
                                                          ) + ' · hasil hitung'
                                                        : field.label}
                                                </dt>
                                                <dd>
                                                    {calculated
                                                        ? label?.replace(
                                                              /\s*\(hasil petugas\)/gi,
                                                              '',
                                                          )
                                                        : label}
                                                </dd>
                                            </div>
                                        );
                                    })}
                                </dl>
                                <p>
                                    Petugas: {textValue(visit.examination.assessor_name, '—')} ·
                                    Sumber: {textValue(visit.examination.assessment_source, '—')}
                                </p>
                                {visit.referral && (
                                    <p>
                                        Rujukan: {visit.referral.destination} ·{' '}
                                        {visit.referral.reason} · Tindak lanjut:{' '}
                                        {visit.referral.follow_up_status}
                                    </p>
                                )}
                            </details>
                            {visit.record_status === 'draft' && editable && (
                                <div className="flex flex-wrap gap-3">
                                    {canRecord && (
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                const data = { ...initial };
                                                Object.keys(data).forEach((key) => {
                                                    if (key in visit.examination)
                                                        data[key] = textValue(
                                                            visit.examination[key],
                                                            initial[key],
                                                        );
                                                });
                                                const details =
                                                    visit.examination.calculation_details;
                                                data.assessment_mode = details?.mode ?? 'manual';
                                                for (const key of [
                                                    'height_method',
                                                    'glucose_context',
                                                    'blood_pressure_confirmed',
                                                ]) {
                                                    const value = details?.inputs[key];
                                                    if (value != null)
                                                        data[key] =
                                                            typeof value === 'boolean'
                                                                ? value
                                                                    ? '1'
                                                                    : '0'
                                                                : String(value);
                                                }
                                                data.activity_session_id = String(
                                                    visit.activity_session_id || '',
                                                );
                                                const date = new Date(visit.visited_at);
                                                data.visited_at = new Date(
                                                    date.getTime() + 7 * 60 * 60 * 1000,
                                                )
                                                    .toISOString()
                                                    .slice(0, 16);
                                                if (visit.referral) {
                                                    data.referral_destination =
                                                        visit.referral.destination;
                                                    data.referral_reason = visit.referral.reason;
                                                    data.follow_up_date =
                                                        visit.referral.follow_up_date || '';
                                                    data.follow_up_status =
                                                        visit.referral.follow_up_status;
                                                    data.follow_up_note =
                                                        visit.referral.follow_up_note || '';
                                                }
                                                form.setData(data);
                                                setEditing(visit.id);
                                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                            }}
                                        >
                                            Edit draft
                                        </Button>
                                    )}
                                    {canFinalize && <FinalizeVisit period={period} visit={visit} />}
                                </div>
                            )}
                            {visit.record_status === 'finalized' && editable && canCorrect && (
                                <Correction period={period} visit={visit} />
                            )}
                        </article>
                    ))
                ) : (
                    <Empty>Belum ada kunjungan untuk sasaran ini.</Empty>
                )}
                <Pagination data={visits} />
            </section>
        </Layout>
    );
}
