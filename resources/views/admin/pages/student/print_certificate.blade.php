<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>شهادات التخرج</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            max-width: 100%;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: white;
            font-family: 'Cairo', sans-serif;
            font-size: 15px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .certificate-page {
            width: 210mm;
            height: 297mm;
            overflow: hidden;
            margin: auto;
            padding: 0;
            position: relative;
            page-break-after: always;
        }

        .certificate-page:last-child {
            page-break-after: avoid;
        }

        @media print {
            @page {
                size: A4;
                margin: 0;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .certificate-page {
                width: 210mm !important;
                height: 297mm !important;
                overflow: hidden !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }

        /* ── Header ── */
        .cert-header {
            color: white;
            text-align: center;
            height: 140px;
            padding-top: 5px;
            overflow: hidden;
            position: relative;
        }

        .logos-full img {
            width: 100%;
            height: 75px;
            object-fit: fill;
            display: block;
        }

        .institute-title {
            margin-top: 4px;
            font-size: 15px;
            color: white;
            line-height: 1.2;
        }

        /* ── Content ── */
        .cert-content {
            padding: 12px 15px;
            font-size: 15px;
            position: relative;
            z-index: 1;
        }

        /* ── Photo block ── */
        .ph-info-block {
            display: flex;
            flex-direction: row-reverse;
            align-items: flex-start;
            justify-content: space-between;
            direction: rtl;
            margin-top: 7px;
            position: relative;
            min-height: 100px;
        }

        .student-photo-preview {
            max-width: 100px;
            width: 100px;
            height: auto;
            border: 2px solid #0e3224;
            padding: 4px;
            background: #fff;
        }

        .student-section-title-center {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        /* ── Student info ── */
        .student-info-block {
            display: flex;
            flex-direction: row-reverse;
            align-items: flex-start;
            justify-content: space-between;
            direction: rtl;
            margin-top: 7px;
            padding: 0;
        }

        .student-text {
            text-align: right;
            flex: 1;
        }

        /* ── Names / signatures ── */
        .names-block,
        .signatures-block {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            width: 100%;
            padding: 0 20px;
            direction: rtl;
        }

        .dean {
            text-align: left;
            margin-top: 5px;
            padding-left: 56px;
            font-weight: bold;
            font-size: 15px;
            direction: rtl;
        }

        .deanreverse {
            text-align: left;
            margin-top: -7px;
            padding-left: 20px;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 15px;
            direction: rtl;
        }

        /* ── Footer ── */
        .cert-footer {
            color: white;
            font-size: 15px;
            height: 50px;
            text-align: center;
            border-top-left-radius: 40px;
            border-top-right-radius: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* ── Extra equivalence block ── */
        .equivalence-block {
            border: 2px solid #000;
            margin-top: 15px;
            padding: 5px;
            direction: rtl;
            font-size: 14px;
            font-weight: normal;
        }
    </style>
</head>
<body>

@php
    if (! function_exists('convertNumbersToArabic')) {
        function convertNumbersToArabic($string) {
            return strtr($string, ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩']);
        }
    }

    $gradeMap = [
        'امتياز'   => 'A+',
        'ممتاز'    => 'A',
        'جيد جداً' => 'B',
        'جيد'      => 'C',
        'مقبول'    => 'D',
        'راسب'     => 'F',
    ];
@endphp

@foreach($students as $student)
@php
    $cgpa        = $student->cgpa ?? null;
    $grade       = '';

    if ($cgpa !== null) {
        $cgpa = (float) $cgpa;
        if ($cgpa >= 3.7)      { $grade = 'امتياز'; }
        elseif ($cgpa >= 3.0)  { $grade = 'ممتاز'; }
        elseif ($cgpa >= 2.3)  { $grade = 'جيد جداً'; }
        elseif ($cgpa >= 2.0)  { $grade = 'جيد'; }
        elseif ($cgpa >= 1.0)  { $grade = 'مقبول'; }
        else                   { $grade = 'راسب'; }
    }

    $photoUrl = null;
    if ($student->image && file_exists(public_path('uploads/students/' . $student->image))) {
        $photoUrl = asset('uploads/students/' . $student->image);
    } elseif ($student->photo) {
        $photoUrl = $student->photo;
    }
@endphp

<div class="certificate-page">

    <div class="cert-header">
        <div class="logos-full"></div>
        <div class="institute-title"></div>
    </div>

    <div class="cert-content">

        {{-- Photo + "شهادة" title --}}
        <div class="ph-info-block">
            @if(optional($settings)->cert_show_photo && $photoUrl)
                <div class="student-photo">
                    <img class="student-photo-preview" src="{{ $photoUrl }}" alt="صورة الطالب">
                </div>
            @endif

            <div class="student-section-title-center">
                <h1 style="font-size: 26px; font-weight: bold; margin: 0;">شهادة</h1>
            </div>
        </div>

        {{-- Student info --}}
        <div class="student-info-block">
            <div class="student-text">

                <div style="text-align: right; direction: rtl;">
                    <label>يشهد المعهد العالي للحاسب الآلي ونظم المعلومات بأبي قير أن:
                        <span class="fontcontent student-name">{{ $student->name }}</span>
                    </label>
                </div>

                @if(optional($settings)->cert_show_birth_info)
                    <label>
                        المولود فى:
                        <span>{{ $student->birth_province ?? '-' }}</span> بتاريخ:
                        <span>
                            @if($student->birth_date)
                                {{ convertNumbersToArabic(\Carbon\Carbon::createFromFormat('d/m/Y', $student->birth_date)->translatedFormat('d F Y')) }}
                            @else
                                -
                            @endif
                        </span>
                    </label>
                @endif

                @if(optional($settings)->cert_show_national_id)
                    @if($student->birth_date || optional($settings)->cert_show_birth_info)
                        @if(($student->nationality ?? '') == 'مصري')
                            برقم قومى
                            <span>{{ $student->national_id ? convertNumbersToArabic($student->national_id) : '-' }}</span>
                        @else
                            برقم الباسبور
                            <span>{{ $student->national_id ?? '-' }}</span>
                        @endif
                    @else
                        <label>
                            @if(($student->nationality ?? '') == 'مصري')
                                الرقم القومي: <span>{{ $student->national_id ? convertNumbersToArabic($student->national_id) : '-' }}</span>
                            @else
                                رقم الباسبور: <span>{{ $student->national_id ?? '-' }}</span>
                            @endif
                        </label>
                    @endif
                @endif

                <br>

                @if(optional($settings)->cert_show_seat_number)
                    <label style="text-align: right; direction: rtl;">
                        رقم الجلوس ({{ date('Y') }}): {{ $student->seat_number ?? '-' }}
                    </label>
                @endif

                <div class="student-section-title text-center mb-4" style="padding-top: 20px;">
                    <label style="font-weight: bold; font-size: 20px;">قد حصل على درجة البكالوريوس</label><br>
                    @if(optional($settings)->cert_show_specialization)
                        <label style="font-weight: bold; font-size: 20px;">
                            شعبة نظم معلومات الأعمال باللغة {{ ($student->specialization == 'عربي') ? 'العربية' : 'الانجليزية' }}
                        </label>
                    @endif
                </div>

                <div class="names-block">
                    @if(optional($settings)->cert_show_semester)
                        <div>
                            <label>فصل <span>{{ $student->semester_graduated ?? '-' }}</span> <span>{{ $student->year_graduated ?? '-' }}</span></label>
                        </div>
                    @endif
                    @if(optional($settings)->cert_show_grade)
                        <div>
                            <label>بتقدير عام <span>{{ $grade }} {{ $student->note_graduation ?? '' }}</span></label>
                        </div>
                    @endif
                    @if(optional($settings)->cert_show_cgpa)
                        <div>
                            <label>بنقاط تراكمية <span>{{ $student->cgpa ? convertNumbersToArabic((string)$student->cgpa) : '-' }}</span> CGPA</label>
                        </div>
                    @endif
                </div>

                <label class="fontcontent">من اجمالى النقاط التراكمية ٤ أربع نقاط</label><br>

                <label>وقد اعتمد السيد الأستاذ الدكتور وزير التعليم العالى والبحث العلمى نتيجة البكالوريوس بتاريخ
                    <span>{{ convertNumbersToArabic(\Carbon\Carbon::create(2025, 7, 15)->locale('ar')->translatedFormat('d F Y')) }}</span>
                </label>

                <div class="names-block" style="margin-top: 20px;">
                    <label>الموظف المختص</label>
                    <label>وكيل ادارة شئون الطلاب</label>
                    <label>امين المعهد</label>
                </div>

                <div class="signatures-block">
                    <label style="margin-right: 28px;">أ/إيمان محمد</label>
                    <label style="margin-right: 78px;">أ/يمان قدري</label>
                    <label style="margin-right: 60px;">أ/فيصل شاهين</label>
                </div>

                <br><br>

                <div style="display: flex; justify-content: space-between; align-items: flex-start; direction: rtl; margin-top: 1px;">
                    <label style="text-align: right; font-size: 15px;">
                        تحريراً فى :
                        <span>{{ convertNumbersToArabic(\Carbon\Carbon::now()->translatedFormat('d F Y')) }}</span>
                    </label>

                    <div style="text-align: left; font-size: 15px;">
                        <label style="font-weight: bold; margin-left: 30px;">عميد المعهد</label><br>
                        <label style="font-weight: bold; margin-bottom: 30px;">أ.د/ شحاته السيد شحاته</label>
                    </div>
                </div>

                @if(optional($settings)->cert_show_extra)
                    <div>
                        <div class="equivalence-block">
                            <label style="font-weight: normal;">
                                وقد صدر قرار رئيس المجلس الأعلى للجامعات رقم (٣٠٤) بتاريخ ٢٠٢٢/٨/٢٨ – بمعادلة درجة البكالوريوس في نظم المعلومات باللغة العربية التي يمنحها المعهد العالي للحاسب الآلي ونظم المعلومات  بأبي قير - الإسكندرية ج.م.ع - درجة بكالوريوس في "نظم المعلومات " التي تمنحها الجامعة المصرية الخاضعة للقانون تنظيم الجامعة رقم ٤٩ لسنة ١٩٧٢ ولائحته التنفيذية من كليات التجارة
                            </label>
                        </div>

                        <div class="names-block" style="margin-top: 8px;">
                            <label>الموظف المختص</label>
                            <label>مدير الإدارة</label>
                            <label>المدير العام</label>
                        </div>
                    </div>
                @endif

            </div>
        </div>

    </div>

    <div class="cert-footer"></div>
</div>
@endforeach

<script>
    window.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            window.print();
        }, 400);

        window.onafterprint = function () {
            window.location.replace('{{ route('print.certificates.index') }}');
        };
    });
</script>
</body>
</html>
