export interface TabItem {
    key: string;
    label: string;
    icon?: string;
}

interface ModalTabsProps {
    tabs: TabItem[];
    activeTab: string;
    onChange: (key: string) => void;
    activeClassName?: string;
    inactiveClassName?: string;
    className?: string;
}

const defaultActiveClassName = 'border-indigo-600 text-indigo-600';
const defaultInactiveClassName = 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';

export default function ModalTabs({ tabs, activeTab, onChange, activeClassName, inactiveClassName, className = '' }: ModalTabsProps) {
    return (
        <div className={`overflow-x-auto border-b border-gray-200 shrink-0 ${className}`}>
            <div className="flex min-w-max px-6">
                {tabs.map(tab => (
                    <button
                        key={tab.key}
                        onClick={() => onChange(tab.key)}
                        className={`flex items-center gap-1.5 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
                            activeTab === tab.key ? (activeClassName ?? defaultActiveClassName) : (inactiveClassName ?? defaultInactiveClassName)
                        }`}
                    >
                        {tab.icon && <i className={tab.icon}></i>}
                        {tab.label}
                    </button>
                ))}
            </div>
        </div>
    );
}