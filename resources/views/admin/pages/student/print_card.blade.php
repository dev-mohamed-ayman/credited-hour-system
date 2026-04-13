<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>card</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@300;400&display=swap" rel="stylesheet">
</head>
<style>
    body{
        margin: 0;
        padding: 0;
        font-family: 'Noto Kufi Arabic', sans-serif;
    }
    .card{
        width:322px;
        height: 204px;
        position: relative;
        margin-bottom: 20px;
        page-break-inside: avoid;
    } 
    .topnav{
        display: flex;
        justify-content: center;
        background-color: #144935;
    }
    .logo{
        height: 45px;
    }

    .flex{
            background-color: goldenrod;
            color: white;
            text-align: center;
            width: 100%;
            font-size: 9px;
            font-weight: 600;
    }

    .content{
        height: 115px;
        display: flex;
    }
    .left{
        width: 30%;
        text-align: center;
    }
    .left img{
        width: 71%;
        margin: auto;
        padding: 4px;
    }
    .center{
        width: 65%;
        padding: 5px;
        text-align: right;
    }
    .right{
        width: 4%;
    }
    #barcode{
         height:20%!important;
         width:100% !important;
    }
    .center div{
        font-size: 9px;
        font-weight: 600;
        line-height: 15px;
    }
    .footer{
         margin-bottom: 0;
        height: 30px;
        background-color: goldenrod;
        position: absolute;
        bottom: 0;
        width: 100%;
        border-top-left-radius: 50px;
        border-top-right-radius: 50px;
    }
    .footer-bottom{
            background-color:#144935;
            height: 20px;
            position: absolute;
            bottom: 0;
            width: 100%;
            border-top-left-radius: 50px;
            border-top-right-radius: 50px;
    }
    .footer-info{
        color: white;
        text-align: center;
        font-size: 9px;
        margin: 0;
        padding-top: 5px;
    }

    @media print {
        body {
            -webkit-print-color-adjust: exact;
        }
        .card {
            margin-bottom: 0px;
        }
    }
</style>
<body>
    @foreach($students as $student)
    <div class="card">
        <div class="header">
            <div class="topnav">
                <img class="logo" src="{{asset('images/logo.png')}}" alt="">
            </div>
            <div class="nav">
                <div class="flex">
                    <div class="flex-content ar">المعهد العالى للعلوم التجارية </div>
                    <div class="flex-content en">High institute for Commercial Sciences</div>
                </div>

            </div>
        </div>
        <div class="content">
            <div class="left">
                @if(optional($settings)->card_show_photo && $student->image)
                    <!-- Using image field instead of photo just in case, but keeping previous logic or similar -->
                    <img src="{{($student->image && file_exists(public_path('uploads/students/'.$student->image))) ? asset('uploads/students/'.$student->image) : $student->photo}}"  alt="">
                @elseif(optional($settings)->card_show_photo && $student->photo)
                    <img src="{{$student->photo}}" alt="">
                @else
                    <div style="height: 60px; width: 60px; margin: auto; padding: 4px; border: 1px solid #ccc; margin-top:10px;">صورة</div>
                @endif
            </div>

            <div class="center">
                @if(optional($settings)->card_show_name)
                <div><span style="margin-left: 12px;">اسم الطالب </span><span id="name">{{Str::words($student->name, 5,'')}}</span></div>
                @endif
                
                @if(optional($settings)->card_show_code)
                <div><span style="margin-left: 12px;">كود الطالب </span><span id="code"> {{$student->username}}</span></div>
                @endif
                
                @if(optional($settings)->card_show_department && $student->section && $student->section->department)
                <div><span style="margin-left: 12px;">التخصص </span><span>{{$student->section->department->name}}</span></div>
                @endif
                
                @if(optional($settings)->card_show_section && $student->section)
                <div><span style="margin-left: 12px;">الشعبة </span><span>{{$student->section->name}}</span></div>
                @endif
                
                @if(optional($settings)->card_show_level && $student->level)
                <div><span style="margin-left: 12px;">الفرقة </span><span>{{$student->level->name}}</span></div>
                @endif

                @if(optional($settings)->card_show_national_id && $student->national_id)
                <div><span style="margin-left: 12px;">الرقم القومي </span><span>{{$student->national_id}}</span></div>
                @endif

                @if(optional($settings)->card_show_barcode)
                <div style="width: 100px; margin: auto;">
                    <svg id="barcode-{{$student->username}}" width="100%" > </svg>
                </div>
                @endif
            </div>

        </div>
        <!--<div class="footer"></div>-->
        <div class="footer-bottom">
            <p class="footer-info">www.ahi.edu.eg</p>
        </div>

    </div>
    @endforeach
    <script src="{{asset('assets/plugins/jquery/jquery.min.js')}}"></script>
    <script src="{{asset('js/JsBarcode.all.min.js')}}"></script>
    <script type="text/javascript">
    $(document).ready(function() {
        @if(optional($settings)->card_show_barcode)
            @foreach($students as $student)
                JsBarcode("#barcode-{{$student->username}}", "{{$student->username}}", {
                    displayValue: false,
                    fontSize: 14,
                    width: 1.5,
                     height: 40
                });
            @endforeach
        @endif

        window.print();
        window.onafterprint = function () {
            window.location.replace("{{route('print.student.cards.index')}}");
        };
    });
</script>
</body>
</html>
