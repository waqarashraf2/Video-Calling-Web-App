import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

function setCsrfToken(token) {
    if (!token) return;

    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.setAttribute('content', token);
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

window.setCsrfToken = setCsrfToken;
setCsrfToken(document.querySelector('meta[name="csrf-token"]')?.content);

window.refreshCsrfToken = async function refreshCsrfToken() {
    const response = await axios.get('/csrf-token', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        withCredentials: true,
    });
    setCsrfToken(response.data.token);

    return response.data.token;
};

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const original = error.config;
        const isSafeRefreshRequest = original?.url?.includes('/csrf-token');

        if (error.response?.status === 419 && original && !original.__csrfRetry && !isSafeRefreshRequest) {
            original.__csrfRetry = true;
            const token = await window.refreshCsrfToken();
            original.headers = original.headers || {};
            original.headers['X-CSRF-TOKEN'] = token;

            return window.axios(original);
        }

        throw error;
    },
);
