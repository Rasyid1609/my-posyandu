import { Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Layout, { usePermission } from '../../components/Layout';
import { Badge, Button, Empty, Errors, Field, Pagination } from '../../components/ui';
import { periodLabel, statusLabels, type Period, type Pagination as PageData } from '../../types';

export default function Index({
    periods,
    currentMonth,
    currentYear,
}: {
    periods: PageData<Period>;
    currentMonth: number;
    currentYear: number;
}) {
    const canManage = usePermission('targets.manage');
    const canIdentity = usePermission('residents.view');
    const canVisits = usePermission('visits.view');
    const form = useForm({ month: currentMonth, year: currentYear });
    return (
        <Layout
            title="Data Sasaran"
            description="Tetapkan sasaran bulanan, konfirmasikan kehadiran, dan catat pelayanan yang dilakukan."
        >
            {canManage && (
                <section className="panel">
                    <h2>Buat periode kegiatan</h2>
                    <p className="subtle">
                        Tanggal acuan snapshot adalah hari terakhir bulan. Pemilihan sasaran
                        dilakukan manual sampai SOP tersedia.
                    </p>
                    <form
                        className="filters"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/sasaran');
                        }}
                    >
                        <Field label="Bulan">
                            <select
                                value={form.data.month}
                                onChange={(e) => form.setData('month', Number(e.target.value))}
                            >
                                {Array.from({ length: 12 }, (_, i) => (
                                    <option key={i} value={i + 1}>
                                        {
                                            periodLabel({ month: i + 1, year: currentYear }).split(
                                                ' ',
                                            )[0]
                                        }
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Tahun" error={form.errors.year}>
                            <input
                                required
                                type="number"
                                min={1900}
                                max={2200}
                                value={form.data.year}
                                onChange={(e) => form.setData('year', Number(e.target.value))}
                            />
                        </Field>
                        <Button type="submit" disabled={form.processing}>
                            <Plus size={16} />
                            Buat periode
                        </Button>
                    </form>
                    <Errors errors={form.errors} />
                </section>
            )}
            <section className="panel">
                <h2>Periode pelayanan</h2>
                {periods.data.length ? (
                    <div className="period-grid">
                        {periods.data.map((period) => (
                            <article className="period-card" key={period.id}>
                                <Badge muted={period.status !== 'open'}>
                                    {statusLabels[period.status]}
                                </Badge>
                                <h3>{periodLabel(period)}</h3>
                                <p>Revisi sumber {period.revision}</p>
                                {canIdentity && (canManage || canVisits) ? (
                                    <Link href={'/sasaran/' + period.id}>Buka sasaran →</Link>
                                ) : (
                                    <span className="subtle">
                                        Detail memerlukan izin identitas dan pelayanan.
                                    </span>
                                )}
                            </article>
                        ))}
                    </div>
                ) : (
                    <Empty>Belum ada periode. Petugas berizin dapat membuat periode pertama.</Empty>
                )}
                <Pagination data={periods} />
            </section>
        </Layout>
    );
}
