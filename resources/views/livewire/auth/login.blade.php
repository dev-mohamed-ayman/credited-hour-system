<div class="row w-100 h-100 m-0 p-0">
    <div class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg p-sm-12 p-6">
        <div class="w-px-400 mx-auto mt-sm-12 mt-8">
            <div class="text-center mb-6">
                <span class="app-brand-logo demo mb-3">
                    <span class="text-primary">
                        <svg width="32" height="22" viewBox="0 0 32 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z"
                                fill="currentColor" />
                            <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                                d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616" />
                            <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                                d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616" />
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z"
                                fill="currentColor" />
                        </svg>
                    </span>
                </span>
                <h3 class="mb-1 fw-bold">نظام الساعات المعتمدة</h3>
                <p class="mb-0 text-body">قم بتسجيل الدخول للوصول إلى لوحة التحكم</p>
            </div>

            <form wire:submit="login">
                <div class="mb-5">
                    <label for="login-email" class="form-label">البريد الإلكتروني</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-mail"></i></span>
                        <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror"
                               id="login-email" placeholder="admin@example.com" autofocus>
                    </div>
                    @error('email')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-5">
                    <label for="login-password" class="form-label">كلمة المرور</label>
                    <div class="input-group input-group-merge" x-data="{ showPassword: false }">
                        <span class="input-group-text"><i class="ti tabler-lock"></i></span>
                        <input :type="showPassword ? 'text' : 'password'" wire:model="password"
                               class="form-control @error('password') is-invalid @enderror"
                               id="login-password" placeholder="············">
                        <span class="input-group-text cursor-pointer" @click="showPassword = !showPassword">
                            <i class="ti" :class="showPassword ? 'tabler-eye' : 'tabler-eye-off'"></i>
                        </span>
                    </div>
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-5 d-flex justify-content-between flex-wrap gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="remember" id="remember-me">
                        <label class="form-check-label" for="remember-me">تذكرني</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary d-grid w-100" wire:loading.attr="disabled">
                    <span wire:loading.remove>
                        <i class="ti tabler-login me-1"></i> تسجيل الدخول
                    </span>
                    <span wire:loading>
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        جاري التحقق...
                    </span>
                </button>
            </form>
        </div>
    </div>

    <div class="d-none d-lg-flex col-lg-7 col-xl-8 align-items-center justify-content-center p-12 pb-2">
        <div class="text-center">
            <img src="{{asset('assets/img/illustrations/auth-login-illustration-light.png')}}"
                 class="authentication-image-model d-none d-lg-block"
                 alt="auth-illustration"
                 data-app-light-img="illustrations/auth-login-illustration-light.png"
                 data-app-dark-img="illustrations/auth-login-illustration-dark.png"
                 width="500">
        </div>
    </div>
</div>
