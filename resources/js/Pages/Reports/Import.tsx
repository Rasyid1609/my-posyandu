import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Layout, { usePermission } from '../../components/Layout';
import { Badge, Button, Empty, Errors, Field, Pagination } from '../../components/ui';
import { useDraftGuard } from '../../components/useDraftGuard';
import { periodLabel, type Period, type Pagination as PageData } from '../../types';
type Row = Record<string, string | number | null>;
type Batch = {
    id: number;
    kind: string;
    report_type: string | null;
    source_name: string;
    status: string;
    revision: number;
    draft_rows: Row[];
    ocr_status: string;
    failure_code: string | null;
};
type Catalog = Record<string, { code: string; label: string }[]>;
function Draft({
    period,
    batch,
    catalog,
    sourceUrl,
    sourceIsImage,
    ocrAvailable,
}: {
    period: Period;
    batch: Batch;
    catalog: Catalog;
    sourceUrl: string | null;
    sourceIsImage: boolean;
    ocrAvailable: boolean;
}) {
    const form = useForm({
        revision: batch.revision,
        period_revision: period.revision,
        rows: batch.draft_rows,
    });
    const action = useForm({
        revision: batch.revision,
        period_revision: period.revision,
        confirmed: false,
        consent: false,
    });
    const editable =
        ['open', 'returned'].includes(period.status) &&
        batch.status === 'draft' &&
        !['queued', 'processing'].includes(batch.ocr_status);
    useDraftGuard(form.isDirty, form.processing);
    const fields =
        batch.kind === 'legacy'
            ? ['metric_code', 'value', 'source_cell']
            : ['full_name', 'gender', 'birth_date', 'address', 'rt', 'rw'];
    const rowLabels: Record<string, string> = {
        metric_code: 'Indikator',
        value: 'Jumlah (kosong = belum diketahui)',
        source_cell: 'Sel sumber',
        full_name: 'Nama lengkap',
        gender: 'Jenis kelamin',
        birth_date: 'Tanggal lahir (YYYY-MM-DD)',
        address: 'Alamat',
        rt: 'RT',
        rw: 'RW',
    };
    function change(index: number, key: string, value: string) {
        form.setData(
            'rows',
            form.data.rows.map((row, i) =>
                i === index ? { ...row, [key]: value === '' ? null : value } : row,
            ),
        );
    }
    const base = '/rekapitulasi/' + period.id + '/import/' + batch.id;
    return (
        <section className="panel form-stack">
            <div className="section-heading">
                <h2>
                    Review draft #{batch.id} · {batch.source_name}
                </h2>
                <Badge muted>{batch.status}</Badge>
            </div>
            <p className="subtle">
                OCR: {batch.ocr_status} {batch.failure_code ? '· ' + batch.failure_code : ''}.
                Pemeriksaan periode, kode dan angka tetap tanggung jawab peninjau. Semua sel kosong
                dipertahankan sebagai belum diketahui.
            </p>
            {sourceUrl &&
                (sourceIsImage ? (
                    <a href={sourceUrl} target="_blank" rel="noreferrer">
                        <img
                            className="max-h-96 w-full rounded-lg object-contain"
                            src={sourceUrl}
                            alt="Foto sumber import untuk dicocokkan dengan draft"
                        />
                    </a>
                ) : (
                    <a href={sourceUrl}>Unduh berkas sumber privat</a>
                ))}
            {sourceIsImage && batch.status === 'draft' && (
                <div className="form-stack">
                    <p>
                        OCR opsional mengirim foto ini ke provider. Pastikan foto yang dipilih hanya
                        rekap yang diizinkan untuk diproses; nama/kontak tidak diperlukan.
                    </p>
                    <label className="checkbox-field">
                        <input
                            type="checkbox"
                            checked={action.data.consent}
                            disabled={!editable || !ocrAvailable}
                            onChange={(e) => action.setData('consent', e.target.checked)}
                        />
                        Saya menyetujui pengiriman foto ini untuk OCR
                    </label>
                    <Button
                        variant="outline"
                        disabled={
                            !editable ||
                            !ocrAvailable ||
                            !action.data.consent ||
                            action.processing ||
                            batch.ocr_status !== 'not_requested'
                        }
                        onClick={() => {
                            action.transform((data) => ({
                                ...data,
                                revision: batch.revision,
                                period_revision: period.revision,
                            }));
                            action.post(base + '/ocr');
                        }}
                    >
                        Kirim foto ke antrean OCR
                    </Button>
                    {!ocrAvailable && (
                        <p className="subtle">
                            OCR provider belum dikonfigurasi. Isi tabel manual sambil melihat foto.
                        </p>
                    )}
                </div>
            )}
            <form
                className="form-stack"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        revision: batch.revision,
                        period_revision: period.revision,
                    }));
                    form.patch(base);
                }}
            >
                <Errors errors={form.errors} />
                <div className="table-scroll" tabIndex={0}>
                    <table>
                        <thead>
                            <tr>
                                <th>Baris</th>
                                {fields.map((key) => (
                                    <th key={key}>{rowLabels[key]}</th>
                                ))}
                                {editable && <th>Tindakan</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {form.data.rows.map((row, index) => (
                                <tr key={index}>
                                    <td>{index + 1}</td>
                                    {fields.map((key) => (
                                        <td key={key}>
                                            {key === 'metric_code' ? (
                                                <select
                                                    aria-label={'Indikator baris ' + (index + 1)}
                                                    disabled={!editable}
                                                    value={String(row[key] ?? '')}
                                                    onChange={(e) =>
                                                        change(index, key, e.target.value)
                                                    }
                                                >
                                                    <option value="">Pilih indikator</option>
                                                    {catalog[batch.report_type || '']?.map(
                                                        (item) => (
                                                            <option
                                                                key={item.code}
                                                                value={item.code}
                                                            >
                                                                {item.label} ({item.code})
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : key === 'gender' ? (
                                                <select
                                                    disabled={!editable}
                                                    aria-label={
                                                        'Jenis kelamin baris ' + (index + 1)
                                                    }
                                                    value={String(row[key] ?? '')}
                                                    onChange={(e) =>
                                                        change(index, key, e.target.value)
                                                    }
                                                >
                                                    <option value="">Pilih</option>
                                                    <option value="L">Laki-laki</option>
                                                    <option value="P">Perempuan</option>
                                                </select>
                                            ) : (
                                                <input
                                                    disabled={!editable}
                                                    aria-label={
                                                        rowLabels[key] + ' baris ' + (index + 1)
                                                    }
                                                    type={
                                                        key === 'value'
                                                            ? 'number'
                                                            : key === 'birth_date'
                                                              ? 'date'
                                                              : 'text'
                                                    }
                                                    min={key === 'value' ? 0 : undefined}
                                                    maxLength={key === 'address' ? 1000 : 150}
                                                    value={String(row[key] ?? '')}
                                                    onChange={(e) =>
                                                        change(index, key, e.target.value)
                                                    }
                                                />
                                            )}
                                        </td>
                                    ))}
                                    {editable && (
                                        <td>
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    form.setData(
                                                        'rows',
                                                        form.data.rows.filter(
                                                            (_, i) => i !== index,
                                                        ),
                                                    )
                                                }
                                            >
                                                Hapus baris {index + 1}
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!form.data.rows.length && (
                    <Empty>
                        Belum ada sel/baris draft. Tambah baris manual atau gunakan OCR setelah
                        tersedia.
                    </Empty>
                )}
                {editable && (
                    <div className="flex flex-wrap gap-3">
                        <Button
                            variant="outline"
                            disabled={form.data.rows.length >= 500}
                            onClick={() =>
                                form.setData('rows', [
                                    ...form.data.rows,
                                    Object.fromEntries(fields.map((key) => [key, null])),
                                ])
                            }
                        >
                            Tambah baris draft
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan perubahan draft
                        </Button>
                    </div>
                )}
            </form>
            <Errors errors={action.errors} />
            {editable && (
                <>
                    <label className="checkbox-field">
                        <input
                            type="checkbox"
                            checked={action.data.confirmed}
                            onChange={(e) => action.setData('confirmed', e.target.checked)}
                        />
                        Saya sudah memeriksa sumber, periode, setiap baris, dan nilai yang belum
                        diketahui
                    </label>
                    <Button
                        disabled={
                            action.processing ||
                            form.isDirty ||
                            !action.data.confirmed ||
                            !form.data.rows.length
                        }
                        onClick={() => {
                            if (
                                !window.confirm(
                                    'Commit draft yang telah diperiksa? Proses ini membuat data terverifikasi dan tidak dapat diulang.',
                                )
                            )
                                return;
                            action.transform((data) => ({
                                ...data,
                                revision: batch.revision,
                                period_revision: period.revision,
                            }));
                            action.post(base + '/commit');
                        }}
                    >
                        Commit import terverifikasi
                    </Button>
                    {form.isDirty && (
                        <p className="subtle">Simpan perubahan draft sebelum commit.</p>
                    )}
                </>
            )}
        </section>
    );
}
function SourceSelection({
    period,
    batches,
    reportTypes,
    selections,
}: {
    period: Period;
    batches: Batch[];
    reportTypes: Record<string, string>;
    selections: { report_type: string; import_batch_id: number | null }[];
}) {
    const form = useForm({
        period_revision: period.revision,
        report_type: 'children',
        import_batch_id: '',
        reason: '',
    });
    return (
        <section className="panel">
            <h2>Rekonsiliasi sumber laporan</h2>
            <p className="subtle">
                Pilih satu sumber untuk setiap jenis laporan. Transaksi dan agregat historis tidak
                dijumlahkan.
            </p>
            {selections.map((source) => (
                <p key={source.report_type}>
                    {reportTypes[source.report_type]}:{' '}
                    {source.import_batch_id
                        ? 'Import #' + source.import_batch_id
                        : 'Transaksi aplikasi'}
                </p>
            ))}
            <form
                className="form-stack"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({ ...data, period_revision: period.revision }));
                    form.post('/rekapitulasi/' + period.id + '/sumber');
                }}
            >
                <Errors errors={form.errors} />
                <div className="form-grid">
                    <Field label="Jenis laporan untuk sumber">
                        <select
                            value={form.data.report_type}
                            onChange={(e) => {
                                form.setData('report_type', e.target.value);
                                form.setData('import_batch_id', '');
                            }}
                        >
                            {Object.entries(reportTypes).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Sumber yang ditetapkan">
                        <select
                            value={form.data.import_batch_id}
                            onChange={(e) => form.setData('import_batch_id', e.target.value)}
                        >
                            <option value="">Transaksi aplikasi</option>
                            {batches
                                .filter(
                                    (b) =>
                                        b.kind === 'legacy' &&
                                        b.status === 'committed' &&
                                        b.report_type === form.data.report_type,
                                )
                                .map((b) => (
                                    <option key={b.id} value={b.id}>
                                        Import #{b.id} — {b.source_name}
                                    </option>
                                ))}
                        </select>
                    </Field>
                    <Field label="Alasan penetapan sumber">
                        <input
                            required
                            minLength={5}
                            maxLength={500}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                        />
                    </Field>
                </div>
                <Button type="submit" disabled={form.processing}>
                    Tetapkan sumber laporan
                </Button>
            </form>
        </section>
    );
}
export default function Import({
    period,
    batches,
    batch,
    catalog,
    reportTypes,
    sourceUrl,
    sourceIsImage,
    ocrAvailable,
    selections,
}: {
    period: Period;
    batches: PageData<Batch>;
    batch: Batch | null;
    catalog: Catalog;
    reportTypes: Record<string, string>;
    sourceUrl: string | null;
    sourceIsImage: boolean;
    ocrAvailable: boolean;
    selections: { report_type: string; import_batch_id: number | null }[];
}) {
    const canManageResidents = usePermission('residents.manage');
    const canSelectSource = usePermission('reports.approve');
    const [selectedKind, setSelectedKind] = useState('legacy');
    const form = useForm<{
        kind: string;
        report_type: string;
        source_name: string;
        period_revision: number;
        file: File | null;
    }>({
        kind: 'legacy',
        report_type: 'children',
        source_name: '',
        period_revision: period.revision,
        file: null,
    });
    const editable = ['open', 'returned'].includes(period.status);
    return (
        <Layout
            title="Import & data historis"
            description={
                periodLabel(period) +
                ' · Draft privat → koreksi → konfirmasi → commit → rekonsiliasi.'
            }
            action={<Link href={'/rekapitulasi?period_id=' + period.id}>Kembali ke rekap</Link>}
        >
            <div className="info-box">
                Foto rekap tidak menghasilkan identitas atau pemeriksaan individu. Unggahan tidak
                dikirim ke provider sampai Anda memilih tindakan OCR yang tersedia. XLSX/CSV dibaca
                lokal tanpa menjalankan formula.
            </div>
            {editable && (
                <form
                    className="panel form-stack"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({ ...data, period_revision: period.revision }));
                        form.post('/rekapitulasi/' + period.id + '/import', {
                            forceFormData: true,
                        });
                    }}
                >
                    <h2>Buat draft import</h2>
                    <Errors errors={form.errors} />
                    <div className="form-grid">
                        <Field label="Jenis import">
                            <select
                                value={form.data.kind}
                                onChange={(e) => {
                                    form.setData('kind', e.target.value);
                                    setSelectedKind(e.target.value);
                                }}
                            >
                                <option value="legacy">Rekap historis agregat</option>
                                {canManageResidents && (
                                    <option value="residents">Identitas warga</option>
                                )}
                            </select>
                        </Field>
                        {selectedKind === 'legacy' && (
                            <Field label="Jenis formulir historis">
                                <select
                                    value={form.data.report_type}
                                    onChange={(e) => form.setData('report_type', e.target.value)}
                                >
                                    {Object.entries(reportTypes).map(([key, label]) => (
                                        <option key={key} value={key}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}
                        <Field label="Nama / keterangan sumber">
                            <input
                                required
                                maxLength={150}
                                value={form.data.source_name}
                                onChange={(e) => form.setData('source_name', e.target.value)}
                            />
                        </Field>
                        <Field
                            label="Berkas sumber (opsional)"
                            hint="CSV/XLSX satu sheet maksimal 500 baris. Foto JPG/PNG/WebP hanya untuk historis. Maksimal 8 MB."
                        >
                            <input
                                type="file"
                                accept={
                                    selectedKind === 'legacy'
                                        ? '.csv,.xlsx,.jpg,.jpeg,.png,.webp'
                                        : '.csv,.xlsx'
                                }
                                onChange={(e) => form.setData('file', e.target.files?.[0] || null)}
                            />
                        </Field>
                    </div>
                    <a
                        href={
                            '/rekapitulasi/' +
                            period.id +
                            '/import/template?kind=' +
                            form.data.kind +
                            '&report_type=' +
                            form.data.report_type
                        }
                    >
                        Unduh template CSV
                    </a>
                    <Button type="submit" disabled={form.processing}>
                        Buat draft untuk review
                    </Button>
                    {form.progress && <p>Mengunggah {form.progress.percentage}%</p>}
                </form>
            )}
            {batch && (
                <Draft
                    key={batch.id + '-' + batch.revision}
                    period={period}
                    batch={batch}
                    catalog={catalog}
                    sourceUrl={sourceUrl}
                    sourceIsImage={sourceIsImage}
                    ocrAvailable={ocrAvailable}
                />
            )}
            <section className="panel">
                <div className="section-heading">
                    <h2>Riwayat import</h2>
                    <Button variant="outline" onClick={() => router.reload()}>
                        Muat ulang status
                    </Button>
                </div>
                {batches.data.length ? (
                    <div className="table-scroll" tabIndex={0}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Sumber</th>
                                    <th>Jenis</th>
                                    <th>Status</th>
                                    <th>OCR</th>
                                    <th>Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {batches.data.map((item) => (
                                    <tr key={item.id}>
                                        <td>
                                            #{item.id} · {item.source_name}
                                        </td>
                                        <td>
                                            {item.kind === 'residents'
                                                ? 'Warga'
                                                : reportTypes[item.report_type || '']}
                                        </td>
                                        <td>{item.status}</td>
                                        <td>{item.ocr_status}</td>
                                        <td>
                                            <Link
                                                href={
                                                    '/rekapitulasi/' +
                                                    period.id +
                                                    '/import?batch_id=' +
                                                    item.id
                                                }
                                            >
                                                Tinjau draft/sumber
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty>Belum ada import.</Empty>
                )}
                <Pagination data={batches} />
            </section>
            {canSelectSource && editable && (
                <SourceSelection
                    period={period}
                    batches={batches.data}
                    reportTypes={reportTypes}
                    selections={selections}
                />
            )}
        </Layout>
    );
}
