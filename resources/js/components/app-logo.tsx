import Crest from '@/components/crest';
import Wordmark from '@/components/wordmark';

export default function AppLogo() {
    return (
        <>
            <Crest className="h-8 w-auto shrink-0" />
            <Wordmark className="ml-2 flex-1 text-left text-[13px]" />
        </>
    );
}
