import { useForm } from '@inertiajs/react';
import { Button, Empty, Errors, Field } from './ui';
import { usePermission } from './Layout';
import type { Period } from '../types';
export type Activity = { id: number; name: string; scheduled_on: string; location: string | null };
export default function Activities({
    period,
    activities,
}: {
    period: Period;
    activities: Activity[];
}) {
    const canManage = usePermission('targets.manage');
    const form = useForm({
        revision: period.revision,
        name: '',
        scheduled_on: period.reference_date.slice(0, 10),
        location: '',
    });
    return (
        <section className="panel">
            <h2>Kegiatan periode</h2>
            {activities.length ? (
                activities.map((activity) => (
                    <div className="history-row" key={activity.id}>
                        <span>
                            {activity.name} · {activity.scheduled_on}
                        </span>
                        <span>{activity.location || 'Lokasi belum dicatat'}</span>
                    </div>
                ))
            ) : (
                <Empty>Belum ada jadwal kegiatan khusus.</Empty>
            )}
            {canManage && ['open', 'returned'].includes(period.status) && (
                <details>
                    <summary>Tambah kegiatan Posyandu</summary>
                    <form
                        className="form-stack"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((data) => ({ ...data, revision: period.revision }));
                            form.post('/sasaran/' + period.id + '/kegiatan', {
                                onSuccess: () => form.reset('name', 'location'),
                            });
                        }}
                    >
                        <Errors errors={form.errors} />
                        <div className="form-grid">
                            <Field label="Nama kegiatan">
                                <input
                                    required
                                    maxLength={150}
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                />
                            </Field>
                            <Field label="Tanggal kegiatan">
                                <input
                                    required
                                    type="date"
                                    value={form.data.scheduled_on}
                                    onChange={(e) => form.setData('scheduled_on', e.target.value)}
                                />
                            </Field>
                            <Field label="Lokasi kegiatan">
                                <input
                                    maxLength={300}
                                    value={form.data.location}
                                    onChange={(e) => form.setData('location', e.target.value)}
                                />
                            </Field>
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            Simpan kegiatan
                        </Button>
                    </form>
                </details>
            )}
        </section>
    );
}
