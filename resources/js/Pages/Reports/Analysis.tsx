import { Link, router, useForm } from '@inertiajs/react';
import Layout, { usePermission } from '../../components/Layout';
import { Badge, Button, Empty, Errors, Field, Pagination } from '../../components/ui';
import { periodLabel, type Period, type Pagination as PageData } from '../../types';
type Result = {
    summary: string;
    findings: { title: string; description: string; evidence_ids: string[]; kind: string }[];
    data_quality_issues: string[];
    limitations: string[];
    suggested_actions: { action: string; reason: string; priority: string; reviewer: string }[];
    conclusion: string;
};
type Run = {
    id: number;
    report_scope: string;
    comparison_period_id: number | null;
    provider: string;
    model: string;
    status: string;
    review_status: string;
    review_note: string | null;
    is_stale: boolean;
    source_revision: number;
    source_hash: string;
    evidence: { id: string; label: string; value: number }[];
    result: Result | null;
    reviewed_result: Result | null;
    failure_code: string | null;
};
function Review({ period, run }: { period: Period; run: Run }) {
    const form = useForm({
        review_status: 'approved',
        review_note: '',
        edited_summary: '',
        edited_conclusion: '',
    });
    return (
        <details>
            <summary>Review / revisi narasi</summary>
            <form
                className="form-stack"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.patch('/rekapitulasi/' + period.id + '/analisis/' + run.id);
                }}
            >
                <Errors errors={form.errors} />
                <div className="form-grid">
                    <Field label="Keputusan review">
                        <select
                            value={form.data.review_status}
                            onChange={(e) => form.setData('review_status', e.target.value)}
                        >
                            <option value="approved">Setujui</option>
                            <option value="rejected">Tolak</option>
                        </select>
                    </Field>
                    <Field label="Catatan peninjau">
                        <textarea
                            required
                            minLength={5}
                            maxLength={1000}
                            value={form.data.review_note}
                            onChange={(e) => form.setData('review_note', e.target.value)}
                        />
                    </Field>
                    <Field label="Revisi ringkasan (opsional)">
                        <textarea
                            maxLength={2000}
                            value={form.data.edited_summary}
                            onChange={(e) => form.setData('edited_summary', e.target.value)}
                        />
                    </Field>
                    <Field label="Revisi kesimpulan (opsional)">
                        <textarea
                            maxLength={2000}
                            value={form.data.edited_conclusion}
                            onChange={(e) => form.setData('edited_conclusion', e.target.value)}
                        />
                    </Field>
                </div>
                <Button type="submit" disabled={form.processing}>
                    Simpan review analisis
                </Button>
            </form>
        </details>
    );
}
function Narrative({ result, run }: { result: Result; run: Run }) {
    return (
        <>
            <p>{result.summary}</p>
            {result.findings.map((finding, index) => (
                <article className="visit-card" key={index}>
                    <h3>{finding.title}</h3>
                    <Badge muted>{finding.kind}</Badge>
                    <p>{finding.description}</p>
                    <ul>
                        {finding.evidence_ids.map((id) => {
                            const evidence = run.evidence.find((e) => e.id === id);
                            return evidence ? (
                                <li key={id}>
                                    {evidence.label}: <strong>{evidence.value}</strong>{' '}
                                    <small>({evidence.id})</small>
                                </li>
                            ) : null;
                        })}
                    </ul>
                </article>
            ))}
            {result.data_quality_issues.length > 0 && (
                <>
                    <h3>Kualitas data</h3>
                    <ul>
                        {result.data_quality_issues.map((item, i) => (
                            <li key={i}>{item}</li>
                        ))}
                    </ul>
                </>
            )}
            <h3>Keterbatasan</h3>
            <ul>
                {result.limitations.map((item, i) => (
                    <li key={i}>{item}</li>
                ))}
            </ul>
            {result.suggested_actions.map((item, i) => (
                <p key={i}>
                    <strong>{item.action}</strong> · {item.reason} · {item.priority} · Peninjau:{' '}
                    {item.reviewer}
                </p>
            ))}
            <p>
                <strong>Kesimpulan:</strong> {result.conclusion}
            </p>
        </>
    );
}
export default function Analysis({
    period,
    runs,
    aiAvailable,
    scopes,
    periods,
}: {
    period: Period;
    runs: PageData<Run>;
    aiAvailable: boolean;
    scopes: Record<string, string>;
    periods: Period[];
}) {
    const form = useForm({ use_ai: false, report_scope: 'operational', compare_id: '' });
    const canReview = usePermission('reports.approve');
    return (
        <Layout
            title="Analisis & review"
            description={
                periodLabel(period) + ' · Bukti angka dihitung backend, narasi ditinjau manusia.'
            }
            action={<Link href={'/rekapitulasi?period_id=' + period.id}>Kembali ke rekap</Link>}
        >
            <section className="panel">
                <h2>Buat ringkasan</h2>
                <div className="form-grid">
                    <Field label="Lingkup analisis">
                        <select
                            value={form.data.report_scope}
                            onChange={(e) => form.setData('report_scope', e.target.value)}
                        >
                            {Object.entries(scopes).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Periode pembanding analisis">
                        <select
                            value={form.data.compare_id}
                            onChange={(e) => form.setData('compare_id', e.target.value)}
                        >
                            <option value="">Tanpa pembanding</option>
                            {periods
                                .filter((item) => item.id !== period.id)
                                .map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {periodLabel(item)}
                                    </option>
                                ))}
                        </select>
                    </Field>
                </div>
                <p className="subtle">
                    Ringkasan lokal selalu tersedia dan diberi label sebagai template. AI hanya
                    menerima indikator agregat dalam lingkup terpilih yang lolos pembatasan kelompok
                    kecil, tanpa nama, tanggal lahir, alamat atau catatan. Sumber historis dan
                    transaksi tidak dijumlahkan. Perbandingan memerlukan definisi dan sumber yang
                    sebanding.
                </p>
                <Errors errors={form.errors} />
                <div className="flex flex-wrap gap-3">
                    <Button
                        disabled={form.processing}
                        onClick={() => {
                            form.transform(() => ({
                                use_ai: false,
                                report_scope: form.data.report_scope,
                                compare_id: form.data.compare_id,
                            }));
                            form.post('/rekapitulasi/' + period.id + '/analisis');
                        }}
                    >
                        Buat ringkasan lokal
                    </Button>
                    <Button
                        variant="outline"
                        disabled={form.processing || !aiAvailable}
                        onClick={() => {
                            if (
                                !window.confirm(
                                    'Kirim agregat operasional yang diizinkan ke provider AI untuk membuat narasi?',
                                )
                            )
                                return;
                            form.transform(() => ({
                                use_ai: true,
                                report_scope: form.data.report_scope,
                                compare_id: form.data.compare_id,
                            }));
                            form.post('/rekapitulasi/' + period.id + '/analisis');
                        }}
                    >
                        Antrekan analisis AI
                    </Button>
                    <Button variant="outline" onClick={() => router.reload()}>
                        Muat ulang status
                    </Button>
                </div>
                {!aiAvailable && (
                    <div className="info-box">
                        Provider belum diaktifkan/dikonfigurasi. Isi konfigurasi backend sebelum
                        memakai AI; tidak ada hasil AI demo atau pengiriman otomatis.
                    </div>
                )}
            </section>
            {runs.data.length ? (
                runs.data.map((run) => (
                    <section className="panel" key={run.id}>
                        <div className="section-heading">
                            <h2>
                                {run.provider === 'local_template'
                                    ? 'Ringkasan lokal — bukan AI'
                                    : 'Analisis AI — perlu ditinjau'}{' '}
                                #{run.id}
                            </h2>
                            <Badge muted>{run.status}</Badge>
                        </div>
                        <p className="subtle">
                            {run.model} · {scopes[run.report_scope] || run.report_scope}{' '}
                            {run.comparison_period_id
                                ? '· pembanding #' + run.comparison_period_id
                                : ''}{' '}
                            · Revisi sumber {run.source_revision} · Review: {run.review_status}
                        </p>
                        {run.is_stale && (
                            <div className="info-box">
                                Perlu diperbarui: sumber periode sudah berubah. Hasil lama
                                dipertahankan dan tidak dapat disetujui.
                            </div>
                        )}
                        {run.failure_code && (
                            <div className="error-box">
                                Analisis gagal ({run.failure_code}). Data inti tetap tersedia.
                            </div>
                        )}
                        {run.result && (
                            <>
                                <Narrative result={run.reviewed_result || run.result} run={run} />
                                {run.reviewed_result && (
                                    <details>
                                        <summary>Narasi asli</summary>
                                        <Narrative result={run.result} run={run} />
                                    </details>
                                )}
                                <details>
                                    <summary>Seluruh bukti backend</summary>
                                    {run.evidence.map((metric) => (
                                        <div className="history-row" key={metric.id}>
                                            <span>
                                                {metric.label} ({metric.id})
                                            </span>
                                            <strong>{metric.value}</strong>
                                        </div>
                                    ))}
                                </details>
                                {run.review_note && <p>Catatan review: {run.review_note}</p>}
                                {canReview && !run.is_stale && <Review period={period} run={run} />}
                            </>
                        )}
                    </section>
                ))
            ) : (
                <Empty>
                    Belum ada analisis. Buat ringkasan lokal atau antrekan AI setelah provider
                    dikonfigurasi.
                </Empty>
            )}
            <Pagination data={runs} />
        </Layout>
    );
}
