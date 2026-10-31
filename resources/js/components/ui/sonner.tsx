import { CircleCheck, CircleX, Info, LoaderCircle, TriangleAlert, X } from 'lucide-react';
import { useSyncExternalStore, type CSSProperties } from 'react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function subscribeTheme(onChange: () => void) {
    const observer = new MutationObserver(onChange);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    return () => observer.disconnect();
}

function getTheme(): 'light' | 'dark' {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

export function Toaster(props: ToasterProps) {
    const theme = useSyncExternalStore(subscribeTheme, getTheme, (): 'light' | 'dark' => 'light');
    return (
        <Sonner
            theme={theme}
            className="app-toaster"
            position="top-right"
            offset={{ top: 76, right: 24, bottom: 24, left: 24 }}
            mobileOffset={{ top: 76, right: 16, bottom: 16, left: 16 }}
            duration={5000}
            visibleToasts={1}
            closeButton
            containerAriaLabel="Notifikasi"
            icons={{
                success: (
                    <CircleCheck
                        className="size-5 text-emerald-700 dark:text-emerald-400"
                        aria-hidden="true"
                    />
                ),
                error: <CircleX className="size-5 text-destructive" aria-hidden="true" />,
                warning: (
                    <TriangleAlert
                        className="size-5 text-amber-700 dark:text-amber-400"
                        aria-hidden="true"
                    />
                ),
                info: (
                    <Info className="size-5 text-primary dark:text-orange-400" aria-hidden="true" />
                ),
                loading: (
                    <LoaderCircle
                        className="size-5 animate-spin text-primary dark:text-orange-400"
                        aria-hidden="true"
                    />
                ),
                close: <X className="size-3.5" aria-hidden="true" />,
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--border-radius': 'var(--radius)',
                    fontFamily: 'var(--font-sans)',
                } as CSSProperties
            }
            toastOptions={{
                closeButtonAriaLabel: 'Tutup notifikasi',
                classNames: {
                    toast: 'app-toast',
                    title: 'app-toast-title',
                    description: 'app-toast-description',
                    closeButton: 'app-toast-close',
                },
            }}
            {...props}
        />
    );
}
