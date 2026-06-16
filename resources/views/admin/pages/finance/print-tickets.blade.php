<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $student->username }}</title>
    <link rel="stylesheet" href="{{ asset('css/adminlte.min.css') }}">
    <style>
        body {
            background-color: white !important;
            font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        .card {
            border: 1px solid #000 !important;
            margin-bottom: 20px;
        }
        .card-header {
            border-bottom: 1px solid #000 !important;
            padding: 10px;
        }
        .font-weight-bolder {
            font-weight: 800 !important;
        }
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row no-print mb-3 pt-3">
            <div class="col-12 text-center">
                <button onclick="window.print()" class="btn btn-primary btn-lg">طباعة</button>
                <a href="{{ route('admin.finance.fee-issuance') }}" class="btn btn-secondary btn-lg">عودة</a>
            </div>
        </div>

        @php
            $typeLabel = $tickets->pluck('fee_name')->unique()->implode(' / ');
        @endphp

        @for ($i = 0; $i < 2; $i++)
            <div class="row">
                <div class="col-12 mb-5">
                    <div class="card">
                        <div class="card-header d-flex">
                            <h2 class="col-6 text-dark my-auto">حافظة توريد مصاريف ({{ $i == 0 ? 'نسخة الطالب' : 'نسخة الخزينة' }})</h2>
                            <div class="col-2"></div>
                            <div class="col-4 float-right">
                                <img class="img-fluid" src="{{ asset('images/logo.jpg') }}" alt="logo">
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="col-12 row justify-content-around">
                                <div class="col-6">
                                    <div class="form-group row">
                                        <label class="col-4 control-label">إسم الطالب</label>
                                        <div class="col-8 font-weight-bolder">
                                            <h5>{{ $student->name }}</h5>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-4 control-label">كود الطالب</label>
                                        <div class="col-8">
                                            <h5>{{ $student->username }}</h5>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-4 control-label">الفرقة الدراسية</label>
                                        <div class="col-8">
                                            <h5>{{ $student->level?->name }}</h5>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-4 control-label">التخصص</label>
                                        <div class="col-8">
                                            <h5>{{ $student->section?->department?->name }} / {{ $student->section?->name }}</h5>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-4 control-label">نوع الحافظة</label>
                                        <div class="col-8">
                                            <h5>{{ $typeLabel }}</h5>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-4 control-label">الفصل الدراسي</label>
                                        <div class="col-8">
                                            <h5>{{ $tickets->first()->created_at->format('Y') }}</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group text-right">
                                        <svg class="barcode"></svg>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-4 control-label">رقم الحافظة</label>
                                        <div class="col-8 font-weight-bolder">
                                            <h5 style="word-break: break-all;">{{ $ticketNumbers }}</h5>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-4 control-label">تاريخ الحافظة</label>
                                        <div class="col-8">
                                            <h5>{{ $date }}</h5>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-4 control-label">المبلغ المراد سداده</label>
                                        <div class="col-8">
                                            <h2 class="font-weight-bold">{{ number_format($totalAmount, 2) }}</h2>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-4 control-label">بيان السداد</label>
                                        <div class="col-8">
                                            <h5>{{ $notes ?? '................................' }}</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @if($i == 0)
                <div class="row no-print">
                    <div class="col-12">
                        <hr style="border-top: 2px dashed #000;">
                    </div>
                </div>
            @endif
        @endfor
    </div>

    <script src="{{ asset('assets/vendor/js/JsBarcode.all.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script>
        $(document).ready(function() {
            $(".barcode").each(function() {
                JsBarcode(this, "{{ $ticketNumbers }}", {
                    displayValue: false,
                    fontSize: 20,
                    width: 1.5,
                    height: 50
                });
            });
            
            // window.print();
        });
    </script>
</body>
</html>