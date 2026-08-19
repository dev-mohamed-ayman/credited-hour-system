<div>
    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('student.dashboard') }}" class="btn btn-label-secondary btn-icon rounded-circle">
                <i class="ti tabler-arrow-right"></i>
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-heading">تغيير كلمة المرور</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                        <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item active">تغيير كلمة المرور</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0"><i class="ti tabler-lock me-2"></i>تحديث كلمة المرور</h5>
                </div>

                <div class="card-body pt-4"
                     x-data="{
                        showCurrent: false,
                        showNew: false,
                        showConfirm: false,
                        get password() { return $wire.password ?? '' },
                        get confirmation() { return $wire.password_confirmation ?? '' },
                        get hasUpper() { return /[A-Z]/.test(this.password) },
                        get hasLower() { return /[a-z]/.test(this.password) },
                        get hasNumber() { return /[0-9]/.test(this.password) },
                        get hasSpecial() { return /[@_#$%\/*\-+?.]/.test(this.password) },
                        get hasLength() { return this.password.length >= 8 },
                        get isMatching() { return this.password.length > 0 && this.password === this.confirmation }
                     }">
                    <form wire:submit="updatePassword">
                        <div class="mb-5">
                            <label for="current-password" class="form-label">كلمة المرور الحالية</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ti tabler-lock"></i></span>
                                <input :type="showCurrent ? 'text' : 'password'" wire:model="current_password"
                                       class="form-control @error('current_password') is-invalid @enderror"
                                       id="current-password" placeholder="············" autocomplete="current-password">
                                <span class="input-group-text cursor-pointer" @click="showCurrent = !showCurrent">
                                    <i class="ti" :class="showCurrent ? 'tabler-eye' : 'tabler-eye-off'"></i>
                                </span>
                            </div>
                            @error('current_password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-5">
                            <label for="new-password" class="form-label">كلمة المرور الجديدة</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ti tabler-key"></i></span>
                                <input :type="showNew ? 'text' : 'password'" wire:model="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       id="new-password" placeholder="············" autocomplete="new-password">
                                <span class="input-group-text cursor-pointer" @click="showNew = !showNew">
                                    <i class="ti" :class="showNew ? 'tabler-eye' : 'tabler-eye-off'"></i>
                                </span>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-5">
                            <label for="confirm-password" class="form-label">تأكيد كلمة المرور الجديدة</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ti tabler-key"></i></span>
                                <input :type="showConfirm ? 'text' : 'password'" wire:model="password_confirmation"
                                       class="form-control @error('password_confirmation') is-invalid @enderror"
                                       id="confirm-password" placeholder="············" autocomplete="new-password">
                                <span class="input-group-text cursor-pointer" @click="showConfirm = !showConfirm">
                                    <i class="ti" :class="showConfirm ? 'tabler-eye' : 'tabler-eye-off'"></i>
                                </span>
                            </div>
                            @error('password_confirmation')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info mb-5" role="alert">
                            <h6 class="alert-heading fw-bold mb-2">
                                <i class="ti tabler-info-circle me-1"></i>
                                شروط كلمة المرور
                            </h6>
                            <ul class="list-unstyled mb-0 small">
                                <li class="mb-1" :class="hasLength ? 'text-success' : ''">
                                    <i class="ti me-1" :class="hasLength ? 'tabler-circle-check' : 'tabler-circle'"></i>
                                    ألا تقل عن 8 أحرف
                                </li>
                                <li class="mb-1" :class="hasUpper ? 'text-success' : ''">
                                    <i class="ti me-1" :class="hasUpper ? 'tabler-circle-check' : 'tabler-circle'"></i>
                                    حرف كبير واحد على الأقل (A – Z)
                                </li>
                                <li class="mb-1" :class="hasLower ? 'text-success' : ''">
                                    <i class="ti me-1" :class="hasLower ? 'tabler-circle-check' : 'tabler-circle'"></i>
                                    حرف صغير واحد على الأقل (a – z)
                                </li>
                                <li class="mb-1" :class="hasNumber ? 'text-success' : ''">
                                    <i class="ti me-1" :class="hasNumber ? 'tabler-circle-check' : 'tabler-circle'"></i>
                                    رقم واحد على الأقل (0 – 9)
                                </li>
                                <li class="mb-1" :class="hasSpecial ? 'text-success' : ''">
                                    <i class="ti me-1" :class="hasSpecial ? 'tabler-circle-check' : 'tabler-circle'"></i>
                                    حرف خاص واحد على الأقل من (<span dir="ltr">&#64;_#$%/*-+?.</span>)
                                </li>
                                <li :class="isMatching ? 'text-success' : ''">
                                    <i class="ti me-1" :class="isMatching ? 'tabler-circle-check' : 'tabler-circle'"></i>
                                    تطابق كلمة المرور الجديدة مع التأكيد
                                </li>
                            </ul>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="updatePassword">
                                    <i class="ti tabler-device-floppy me-1"></i> حفظ كلمة المرور
                                </span>
                                <span wire:loading wire:target="updatePassword">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    جاري الحفظ...
                                </span>
                            </button>
                            <a href="{{ route('student.dashboard') }}" class="btn btn-label-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
