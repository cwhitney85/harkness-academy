import { usePage } from '@inertiajs/react';

/**
 * The app's name, set as W3 — stacked letterspaced caps (D46).
 *
 * One word per line, because the 252px rail is what decided the form: the
 * name set as one line of display type wraps there at any size large enough
 * to read, and stacking it fits with room to spare. The split is on
 * whitespace rather than a hardcoded pair of words, so `APP_NAME` stays the
 * one owner of the string (H1.2) and a one-word name still renders.
 *
 * Size and colour come from the call site; the letterform does not.
 */
export default function Wordmark({ className = '' }: { className?: string }) {
    const { name } = usePage().props;

    return (
        <div className={`wordmark ${className}`}>
            {name.split(/\s+/).map((word) => (
                <div key={word}>{word}</div>
            ))}
        </div>
    );
}
