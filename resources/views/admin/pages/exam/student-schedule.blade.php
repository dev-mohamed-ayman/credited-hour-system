<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جدول امتحانات — {{ $student->name }}</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 24px; font-family: 'Noto Kufi Arabic', sans-serif; color: #000; }
        h2 { text-align: center; margin: 0 0 4px; }
        .meta { text-align: center; margin-bottom: 16px; font-size: 14px; }
        .meta span { margin: 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px 8px; font-size: 13px; text-align: right; }
        th { background: #f2f2f2; }
        .print-btn { position: fixed; top: 8px; left: 8px; padding: 8px 16px; cursor: pointer; }
        @media print { .print-btn { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">طباعة</button>

    <h2>جدول الامتحانات</h2>
    <div class="meta">
        <span>الطالب: <b>{{ $student->name }}</b></span>
        <span>كود: {{ $student->username }}</span>
        <span>السنة: {{ $year?->year }}</span>
        <span>الترم: {{ $semester?->label() }}</span>
    </div>

    <table>
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
                <tr>
                    <td>{{ $session->course->name }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($session->exam_date)->locale('ar')->isoFormat('dddd') }}</td>
                    <td>{{ $session->exam_date->format('Y-m-d') }}</td>
                    <td>{{ substr($session->start_time, 0, 5) }} — {{ substr($session->end_time, 0, 5) }}</td>
                    <td>{{ $assignment?->committee?->venue?->name ?? '—' }}</td>
                    <td>{{ $assignment?->committee?->name ?? '—' }}</td>
                    <td>{{ $assignment?->seat_number ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center">لا توجد امتحانات منشورة.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
