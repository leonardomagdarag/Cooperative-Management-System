import { useRef, useState } from 'react';
import type { ButtonHTMLAttributes, MouseEvent, ReactNode } from 'react';

type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'link';
type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onClick'> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    icon?: ReactNode;
    isLoading?: boolean;
    loadingText?: string;
    disableOnClick?: boolean;
    onClick?: (event: MouseEvent<HTMLButtonElement>) => void | PromiseLike<unknown>;
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

export default function Button({
    variant = 'primary',
    size = 'md',
    icon,
    className = '',
    children,
    type = 'button',
    disabled,
    isLoading = false,
    loadingText,
    disableOnClick = false,
    onClick,
    ...props
}: ButtonProps) {
    const clickLocked = useRef(false);
    const [clickPending, setClickPending] = useState(false);
    const loading = isLoading || (disableOnClick && clickPending);

    const handleClick = (event: MouseEvent<HTMLButtonElement>) => {
        if (!disableOnClick) {
            onClick?.(event);
            return;
        }

        if (clickLocked.current || disabled || isLoading) {
            event.preventDefault();
            return;
        }

        clickLocked.current = true;
        setClickPending(true);

        const unlock = () => {
            clickLocked.current = false;
            setClickPending(false);
        };

        try {
            const result = onClick?.(event);
            if (result && typeof (result as PromiseLike<unknown>).then === 'function') {
                Promise.resolve(result).then(unlock, unlock);
            } else {
                // Hold the lock briefly so rapid double clicks cannot submit twice.
                window.setTimeout(unlock, 500);
            }
        } catch (error) {
            unlock();
            throw error;
        }
    };

    return (
        <button
            type={type}
            disabled={disabled || loading}
            aria-busy={loading}
            onClick={handleClick}
            className={`ui-btn ${variants[variant]} ${sizes[size]} ${className}`}
            {...props}
        >
            {loading ? (
                <>
                    <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent shrink-0" />
                    {loadingText ?? children}
                </>
            ) : (
                <>
                    {icon}
                    {children}
                </>
            )}
        </button>
    );
}
