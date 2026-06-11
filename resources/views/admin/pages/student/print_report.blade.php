<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $student->username }}</title>

    <!-- Bootstrap 4 RTL -->
    <link rel="stylesheet" href="https://cdn.rtlcss.com/bootstrap/v4.2.1/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: white;
            padding: 20px;
        }

        .card {
            border: 2px solid #333 !important;
            margin-bottom: 30px;
        }

        .card-header {
            background-color: transparent !important;
            border-bottom: 1px solid #333 !important;
            padding: 10px 15px;
        }

        .control-label {
            font-weight: 700;
            color: #333;
        }

        h5 {
            font-weight: 700;
            margin-bottom: 0;
        }

        .img-fluid {
            max-height: 80px;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
            }

            .card {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="no-print text-center mb-4">
        <button onclick="window.print()" class="btn btn-primary">طباعة</button>
    </div>

    <div class="container">
        <!-- النسخة الأولى -->
        <div class="row">
            <div class="col-12 mb-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="text-dark mb-0">بيانات الطالب</h2>
                        <div class="text-right">
                            <img class="img-fluid" src="{{ asset('assets/img/branding/logo.png') }}" alt="logo">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-8">
                                <div class="form-group row">
                                    <label class="col-4 control-label">إسم الطالب</label>
                                    <div class="col-8">
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
                                    <label class="col-4 control-label">كلمة المرور</label>
                                    <div class="col-8">
                                        <h5>{{ $student->plain_password }}</h5>
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
                                        <h5>{{ $student->section?->name }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 d-flex align-items-end justify-content-end">
                                <div class="text-right">
                                    <svg id="barcode1"></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- النسخة الثانية -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="text-dark mb-0">بيانات الطالب</h2>
                        <div class="text-right">
                            <img class="img-fluid" src="{{ asset('assets/img/branding/logo.png') }}" alt="logo">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-8">
                                <div class="form-group row">
                                    <label class="col-4 control-label">إسم الطالب</label>
                                    <div class="col-8">
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
                                    <label class="col-4 control-label">كلمة المرور</label>
                                    <div class="col-8">
                                        <h5>{{ $student->plain_password }}</h5>
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
                                        <h5>{{ $student->section?->name }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 d-flex align-items-end justify-content-end">
                                <div class="text-right">
                                    <svg id="barcode2"></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery and JsBarcode -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <script>
        $(document).ready(function () {
            JsBarcode("#barcode1", "{{ $student->username }}", {
                displayValue: false,
                fontSize: 25,
                width: 2,
                height: 60
            });
            JsBarcode("#barcode2", "{{ $student->username }}", {
                displayValue: false,
                fontSize: 25,
                width: 2,
                height: 60
            });

            // Auto print
            setTimeout(function () {
                window.print();
            }, 500);
        });

        // Disable right click like the old system
        $(document).bind("contextmenu", function (e) {
            e.preventDefault();
        });
    </script>
</body>

</html>
