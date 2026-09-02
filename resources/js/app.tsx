import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            // The pipeline screens are set in PipelineShell, which is the whole
            // viewport (spec 8.7). Nesting them in AppLayout would put the
            // starter kit's sidebar beside the shell's own header.
            case name.startsWith('courses/'):
                return null;
            // The lesson is set in StudyShell and the homework beside it in
            // AssessmentShell, both likewise the whole viewport (8.8, 8.9).
            case name.startsWith('lessons/'):
                return null;
            // The refresher is set in AssessmentShell (spec 8.9.4).
            case name.startsWith('refreshers/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
