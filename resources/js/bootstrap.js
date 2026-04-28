import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

delete window.axios.defaults.headers.common['X-CSRF-TOKEN'];

function hasCookie(name) {
    return document.cookie
        .split(';')
        .some((cookie) => cookie.trim().startsWith(`${name}=`));
}

function isUnsafeMethod(method) {
    return ['post', 'put', 'patch', 'delete'].includes((method ?? 'get').toLowerCase());
}

window.axios.interceptors.request.use(async (config) => {
    if (isUnsafeMethod(config.method) && !hasCookie('XSRF-TOKEN')) {
        await window.axios.get('/sanctum/csrf-cookie');
    }

    return config;
});

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        if (error.response?.status !== 419 || originalRequest?.__csrfRetry) {
            return Promise.reject(error);
        }

        originalRequest.__csrfRetry = true;
        await window.axios.get('/sanctum/csrf-cookie');
        delete originalRequest.headers?.['X-XSRF-TOKEN'];
        delete originalRequest.headers?.['x-xsrf-token'];

        return window.axios(originalRequest);
    },
);
