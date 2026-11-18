import { Link, router } from '@inertiajs/react';
import { ArrowRight, CalendarDays, ClipboardCheck, Clock3, FilePenLine, Users } from 'lucide-react';
import Layout, { usePermission } from '../components/Layout';
import { Badge, Button, Empty, Field, Pagination } from '../components/ui';
import { Card, CardContent, CardDescription, CardHeader } from '../components/ui/card';
import { periodLabel, statusLabels, type Pagination as PageData, type Period } from '../types';

type TaskKind = 'attendance' | 'drafts' | 'referrals';
type Summary = {
    targets: number;
    present: number;
    absent: number;
    unconfirmed: number;
    draft_visits: number;
    pending_referrals: number;
    overdue_referrals: number;
};
type Task = {
    id: number;
    resident_name: string;
    registration_number: string;
    status: string;
    date: string | null;
    overdue: boolean;
    href: string;
};
const taskLabels: Record<TaskKind, string> = {
    attendance: 'Kehadiran',
    drafts: 'Draft pelayanan',
    referrals: 'Rujukan tertunda',
};
const taskDescriptions: Record<TaskKind, string> = {
    attendance: 'Konfirmasikan kehadiran dan tindak lanjuti warga yang tidak hadir.',
    drafts: 'Periksa pencatatan yang belum difinalkan secara administratif. Catatan terlama ditampilkan lebih dahulu.',
    referrals: 'Rujukan dari pelayanan final yang masih menunggu atau dijadwalkan. Jadwal terlama ditampilkan lebih dahulu.',
};
const followUpLabels: Record<string, string> = { pending: 'Menunggu', scheduled: 'Terjadwal' };
const number = (value: number) => value.toLocaleString('id-ID');
const dateLabel = (date: string) =>
    new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(date + 'T00:00:00+07:00'));

export default function Dashboard({
    periods,
    period,
    summary,
    taskCounts,
    selectedTask,
    tasks,
}: {
    periods: Period[];
    period: Period | null;
    summary: Summary | null;
    taskCounts: Partial<Record<TaskKind, number>> | null;
    selectedTask: TaskKind;
    tasks: PageData<Task> | null;
}) {
    const canManageTargets = usePermission('targets.manage');
    const canViewAggregates = usePermission('aggregates.view');
    const canViewResidents = usePermission('residents.view');
    const hasVisitPermission = usePermission('visits.view');
    const canViewVisits = canViewResidents && hasVisitPermission;
    const coverage = summary && summary.targets > 0
        ? Math.round(summary.present / summary.targets * 100)
        : null;
    const cards = summary ? [
        {
            title: 'Sasaran periode ini', value: summary.targets,
            note: 'Sasaran aktif dalam cakupan periode', icon: Users,
        },
        {
            title: 'Sudah hadir', value: summary.present,
            note: coverage === null ? 'Belum ada sasaran' : coverage + '% dari sasaran periode ini',
            icon: ClipboardCheck,
        },
        {
            title: 'Draft pelayanan', value: summary.draft_visits,
            note: 'Menunggu pemeriksaan dan finalisasi', icon: FilePenLine,
        },
        {
            title: 'Rujukan tertunda', value: summary.pending_referrals,
            note: number(summary.overdue_referrals) + ' melewati jadwal tindak lanjut', icon: Clock3,
        },
    ] : [];

    return (
        <Layout
            title="Dashboard"
            description="Pantau pelayanan dan lanjutkan pekerjaan kader dari satu tempat."
        >
            {!period ? (
                <section className="panel">
                    <Empty>
                        <CalendarDays
                            className="mx-auto mb-3 size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <h2>Belum ada periode pelayanan</h2>
                        <p className="subtle">
                            Ringkasan dan tindak lanjut akan tampil setelah sasaran periode tersedia.
                        </p>
                        <Button asChild variant="outline">
                            <Link href="/sasaran">
                                {canManageTargets ? 'Buat periode pertama' : 'Lihat periode pelayanan'}
                            </Link>
                        </Button>
                    </Empty>
                </section>
            ) : (
                <>
                    <section
                        className="panel flex flex-wrap items-center justify-between gap-4"
                        aria-label="Periode dashboard"
                    >
                        <div className="flex items-center gap-3">
                            <span className="rounded-lg bg-primary/10 p-3 text-primary">
                                <CalendarDays size={20} aria-hidden="true" />
                            </span>
                            <div>
                                <p className="text-xs text-muted-foreground">Periode pelayanan</p>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2>{periodLabel(period)}</h2>
                                    <Badge muted={period.status !== 'open'}>
                                        {statusLabels[period.status]}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <div className="w-full sm:w-60">
                            <Field label="Pilih periode">
                                <select
                                    value={period.id}
                                    onChange={(event) => router.get('/dashboard', {
                                        period: event.target.value,
                                        ...(taskCounts ? { task: selectedTask } : {}),
                                    })}
                                >
                                    {periods.map((item) => (
                                        <option key={item.id} value={item.id}>{periodLabel(item)}</option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                    </section>

                    {summary && (
                        <div
                            className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                            aria-label="Ringkasan pelayanan"
                        >
                            {cards.map(({ title, value, note, icon: Icon }) => (
                                <Card className="stat-card" key={title}>
                                    <CardHeader>
                                        <CardDescription>{title}</CardDescription>
                                        <Icon aria-hidden="true" />
                                    </CardHeader>
                                    <CardContent>
                                        <span className="stat-number">{number(value)}</span>
                                        <p className="stat-note">{note}</p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}

                    <div className="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
                        <section className="panel" aria-labelledby="follow-up-heading">
                            <div className="section-heading">
                                <div>
                                    <h2 id="follow-up-heading">Perlu ditindaklanjuti</h2>
                                    <p className="subtle">
                                        Pilih pekerjaan, lalu buka catatan warga untuk melanjutkan.
                                    </p>
                                </div>
                            </div>
                            {tasks && taskCounts ? (
                                <>
                                    <nav className="mb-4 flex flex-wrap gap-2" aria-label="Jenis tindak lanjut">
                                        {(Object.keys(taskCounts) as TaskKind[]).map((kind) => (
                                            <Button
                                                key={kind} asChild size="sm"
                                                variant={kind === selectedTask ? 'secondary' : 'outline'}
                                            >
                                                <Link
                                                    href={'/dashboard?period=' + period.id + '&task=' + kind}
                                                    preserveScroll
                                                    aria-current={kind === selectedTask ? 'page' : undefined}
                                                >
                                                    {taskLabels[kind]}
                                                    <span className="rounded bg-muted px-1.5 text-xs tabular-nums">
                                                        {number(taskCounts[kind] ?? 0)}
                                                    </span>
                                                </Link>
                                            </Button>
                                        ))}
                                    </nav>
                                    <p className="subtle">{taskDescriptions[selectedTask]}</p>
                                    {tasks.data.length ? (
                                        <div
                                            className="table-scroll" tabIndex={0}
                                            role="region" aria-label="Daftar tindak lanjut"
                                        >
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th>Warga</th>
                                                        <th>Status</th>
                                                        {selectedTask !== 'attendance' && (
                                                            <th>{selectedTask === 'drafts' ? 'Tanggal pelayanan' : 'Jadwal tindak lanjut'}</th>
                                                        )}
                                                        <th>Tindakan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {tasks.data.map((task) => (
                                                        <tr key={task.id}>
                                                            <td>
                                                                <strong>{task.resident_name}</strong>
                                                                <small>{task.registration_number}</small>
                                                            </td>
                                                            <td>
                                                                <Badge muted>
                                                                    {statusLabels[task.status] ?? followUpLabels[task.status]}
                                                                </Badge>
                                                            </td>
                                                            {selectedTask !== 'attendance' && (
                                                                <td>
                                                                    {task.date ? dateLabel(task.date) : 'Belum dijadwalkan'}
                                                                    {task.overdue && (
                                                                        <small className="font-medium text-destructive">
                                                                            Lewat jadwal
                                                                        </small>
                                                                    )}
                                                                </td>
                                                            )}
                                                            <td>
                                                                <Link href={task.href} className="inline-flex items-center gap-1">
                                                                    {selectedTask === 'attendance' ? 'Buka sasaran' : 'Buka pelayanan'}
                                                                    <ArrowRight size={14} aria-hidden="true" />
                                                                </Link>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <Empty>
                                            {tasks.total > 0
                                                ? 'Tidak ada pekerjaan di halaman ini. Gunakan halaman sebelumnya.'
                                                : 'Tidak ada tindak lanjut pada kategori ini.'}
                                        </Empty>
                                    )}
                                    <Pagination data={tasks} />
                                </>
                            ) : (
                                <Empty>
                                    Detail warga memerlukan izin identitas dan pelayanan.
                                    Ringkasan tetap tersedia sesuai izin akun.
                                </Empty>
                            )}
                        </section>

                        <aside className="space-y-5">
                            {summary && (
                                <section className="panel" aria-labelledby="attendance-heading">
                                    <h2 id="attendance-heading">Kehadiran periode ini</h2>
                                    <p className="my-4 text-3xl font-semibold tracking-tight tabular-nums">
                                        {coverage === null ? '—' : coverage + '%'}
                                        <span className="ml-2 text-xs font-normal tracking-normal text-muted-foreground">
                                            sudah hadir
                                        </span>
                                    </p>
                                    <progress
                                        className="h-2 w-full accent-primary"
                                        max={summary.targets || 1} value={summary.present}
                                        aria-label="Cakupan kehadiran"
                                    />
                                    <dl className="mt-4 space-y-3 text-sm">
                                        {[
                                            ['Hadir', summary.present],
                                            ['Tidak hadir', summary.absent],
                                            ['Belum dikonfirmasi', summary.unconfirmed],
                                        ].map(([label, value]) => (
                                            <div key={label} className="flex items-center justify-between gap-2">
                                                <dt className="text-muted-foreground">{label}</dt>
                                                <dd className="font-medium tabular-nums">
                                                    {typeof value === 'number' ? number(value) : value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </section>
                            )}
                            <section className="panel">
                                <h2>Akses cepat</h2>
                                <div className="mt-3 flex flex-col gap-3 text-sm">
                                    {taskCounts && (
                                        <Link href={'/sasaran/' + period.id} className="inline-flex items-center justify-between">
                                            Buka data sasaran<ArrowRight size={15} aria-hidden="true" />
                                        </Link>
                                    )}
                                    {canViewVisits && (
                                        <Link href={'/sasaran/' + period.id + '/rumah'} className="inline-flex items-center justify-between">
                                            Kunjungan rumah<ArrowRight size={15} aria-hidden="true" />
                                        </Link>
                                    )}
                                    {canViewAggregates && (
                                        <Link href={'/rekapitulasi?period_id=' + period.id} className="inline-flex items-center justify-between">
                                            Lihat rekapitulasi<ArrowRight size={15} aria-hidden="true" />
                                        </Link>
                                    )}
                                </div>
                                <p className="mt-4 text-xs leading-relaxed text-muted-foreground">
                                    Dashboard mengikuti pencatatan pelayanan saat ini.
                                    Arsip laporan tetap tersedia di rekapitulasi.
                                </p>
                            </section>
                        </aside>
                    </div>
                </>
            )}
        </Layout>
    );
}
