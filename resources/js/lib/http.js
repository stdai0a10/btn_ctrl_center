export function formErrors(error, fallback = '操作失敗。') {
    return error.response?.data?.data ?? { form: [error.response?.data?.message ?? fallback] };
}

export function errorMessage(error, fallback = '操作失敗。') {
    return error.response?.data?.message ?? fallback;
}
