<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">{{ $isEdit ? 'تعديل بيانات المستخدم والصلاحيات' : 'إضافة مستخدم جديد' }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">المستخدمين</a></li>
                    <li class="breadcrumb-item active">{{ $isEdit ? 'تعديل مستخدم' : 'إضافة مستخدم' }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                <i class="ti tabler-arrow-right me-1"></i> العودة للقائمة
            </a>
        </div>
    </div>

    <form wire:submit="save">
        <div class="row g-4">
            <!-- User Basic Details Card -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">بيانات المستخدم الأساسية</h5>
                    </div>
                    <div class="card-body pt-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="user-name" class="form-label">الاسم الكامل</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="ti tabler-user"></i></span>
                                    <input type="text" id="user-name" wire:model="name" class="form-control @error('name') is-invalid @enderror" placeholder="أدخل اسم المستخدم...">
                                </div>
                                @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="user-email" class="form-label">البريد الإلكتروني</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="ti tabler-mail"></i></span>
                                    <input type="email" id="user-email" wire:model="email" class="form-control @error('email') is-invalid @enderror" placeholder="example@domain.com">
                                </div>
                                @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="user-password" class="form-label">كلمة المرور {{ $isEdit ? '(اتركه فارغاً للاحتفاظ بالقديمة)' : '' }}</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="ti tabler-lock"></i></span>
                                    <input type="password" id="user-password" wire:model="password" class="form-control @error('password') is-invalid @enderror" placeholder="········">
                                </div>
                                @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            @if (auth()->user()->is_super_admin)
                                <div class="col-12 mt-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="user-super-admin" wire:model.live="is_super_admin">
                                        <label class="form-check-label fw-medium text-danger" for="user-super-admin">
                                            تعيين كمدير نظام (Super Admin) - يمتلك صلاحية كاملة على كل شيء
                                        </label>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permissions Matrix Card -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0">مصفوفة صلاحيات المستخدم</h5>
                        </div>
                        
                        <div class="d-flex align-items-center gap-3">
                            <!-- Copy Permissions Select -->
                            @if ($otherUsers->isNotEmpty() && !$is_super_admin)
                                <div class="d-flex align-items-center gap-2">
                                    <label for="copy-select" class="text-nowrap small mb-0">نسخ الصلاحيات من:</label>
                                    <select id="copy-select" class="form-select form-select-sm" style="min-width: 200px;"
                                            x-on:change="
                                                if ($event.target.value) {
                                                    confirmAction(
                                                        'نسخ الصلاحيات',
                                                        'هل أنت متأكد من نسخ صلاحيات المستخدم المختار؟ سيؤدي ذلك لاستبدال الصلاحيات المحددة حالياً.',
                                                        () => { $wire.set('selectedUserIdToCopyFrom', $event.target.value); },
                                                        false
                                                    );
                                                    $event.target.value = '';
                                                }
                                            ">
                                        <option value="">اختر مستخدم للنسخ منه...</option>
                                        @foreach ($otherUsers as $ou)
                                            <option value="{{ $ou->id }}">{{ $ou->name }} ({{ $ou->email }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if (!$is_super_admin)
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" wire:click="toggleAllPermissions(true)">تحديد الكل</button>
                                    <button type="button" class="btn btn-outline-secondary" wire:click="toggleAllPermissions(false)">إلغاء التحديد</button>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <div class="card-body pt-4">
                        @if ($is_super_admin)
                            <div class="alert alert-warning d-flex align-items-center mb-0" role="alert">
                                <i class="ti tabler-alert-triangle me-2 fs-4"></i>
                                <div>
                                    <strong>تنبيه:</strong> هذا المستخدم معين كمدير نظام (Super Admin)، لذلك يمتلك كافة الصلاحيات المتاحة في النظام بشكل تلقائي ومباشر. لا حاجة لتعديل صلاحياته الفردية.
                                </div>
                            </div>
                        @else
                            <div class="row row-cols-1 row-cols-md-3 g-4">
                                @foreach ($modules as $moduleKey => $module)
                                    @php
                                        // Check if all actions in this module are selected
                                        $moduleActionNames = array_map(fn($act) => "{$moduleKey}.{$act}", array_keys($module['actions']));
                                        $isAllModuleSelected = count(array_intersect($moduleActionNames, $selectedPermissions)) === count($moduleActionNames);
                                    @endphp
                                    <div class="col">
                                        <div class="card border h-100 shadow-none">
                                            <div class="card-header bg-light py-3 d-flex align-items-center justify-content-between border-bottom">
                                                <span class="fw-bold text-heading small">{{ $module['label'] }}</span>
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="all-{{ $moduleKey }}"
                                                           {{ $isAllModuleSelected ? 'checked' : '' }}
                                                           wire:change="toggleModulePermissions('{{ $moduleKey }}', $event.target.checked)">
                                                    <label class="form-check-label small" for="all-{{ $moduleKey }}">
                                                        الكل
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="card-body pt-3">
                                                <div class="d-flex flex-column gap-2">
                                                    @foreach ($module['actions'] as $actionKey => $actionLabel)
                                                        @php
                                                            $permName = "{$moduleKey}.{$actionKey}";
                                                        @endphp
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   value="{{ $permName }}" 
                                                                   id="perm-{{ $moduleKey }}-{{ $actionKey }}"
                                                                   wire:model="selectedPermissions">
                                                            <label class="form-check-label text-body" for="perm-{{ $moduleKey }}-{{ $actionKey }}">
                                                                {{ $actionLabel }}
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    
                    <div class="card-footer border-top d-flex align-items-center justify-content-end gap-2">
                        <a href="{{ route('users.index') }}" class="btn btn-label-secondary">إلغاء</a>
                        <button type="submit" class="btn btn-primary">حفظ البيانات</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
