import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight, House, Users } from 'lucide-react';
import Layout from '../../components/Layout';
import { Badge, Button, Empty, Pagination } from '../../components/ui';
import { Card, CardContent, CardDescription, CardHeader } from '../../components/ui/card';
import { periodLabel, statusLabels, type Pagination as PageData, type Resident } from '../../types';

type Household = {
    id: number;
    head_of_household_name: string;
    family_card_display: string | null;
    address: string | null;
    rt: string | null;
    rw: string | null;
    is_active: boolean;
    residents_count: number;
};
type Member = Pick<
    Resident,
    | 'id'
    | 'registration_number'
    | 'full_name'
    | 'gender'
    | 'birth_date'
    | 'is_birth_date_estimated'
    | 'residency_status'
    | 'family_relationship'
> & { detail_url: string };
type HomeVisit = {
    id: number;
    visited_on: string;
    reason: string;
    follow_up: string | null;
    record_status: string;
    period: { id: number; month: number; year: number; status: string };
    staff: { id: number; name: string }[];
    residents: { id: number; full_name: string; detail_url: string | null }[];
    period_url: string;
};

function dateLabel(date: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'UTC' }).format(
        new Date(date.slice(0, 10) + 'T00:00:00Z'),
    );
}

export default function HouseholdDetail({
    household,
    members,
    homeVisits,
    links,
}: {
    household: Household;
    members: PageData<Member>;
    homeVisits: PageData<HomeVisit> | null;
    links: { households: string; residents: string };
}) {
    return (
        <Layout
            title="Detail keluarga"
            description="Profil, anggota, dan riwayat kunjungan rumah dalam satu tempat."
            action={
                <Button asChild variant="outline">
                    <Link href={links.households}>
                        <ArrowLeft />
                        Daftar keluarga
                    </Link>
                </Button>
            }
        >
            <div className="mb-5 grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <Card role="region" aria-labelledby="profil-keluarga">
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <CardDescription>Profil keluarga</CardDescription>
                                <h2 id="profil-keluarga" className="mt-1 break-words text-xl">
                                    {household.head_of_household_name}
                                </h2>
                            </div>
                            <Badge muted={!household.is_active}>
                                {household.is_active ? 'Aktif' : 'Diarsipkan'}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <p className="text-xs text-muted-foreground">Nomor KK</p>
                                <p className="mt-1 break-all font-medium">
                                    {household.family_card_display || 'Belum diisi'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">RT / RW</p>
                                <p className="mt-1 font-medium">
                                    {household.rt || 'Belum diisi'} /{' '}
                                    {household.rw || 'Belum diisi'}
                                </p>
                            </div>
                            <div className="sm:col-span-2">
                                <p className="text-xs text-muted-foreground">Alamat keluarga</p>
                                <p className="mt-1 whitespace-pre-wrap break-words">
                                    {household.address || 'Alamat belum diisi.'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card aria-label="Ringkasan keluarga">
                    <CardHeader>
                        <CardDescription>Ringkasan keluarga</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Users
                                    className="mb-2 size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <strong className="text-3xl font-semibold">
                                    {household.residents_count}
                                </strong>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Anggota terhubung
                                </p>
                            </div>
                            <div>
                                <House
                                    className="mb-2 size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <strong className="text-3xl font-semibold">
                                    {homeVisits ? homeVisits.total : '—'}
                                </strong>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Catatan kunjungan
                                </p>
                            </div>
                        </div>
                        <p className="mt-4 text-xs leading-relaxed text-muted-foreground">
                            Daftar anggota mengikuti hubungan keluarga saat ini. Riwayat kunjungan
                            mencakup draft dan catatan final administratif.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card
                className="mb-5"
                id="anggota-keluarga"
                role="region"
                aria-labelledby="judul-anggota"
            >
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="judul-anggota">Anggota keluarga</h2>
                        <Button asChild variant="outline" size="sm">
                            <Link href={links.residents}>
                                Data Masyarakat
                                <ArrowUpRight />
                            </Link>
                        </Button>
                    </div>
                    <CardDescription>
                        Hubungkan atau ubah hubungan keluarga melalui detail warga.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {members.data.length ? (
                        <div
                            className="table-scroll"
                            tabIndex={0}
                            aria-label="Tabel anggota keluarga"
                        >
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nama anggota</th>
                                        <th>Hubungan keluarga</th>
                                        <th>Jenis kelamin</th>
                                        <th>Tanggal lahir</th>
                                        <th>Status</th>
                                        <th>Tindakan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {members.data.map((member) => (
                                        <tr key={member.id}>
                                            <td>
                                                <strong>{member.full_name}</strong>
                                                <small>{member.registration_number}</small>
                                            </td>
                                            <td>{member.family_relationship || 'Belum diisi'}</td>
                                            <td>
                                                {member.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                                            </td>
                                            <td>
                                                {member.birth_date
                                                    ? dateLabel(member.birth_date)
                                                    : 'Belum diketahui'}
                                                {member.is_birth_date_estimated && (
                                                    <small>Estimasi</small>
                                                )}
                                            </td>
                                            <td>
                                                <Badge muted={member.residency_status !== 'active'}>
                                                    {statusLabels[member.residency_status] ||
                                                        member.residency_status}
                                                </Badge>
                                            </td>
                                            <td>
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={member.detail_url}>
                                                        Detail warga
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <Empty>Belum ada anggota yang dihubungkan ke keluarga ini.</Empty>
                    )}
                    <Pagination data={members} />
                </CardContent>
            </Card>

            <Card id="riwayat-kunjungan" role="region" aria-labelledby="judul-kunjungan">
                <CardHeader>
                    <h2 id="judul-kunjungan">Riwayat kunjungan rumah</h2>
                    <CardDescription>
                        Catatan keluarga ini dari seluruh periode, diurutkan dari kunjungan terbaru.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {homeVisits === null ? (
                        <Empty>Akun Anda belum memiliki izin melihat riwayat pelayanan.</Empty>
                    ) : (
                        <>
                            {homeVisits.data.length ? (
                                <div className="grid gap-4">
                                    {homeVisits.data.map((visit) => (
                                        <article
                                            className="rounded-lg border border-border p-4"
                                            key={visit.id}
                                            aria-label={'Kunjungan ' + dateLabel(visit.visited_on)}
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <h3>{dateLabel(visit.visited_on)}</h3>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Periode {periodLabel(visit.period)} ·{' '}
                                                        {statusLabels[visit.period.status] ||
                                                            visit.period.status}
                                                    </p>
                                                </div>
                                                <Badge muted={visit.record_status === 'draft'}>
                                                    {visit.record_status === 'draft'
                                                        ? 'Draft'
                                                        : 'Final administratif'}
                                                </Badge>
                                            </div>
                                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <p className="text-xs text-muted-foreground">
                                                        Alasan kunjungan
                                                    </p>
                                                    <p className="mt-1 whitespace-pre-wrap break-words">
                                                        {visit.reason}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-muted-foreground">
                                                        Tindak lanjut
                                                    </p>
                                                    <p className="mt-1 whitespace-pre-wrap break-words">
                                                        {visit.follow_up || 'Belum dicatat'}
                                                    </p>
                                                </div>
                                            </div>
                                            <p className="mt-4 break-words text-xs text-muted-foreground">
                                                Petugas:{' '}
                                                {visit.staff
                                                    .map((staff) => staff.name)
                                                    .join(', ') || 'Belum dicatat'}
                                            </p>
                                            <div className="mt-3">
                                                <p className="text-xs text-muted-foreground">
                                                    Warga yang dikunjungi
                                                </p>
                                                {visit.residents.length ? (
                                                    <ul className="mt-1 flex flex-wrap gap-x-4 gap-y-2 text-sm">
                                                        {visit.residents.map((resident) => (
                                                            <li
                                                                className="break-words"
                                                                key={resident.id}
                                                            >
                                                                {resident.detail_url ? (
                                                                    <Link
                                                                        href={resident.detail_url}
                                                                    >
                                                                        {resident.full_name}
                                                                    </Link>
                                                                ) : (
                                                                    <span>
                                                                        {resident.full_name}
                                                                    </span>
                                                                )}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                ) : (
                                                    <p className="mt-1 text-sm">
                                                        Belum ada warga tercatat.
                                                    </p>
                                                )}
                                            </div>
                                            <div className="mt-4">
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={visit.period_url}>
                                                        Buka layanan periode
                                                        <ArrowUpRight />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            ) : (
                                <Empty>Belum ada kunjungan rumah untuk keluarga ini.</Empty>
                            )}
                            <Pagination data={homeVisits} />
                        </>
                    )}
                </CardContent>
            </Card>
        </Layout>
    );
}
