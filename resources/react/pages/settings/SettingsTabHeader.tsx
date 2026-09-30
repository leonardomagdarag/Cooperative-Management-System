import HelpTip from '../../components/ui/HelpTip';
import { ReactNode } from 'react';

interface SettingsTabHeaderProps {
    eyebrow?: string;
    title: string;
    description: string;
    icon: string;
    action?: ReactNode;
    helpText?: string;
}

export default function SettingsTabHeader({ eyebrow = 'System settings', title, description, icon, action, helpText }: SettingsTabHeaderProps) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-4">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i className={`${icon} text-base`}></i>
                    </div>
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">{eyebrow}</p>
                        <h2 className="mt-0.5 flex items-center gap-2 text-lg font-bold text-gray-900">{title}{helpText && <HelpTip text={helpText} />}</h2>
                        <p className="mt-0.5 text-sm text-gray-500">{description}</p>
                    </div>
                </div>
                {action}
            </div>
        </div>
    );
}
