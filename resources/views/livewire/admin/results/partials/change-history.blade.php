@php
    $describe = function (?array $result): string {
        if (! $result) {
            return '—';
        }

        $marks = collect([$result['written_score'] ?? null, $result['coursework_score'] ?? null, $result['total_score'] ?? null])
            ->map(fn ($mark) => $mark === null ? '—' : rtrim(rtrim(number_format((float) $mark, 2, '.', ''), '0'), '.'))
            ->implode(' / ');

        $status = ! empty($result['exam_status']) ? (\App\Enums\ExamResultStatus::tryFrom($result['exam_status'])?->label() ?? $result['exam_status']) : null;

        return trim(($result['grade'] ?? 'Pending').' ('.$marks.')'.($status ? ' '.$status : ''));
    };
@endphp

<div class="table-responsive">
    <table class="table table-sm mb-0">
        <thead class="table-light">
            <tr>
                @isset($showEnrollment)
                    <th>الطالب</th>
                    <th>المادة</th>
                    <th>الترم</th>
                @endisset
                <th>التاريخ</th>
                <th>بواسطة</th>
                <th>الطريقة</th>
                <th>قبل (تحريري / أعمال / مجموع)</th>
                <th>بعد</th>
                <th>السبب</th>
            </tr>
        </thead>
        <tbody>
            @forelse($changes as $change)
                <tr wire:key="change-{{ $change->id }}">
                    @isset($showEnrollment)
                        <td>{{ $change->registrationCourse?->registration?->student?->username }} — {{ $change->registrationCourse?->registration?->student?->name }}</td>
                        <td>{{ $change->registrationCourse?->course?->code }}</td>
                        <td>{{ $change->registrationCourse?->registration?->semester?->label() }} {{ $change->registrationCourse?->registration?->year?->year }}</td>
                    @endisset
                    <td class="text-nowrap">{{ $change->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $change->user?->name ?? '—' }}</td>
                    <td><span class="badge bg-label-info">{{ $change->source->label() }}</span></td>
                    <td>{{ $describe($change->before) }}{{ ! empty($change->before['note']) ? ' — '.$change->before['note'] : '' }}</td>
                    <td class="fw-medium">{{ $describe($change->after) }}{{ ! empty($change->after['note']) ? ' — '.$change->after['note'] : '' }}</td>
                    <td class="small">{{ $change->reason }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ isset($showEnrollment) ? 9 : 6 }}" class="text-center text-muted py-3">لا توجد تعديلات مسجلة.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
