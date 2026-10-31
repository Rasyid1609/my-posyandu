import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import { Badge, Button, Errors, Field } from '../../components/ui';
import { useDraftGuard } from '../../components/useDraftGuard';

type Account = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles: { name: string }[];
    permissions: { name: string }[];
};
export default function Accounts({
    users,
    roles,
    availablePermissions,
    rolePermissions,
    matrixVersion,
}: {
    users: Account[];
    roles: Record<string, string>;
    availablePermissions: Record<string, string>;
    rolePermissions: Record<string, string[]>;
    matrixVersion: string;
}) {
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm({
        name: '',
        email: '',
        password: '',
        role: 'admin',
        is_active: true,
        permissions: [] as string[],
    });
    const inheritedPermissions = rolePermissions[form.data.role] || [];
    useDraftGuard(form.isDirty, form.processing);
    return (
        <Layout
            title="Akun & izin"
            description="Utilitas Ketua. Izin bawaan jabatan dan penugasan tambahan pengurus."
        >
            <div className="info-box">
                Matriks izin administratif {matrixVersion} aktif. Izin bertanda “Bawaan jabatan”
                otomatis mengikuti jabatan dan tidak dapat dicabut melalui checkbox akun. Izin
                tambahan dapat diatur oleh Ketua. SOP klinis menunggu dokumen yang disahkan;
                finalisasi administratif bukan validasi klinis.
            </div>
            <section className="panel">
                <h2>Matriks izin aktif</h2>
                <div className="table-scroll" tabIndex={0}>
                    <table>
                        <thead>
                            <tr>
                                <th>Kemampuan</th>
                                {Object.entries(roles).map(([role, label]) => (
                                    <th key={role}>{label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(availablePermissions).map(([permission, label]) => (
                                <tr key={permission}>
                                    <td>{label}</td>
                                    {Object.keys(roles).map((role) => (
                                        <td key={role}>
                                            {rolePermissions[role]?.includes(permission)
                                                ? 'Ya'
                                                : 'Tidak'}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className="subtle">
                    Akun tanpa jabatan atau izin tetap tidak memiliki akses data. Izin individual
                    merupakan tambahan pada matriks ini.
                </p>
            </section>
            <section className="panel">
                <h2>{editing ? 'Edit akun' : 'Buat akun pengurus'}</h2>
                <form
                    className="form-stack"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const options = {
                            onSuccess: () => {
                                form.reset();
                                setEditing(null);
                            },
                        };
                        if (editing) form.put('/pengaturan/akun/' + editing, options);
                        else form.post('/pengaturan/akun', options);
                    }}
                >
                    <Errors errors={form.errors} />
                    <div className="form-grid">
                        <Field label="Nama petugas" error={form.errors.name}>
                            <input
                                required
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                        </Field>
                        <Field label="Email akun" error={form.errors.email}>
                            <input
                                required
                                type="email"
                                autoComplete="off"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                        </Field>
                        <Field
                            label={editing ? 'Kata sandi baru (opsional)' : 'Kata sandi awal'}
                            error={form.errors.password}
                        >
                            <input
                                type="password"
                                required={!editing}
                                minLength={12}
                                autoComplete="new-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                        </Field>
                        <Field label="Jabatan" error={form.errors.role}>
                            <select
                                value={form.data.role}
                                onChange={(e) => form.setData('role', e.target.value)}
                            >
                                {Object.entries(roles).map(([role, label]) => (
                                    <option value={role} key={role}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <label className="checkbox-field">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) => form.setData('is_active', e.target.checked)}
                            />
                            Akun aktif
                        </label>
                    </div>
                    {form.data.role === 'superadmin' ? (
                        <div className="info-box">
                            Ketua memiliki seluruh izin administratif yang tersedia. Superadmin
                            aktif terakhir dilindungi dari penonaktifan dan penurunan jabatan.
                        </div>
                    ) : (
                        <fieldset className="permission-grid">
                            <legend>Izin jabatan dan tambahan akun</legend>
                            {Object.entries(availablePermissions).map(([permission, label]) => (
                                <label className="checkbox-field" key={permission}>
                                    <input
                                        type="checkbox"
                                        disabled={inheritedPermissions.includes(permission)}
                                        checked={
                                            inheritedPermissions.includes(permission) ||
                                            form.data.permissions.includes(permission)
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'permissions',
                                                e.target.checked
                                                    ? [...form.data.permissions, permission]
                                                    : form.data.permissions.filter(
                                                          (item) => item !== permission,
                                                      ),
                                            )
                                        }
                                    />
                                    <span>
                                        {label}
                                        {inheritedPermissions.includes(permission) && (
                                            <small className="block text-stone-500 dark:text-stone-400">
                                                Bawaan jabatan
                                            </small>
                                        )}
                                    </span>
                                </label>
                            ))}
                        </fieldset>
                    )}
                    <div className="form-actions">
                        {editing && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setEditing(null);
                                    form.reset();
                                }}
                            >
                                Batal edit
                            </Button>
                        )}
                        <Button type="submit" disabled={form.processing}>
                            Simpan akun
                        </Button>
                    </div>
                </form>
            </section>
            <section className="panel">
                <h2>Pengurus terdaftar</h2>
                <div className="table-scroll" tabIndex={0}>
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Jabatan</th>
                                <th>Status</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <td>{user.name}</td>
                                    <td>{user.email}</td>
                                    <td>
                                        {user.roles.map((role) => roles[role.name]).join(', ') ||
                                            'Belum ditetapkan'}
                                    </td>
                                    <td>
                                        <Badge muted={!user.is_active}>
                                            {user.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </td>
                                    <td>
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                if (
                                                    form.isDirty &&
                                                    !window.confirm(
                                                        'Buang perubahan formulir yang belum tersimpan?',
                                                    )
                                                )
                                                    return;
                                                setEditing(user.id);
                                                form.setData({
                                                    name: user.name,
                                                    email: user.email,
                                                    password: '',
                                                    role: user.roles[0]?.name || 'admin',
                                                    is_active: user.is_active,
                                                    permissions: user.permissions.map(
                                                        (permission) => permission.name,
                                                    ),
                                                });
                                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                            }}
                                        >
                                            Edit akun
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </Layout>
    );
}
