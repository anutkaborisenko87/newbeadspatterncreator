import { router } from '@inertiajs/react';
export const updateQuery = (key, value) => {
    const searchParams = new URLSearchParams(window.location.search);
    if (!value || value === '') {
        searchParams.delete(key);
    } else {
        searchParams.set(key, value);
    }

    const query = Object.fromEntries(searchParams.entries());
    router.get(window.location.pathname, query, {
        preserveState: true,
        replace: true,
    });
};
