<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">الشئون الأكاديمية /</span> معادلة المحولين
    </h4>

    <div class="row">
        {{-- Search Card --}}
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <form wire:submit.prevent="searchStudent">
                        <div class="row align-items-end g-3">
                            <div class="col-md-10">
                                <label class="form-label" for="searchQuery">كود الطالب</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="ti tabler-search"></i></span>
                                    <input type="text" wire:model="searchQuery" id="searchQuery" class="form-control"
                                        placeholder="أدخل كود الطالب المحول هنا..." autofocus>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="searchStudent">
                                    <span wire:loading.remove wire:target="searchStudent">
                                        <i class="ti tabler-search me-1"></i> بحث
                                    </span>
                                    <span wire:loading wire:target="searchStudent">جاري البحث...</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if($student)
            {{-- Student Info Card --}}
            <div class="col-md-12 mb-4">
                <div class="card bg-label-primary border-0 shadow-none">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                @if($student->image)
                                    <img src="{{ asset('storage/' . $student->image) }}" class="rounded-circle img-fluid"
                                        style="width: 100px;">
                                @else
                                    <div class="avatar avatar-xl d-inline-block">
                                        <span class="avatar-initial rounded-circle bg-primary fs-2">
                                            {{ mb_substr($student->name, 0, 1) }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-10">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">اسم الطالب</small>
                                        <span class="fw-bold text-heading fs-5">{{ $student->name }}</span>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block">الكود</small>
                                        <span class="fw-bold text-heading fs-5">{{ $student->username }}</span>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block">الحالة</small>
                                        <span class="badge bg-label-warning fs-6">محول</span>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block">الفرقة</small>
                                        <span class="fw-bold text-heading fs-5">{{ $student->level?->name ?? 'غير محدد' }}</span>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted d-block">التخصص / الشعبة</small>
                                        <span class="fw-bold text-heading fs-5">
                                            {{ $student->section?->department?->name }} / {{ $student->section?->name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Add Equivalency Form --}}
            @can('transfer_equivalency.create')
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="ti tabler-plus me-1"></i> إضافة مادة معادلة</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label for="selectedCourseId" class="form-label">المادة</label>
                                    <select wire:model="selectedCourseId" id="selectedCourseId"
                                            class="form-select @error('selectedCourseId') is-invalid @enderror">
                                        <option value="">-- اختر المادة --</option>
                                        @foreach($courses as $course)
                                            <option value="{{ $course->id }}">
                                                {{ $course->code }} - {{ $course->name }} ({{ $course->hours }} ساعة)
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('selectedCourseId')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="selectedGradeId" class="form-label">التقييم</label>
                                    <select wire:model="selectedGradeId" id="selectedGradeId"
                                            class="form-select @error('selectedGradeId') is-invalid @enderror">
                                        <option value="">-- اختر التقييم --</option>
                                        @foreach($grades as $grade)
                                            <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('selectedGradeId')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <button type="button" wire:click="addEquivalency" class="btn btn-primary w-100"
                                            wire:loading.attr="disabled" wire:target="addEquivalency">
                                        <span wire:loading.remove wire:target="addEquivalency">
                                            <i class="ti tabler-plus me-1"></i> إضافة
                                        </span>
                                        <span wire:loading wire:target="addEquivalency">جاري الإضافة...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endcan

            {{-- Equivalencies Table --}}
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="ti tabler-transfer me-1"></i> سجل المعادلة</h5>
                        @if($equivalencies->count() > 0)
                            <span class="badge bg-label-info">
                                {{ $equivalencies->count() }} مادة — إجمالي {{ $equivalencies->sum(fn($eq) => $eq->course->hours) }} ساعة
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($equivalencies->count() > 0)
                            <div class="table-responsive border rounded">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="50">#</th>
                                            <th>كود المادة</th>
                                            <th>اسم المادة</th>
                                            <th class="text-center">الساعات</th>
                                            <th class="text-center">التقييم</th>
                                            @can('transfer_equivalency.delete')
                                                <th width="80" class="text-center">حذف</th>
                                            @endcan
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($equivalencies as $eq)
                                            <tr wire:key="eq-{{ $eq->id }}">
                                                <td>{{ $loop->iteration }}</td>
                                                <td><span class="badge bg-label-primary">{{ $eq->course->code }}</span></td>
                                                <td>{{ $eq->course->name }}</td>
                                                <td class="text-center">{{ $eq->course->hours }}</td>
                                                <td class="text-center">
                                                    <span class="badge bg-label-success">{{ $eq->grade->name }}</span>
                                                </td>
                                                @can('transfer_equivalency.delete')
                                                    <td class="text-center">
                                                        <button type="button"
                                                                wire:confirm="هل أنت متأكد من حذف هذه المادة من المعادلة؟"
                                                                wire:click="deleteEquivalency({{ $eq->id }})"
                                                                class="btn btn-sm btn-icon btn-label-danger" title="حذف">
                                                            <i class="ti tabler-trash"></i>
                                                        </button>
                                                    </td>
                                                @endcan
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="3" class="fw-bold text-end">الإجمالي</td>
                                            <td class="text-center fw-bold">{{ $equivalencies->sum(fn($eq) => $eq->course->hours) }} ساعة</td>
                                            <td></td>
                                            @can('transfer_equivalency.delete')
                                                <td></td>
                                            @endcan
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info text-center py-3 mb-0">
                                <i class="ti tabler-info-circle fs-4 mb-2"></i>
                                <p class="mb-0">لا توجد مواد معادلة مسجلة لهذا الطالب بعد.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    @script
    <script>
        $wire.on('alert', (data) => {
            const [payload] = data;
            window.toast(payload.type || 'info', payload.message);
        });
    </script>
    @endscript
</div>
