<div class="row w-100 h-100 m-0 p-0">
    <div class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg p-sm-12 p-6">
        <div class="w-px-400 mx-auto mt-sm-12 mt-8">
            <div class="text-center mb-6">
                <img src="{{ asset('images/aboukir-logo.png') }}" alt="معاهد أبوقير العليا" class="brand-logo-full mb-5">
                <h3 class="mb-1 fw-bold">بوابة الطلاب</h3>
                <p class="mb-0 text-body">قم بتسجيل الدخول للوصول إلى خدماتك الأكاديمية وتسجيل المواد</p>
            </div>

            <form wire:submit="login">
                <div class="mb-5">
                    <label for="login-username" class="form-label">الكود الجامعي</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-user"></i></span>
                        <input type="text" wire:model="username" class="form-control @error('username') is-invalid @enderror"
                               id="login-username" placeholder="أدخل الكود الجامعي" autofocus>
                    </div>
                    @error('username')
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
