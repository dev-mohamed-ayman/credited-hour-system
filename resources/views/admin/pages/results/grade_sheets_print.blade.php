<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>كشف الدرجات {{ $year->year }}</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; margin: 0; color: #000; background: #fff; font-size: 12px; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 10mm 12mm; box-sizing: border-box; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        h2, h3, h4 { margin: 4px 0; }
        .header { text-align: center; margin-bottom: 8px; }
        .info { display: flex; flex-wrap: wrap; gap: 4px 24px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #000; padding: 3px 4px; text-align: center; }
        th { background: #eee; }
        .totals td, .totals th { border: none; text-align: right; padding: 1px 4px; }
        .signatures td { border: none; vertical-align: top; }
        .signatures img { max-height: 50px; max-width: 140px; }
        .empty { text-align: center; padding: 40px; }
        @media print { @page { size: A4; margin: 0; } * { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
@forelse($pages as $page)
    @php($student = $page['student'])
    <div class="page">
        <div class="header">
            <h2>كشف درجات العام الجامعي {{ $year->year }}{{ $semester ? ' — '.$semester->label() : '' }}</h2>
        </div>

        <div class="info">
            <div><b>الاسم:</b> {{ $student->name }}</div>
            <div><b>الكود:</b> {{ $student->username }}</div>
            <div><b>التخصص:</b> {{ $student->section?->department?->name }}</div>
            <div><b>الشعبة:</b> {{ $student->section?->name }}</div>
            <div><b>الفرقة:</b> {{ $page['level']?->name }}</div>
            <div><b>حالة القيد:</b> {{ $student->study_status?->label() }}</div>
        </div>

        @forelse($page['terms'] as $term)
            <h4>{{ $term['semester']->label() }}</h4>
            <table>
                <thead>
                    <tr>
                        <th>كود المادة</th>
                        <th>اسم المادة</th>
                        <th>الساعات</th>
                        <th>التحريري</th>
                        <th>أعمال السنة</th>
                        <th>المجموع</th>
                        <th>التقدير</th>
                        <th>النقاط</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($term['courses'] as $course)
                        <tr>
                            <td>{{ $course['code'] }}</td>
                            <td>{{ $course['name'] }}</td>
                            <td>{{ $course['hours'] }}</td>
                            <td>{{ $course['written'] ?? '' }}</td>
                            <td>{{ $course['coursework'] ?? '' }}</td>
                            <td>{{ $course['total'] ?? '' }}</td>
                            <td>{{ $course['grade'] }}</td>
                            <td>{{ $course['points'] !== null ? number_format($course['points'], 2) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <table class="totals">
                <tr>
                    <th>المعدل الفصلي: {{ number_format($term['standing']['gpa'], 2) }}</th>
                    <th>الساعات المسجلة في الترم: {{ $term['registered_hours'] }}</th>
                    <th>الساعات المكتسبة في الترم: {{ $term['standing']['earned_hours'] }}</th>
                </tr>
                <tr>
                    <th>المعدل التراكمي: {{ number_format($term['cumulative']['gpa'], 2) }}</th>
                    <th>إجمالي الساعات المكتسبة: {{ $term['cumulative']['earned_hours'] }}</th>
                    <th></th>
                </tr>
            </table>
        @empty
            <p>لا توجد تسجيلات للطالب في السنة دي.</p>
        @endforelse

        <h4>ملاحظات</h4>
        <table>
            <thead><tr><th style="width: 25%">الترم</th><th>الملحوظة</th></tr></thead>
            <tbody>
                @php($notes = collect($page['terms'])->filter(fn ($term) => filled($term['note'])))
                @forelse($notes as $term)
                    <tr><td>{{ $term['semester']->label() }}</td><td>{{ $term['note'] }}</td></tr>
                @empty
                    <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
                @endforelse
            </tbody>
        </table>

        <h4>ملاحظات عامة</h4>
        <table>
            <tr>
                <th style="width: 35%">التربية العسكرية: {{ $page['military_education'] }}</th>
                <th>
                    التقدير العام: {{ $page['classification'] ?: '—' }}
                    — المعدل التراكمي: {{ number_format($page['cumulative']['gpa'], 2) }}
                    — إجمالي الساعات المكتسبة: {{ $page['cumulative']['earned_hours'] }}
                </th>
            </tr>
        </table>

        <table class="signatures">
            <tr>
                @foreach($signatureRoles as $role => $label)
                    <td>
                        <b>{{ $label }}</b><br>
                        {{ $settings?->{"signature_{$role}_name"} }}
                        @if($image = $settings?->{"signature_{$role}_image"})
                            <br><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}" alt="{{ $label }}">
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
@empty
    <div class="empty">لا يوجد طلاب مطابقين للاختيار.</div>
@endforelse

<script>
    window.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () { window.print(); }, 400);
    });
</script>
</body>
</html>
