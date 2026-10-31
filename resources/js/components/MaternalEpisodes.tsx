import { useForm } from '@inertiajs/react';
import { Button, Errors, Field } from './ui';
import type { Resident } from '../types';
type Episode = {
    id: number;
    episode_type: string;
    start_date: string;
    end_date: string | null;
    source: string;
};
const labels: Record<string, string> = {
    pregnant: 'Hamil',
    postpartum: 'Nifas',
    breastfeeding: 'Menyusui',
};
function Close({
    episode,
    resident,
    today,
}: {
    episode: Episode;
    resident: Resident;
    today: string;
}) {
    const form = useForm({ revision: resident.revision, end_date: today });
    return (
        <form
            className="inline-form"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({ ...data, revision: resident.revision }));
                form.patch('/masyarakat/' + resident.id + '/episode/' + episode.id);
            }}
        >
            <Field label="Tanggal akhir episode">
                <input
                    type="date"
                    required
                    min={episode.start_date.slice(0, 10)}
                    max={today}
                    value={form.data.end_date}
                    onChange={(e) => form.setData('end_date', e.target.value)}
                />
            </Field>
            <Errors errors={form.errors} />
            <Button type="submit" disabled={form.processing} variant="outline">
                Tutup episode
            </Button>
        </form>
    );
}
export default function MaternalEpisodes({
    resident,
    episodes,
    today,
    canManage,
}: {
    resident: Resident;
    episodes: Episode[];
    today: string;
    canManage: boolean;
}) {
    const form = useForm({
        revision: resident.revision,
        episode_type: 'pregnant',
        start_date: today,
        end_date: '',
        source: '',
    });
    return (
        <section className="panel">
            <h2>Episode maternal</h2>
            <p className="subtle">
                Status bertanggal dapat tumpang tindih dengan kelompok usia. Snapshot periode lama
                tidak berubah.
            </p>
            {canManage && (
                <form
                    className="form-stack"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({ ...data, revision: resident.revision }));
                        form.post('/masyarakat/' + resident.id + '/episode', {
                            onSuccess: () => form.reset('source', 'end_date'),
                        });
                    }}
                >
                    <Errors errors={form.errors} />
                    <div className="form-grid">
                        <Field label="Jenis episode">
                            <select
                                value={form.data.episode_type}
                                onChange={(e) => form.setData('episode_type', e.target.value)}
                            >
                                {Object.entries(labels).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Tanggal mulai episode">
                            <input
                                required
                                type="date"
                                max={today}
                                value={form.data.start_date}
                                onChange={(e) => form.setData('start_date', e.target.value)}
                            />
                        </Field>
                        <Field label="Tanggal akhir (jika diketahui)">
                            <input
                                type="date"
                                min={form.data.start_date}
                                value={form.data.end_date}
                                onChange={(e) => form.setData('end_date', e.target.value)}
                            />
                        </Field>
                        <Field label="Sumber penetapan episode">
                            <input
                                required
                                minLength={5}
                                maxLength={500}
                                value={form.data.source}
                                onChange={(e) => form.setData('source', e.target.value)}
                            />
                        </Field>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Tambah episode
                    </Button>
                </form>
            )}
            {episodes.map((episode) => (
                <article className="visit-card" key={episode.id}>
                    <strong>{labels[episode.episode_type]}</strong>
                    <p>
                        {episode.start_date.slice(0, 10)} —{' '}
                        {episode.end_date?.slice(0, 10) || 'Berlangsung'} · {episode.source}
                    </p>
                    {!episode.end_date && canManage && (
                        <Close episode={episode} resident={resident} today={today} />
                    )}
                </article>
            ))}
        </section>
    );
}
