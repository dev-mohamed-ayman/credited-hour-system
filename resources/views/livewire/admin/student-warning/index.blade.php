<div>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">بحث عن تنبيهات طالب</h5>
            <a href="{{ route('student-warnings.create') }}" class="btn btn-primary btn-sm">إضافة تنبيه</a>
        </div>
        <div class="card-body">
            <form wire:submit.prevent="search" class="row gx-3 gy-2 align-items-center">
                <div class="col-sm-6">
                    <label class="visually-hidden" for="searchCode">كود الطالب</label>
                    <input type="text" class="form-control" id="searchCode" wire:model="searchCode" placeholder="أدخل كود الطالب (مثال: E260001)">
                    @error('searchCode') <span class="text-danger d-block mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading wire:target="search" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($student)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">بيانات الطالب</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <strong>الاسم:</strong> {{ $student->name }}
                    </div>
                    <div class="col-md-4 mb-2">
                        <strong>الكود:</strong> {{ $student->username }}
                    </div>
                    <div class="col-md-4 mb-2">
                        <strong>الحالة:</strong> 
                        <span class="badge bg-{{ $student->status->value === \App\Enums\Student\StudentStatus::SUSPENDED->value ? 'danger' : 'success' }}">
                            {{ $student->status->label() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">سجل التنبيهات</h5>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th>السبب</th>
                            <th>تاريخ الإضافة</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($warnings as $warning)
                            <tr>
                                <td>
                                    @if($warning->type->value === \App\Enums\Student\StudentWarningType::DANGER->value)
                                        <span class="badge bg-danger">خطر</span>
                                    @else
                                        <span class="badge bg-warning">تحذير</span>
                                    @endif
                                </td>
                                <td style="white-space: pre-wrap;">{{ $warning->reason }}</td>
                                <td>{{ $warning->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($warning->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-secondary">ملغي</span>
                                    @endif
                                </td>
                                <td>
                                    @if($warning->is_active)
                                        <button wire:click="cancelWarning({{ $warning->id }})" class="btn btn-sm btn-outline-danger" onclick="confirm('هل أنت متأكد من إلغاء هذا التنبيه؟') || event.stopImmediatePropagation()">
                                            إلغاء التنبيه
                                        </button>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">لا توجد تنبيهات مسجلة لهذا الطالب.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
