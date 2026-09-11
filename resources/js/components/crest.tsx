import type { SVGProps } from 'react';

/**
 * The Harkness Academy crest (D21) — the single artwork source for the rail, the exam
 * cover, completion states, the favicon and package 7's icons (spec 8.3.5).
 * No call site draws its own shield.
 *
 * `filled` is the app mark: a wine shield outlined in gold.
 * `outline` is the one-ink printing used on the blue-book cover; it takes its
 * colour from `currentColor`, so the surface decides the ink.
 */
export default function Crest({
    variant = 'filled',
    ...props
}: SVGProps<SVGSVGElement> & { variant?: 'filled' | 'outline' }) {
    const field = variant === 'filled' ? 'var(--wine)' : 'none';
    const line = variant === 'filled' ? 'var(--gold)' : 'currentColor';

    return (
        <svg
            viewBox="0 0 34 40"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Harkness Academy"
            {...props}
        >
            <path
                d="M2 2h30v22c0 8-9 12-15 14-6-2-15-6-15-14V2z"
                fill={field}
                stroke={line}
                strokeWidth={1.8}
            />
            <path
                d="M2 15l15 7 15-7"
                stroke={line}
                strokeWidth={1.6}
                fill="none"
            />
            <text
                x={17}
                y={13}
                textAnchor="middle"
                fill={line}
                fontFamily="Georgia, serif"
                fontSize={9.5}
                letterSpacing={1}
            >
                HA
            </text>
        </svg>
    );
}
