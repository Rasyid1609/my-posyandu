import { Calculator } from 'lucide-react';
import type { CalculationDetails } from '../types';
import { Badge } from './ui';

export default function ClinicalCalculationResults({
    details,
    pending = false,
    failed = false,
}: {
    details: CalculationDetails | null;
    pending?: boolean;
    failed?: boolean;
}) {
    return (
        <section
            className="rounded-lg border border-border bg-muted/20 p-4 sm:p-5"
            aria-label="Hasil hitung otomatis"
            aria-live="polite"
            aria-busy={pending}
        >
            <div className="mb-2 flex items-center gap-2">
                <Calculator className="size-4 text-primary" aria-hidden="true" />
                <h2 className="text-base font-semibold">Hasil hitung otomatis</h2>
            </div>
            <p className="mb-4 text-sm text-muted-foreground">
                Hasil mengikuti angka dan konteks pemeriksaan. Periksa sebelum finalisasi; kategori
                skrining memerlukan tindak lanjut petugas sesuai kebutuhan.
            </p>
            {pending && (
                <p className="animate-pulse text-sm text-muted-foreground" role="status">
                    Memperbarui perhitungan…
                </p>
            )}
            {failed && (
                <p className="text-sm text-destructive" role="alert">
                    Pratinjau belum tersedia. Periksa tanggal, angka, dan koneksi. Hasil tetap
                    dihitung ulang saat penyimpanan.
                </p>
            )}
            {details?.results && (
                <>
                    <p className="mb-3 text-xs text-muted-foreground">
                        Tanggal pemeriksaan: {details.reference_date} ·{' '}
                        {details.age_months == null
                            ? 'Usia belum pasti'
                            : `Usia ${details.age_months} bulan penuh`}{' '}
                        · {details.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                    </p>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {Object.entries(details.results).map(([key, result]) => {
                            const attention =
                                result.status === 'calculated' &&
                                !['normal', 'below_threshold', 'adequate'].includes(
                                    result.category ?? '',
                                );
                            const unit =
                                key === 'bmi'
                                    ? 'kg/m²'
                                    : key === 'height_age' || key === 'waist'
                                      ? 'cm'
                                      : key === 'glucose'
                                        ? 'mg/dL'
                                        : 'kg';
                            return (
                                <article
                                    key={key}
                                    className={`min-w-0 rounded-lg border p-3 ${attention ? 'border-destructive/30 bg-destructive/5' : 'border-border bg-card'}`}
                                    aria-label={result.title}
                                >
                                    <h3 className="mb-2 text-sm font-medium">{result.title}</h3>
                                    {result.value !== null && (
                                        <p className="mb-2 text-lg font-semibold tabular-nums">
                                            {key === 'weight_trend' && result.value > 0 ? '+' : ''}
                                            {new Intl.NumberFormat('id-ID', {
                                                maximumFractionDigits: 2,
                                            }).format(result.value)}{' '}
                                            <span className="text-xs font-normal text-muted-foreground">
                                                {unit}
                                            </span>
                                        </p>
                                    )}
                                    <p
                                        className={`text-sm ${attention ? 'font-medium text-destructive' : ''}`}
                                    >
                                        {result.label}
                                    </p>
                                    {result.reason && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {result.reason}
                                        </p>
                                    )}
                                    {result.previous && (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            Dibandingkan dengan {result.previous.weight_kg} kg pada{' '}
                                            {result.previous.date}.
                                        </p>
                                    )}
                                    {result.minimum_gain_g != null && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Standar kenaikan interval ini: {result.minimum_gain_g}{' '}
                                            g.
                                        </p>
                                    )}
                                    {result.source && (
                                        <a
                                            className="mt-2 inline-block text-xs text-primary underline-offset-4 hover:underline"
                                            href={result.source.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {result.source.title}
                                        </a>
                                    )}
                                </article>
                            );
                        })}
                    </div>
                    {details.corrected_height_cm != null &&
                        details.inputs.height_cm != null &&
                        Number(details.inputs.height_cm) !== details.corrected_height_cm && (
                            <p className="mt-3 text-xs text-muted-foreground">
                                Panjang/tinggi acuan setelah koreksi posisi ukur:{' '}
                                {details.corrected_height_cm} cm. Angka asli tetap disimpan.
                            </p>
                        )}
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <Badge>Hasil hitung otomatis</Badge>
                        {details.reviewed_at && (
                            <span className="text-xs text-muted-foreground">
                                Angka dan hasil hitung telah dikonfirmasi saat finalisasi
                                administratif.
                            </span>
                        )}
                    </div>
                </>
            )}
        </section>
    );
}
