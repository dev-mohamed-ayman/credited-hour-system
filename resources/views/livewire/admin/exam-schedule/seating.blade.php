<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">توزيع اللجان — {{ $session->course->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('exam-schedules.index') }}">جدول الامتحانات</a></li>
                    <li class="breadcrumb-item active">التوزيع</li>
                </ol>
            </nav>
        </div>
        <span class="badge bg-label-primary fs-6">
            {{ $session->exam_date->format('Y-m-d') }} — {{ substr($session->start_time, 0, 5) }} إلى {{ substr($session->end_time, 0, 5) }} — {{ $session->type->label() }}
        </span>
    </div>

    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span>الممتحنون: <span class="fw-bold">{{ $examineeCount }}</span></span>
            <span>إجمالي السعة: <span class="fw-bold">{{ $totalCapacity }}</span></span>
            <span class="badge {{ $session->status->badgeClass() }}">{{ $session->status->label() }}</span>
            @if($stale)
                <span class="badge bg-label-warning">توزيع غير محدّث</span>
            @endif
            <button type="button" class="btn btn-sm btn-primary ms-auto" wire:click="generate" wire:loading.attr="disabled">
                <i class="ti tabler-refresh me-1"></i> توليد التوزيع
            </button>
        </div>
    </div>

    @if($examineeCount === 0)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="ti tabler-alert-triangle"></i>
            <span><b>لا يوجد ممتحنون</b> — لا توجد تسجيلات معتمدة لهذه المادة في هذا الترم، ولن يمكن نشر الجلسة.</span>
        </div>
    @endif

    @if($committees->isEmpty())
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                لا توجد لجان بعد — أضف اللجان من شاشة
                <a href="{{ route('exam-schedules.edit', $session) }}">تعديل الجلسة</a>.
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    @foreach($committees as $committee)
                        <li class="nav-item" wire:key="tab-{{ $committee->id }}">
                            <button type="button"
                                    class="nav-link {{ (int) $active?->id === $committee->id ? 'active' : '' }}"
                                    wire:click="selectCommittee({{ $committee->id }})">
                                {{ $committee->venue?->name }} — {{ $committee->name }}
                                <span class="badge {{ $committee->assignments_count > $committee->capacity ? 'bg-label-danger' : 'bg-label-secondary' }} ms-1">
                                    {{ $committee->assignments_count }}/{{ $committee->capacity }}
                                </span>
                                @if($committee->assignments_count > $committee->capacity)
                                    <span class="badge bg-label-danger">تجاوز السعة</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if($active)
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>رقم الجلوس</th>
                                <th>كود الطالب</th>
                                <th>الاسم</th>
                                <th>الشعبة</th>
                                <th class="text-end">نقل إلى لجنة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignments as $assignment)
                                <tr wire:key="assignment-{{ $assignment->id }}">
                                    <td class="fw-medium">{{ $assignment->seat_number }}</td>
                                    <td>{{ $assignment->student->username }}</td>
                                    <td>{{ $assignment->student->name }}</td>
                                    <td>{{ $assignment->student->section?->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <select class="form-select form-select-sm w-auto"
                                                    wire:model="moveTarget.{{ $assignment->id }}"
                                                    wire:key="move-select-{{ $assignment->id }}">
                                                <option value="">اختر لجنة</option>
                                                @foreach($committees->where('id', '!=', $active->id) as $other)
                                                    <option value="{{ $other->id }}">{{ $other->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="btn btn-sm btn-label-info"
                                                    wire:click="move({{ $assignment->id }})">نقل</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        لا يوجد ممتحنون في هذه اللجنة بعد — اضغط "توليد التوزيع".
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-center">
                    {{ $assignments->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
