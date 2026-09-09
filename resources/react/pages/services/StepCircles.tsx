interface StepColors {
    done: string;
    upcoming: string;
    connector: string;
}

export default function StepCircles({ step, steps, colors }: { step: number; steps: number; colors: StepColors }) {
    return (
        <div className="flex items-center justify-between">
            {Array.from({ length: steps }, (_, i) => i + 1).map(n => (
                <div key={n} className={`flex items-center ${n < steps ? 'flex-1' : ''}`}>
                    <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ${step >= n ? colors.done : colors.upcoming}`}>
                        {n}
                    </div>
                    {n < steps && <div className={`flex-1 h-1 mx-2 ${step > n ? 'bg-white' : colors.connector}`} />}
                </div>
            ))}
        </div>
    );
}