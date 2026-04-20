<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أرقام الجلوس</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@300;400;700&display=swap" rel="stylesheet">
</head>
<style>
    body{
        margin: 0;
        padding: 0;
        font-family: 'Noto Kufi Arabic', sans-serif;
    }
    .card{
        width: 322px;
        height: 204px;
        position: relative;
        margin-bottom: 20px;
        page-break-inside: avoid;
        border: 2px solid #edaf2a;
        box-sizing: border-box;
    } 
    .topnav{
        display: flex;
        justify-content: center;
        background-color: #144935;
        padding: 5px 0;
    }
    .logo{
        height: 40px;
    }

    .flex{
            background-color: goldenrod;
            color: white;
            text-align: center;
            width: 100%;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 0;
    }

    .content{
        height: 110px;
        display: flex;
        flex-direction: row-reverse; /* Switch direction to Arabic correct RTL flow inside card */
    }
    
    .right{
        width: 30%;
        text-align: center;
    }
    .right img{
        width: 80%;
        margin: auto;
        padding: 5px;
        box-sizing: border-box;
    }
    
    .left{
        width: 70%;
        padding: 5px 10px;
        text-align: right;
        display: flex;
        flex-direction: column;
        justify-content: space-around;
        align-items: flex-start;
    }

    .left div{
        font-size: 9px;
        font-weight: 600;
        line-height: 15px;
        color: #144935;
    }
    
    .left div span.value {
        color: #000;
        font-weight: 700;
        margin-right: 5px;
    }

    .seat-badge {
        background-color: #144935;
        color: white !important;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px !important;
        font-weight: 700;
        display: inline-block;
        margin-top: 2px;
        border: 1px solid #edaf2a;
    }

    .footer-bottom{
            background-color:#144935;
            height: 20px;
            position: absolute;
            bottom: 0;
            width: 100%;
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
            margin-bottom: 5px;
        }
    }
</style>
<body dir="rtl">
    @foreach($students as $student)
    <div class="card">
        <div class="header">
            <div class="topnav">
                <img class="logo" src="{{asset('images/logo.png')}}" alt="Logo">
            </div>
            <div class="nav">
                <div class="flex">
                    <div class="flex-content ar">المعهد العالى للعلوم التجارية | بطاقة رقم الجلوس</div>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="right">
                @if(optional($settings)->seat_show_photo && $student->image)
                    <img src="{{($student->image && file_exists(public_path('uploads/students/'.$student->image))) ? asset('uploads/students/'.$student->image) : $student->photo}}" alt="صورة">
                @elseif(optional($settings)->seat_show_photo && $student->photo)
                    <img src="{{$student->photo}}" alt="صورة">
                @else
                    <div style="height: 60px; width: 60px; margin: 10px auto; border: 1px dashed #ccc; text-align:center; line-height: 60px; font-size: 10px; color:#999;">صورة</div>
                @endif
            </div>

            <div class="left">
                @if(optional($settings)->seat_show_name)
                <div>الاسم: <span class="value">{{Str::words($student->name, 4,'')}}</span></div>
                @endif
                
                @if(optional($settings)->seat_show_code)
                <div>كود الطالب: <span class="value">{{$student->username}}</span></div>
                @endif
                
                @if(optional($settings)->seat_show_department && $student->section && $student->section->department)
                <div>التخصص: <span class="value">{{$student->section->department->name}}</span></div>
                @endif
                
                @if(optional($settings)->seat_show_section && $student->section)
                <div>الشعبة: <span class="value">{{$student->section->name}}</span></div>
                @endif
                
                @if(optional($settings)->seat_show_level && $student->level)
                <div>الفرقة: <span class="value">{{$student->level->name}}</span></div>
                @endif

                @if(optional($settings)->seat_show_seat_number)
                <div class="seat-badge">رقم الجلوس: <span>{{$student->seat_number ?? 'غير مسجل'}}</span></div>
                @endif
            </div>
        </div>

        <div class="footer-bottom">
            <p class="footer-info">مع تمنيات المعهد بالنجاح والتوفيق</p>
        </div>
    </div>
    @endforeach

    <script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            window.print();
        }, 500); // Give a small delay to render images
        
        window.onafterprint = function () {
            window.location.replace("{{route('print.seat.numbers.index')}}");
        };
    });
</script>
</body>
</html>
