import { Link } from '@inertiajs/react';
import {
    cloneElement,
    isValidElement,
    useId,
    type ComponentProps,
    type ReactElement,
    type ReactNode,
} from 'react';
import { Button as ShadcnButton } from './ui/button';
import { Badge as ShadcnBadge } from './ui/badge';
import type { Pagination as PaginationData } from '../types';

export { cn } from '../lib/utils';

type ButtonProps = Omit<ComponentProps<typeof ShadcnButton>, 'variant'> & {
    variant?: ComponentProps<typeof ShadcnButton>['variant'] | 'danger';
};
export function Button({ variant, type = 'button', ...props }: ButtonProps) {
    return (
        <ShadcnButton
            type={type}
            variant={variant === 'danger' ? 'destructive' : variant}
            {...props}
        />
    );
}
export function Field({
    label,
    error,
    hint,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    const id = useId();
    const control = isValidElement(children)
        ? cloneElement(
              children as ReactElement<{
                  id?: string;
                  'aria-describedby'?: string;
                  'aria-invalid'?: boolean;
              }>,
              {
                  id,
                  'aria-describedby': hint || error ? id + '-help' : undefined,
                  'aria-invalid': !!error,
              },
          )
        : children;
    return (
        <div className="field">
            <label htmlFor={id}>{label}</label>
            {control}
            <div id={id + '-help'}>
                {hint && <small>{hint}</small>}
                {error && (
                    <span className="field-error" role="alert">
                        {error}
                    </span>
                )}
            </div>
        </div>
    );
}
export function Errors({ errors }: { errors: Record<string, string | undefined> }) {
    const messages = [...new Set(Object.values(errors).filter(Boolean))];
    return messages.length ? (
        <div className="error-box" role="alert">
            <strong>Data belum tersimpan.</strong>
            <ul>
                {messages.map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
        </div>
    ) : null;
}
export function Badge({ children, muted = false }: { children: ReactNode; muted?: boolean }) {
    return (
        <ShadcnBadge
            variant={muted ? 'outline' : 'secondary'}
            className={muted ? 'text-muted-foreground' : 'status-badge'}
        >
            {children}
        </ShadcnBadge>
    );
}
export function Empty({ children }: { children: ReactNode }) {
    return <div className="empty">{children}</div>;
}
export function Pagination({ data }: { data: PaginationData<unknown> }) {
    return (
        <div className="pagination">
            <span>
                {data.total} data · Halaman {data.current_page} dari {data.last_page}
            </span>
            <div className="flex gap-3">
                {data.prev_page_url && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={data.prev_page_url}>Sebelumnya</Link>
                    </Button>
                )}
                {data.next_page_url && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={data.next_page_url}>Berikutnya</Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
