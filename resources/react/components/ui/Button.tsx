import type { ButtonHTMLAttributes, ReactNode } from 'react';

type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'link';
type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    icon?: ReactNode;
}

const variants: Record<ButtonVariant, string> = {
    primary: 'ui-btn-primary',
    secondary: 'ui-btn-secondary',
    danger: 'ui-btn-danger',
    ghost: 'ui-btn-ghost',
    link: 'ui-btn-link',
};

const sizes: Record<ButtonSize, string> = {
    sm: 'ui-btn-sm',
    md: 'ui-btn-md',
    lg: 'ui-btn-lg',
};

export default function Button({ variant = 'primary', size = 'md', icon, className = '', children, type = 'button', ...props }: ButtonProps) {
    return (
        <button type={type} className={`ui-btn ${variants[variant]} ${sizes[size]} ${className}`} {...props}>
            {icon}
            {children}
        </button>
    );
}
