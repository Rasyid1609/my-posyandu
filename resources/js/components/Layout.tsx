import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    ClipboardList,
    FileBarChart2,
    Leaf,
    LogOut,
    Menu,
    Moon,
    Settings2,
    ShieldCheck,
    Sun,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import type { AppProps } from '../types';
import { Button, cn } from './ui';

export function usePermission(permission: string) {
    return usePage<AppProps>().props.auth?.permissions.includes(permission) ?? false;
}
export default function Layout({
    title,
    description,
    children,
    action,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    action?: ReactNode;
}) {
    const { auth, configuration } = usePage<AppProps>().props;
    const url = usePage().url;
    const [open, setOpen] = useState(false);
    const sidebarRef = useRef<HTMLElement>(null);
    const menuRef = useRef<HTMLButtonElement>(null);
    const [dark, setDark] = useState(() => localStorage.getItem('jeruk-theme') === 'dark');
    useEffect(() => {
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem('jeruk-theme', dark ? 'dark' : 'light');
    }, [dark]);
    useEffect(() => {
        if (!open) return;
        const elements = sidebarRef.current?.querySelectorAll<HTMLElement>(
            'a[href], button:not([disabled])',
        );
        const first = elements?.[0];
        const last = elements?.[elements.length - 1];
        const trigger = menuRef.current;
        first?.focus();
        const keyboard = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setOpen(false);
            if (event.key === 'Tab' && event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            }
            if (event.key === 'Tab' && !event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        };
        document.addEventListener('keydown', keyboard);
        return () => {
            document.removeEventListener('keydown', keyboard);
            trigger?.focus();
        };
    }, [open]);
    const links = [
        { href: '/masyarakat', label: 'Data Masyarakat', icon: Users },
        { href: '/sasaran', label: 'Data Sasaran', icon: ClipboardList },
        { href: '/rekapitulasi', label: 'Data Rekapitulasi', icon: FileBarChart2 },
    ];
    return (
        <div className="app-shell style-nova" data-menu-color="default" data-menu-accent="subtle">
            <Head title={title} />
            <a className="skip-link" href="#main">
                Lewati navigasi
            </a>
            {open && (
                <button
                    className="sidebar-overlay"
                    aria-label="Tutup navigasi"
                    onClick={() => setOpen(false)}
                />
            )}
            <aside
                ref={sidebarRef}
                id="main-navigation"
                data-slot="sidebar"
                className={cn('sidebar', open && 'sidebar-open')}
                aria-label="Navigasi utama"
            >
                <Link className="brand" href="/masyarakat">
                    <span className="brand-mark">
                        <Leaf size={24} />
                    </span>
                    <span>
                        Posyandu Jeruk<small>Ruang kerja kader</small>
                    </span>
                </Link>
                <div className="sidebar-label">Pelayanan masyarakat</div>
                <nav aria-label="Navigasi utama">
                    {links.map(({ href, label, icon: Icon }) => (
                        <Link
                            key={href}
                            href={href}
                            className={cn('nav-link', url.startsWith(href) && 'nav-active')}
                            aria-current={url.startsWith(href) ? 'page' : undefined}
                        >
                            <Icon size={19} />
                            {label}
                        </Link>
                    ))}
                </nav>
                <div className="sidebar-note">
                    <Leaf size={20} />
                    <strong>
                        Data yang tertib,
                        <br />
                        pelayanan yang lebih baik.
                    </strong>
                    <p>
                        Catat pelayanan sesuai yang dilakukan. Periksa kembali sebelum finalisasi.
                    </p>
                </div>
                <div className="sidebar-bottom">
                    {auth?.roles.includes('superadmin') && (
                        <Link className="nav-link" href="/pengaturan/aturan">
                            <Settings2 size={18} />
                            Aturan usia
                        </Link>
                    )}
                    {auth?.roles.includes('superadmin') && (
                        <Link className="nav-link" href="/pengaturan/akun">
                            <Settings2 size={18} />
                            Akun & izin
                        </Link>
                    )}
                    <div className="account">
                        <span className="avatar">{auth?.user.name.charAt(0)}</span>
                        <span>
                            <strong>{auth?.user.name}</strong>
                            <small>
                                {auth?.roles
                                    .map(
                                        (role) =>
                                            ({
                                                superadmin: 'Ketua',
                                                sekretaris: 'Sekretaris',
                                                bendahara: 'Bendahara',
                                                admin: 'Anggota',
                                            })[role],
                                    )
                                    .join(', ') || 'Izin belum ditetapkan'}
                            </small>
                        </span>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Keluar"
                            onClick={() => router.post('/logout')}
                        >
                            <LogOut size={18} />
                        </Button>
                    </div>
                </div>
            </aside>
            <div className="main-shell">
                <header className="topbar">
                    <Button
                        ref={menuRef}
                        variant="ghost"
                        size="icon"
                        className="mobile-menu"
                        aria-controls="main-navigation"
                        aria-expanded={open}
                        aria-label={open ? 'Tutup menu' : 'Buka menu'}
                        onClick={() => setOpen(!open)}
                    >
                        {open ? <X /> : <Menu />}
                    </Button>
                    <span className="breadcrumb">
                        Posyandu Jeruk <ChevronRight aria-hidden="true" /> <strong>{title}</strong>
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label={dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'}
                        onClick={() => setDark(!dark)}
                    >
                        {dark ? <Sun size={17} /> : <Moon size={17} />}
                    </Button>
                </header>
                <main id="main" tabIndex={-1}>
                    {!configuration.real_data_approved && (
                        <div className="demo-notice">
                            <ShieldCheck aria-hidden="true" />
                            Lingkungan pengembangan{' '}
                            <span>
                                Gunakan data sintetis. Kelompok usia sementara; izin data nyata
                                belum ditetapkan.
                            </span>
                        </div>
                    )}
                    <div className="page-heading">
                        <div>
                            <p className="eyebrow">POSYANDU JERUK</p>
                            <h1>{title}</h1>
                            {description && <p className="description">{description}</p>}
                        </div>
                        {action}
                    </div>
                    {children}
                    <footer>
                        Posyandu Jeruk{' '}
                        <span>Waktu layanan ditampilkan dalam WIB · Asia/Jakarta</span>
                    </footer>
                </main>
            </div>
        </div>
    );
}
