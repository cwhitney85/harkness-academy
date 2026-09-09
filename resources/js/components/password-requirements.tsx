export default function PasswordRequirements({
    id,
    requirements,
}: {
    id: string;
    requirements: string[];
}) {
    return (
        <div id={id} className="text-sm text-muted-foreground">
            <p>Your password needs:</p>
            <ul className="mt-1 list-disc pl-5">
                {requirements.map((requirement) => (
                    <li key={requirement}>{requirement}</li>
                ))}
            </ul>
        </div>
    );
}
