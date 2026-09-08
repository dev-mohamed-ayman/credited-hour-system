<div class="card mt-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">جدول الامتحانات — {{ $semester?->label() }}</h5>
        @if($sessions->isNotEmpty())
            <button type="button" class="btn btn-sm btn-label-primary" onclick="window.print()">
                <i class="ti tabler-printer me-1"></i> طباعة
            </button>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>المادة</th>
                        <th>اليوم</th>
                        <th>التاريخ</th>
                        <th>الميعاد</th>
                        <th>المكان</th>
                        <th>اللجنة</th>
                        <th>رقم الجلوس</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        @php($assignment = $session->seatAssignments->first())
                        <tr wire:key="exam-{{ $session->id }}">
                            <td class="fw-medium">{{ $session->course->name }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($session->exam_date)->locale('ar')->isoFormat('dddd') }}</td>
                            <td>{{ $session->exam_date->format('Y-m-d') }}</td>
                            <td>{{ substr($session->start_time, 0, 5) }} – {{ substr($session->end_time, 0, 5) }}</td>
                            <td>{{ $assignment?->committee?->venue?->name ?? '—' }}</td>
                            <td>{{ $assignment?->committee?->name ?? '—' }}</td>
                            <td><span class="badge bg-label-primary">{{ $assignment?->seat_number ?? '—' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                لا توجد امتحانات منشورة حاليًا.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($unscheduled->isNotEmpty())
            <div class="card-body border-top">
                <p class="text-muted small mb-1">مواد لم يُحدد بعد موعد امتحانها:</p>
                <ul class="mb-0 small text-muted">
                    @foreach($unscheduled as $course)
                        <li>{{ $course->name }} — لم يُحدد بعد</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
