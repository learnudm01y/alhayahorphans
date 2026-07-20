/**
 * TokenInterceptor - نظام التجديد الصامت للرموز (Silent Token Refresh)
 * يعالج مشكلة انتهاء صلاحية الرمز أثناء العمل دون اتصال، ويمنع Race Conditions عند عودة الاتصال.
 */
class TokenInterceptor {
    constructor(apiBaseUrl) {
        this.apiBaseUrl = apiBaseUrl;
        this.isRefreshing = false;
        this.failedQueue = [];
    }

    /**
     * اعتراض الطلب قبل إرساله لضمان وجود الرمز
     */
    async interceptRequest(url, options = {}) {
        let token = localStorage.getItem('auth_token');
        
        if (!options.headers) {
            options.headers = {};
        }

        if (token) {
            options.headers['Authorization'] = `Bearer ${token}`;
        }
        
        return { url, options };
    }

    /**
     * معالجة الاستجابة ومحاولة التجديد عند ظهور 401
     */
    async handleResponse(response, originalUrl, originalOptions) {
        if (response.status === 401 && !originalOptions._retry) {
            if (this.isRefreshing) {
                // تجميد الطلبات في الطابور إذا كان التجديد جارياً
                return new Promise((resolve, reject) => {
                    this.failedQueue.push({ resolve, reject, url: originalUrl, options: originalOptions });
                });
            }

            originalOptions._retry = true;
            this.isRefreshing = true;

            try {
                const refreshToken = localStorage.getItem('refresh_token');
                
                if (!refreshToken) {
                    throw new Error('No refresh token available');
                }

                // محاولة الحصول على رمز جديد
                const refreshResponse = await fetch(`${this.apiBaseUrl}/mobile/refresh-token`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Bypass-Tunnel-Reminder': 'true'
                    },
                    body: JSON.stringify({ refresh_token: refreshToken })
                });

                if (!refreshResponse.ok) {
                    throw new Error('Refresh token invalid or expired');
                }

                const data = await refreshResponse.json();
                
                if (data.success && data.token) {
                    // تحديث الرمز المحلي
                    localStorage.setItem('auth_token', data.token);
                    
                    // تحرير الطابور
                    this.failedQueue.forEach(req => {
                        req.options.headers['Authorization'] = `Bearer ${data.token}`;
                        fetch(req.url, req.options)
                            .then(res => req.resolve(res))
                            .catch(err => req.reject(err));
                    });
                    this.failedQueue = [];

                    // إعادة إرسال الطلب الأصلي
                    originalOptions.headers['Authorization'] = `Bearer ${data.token}`;
                    return await fetch(originalUrl, originalOptions);
                } else {
                    throw new Error('Invalid refresh response structure');
                }

            } catch (error) {
                // فشل التجديد -> تفريغ الطابور كأخطاء وتسجيل خروج
                this.failedQueue.forEach(req => req.reject(error));
                this.failedQueue = [];
                this.forceLogout();
                throw error;
            } finally {
                this.isRefreshing = false;
            }
        }

        // إرجاع الاستجابة العادية إذا لم يكن هناك خطأ 401
        return response;
    }

    /**
     * تنفيذ طلب HTTP محمي باستخدام المعترض
     */
    async fetchWithAuth(url, options = {}) {
        try {
            const { url: reqUrl, options: reqOptions } = await this.interceptRequest(url, options);
            const response = await fetch(reqUrl, reqOptions);
            
            // تحقق من الـ 401 لمعالجة التجديد
            if (response.status === 401) {
                return await this.handleResponse(response, reqUrl, reqOptions);
            }
            
            return response;
        } catch (error) {
            // أخطاء الشبكة
            throw error;
        }
    }

    forceLogout() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('refresh_token');
        // يمكن إضافة حدث (event) لإعلام التطبيق بضرورة الانتقال لصفحة الدخول
        document.dispatchEvent(new CustomEvent('auth-expired'));
        console.error('Session expired. User logged out.');
    }
}

// يمكن استخدامه بشكل عام
// const tokenInterceptor = new TokenInterceptor('https://api.example.com/api');
