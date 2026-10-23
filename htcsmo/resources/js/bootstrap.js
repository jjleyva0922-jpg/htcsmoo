import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.response.use(response => response, error => {
    if ([401, 419].includes(error.response?.status)) window.htcSession?.show();
    return Promise.reject(error);
});
