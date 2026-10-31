import { Head, useForm } from '@inertiajs/react';
import { Leaf, LockKeyhole } from 'lucide-react';
import { Button, Field, Errors } from '../../components/ui';

export default function Login() {
    const form = useForm({ email: '', password: '' });
    return (
        <div className="login-page">
            <Head title="Masuk" />
            <section className="login-story">
                <span className="brand-mark">
                    <Leaf size={28} />
                </span>
                <p className="eyebrow">POSYANDU JERUK</p>
                <h1>
                    Pelayanan dimulai
                    <br />
                    dari data yang baik.
                </h1>
                <p>
                    Satu ruang kerja untuk mencatat warga, pelayanan, dan rekap kegiatan Posyandu.
                </p>
                <div className="login-story-note">
                    Tertib mencatat. Mudah meninjau.
                    <br />
                    Bersama melayani masyarakat.
                </div>
            </section>
            <main className="login-panel">
                <div className="login-form">
                    <LockKeyhole className="text-orange-700" size={28} />
                    <h2>Selamat datang kembali</h2>
                    <p>Masuk menggunakan akun pengurus yang telah dibuat oleh Ketua.</p>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/login', { onFinish: () => form.reset('password') });
                        }}
                    >
                        <Errors errors={form.errors} />
                        <Field label="Email" error={form.errors.email}>
                            <input
                                name="email"
                                type="email"
                                autoComplete="username"
                                required
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                        </Field>
                        <Field label="Kata sandi" error={form.errors.password}>
                            <input
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                required
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                        </Field>
                        <Button type="submit" disabled={form.processing} className="w-full">
                            {form.processing ? 'Memeriksa…' : 'Masuk ke ruang kerja'}
                        </Button>
                    </form>
                    <small>
                        Registrasi publik dan pemulihan kata sandi belum tersedia. Hubungi Ketua
                        jika membutuhkan akses.
                    </small>
                </div>
            </main>
        </div>
    );
}
