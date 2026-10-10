import api, {
    csrf,
    saveUser,
    getStoredUser,
    clearAuth,
} from './api';

export {
    csrf,
    saveUser,
    getStoredUser,
    clearAuth,
};


/*
|--------------------------------------------------------------------------
| REGISTER
|--------------------------------------------------------------------------
*/

export const register = async (data) => {
    await csrf();

    return api.post('/register', {
        name: data.name,
        email: data.email,
        password: data.password,

        password_confirmation:
            data.password_confirmation ||
            data.passwordConfirmation,
    });
};


/*
|--------------------------------------------------------------------------
| REGISTRATION OTP
|--------------------------------------------------------------------------
*/

export const verifyRegistrationOtp = async (emailOrOtp, otpMaybe) => {
    const payload =
        otpMaybe !== undefined
            ? { email: emailOrOtp, otp: otpMaybe }
            : { otp: emailOrOtp };

    return api.post('/verify-registration-otp', payload);
};

export const resendRegistrationOtp = async (email = null) => {
    return api.post(
        '/resend-registration-otp',
        email ? { email } : {}
    );
};


/*
|--------------------------------------------------------------------------
| LOGIN / LOGOUT
|--------------------------------------------------------------------------
*/

export const login = async (email, password) => {
    await csrf();

    return api.post('/login', {
        email,
        password,
    });
};

export const getUser = async () => {
    return api.get('/user');
};

export const logout = async () => {
    try {
        return await api.post('/logout');
    } finally {
        clearAuth();
    }
};


/*
|--------------------------------------------------------------------------
| FORGOT PASSWORD / RESET
|--------------------------------------------------------------------------
*/

export const forgotPassword = async (email) => {
    await csrf();

    return api.post('/forgot-password', { email });
};

export const verifyPasswordResetOtp = async (emailOrOtp, otpMaybe) => {
    const payload =
        otpMaybe !== undefined
            ? { email: emailOrOtp, otp: otpMaybe }
            : { otp: emailOrOtp };

    return api.post('/verify-password-otp', payload);
};

export const verifyPasswordOtp = verifyPasswordResetOtp;

export const resendPasswordResetOtp = async (email = null) => {
    return api.post(
        '/resend-password-otp',
        email ? { email } : {}
    );
};

export const resendPasswordOtp = resendPasswordResetOtp;

export const resetPassword = async (payload, passwordConfirmation) => {
    if (typeof payload === 'string') {
        return api.post('/reset-password', {
            password: payload,
            password_confirmation: passwordConfirmation,
        });
    }

    return api.post('/reset-password', payload);
};


/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/

export const changePassword = async (data) => {
    return api.post('/profile/change-password', data);
};

export const updatePassword = changePassword;


/*
|--------------------------------------------------------------------------
| DEFAULT EXPORT
|--------------------------------------------------------------------------
*/

export default api;