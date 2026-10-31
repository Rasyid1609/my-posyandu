import { useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { CalculationDetails } from '../types';

export function useClinicalCalculation(url: string, input: string, enabled: boolean) {
    const { post, cancel, transform } = useHttp<Record<string, string>, CalculationDetails>({});
    const [response, setResponse] = useState<{ key: string; details: CalculationDetails } | null>(
        null,
    );
    const [failedKey, setFailedKey] = useState<string | null>(null);
    const key = url + input;
    useEffect(() => {
        if (!enabled) return;
        let active = true;
        const timer = window.setTimeout(() => {
            transform(() => JSON.parse(input) as Record<string, string>);
            post(url, {
                onSuccess: (details) => {
                    if (active) setResponse({ key, details });
                },
                onError: () => {
                    if (active) setFailedKey(key);
                },
                onHttpException: () => {
                    if (active) setFailedKey(key);
                    return false;
                },
                onNetworkError: () => {
                    if (active) setFailedKey(key);
                    return false;
                },
            }).catch(() => {
                if (active) setFailedKey(key);
            });
        }, 350);
        return () => {
            active = false;
            window.clearTimeout(timer);
            cancel();
        };
    }, [url, input, enabled, post, cancel, transform, key]);
    const details = enabled && response?.key === key ? response.details : null;

    return {
        details,
        pending: enabled && details === null && failedKey !== key,
        failed: enabled && failedKey === key && details === null,
    };
}
