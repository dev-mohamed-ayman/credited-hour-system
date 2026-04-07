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
        line-height: 20px;
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
                <img src="{{$student->photo}}"  alt="">
            </div>

            <div class="center">
                <div><span style="margin-left: 12px;">اسم الطالب </span><span id="name">{{Str::words($student->name, 5,'')}}</span></div>
                <div><span id="code"> {{$student->username}}</span><span style="margin-left: 12px;">كود الطالب </span></div>
                    <div style="width: 100px; margin: auto;">
                        <svg id="barcode-{{$student->username}}" width="100%" > </svg>
                    </div>
            </div>

        </div>
        <!--<div class="footer"></div>-->
        <div class="footer-bottom">
            <p class="footer-info pt-5">www.ahi.edu.eg</p>
            <!--<p style="color:white;font-size:7px;margin-top:-15px;margin-left:12%;">{{$year}}</p>-->
        </div>

    </div>
    @endforeach
    <script src="{{asset('assets/plugins/jquery/jquery.min.js')}}"></script>
    <script src="{{asset('js/JsBarcode.all.min.js')}}"></script>
    <script type="text/javascript">
    $(document).ready(function() {
        @foreach($students as $student)
            JsBarcode("#barcode-{{$student->username}}", "{{$student->username}}", {
                displayValue: false,
                fontSize: 14,
                width: 1.5,
                 height: 40

            });
        @endforeach

        window.print();
        window.onafterprint = function () {
            window.location.replace("{{route('print.student.cards.index')}}");
        };
    });
</script>
</body>
</html>
