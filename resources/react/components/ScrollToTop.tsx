import { useEffect } from 'react';
import { useLocation } from 'react-router';

export default function ScrollToTop() {
    const { pathname, hash } = useLocation();

    useEffect(() => {
        if (hash) {
            const id = decodeURIComponent(hash.replace('#', ''));
            const element = document.getElementById(id);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth' });
                return;
            }
        }

        window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
        if (document.documentElement) document.documentElement.scrollTop = 0;
        if (document.body) document.body.scrollTop = 0;

        const main = document.querySelector('main');
        if (main) {
            main.scrollTop = 0;
            main.scrollLeft = 0;
        }
    }, [pathname, hash]);

    return null;
}
