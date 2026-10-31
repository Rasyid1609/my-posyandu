import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Layout, { usePermission } from '../../components/Layout';
import { Badge, Button, Empty, Errors, Field } from '../../components/ui';
import { periodLabel, statusLabels, type Period, type Metrics } from '../../types';

type MetricRow = {
    code: string;
    label: string;
    value: number | null;
    denominator: number | null;
    percentage: number | null;
};
type Report = {
    title: string;
    mapping_version: string;
    source: string;
    import_batch_id?: number;
    rows: MetricRow[];
};
type Dataset = {
    version: number | null;
    source_revision: number;
    source_hash: string;
    status: string;
    mapping_version: string;
    metrics: Metrics & {
        _reports: Record<string, Report>;
        _age_rule_version_id: number | null;
        _group_mapping?: string;
        draft_home_visits?: number;
        unclassified_targets?: number;
    };
};
type Snapshot = {
    id: number;
    version: number;
    source_revision: number;
    mapping_version: string;
    source_hash: string;
    created_at: string;
};
const labels: Record<keyof Metrics, string> = {
    targets: 'Sasaran unik',
    present: 'Hadir unik dalam sasaran',
    absent: 'Tidak hadir terkonfirmasi',
    unconfirmed: 'Belum dikonfirmasi',
    attendance_coverage: 'Cakupan kehadiran',
    finalized_visits: 'Kunjungan final administratif',
    draft_visits: 'Kunjungan draft (terpisah)',
    weight_measured: 'Warga diukur berat badan',
    blood_sugar_measured: 'Warga diperiksa gula darah',
};
function Operational({ metrics }: { metrics: Metrics }) {
    return (
        <div className="table-scroll" tabIndex={0}>
            <table>
                <thead>
                    <tr>
                        <th>Indikator operasional</th>
                        <th>Hasil</th>
                        <th>Dasar perhitungan</th>
                    </tr>
                </thead>
                <tbody>
                    {Object.entries(labels).map(([key, label]) => (
                        <tr key={key}>
                            <td>{label}</td>
                            <td>
                                <strong>
                                    {key === 'attendance_coverage'
                                        ? metrics.attendance_coverage === null
                                            ? 'Tidak berlaku'
                                            : metrics.attendance_coverage + '%'
                                        : metrics[key as keyof Metrics]}
                                </strong>
                            </td>
                            <td>
                                {key === 'attendance_coverage'
                                    ? metrics.present + ' / ' + metrics.targets
                                    : key.endsWith('_measured')
                                      ? 'Warga unik; hanya kunjungan final'
                                      : key.endsWith('_visits')
                                        ? 'Jumlah kejadian'
                                        : 'Warga unik pada sasaran aktif'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
export default function Index({
    periods,
    period,
    metrics,
    snapshots,
    mappingVersion,
    dataset,
    comparison,
    reportTypes,
}: {
    periods: Period[];
    period: Period | null;
    metrics: Metrics | null;
    snapshots: Snapshot[];
    mappingVersion: string;
    dataset: Dataset | null;
    comparison: Dataset | null;
    reportTypes: Record<string, string>;
}) {
    const canSubmit = usePermission('reports.submit');
    const canApprove = usePermission('reports.approve');
    const canExport = usePermission('exports.aggregate');
    const canImport = usePermission('imports.manage');
    const canAnalyse = usePermission('analysis.manage');
    const [kind, setKind] = useState('children');
    const form = useForm({ action: 'submit', reason: '', revision: period?.revision || 1 });
    const report = dataset?.metrics._reports[kind];
    const compareReport = comparison?.metrics._reports[kind];
    const compatible =
        comparison &&
        dataset &&
        comparison.metrics._age_rule_version_id === dataset.metrics._age_rule_version_id &&
        comparison.metrics._group_mapping === dataset.metrics._group_mapping &&
        compareReport?.source === report?.source;
    function review(action: string) {
        if (
            !period ||
            !window.confirm(
                'Lanjutkan perubahan status rekap operasional dengan alasan yang ditulis?',
            )
        )
            return;
        form.transform((data) => ({ ...data, action, revision: period.revision }));
        form.patch('/rekapitulasi/' + period.id, { onSuccess: () => form.reset('reason') });
    }
    return (
        <Layout
            title="Data Rekapitulasi"
            description="Lima rekap dari transaksi final, perbandingan periode, ekspor, dan arsip versi."
        >
            <section className="panel">
                <div className="section-heading">
                    <h2>Rekap operasional umum</h2>
                    <Badge muted>{mappingVersion}</Badge>
                </div>
                <div className="filters">
                    <Field label="Periode laporan">
                        <select
                            value={period?.id || ''}
                            onChange={(e) =>
                                router.get('/rekapitulasi', { period_id: e.target.value })
                            }
                        >
                            <option disabled value="">
                                Pilih periode
                            </option>
                            {periods.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {periodLabel(item)}
                                </option>
                            ))}
                        </select>
                    </Field>
                    {period && <Badge>{statusLabels[period.status]}</Badge>}
                    {dataset && (
                        <span>
                            Versi {dataset.version ?? 'draft'} · sumber revisi{' '}
                            {dataset.source_revision}
                        </span>
                    )}
                </div>
                <div className="info-box">
                    Sumber: transaksi aplikasi. Konfigurasi usia dan pemetaan sementara dapat
                    diperbarui. Rekap aplikasi ini belum dinyatakan sebagai formulir resmi yang
                    disahkan Dinkes. Nilai kosong bukan hasil normal.
                </div>
                {metrics && period ? (
                    <>
                        <Operational metrics={metrics} />
                        <div className="quality-note">
                            <strong>Kualitas data</strong>
                            <p>
                                {metrics.unconfirmed} sasaran belum dikonfirmasi ·{' '}
                                {metrics.draft_visits} kunjungan masih draft ·{' '}
                                {dataset?.metrics.draft_home_visits || 0} kunjungan rumah draft ·{' '}
                                {dataset?.metrics.unclassified_targets || 0} sasaran usia belum
                                diketahui.{' '}
                                {metrics.targets === 0 && 'Penyebut nol: cakupan tidak berlaku.'}
                            </p>
                        </div>
                        {(canSubmit || canApprove) && !dataset?.version && (
                            <div className="review-panel">
                                <h3>Review administratif</h3>
                                <p className="subtle">
                                    Selesaikan kehadiran dan semua draft kunjungan individu/rumah
                                    sebelum pengajuan.
                                </p>
                                <Field label="Alasan / catatan review" error={form.errors.reason}>
                                    <textarea
                                        minLength={5}
                                        maxLength={500}
                                        value={form.data.reason}
                                        onChange={(e) => form.setData('reason', e.target.value)}
                                    />
                                </Field>
                                <Errors errors={form.errors} />
                                <div className="flex flex-wrap gap-3">
                                    {canSubmit && ['open', 'returned'].includes(period.status) && (
                                        <Button
                                            disabled={
                                                form.processing ||
                                                form.data.reason.trim().length < 5
                                            }
                                            onClick={() => review('submit')}
                                        >
                                            Ajukan rekap
                                        </Button>
                                    )}
                                    {canApprove && period.status === 'submitted' && (
                                        <>
                                            <Button
                                                disabled={
                                                    form.processing ||
                                                    form.data.reason.trim().length < 5
                                                }
                                                onClick={() => review('approve')}
                                            >
                                                Setujui & kunci
                                            </Button>
                                            <Button
                                                variant="outline"
                                                disabled={
                                                    form.processing ||
                                                    form.data.reason.trim().length < 5
                                                }
                                                onClick={() => review('return')}
                                            >
                                                Kembalikan
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        )}
                        {canApprove && period.status === 'locked' && (
                            <div className="review-panel">
                                <Field label="Alasan / catatan review">
                                    <textarea
                                        minLength={5}
                                        maxLength={500}
                                        value={form.data.reason}
                                        onChange={(e) => form.setData('reason', e.target.value)}
                                    />
                                </Field>
                                <Errors errors={form.errors} />
                                <Button
                                    variant="outline"
                                    disabled={form.processing || form.data.reason.trim().length < 5}
                                    onClick={() => review('reopen')}
                                >
                                    Buka kembali dengan alasan
                                </Button>
                            </div>
                        )}
                    </>
                ) : (
                    <Empty>Belum ada periode laporan. Buat periode melalui Data Sasaran.</Empty>
                )}
            </section>
            {period && dataset && (
                <section className="panel">
                    <div className="section-heading">
                        <h2>Lima rekap pelayanan</h2>
                        <Badge muted>
                            Aturan usia #
                            {dataset.metrics._age_rule_version_id || 'belum ditetapkan'}
                        </Badge>
                    </div>
                    <div className="filters">
                        <Field label="Jenis rekap">
                            <select value={kind} onChange={(e) => setKind(e.target.value)}>
                                {Object.entries(reportTypes).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Periode pembanding">
                            <select
                                defaultValue=""
                                onChange={(e) =>
                                    router.get(
                                        '/rekapitulasi',
                                        {
                                            period_id: period.id,
                                            compare_id: e.target.value,
                                            version: dataset.version || '',
                                        },
                                        { preserveState: true },
                                    )
                                }
                            >
                                <option value="">Tanpa pembanding</option>
                                {periods
                                    .filter((p) => p.id !== period.id)
                                    .map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {periodLabel(p)}
                                        </option>
                                    ))}
                            </select>
                        </Field>
                    </div>
                    <p className="subtle">
                        Hasil diketahui terakhir per indikator dari kunjungan final; pemeriksaan
                        berulang tidak menggandakan warga. Penyebut kategori hasil adalah jumlah
                        warga dengan hasil tersebut, bukan semua yang hadir. Kunjungan rumah memakai
                        temuan “Ya” minimal satu kali per orang.
                    </p>
                    {comparison && !compatible && (
                        <div className="info-box">
                            Versi kelompok, mapping, atau sumber berbeda. Selisih tidak dihitung
                            agar tidak membandingkan definisi yang berbeda.
                        </div>
                    )}
                    {report ? (
                        <>
                            <h3>{report.title}</h3>
                            <Badge muted>
                                {report.source === 'legacy_import'
                                    ? 'Sumber: import historis #' + report.import_batch_id
                                    : 'Sumber: transaksi aplikasi'}
                            </Badge>
                            {canExport && (
                                <div className="flex flex-wrap gap-4">
                                    <a
                                        href={
                                            '/rekapitulasi/' +
                                            period.id +
                                            '/ekspor?format=xlsx&type=' +
                                            kind +
                                            (dataset.version ? '&version=' + dataset.version : '')
                                        }
                                    >
                                        Unduh XLSX
                                    </a>
                                    <a
                                        href={
                                            '/rekapitulasi/' +
                                            period.id +
                                            '/ekspor?format=pdf&type=' +
                                            kind +
                                            (dataset.version ? '&version=' + dataset.version : '')
                                        }
                                    >
                                        Unduh PDF
                                    </a>
                                </div>
                            )}
                            <div className="table-scroll" tabIndex={0}>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Indikator</th>
                                            <th>Jumlah</th>
                                            <th>Penyebut</th>
                                            <th>Persentase</th>
                                            {compatible && <th>Selisih jumlah</th>}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.rows.map((row) => {
                                            const previous = compareReport?.rows.find(
                                                (r) => r.code === row.code,
                                            );
                                            return (
                                                <tr key={row.code}>
                                                    <td>
                                                        {row.label}
                                                        <small>{row.code}</small>
                                                    </td>
                                                    <td>{row.value ?? 'Belum diketahui'}</td>
                                                    <td>{row.denominator ?? 'Tidak berlaku'}</td>
                                                    <td>
                                                        {row.percentage === null
                                                            ? 'Tidak berlaku'
                                                            : row.percentage + '%'}
                                                    </td>
                                                    {compatible && (
                                                        <td>
                                                            {previous &&
                                                            row.value !== null &&
                                                            previous.value !== null
                                                                ? row.value - previous.value
                                                                : 'Tidak sebanding'}
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    ) : (
                        <Empty>
                            Arsip ini dibuat sebelum mapping lima rekap tersedia; angka historis
                            tidak dihitung ulang.
                        </Empty>
                    )}
                </section>
            )}
            {!!snapshots.length && period && (
                <section className="panel">
                    <h2>Arsip versi terkunci</h2>
                    <p className="subtle">
                        Versi sebelumnya tetap tersedia setelah periode dibuka kembali.
                    </p>
                    {snapshots.map((snapshot) => (
                        <div className="snapshot" key={snapshot.id}>
                            <Link
                                href={
                                    '/rekapitulasi?period_id=' +
                                    period.id +
                                    '&version=' +
                                    snapshot.version
                                }
                            >
                                Versi {snapshot.version} · revisi sumber {snapshot.source_revision}{' '}
                                · {snapshot.mapping_version}
                            </Link>
                            <p className="mono break-all">Hash sumber: {snapshot.source_hash}</p>
                        </div>
                    ))}
                    <Link href={'/rekapitulasi?period_id=' + period.id}>
                        Lihat versi periode saat ini
                    </Link>
                </section>
            )}
            {period && (
                <div className="overview-grid">
                    {canAnalyse && (
                        <section className="panel">
                            <h2>Analisis agregat</h2>
                            <Link href={'/rekapitulasi/' + period.id + '/analisis'}>
                                Buka analisis & review
                            </Link>
                        </section>
                    )}
                    {canImport && (
                        <section className="panel">
                            <h2>Import & data historis</h2>
                            <Link href={'/rekapitulasi/' + period.id + '/import'}>
                                Buka import, template & rekonsiliasi
                            </Link>
                        </section>
                    )}
                </div>
            )}
        </Layout>
    );
}
