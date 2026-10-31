import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export function useDraftGuard(dirty: boolean, processing: boolean) {
    useEffect(() => {
        if (!dirty || processing) return;
        const unload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };
        window.addEventListener('beforeunload', unload);
        const remove = router.on('before', (event) => {
            if (
                event.detail.visit.method === 'get' &&
                !window.confirm('Perubahan belum tersimpan. Tinggalkan formulir?')
            )
                return false;
        });
        return () => {
            window.removeEventListener('beforeunload', unload);
            remove();
        };
    }, [dirty, processing]);
}
