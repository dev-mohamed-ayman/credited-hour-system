<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف حضور — {{ $committee->name }}</title>
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
        .sign { width: 120px; }
        .footer { margin-top: 12px; font-size: 11px; color: #555; text-align: left; }
        .print-btn { position: fixed; top: 8px; left: 8px; padding: 8px 16px; cursor: pointer; }
        @media print { .print-btn { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">طباعة</button>

    <h2>كشف حضور لجنة الامتحان</h2>
    <div class="meta">
        <span>المادة: <b>{{ $committee->examSession->course->name }}</b></span>
        <span>({{ $committee->examSession->type->label() }})</span>
        <span>السنة: {{ $committee->examSession->year?->year }}</span>
        <span>التاريخ: {{ $committee->examSession->exam_date->format('Y-m-d') }}</span>
        <span>الوقت: {{ substr($committee->examSession->start_time, 0, 5) }} — {{ substr($committee->examSession->end_time, 0, 5) }}</span>
        <span>المكان: {{ $committee->venue?->name }}</span>
        <span>اللجنة: {{ $committee->name }}</span>
        <span>عدد الطلاب: {{ $assignments->count() }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>م</th>
                <th>رقم الجلوس</th>
                <th>كود الطالب</th>
                <th>الاسم</th>
                <th>الشعبة</th>
                <th class="sign">التوقيع</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assignments as $index => $assignment)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $assignment->seat_number }}</td>
                    <td>{{ $assignment->student->username }}</td>
                    <td>{{ $assignment->student->name }}</td>
                    <td>{{ $assignment->student->section?->name ?? '—' }}</td>
                    <td class="sign"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">آخر تحديث: {{ $committee->updated_at?->format('Y-m-d H:i') }}</div>
</body>
</html>
