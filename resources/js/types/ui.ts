import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};

/**
 * Where something sits in a numbered series — 'Lesson 2 of 5', 'Section 2 of 4'.
 *
 * Pre: `number` is 1-based and `1 <= number <= total`. The shell renders the
 * counts and the progress ticks from this one pair, so a caller that formats
 * its own "of 5" string somewhere else can drift from the ticks beside it.
 */
export type StudyPosition = {
    number: number;
    total: number;
};
