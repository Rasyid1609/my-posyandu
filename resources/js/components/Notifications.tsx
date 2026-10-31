import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { Toaster } from './ui/sonner';

export default function Notifications({ initialSuccess }: { initialSuccess?: string }) {
    useEffect(() => {
        let notificationId = 'initial-flash';
        let activeVisit: {
            id: string;
            timer: ReturnType<typeof setTimeout>;
            loading: boolean;
            settled: boolean;
        } | null = null;

        function finishLoading() {
            if (activeVisit) {
                clearTimeout(activeVisit.timer);
                activeVisit.settled = true;
            }
        }

        function showSuccess(message: string) {
            toast.success('Perubahan tersimpan', {
                id: notificationId,
                description: message,
                duration: 5000,
            });
        }

        if (initialSuccess) showSuccess(initialSuccess);

        const removeListeners = [
            router.on('start', ({ detail: { visit } }) => {
                if (visit.method === 'get' || ['/login', '/logout'].includes(visit.url.pathname))
                    return;
                if (activeVisit) clearTimeout(activeVisit.timer);
                toast.dismiss(notificationId);
                notificationId = 'data-change-' + visit.id;
                activeVisit = {
                    id: visit.id,
                    loading: false,
                    settled: false,
                    timer: setTimeout(() => {
                        if (!activeVisit || activeVisit.id !== visit.id) return;
                        activeVisit.loading = true;
                        toast.loading(
                            visit.url.pathname.includes('/import')
                                ? 'Memproses import…'
                                : 'Memproses perubahan…',
                            {
                                id: notificationId,
                                description: 'Tunggu sampai proses selesai.',
                            },
                        );
                    }, 400),
                };
            }),
            router.on('success', ({ detail: { page, visitId } }) => {
                if (!page.props.auth) {
                    toast.dismiss();
                    return;
                }
                if (!activeVisit || activeVisit.id !== visitId) return;
                finishLoading();
                const flash = page.props.flash as { success?: string } | undefined;
                if (flash?.success) showSuccess(flash.success);
                else if (activeVisit?.loading) toast.dismiss(notificationId);
            }),
            router.on('error', ({ detail: { errors, visitId } }) => {
                if (!activeVisit || activeVisit.id !== visitId) return;
                finishLoading();
                const message = Object.values(errors).find((value) => typeof value === 'string');
                const notify = errors.revision ? toast.warning : toast.error;
                notify(errors.revision ? 'Data telah berubah' : 'Data belum tersimpan', {
                    id: notificationId,
                    description: message || 'Periksa kolom yang ditandai lalu coba lagi.',
                    duration: Infinity,
                });
            }),
            router.on('networkError', (event) => {
                if (!activeVisit) return;
                event.preventDefault();
                finishLoading();
                toast.error('Hasil penyimpanan belum dapat dipastikan', {
                    id: notificationId,
                    description: 'Koneksi terputus. Periksa data terbaru sebelum mencoba kembali.',
                    duration: Infinity,
                });
            }),
            router.on('httpException', (event) => {
                if (!activeVisit || event.detail.response.status < 400) return;
                event.preventDefault();
                finishLoading();
                const status = event.detail.response.status;
                if (status === 419) {
                    toast.warning('Sesi telah berakhir', {
                        id: notificationId,
                        description: 'Muat ulang halaman dan masuk kembali sebelum melanjutkan.',
                        duration: Infinity,
                    });
                } else if (status === 403) {
                    toast.error('Akses tidak diizinkan', {
                        id: notificationId,
                        description: 'Akun Anda tidak memiliki izin untuk tindakan ini.',
                        duration: Infinity,
                    });
                } else if (status === 409) {
                    toast.warning('Data telah berubah', {
                        id: notificationId,
                        description: 'Periksa data terbaru sebelum menyimpan kembali.',
                        duration: Infinity,
                    });
                } else if (status === 429) {
                    toast.warning('Terlalu banyak permintaan', {
                        id: notificationId,
                        description: 'Tunggu beberapa saat sebelum mencoba kembali.',
                        duration: Infinity,
                    });
                } else {
                    toast.error('Permintaan belum berhasil', {
                        id: notificationId,
                        description:
                            'Periksa data terbaru sebelum mencoba kembali. Jika masih terjadi, hubungi pengelola.',
                        duration: Infinity,
                    });
                }
            }),
            router.on('finish', ({ detail: { visit } }) => {
                if (!activeVisit || activeVisit.id !== visit.id) return;
                clearTimeout(activeVisit.timer);
                if (activeVisit.loading && !activeVisit.settled) toast.dismiss(notificationId);
                activeVisit = null;
            }),
        ];

        return () => {
            removeListeners.forEach((removeListener) => removeListener());
            if (activeVisit) clearTimeout(activeVisit.timer);
        };
    }, [initialSuccess]);

    return <Toaster />;
}
