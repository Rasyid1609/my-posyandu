import { Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    Plus,
    Search,
    Users,
    UserCheck,
    CalendarSearch,
    Columns3,
    ClipboardList,
} from 'lucide-react';
import { useState } from 'react';
import { BarChart, Bar, ResponsiveContainer, XAxis, YAxis, Tooltip, CartesianGrid } from 'recharts';
import Layout, { usePermission } from '../../components/Layout';
import { Badge, Button, Empty, Pagination } from '../../components/ui';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
    CardAction,
} from '../../components/ui/card';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuCheckboxItem,
} from '../../components/ui/dropdown-menu';
import { Input } from '../../components/ui/input';
import { statusLabels, type Pagination as PageData, type Resident } from '../../types';

type Stats = {
    total: number;
    active: number;
    missing_birth_date: number;
    estimated_birth_date: number;
    distribution: { name: string; value: number }[];
};
export default function Index({
    residents,
    stats,
    filters,
    categoryLabels,
    ageRule,
}: {
    residents: PageData<Resident> | null;
    stats: Stats | null;
    filters: { q?: string; status?: string; category?: string };
    categoryLabels: Record<string, string>;
    ageRule: { version: string; is_provisional: boolean };
}) {
    const canManage = usePermission('residents.manage');
    const [q, setQ] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || '');
    const [showRegistration, setShowRegistration] = useState(true);
    return (
        <Layout
            title="Data Masyarakat"
            description="Kelola identitas warga dan pantau kelengkapan data pelayanan."
            action={
                canManage && (
                    <Button asChild>
                        <Link href="/masyarakat/create">
                            <Plus />
                            Tambah warga
                        </Link>
                    </Button>
                )
            }
        >
            {stats && (
                <>
                    <div className="stat-grid">
                        {[
                            {
                                label: 'Warga unik',
                                value: stats.total,
                                icon: Users,
                                note: 'Sesuai filter yang dipilih',
                            },
                            {
                                label: 'Warga aktif',
                                value: stats.active,
                                icon: UserCheck,
                                note: 'Identitas, bukan jumlah kunjungan',
                            },
                            {
                                label: 'Tanggal lahir belum lengkap',
                                value: stats.missing_birth_date,
                                icon: CalendarSearch,
                                note: stats.estimated_birth_date + ' tanggal merupakan estimasi',
                            },
                        ].map(({ label, value, icon: Icon, note }) => (
                            <Card className="stat-card" key={label}>
                                <CardHeader>
                                    <CardDescription>{label}</CardDescription>
                                    <Icon aria-hidden="true" />
                                </CardHeader>
                                <CardContent>
                                    <strong className="stat-number">
                                        {value.toLocaleString('id-ID')}
                                    </strong>
                                    <p className="stat-note">{note}</p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <div className="overview-grid">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    <h2>Komposisi warga</h2>
                                </CardTitle>
                                <CardDescription>
                                    Distribusi kelompok usia sesuai filter.
                                </CardDescription>
                                <CardAction>
                                    <Badge muted>Sesuai filter</Badge>
                                </CardAction>
                            </CardHeader>
                            <CardContent>
                                {stats.total ? (
                                    <div className="chart" aria-hidden="true">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <BarChart
                                                data={stats.distribution}
                                                layout="vertical"
                                                margin={{ top: 8, left: 0, right: 16, bottom: 0 }}
                                            >
                                                <CartesianGrid
                                                    horizontal={false}
                                                    stroke="var(--border)"
                                                    strokeDasharray="3 3"
                                                />
                                                <XAxis
                                                    type="number"
                                                    allowDecimals={false}
                                                    axisLine={false}
                                                    tickLine={false}
                                                    tick={{
                                                        fill: 'var(--muted-foreground)',
                                                        fontSize: 11,
                                                    }}
                                                />
                                                <YAxis
                                                    dataKey="name"
                                                    type="category"
                                                    width={106}
                                                    axisLine={false}
                                                    tickLine={false}
                                                    tick={{
                                                        fill: 'var(--muted-foreground)',
                                                        fontSize: 12,
                                                    }}
                                                />
                                                <Tooltip
                                                    cursor={{ fill: 'var(--muted)', opacity: 0.6 }}
                                                    contentStyle={{
                                                        background: 'var(--popover)',
                                                        color: 'var(--popover-foreground)',
                                                        border: '1px solid var(--border)',
                                                        borderRadius: 'var(--radius-lg)',
                                                        fontSize: 12,
                                                    }}
                                                    itemStyle={{ color: 'var(--foreground)' }}
                                                />
                                                <Bar
                                                    dataKey="value"
                                                    name="Warga"
                                                    fill="var(--chart-3)"
                                                    radius={[0, 4, 4, 0]}
                                                    barSize={18}
                                                />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </div>
                                ) : (
                                    <Empty>Belum ada data untuk ditampilkan.</Empty>
                                )}
                                <div className="chart-values">
                                    {stats.distribution.map((item) => (
                                        <span key={item.name}>
                                            {item.name}
                                            <strong>{item.value}</strong>
                                        </span>
                                    ))}
                                </div>
                                <p className="mt-3 text-[11px] text-muted-foreground">
                                    Aturan {ageRule.version}
                                    {ageRule.is_provisional ? ' · sementara' : ''}
                                </p>
                            </CardContent>
                        </Card>
                        <Card className="context-card">
                            <CardContent>
                                <div className="context-icon">
                                    <ClipboardList size={18} aria-hidden="true" />
                                </div>
                                <span className="eyebrow">Pencatatan terhubung</span>
                                <h2>Satu warga, satu riwayat pelayanan.</h2>
                                <p>
                                    Identitas tersimpan sekali. Catat pengukuran dan pelayanan
                                    melalui sasaran pada periode kegiatan yang sesuai.
                                </p>
                            </CardContent>
                            <CardFooter>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full justify-between"
                                >
                                    <Link href="/sasaran">
                                        Buka Data Sasaran <ArrowUpRight />
                                    </Link>
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </>
            )}
            <section className="panel">
                <div className="section-heading residents-table-heading">
                    <div>
                        <h2>Daftar warga</h2>
                        <p className="subtle">
                            Cari dan periksa identitas sebelum menambah data baru.
                        </p>
                    </div>
                    {residents && (
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button asChild variant="outline" size="sm">
                                <Link href="/masyarakat/keluarga/daftar">
                                    Data keluarga & anggota
                                </Link>
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <Columns3 />
                                        Kolom
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-52">
                                    <DropdownMenuLabel>Tampilkan kolom</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuCheckboxItem
                                        checked={showRegistration}
                                        onCheckedChange={(checked) => setShowRegistration(checked)}
                                        onSelect={(event) => event.preventDefault()}
                                    >
                                        Nomor registrasi
                                    </DropdownMenuCheckboxItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    )}
                </div>
                <div className="category-tabs">
                    <Link className={!filters.category ? 'selected' : ''} href="/masyarakat">
                        Semua warga
                    </Link>
                    {Object.entries(categoryLabels).map(([code, label]) => (
                        <Link
                            key={code}
                            className={filters.category === code ? 'selected' : ''}
                            href={'/masyarakat?category=' + code}
                        >
                            {label}
                        </Link>
                    ))}
                </div>
                {residents ? (
                    <>
                        <form
                            className="filters"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(
                                    '/masyarakat',
                                    { q, status, category: filters.category },
                                    { preserveState: true },
                                );
                            }}
                        >
                            <div className="search-input">
                                <Search aria-hidden="true" />
                                <Input
                                    aria-label="Cari nama warga"
                                    placeholder="Cari nama warga…"
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    maxLength={100}
                                />
                            </div>
                            <select
                                aria-label="Filter status warga"
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                            >
                                <option value="">Semua status</option>
                                {['active', 'moved', 'deceased', 'inactive'].map((value) => (
                                    <option key={value} value={value}>
                                        {statusLabels[value]}
                                    </option>
                                ))}
                            </select>
                            <Button type="submit" variant="outline">
                                Terapkan
                            </Button>
                        </form>
                        {residents.data.length ? (
                            <div className="table-scroll" tabIndex={0}>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Nama warga</th>
                                            {showRegistration && <th>Nomor registrasi</th>}
                                            <th>Jenis kelamin</th>
                                            <th>Tanggal lahir</th>
                                            <th>Status</th>
                                            <th>
                                                <span className="sr-only">Tindakan</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {residents.data.map((resident) => (
                                            <tr key={resident.id}>
                                                <td>
                                                    <strong>{resident.full_name}</strong>
                                                </td>
                                                {showRegistration && (
                                                    <td className="mono">
                                                        {resident.registration_number}
                                                    </td>
                                                )}
                                                <td>
                                                    {resident.gender === 'L'
                                                        ? 'Laki-laki'
                                                        : 'Perempuan'}
                                                </td>
                                                <td>
                                                    {resident.birth_date?.slice(0, 10) ||
                                                        'Belum diketahui'}
                                                    {resident.is_birth_date_estimated && (
                                                        <small>Estimasi</small>
                                                    )}
                                                </td>
                                                <td>
                                                    <Badge
                                                        muted={
                                                            resident.residency_status !== 'active'
                                                        }
                                                    >
                                                        {statusLabels[resident.residency_status]}
                                                    </Badge>
                                                </td>
                                                <td>
                                                    <Button asChild variant="ghost" size="sm">
                                                        <Link
                                                            href={
                                                                '/masyarakat/' +
                                                                resident.id +
                                                                '/edit'
                                                            }
                                                        >
                                                            Detail <ArrowUpRight />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <Empty>
                                Belum ada warga sesuai filter.{' '}
                                {canManage && (
                                    <Link href="/masyarakat/create">Tambahkan warga pertama.</Link>
                                )}
                            </Empty>
                        )}
                        <Pagination data={residents} />
                    </>
                ) : (
                    <Empty>
                        Akun Anda memiliki akses agregat. Identitas warga memerlukan izin terpisah
                        dari Ketua.
                    </Empty>
                )}
            </section>
        </Layout>
    );
}
