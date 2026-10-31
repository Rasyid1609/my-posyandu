import { useForm } from '@inertiajs/react';
import Layout from '../../components/Layout';
import { Badge, Button, Errors, Field } from '../../components/ui';
import { useDraftGuard } from '../../components/useDraftGuard';
type Version = {
    id: number;
    version: string;
    source: string;
    is_provisional: boolean;
    boundaries: number[];
};
export default function Rules({
    versions,
    labels,
}: {
    versions: Version[];
    labels: Record<string, string>;
}) {
    const form = useForm({
        version: '',
        source: '',
        is_provisional: true,
        boundaries: versions[0]?.boundaries || [12, 60, 72, 180, 228, 720],
    });
    useDraftGuard(form.isDirty, form.processing);
    const names = Object.values(labels).slice(0, 7);
    return (
        <Layout
            title="Aturan kelompok usia"
            description="Setiap periode menyimpan versi aturan. Versi baru berlaku untuk periode baru dan tidak menghitung ulang arsip."
        >
            <div className="info-box">
                Rentang usia adalah konfigurasi pelaporan. Ini tidak mengaktifkan diagnosis atau
                ambang klinis. Batas diisi dalam bulan penuh, dari usia 0; batas atas eksklusif.
            </div>
            <form
                className="panel form-stack"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/pengaturan/aturan', {
                        onSuccess: () => form.reset('version', 'source'),
                    });
                }}
            >
                <h2>Buat versi aturan baru</h2>
                <Errors errors={form.errors} />
                <div className="form-grid">
                    <Field label="Kode versi">
                        <input
                            required
                            maxLength={60}
                            value={form.data.version}
                            onChange={(e) => form.setData('version', e.target.value)}
                        />
                    </Field>
                    <Field label="Sumber / keputusan">
                        <textarea
                            required
                            minLength={10}
                            maxLength={1000}
                            value={form.data.source}
                            onChange={(e) => form.setData('source', e.target.value)}
                        />
                    </Field>
                </div>
                <div className="measurement-grid">
                    {form.data.boundaries.map((bound, index) => (
                        <Field
                            key={index}
                            label={'Batas akhir ' + names[index] + ' (bulan, eksklusif)'}
                        >
                            <input
                                type="number"
                                min={1}
                                max={2400}
                                required
                                value={bound}
                                onChange={(e) =>
                                    form.setData(
                                        'boundaries',
                                        form.data.boundaries.map((n, i) =>
                                            i === index ? Number(e.target.value) : n,
                                        ),
                                    )
                                }
                            />
                        </Field>
                    ))}
                </div>
                <label className="checkbox-field">
                    <input
                        type="checkbox"
                        checked={form.data.is_provisional}
                        onChange={(e) => form.setData('is_provisional', e.target.checked)}
                    />
                    Aturan sementara
                </label>
                <Button type="submit" disabled={form.processing}>
                    Simpan versi untuk periode baru
                </Button>
            </form>
            {versions.map((version) => (
                <section key={version.id} className="panel">
                    <div className="section-heading">
                        <h2>{version.version}</h2>
                        <Badge muted>
                            {version.is_provisional ? 'Sementara' : 'Dokumen ditetapkan pengelola'}
                        </Badge>
                    </div>
                    <p>{version.source}</p>
                    <div className="table-scroll" tabIndex={0}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Kelompok</th>
                                    <th>Usia (bulan penuh)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {names.map((name, index) => (
                                    <tr key={name}>
                                        <td>{name}</td>
                                        <td>
                                            {index === 0 ? 0 : version.boundaries[index - 1]} —{' '}
                                            {index === 6
                                                ? 'seterusnya'
                                                : version.boundaries[index] - 1}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            ))}
        </Layout>
    );
}
