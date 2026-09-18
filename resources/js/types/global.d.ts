import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** Set while an admin views as a student (spec 13.4.4). */
            viewingAs: {
                mode: 'preview' | 'act_as';
                reason: string | null;
            } | null;
            [key: string]: unknown;
        };
    }
}
