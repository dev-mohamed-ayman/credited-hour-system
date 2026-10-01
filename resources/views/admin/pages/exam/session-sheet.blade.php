<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف حضور — {{ $session->committee->name }} — {{ $session->course->name }}</title>
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
        .print-btn { position: fixed; top: 8px; left: 8px; padding: 8px 16px; cursor: pointer; }
        @media print { .print-btn { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">طباعة</button>

    <h2>كشف حضور لجنة الامتحان</h2>
    <div class="meta">
        <span>المادة: <b>{{ $session->course->name }}</b></span>
        <span>({{ $session->type->label() }})</span>
        <span>السنة: {{ $session->committee->year?->year }}</span>
        <span>التاريخ: {{ $session->exam_date->format('Y-m-d') }}</span>
        <span>الوقت: {{ $session->timeRangeLabel() }}</span>
        <span>المكان: {{ $session->committee->venue?->name }}</span>
        <span>اللجنة: {{ $session->committee->name }}</span>
        <span>عدد الطلاب: {{ $members->count() }}</span>
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
            @foreach($members as $index => $member)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $member->seat_number }}</td>
                    <td>{{ $member->student?->username }}</td>
                    <td>{{ $member->student?->name }}</td>
                    <td>{{ $member->student?->section?->name ?? '—' }}</td>
                    <td class="sign"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
