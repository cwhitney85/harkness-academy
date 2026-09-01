import { usePage } from '@inertiajs/react';

import Crest from '@/components/crest';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <Crest className="h-8 w-auto shrink-0" />
            <div className="ml-1 grid flex-1 text-left">
                <span className="display truncate text-lg leading-tight">
                    {name}
                </span>
            </div>
        </>
    );
}
