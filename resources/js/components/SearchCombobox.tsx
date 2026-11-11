import { useRef, useState, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { Field } from './ui';

export type SearchOption = {
    value: string;
    label: string;
    /** Teks tambahan untuk pencarian/kecocokan persis (default: label). */
    searchText?: string;
};
export type SearchSelection = SearchOption | { freeText: string };

export function isFreeTextSelection(
    selection: SearchSelection,
): selection is { freeText: string } {
    return 'freeText' in selection;
}

function normalize(text: string): string {
    return text.trim().toLowerCase();
}

export default function SearchCombobox({
    label,
    hint,
    error,
    disabled,
    options,
    selectedLabel,
    placeholder,
    emptyHint,
    allowFreeText = false,
    onSelect,
}: {
    label: string;
    hint?: string;
    error?: string;
    disabled?: boolean;
    options: SearchOption[];
    selectedLabel: string;
    placeholder?: string;
    emptyHint?: string;
    allowFreeText?: boolean;
    onSelect: (selection: SearchSelection) => void;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);

    const search = normalize(query);
    const matches = (option: SearchOption): boolean =>
        (option.searchText ?? option.label).toLowerCase().includes(search) ||
        option.label.toLowerCase().includes(search);
    const filtered = search ? options.filter(matches) : options;
    const exactMatch = options.find(
        (option) => (option.searchText ?? option.label).toLowerCase() === search,
    );
    const canFreeText = allowFreeText && search !== '' && !exactMatch;
    const itemCount = filtered.length + (canFreeText ? 1 : 0);
    const showList = open && itemCount > 0;
    const highlight = Math.min(active, itemCount - 1);

    const openList = () => {
        if (disabled) return;
        setQuery(selectedLabel);
        setActive(0);
        setOpen(true);
    };

    const select = (selection: SearchSelection) => {
        setOpen(false);
        setQuery('');
        onSelect(selection);
    };

    const commit = () => {
        const text = query.trim();
        const matched = options.find(
            (option) => (option.searchText ?? option.label).toLowerCase() === normalize(text),
        );
        setOpen(false);
        setQuery('');
        if (matched) {
            onSelect(matched);
        } else if (allowFreeText) {
            onSelect({ freeText: text });
        }
    };

    const onKeyDown = (event: ReactKeyboardEvent<HTMLInputElement>) => {
        if (!open) {
            if (['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) {
                event.preventDefault();
                openList();
            }
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => Math.min(index + 1, itemCount - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (highlight < filtered.length && filtered[highlight]) select(filtered[highlight]);
            else if (canFreeText) select({ freeText: query.trim() });
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            setQuery('');
        }
    };

    return (
        <Field label={label} hint={hint} error={error}>
            <div
                className="wilayah-combobox"
                ref={containerRef}
                onBlur={(event) => {
                    if (!containerRef.current?.contains(event.relatedTarget as Node)) commit();
                }}
            >
                <input
                    type="text"
                    role="combobox"
                    aria-expanded={showList}
                    aria-autocomplete="list"
                    autoComplete="off"
                    disabled={disabled}
                    placeholder={placeholder}
                    value={open ? query : selectedLabel}
                    onFocus={openList}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setActive(0);
                        setOpen(true);
                    }}
                    onKeyDown={onKeyDown}
                />
                {showList && (
                    <ul className="wilayah-combobox-list" role="listbox">
                        {filtered.map((option, index) => (
                            <li
                                key={option.value || 'empty-' + option.label}
                                role="option"
                                aria-selected={index === highlight}
                            >
                                <button
                                    type="button"
                                    className={index === highlight ? 'active' : undefined}
                                    tabIndex={-1}
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => select(option)}
                                >
                                    {option.label}
                                </button>
                            </li>
                        ))}
                        {canFreeText && (
                            <li
                                key="free-text"
                                role="option"
                                aria-selected={highlight === filtered.length}
                            >
                                <button
                                    type="button"
                                    className={highlight === filtered.length ? 'active' : undefined}
                                    tabIndex={-1}
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => select({ freeText: query.trim() })}
                                >
                                    Gunakan “{query.trim()}”
                                    {emptyHint && <small>{emptyHint}</small>}
                                </button>
                            </li>
                        )}
                    </ul>
                )}
            </div>
        </Field>
    );
}
