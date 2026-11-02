import HelpTip from '../../components/ui/HelpTip';
import { ReactNode } from 'react';
import PageHeader from '../../components/ui/PageHeader';

interface SettingsTabHeaderProps {
    eyebrow?: string;
    title: string;
    description: string;
    icon: string;
    action?: ReactNode;
    helpText?: string;
}

export default function SettingsTabHeader({ eyebrow = 'System settings', title, description, icon, action, helpText }: SettingsTabHeaderProps) {
    return <PageHeader level="h2" eyebrow={eyebrow} title={<>{title}{helpText && <HelpTip text={helpText} />}</>} description={description} icon={icon} action={action} />;
}
