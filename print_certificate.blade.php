@extends('layout.layout')
@section('title', 'طباعة شهادة التخرج')
@section('styles')
  <title>High Institute for Commercial Sciences</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    #student-search-page {
      font-family: 'Cairo', sans-serif;
      width: 210mm;
      height: 297mm;
      overflow: hidden;
      margin: auto;
      padding: 0;
      box-sizing: border-box;
      position: relative;
    }

    html, body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      box-sizing: border-box;
      background: white;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
      font-size: 15px;
      overflow: hidden !important;
      scrollbar-width: none;
    }
img, label, div, span {
  max-width: 100%;
  box-sizing: border-box;
  overflow: hidden;
}


@media print {
  @page {
    size: A4;
    margin: 0;
  }
 html, body {
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    height: 297mm !important;
    width: 210mm !important;
  }

  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .page, #student-search-page {
    width: 210mm !important;
    height: 297mm !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
  }
}
html::-webkit-scrollbar,
body::-webkit-scrollbar {
  display: none;
}
    

    .container, .container-fluid {
      padding-left: 0 !important;
      padding-right: 0 !important;
    }

   #student-search-page .page {
  width: 210mm;
  height: 297mm;
  padding: 0;
  margin: 0;
  box-sizing: border-box;
  overflow: hidden;
  position: relative;
}


    #student-search-page .header {
      color: white;
      text-align: center;
      height: 140px;
      padding-top: 5px;
      overflow: hidden;
      position: relative;
    }

    #student-search-page .logos-full img {
      width: 100%;
      height: 75px;
      object-fit: fill;
      display: block;
    }

    #student-search-page .institute-title {
      margin-top: 4px;
      font-size: 15px;
      color: white;
      line-height: 1.2;
    }

    #student-search-page .content {
      padding: 12px 15px;
      position: relative;
      
      font-size: 15px;
      z-index: 1;
      flex: 1;
    }

    #student-search-page .footer {
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
    * {
  max-width: 100% !important;
  overflow-wrap: break-word;
  word-break: break-word;
  box-sizing: border-box !important;
}

@media print, screen {
  html, body, #student-search-page, .page {
    overflow-x: hidden !important;
  }

  * {
    box-sizing: border-box !important;
    max-width: 100% !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    word-break: break-word !important;
  }

  .ph-info-block, .student-info-block {
    
  padding-right: 15px !important;

    margin: 0 auto !important;
    overflow: hidden !important;
    max-width: 100% !important;
  }
}


    #student-search-page .student-info-block,
    #student-search-page .ph-info-block {
      display: flex;
      flex-direction: row-reverse;
      align-items: flex-start;
      justify-content: space-between;
      direction: rtl;
      margin-top: 7px;
    }

    #student-search-page .ph-info-block {
      text-align: center;
      padding-right: 310px;
    }

    #student-search-page .student-text {
      text-align: right;
      flex: 1;
    }

    #student-search-page .student-photo {
      padding-left: 0;
      margin-left: 0;
      margin-top: 0;
    }

    #student-search-page .student-photo-preview {
      max-width: 100px;
      width: 100px;
      height: auto;
      border: 2px solid #0e3224;
      padding: 4px;
      background: #fff;
    }

    /*#student-search-page .dean {*/
    /*  text-align: left;*/
    /*  margin-top: 50px;*/
    /*  padding-right: 50px;*/
    /*  font-weight: bold;*/
    /*  font-size: 15px;*/
    /*  direction: rtl;*/
    /*}*/
    #student-search-page .dean {
  text-align: left;      
  margin-top: 5px;
  padding-left: 56px;      
  font-weight: bold;
  /*margin-bottom:25px ;*/
  font-size: 15px;
  direction: rtl;
}


    #student-search-page .deanreverse {
        
  text-align: left;      
  margin-top: -7px;
  padding-left: 20px;      
  font-weight: bold;
  margin-bottom:25px ;
  font-size: 15px;
  direction: rtl;

    }

    .names-block, .signatures-block {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-around;
      width: 100%;
      padding: 0 20px;
      direction: rtl;
    }
    body,
html,
.wrapper,
.os-padding,
.os-viewport,
.os-content {
  overflow: hidden !important;
  height: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 0 !important;
  scrollbar-width: none !important;
}

body::-webkit-scrollbar,
html::-webkit-scrollbar,
.wrapper::-webkit-scrollbar,
.os-padding::-webkit-scrollbar,
.os-viewport::-webkit-scrollbar,
.os-content::-webkit-scrollbar {
  display: none !important;
}

  </style>
@endsection

@section('content')
@php
    if (! function_exists('convertNumbersToArabic')) {
        function convertNumbersToArabic($string) {
            return strtr($string, ['0'=>'٠', '1'=>'١', '2'=>'٢', '3'=>'٣', '4'=>'٤', '5'=>'٥', '6'=>'٦', '7'=>'٧', '8'=>'٨', '9'=>'٩']);
        }
    }
@endphp

<div id="student-search-page">
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
    </div>
@endif
@if(isset($student))
<form id="form2" action="{{route('print.cetificate')}}" method="post">
    @csrf
    <div class="card card-success" style="all: unset;">
      <div class="page">
        <div class="header">
          <div class="logos-full"></div>
          <div class="institute-title"></div>
        </div>

        <div class="content">
         <div class="ph-info-block" style="position: relative; min-height: 100px;">
  <div class="student-photo">
    <img id="photoPreview" class="student-photo-preview" src="{{$student['photo']}}" alt="Student Photo">
  </div>
  <div class="student-section-title" style="
      position: absolute;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
      text-align: center;
  ">
    <h1 style="font-size: 26px; font-weight: bold; margin: 0;">شهادة</h1>
  </div>
</div>




          <div class="student-info-block" style="padding: 0; margin: 0;">
            <div class="student-text">
             <div style="text-align: right; direction: rtl;"> <label>يشهد المعهد العالي للحاسب الآلي ونظم المعلومات بأبي قير أن:<span class="fontcontent student-name">{{ $student['name'] }}</span></label> </div>
             
             
<!--              <label>المولود فى: <span class="fontcontent">{{ $student['birth_province'] ?? '' }}</span> بتاريخ:-->
               
               
<!--<span class="fontcontent">-->
<!--    {{ convertNumbersToArabic(\Carbon\Carbon::createFromFormat('d/m/Y', $student['birth_date'])->translatedFormat('d F Y')) }}-->
<!--</span>-->


                
<!--                برقم قومى-->
<!--                <span class="fontcontent">{{ convertNumbersToArabic($student['national_id']) ?? '---' }}</span>-->
<!--              </label>-->
<label>
    المولود فى: 
    <span class="fontcontent">{{ $student['birth_province'] ?? '' }}</span> بتاريخ:
    
    <span class="fontcontent">
        {{ convertNumbersToArabic(\Carbon\Carbon::createFromFormat('d/m/Y', $student['birth_date'])->translatedFormat('d F Y')) }}
    </span>

    @if($student['nationality'] == 'مصري')
        برقم قومى
        <span class="fontcontent">{{ convertNumbersToArabic($student['national_id']) ?? '---' }}</span>
    @else
        برقم الباسبور
        <span class="fontcontent">{{ $student['national_id'] ?? '---' }}</span>
    @endif
</label> <br>
<label style="text-align: right; direction: rtl;">رقم الجلوس ({{ date('Y') }}): {{ $seat_number ?? 'غير متوفر' }}</label> 

              <div class="student-section-title text-center mb-4" style="padding-top: 20px;">
                <label style="font-weight: bold; font-size: 20px">قد حصل على درجة البكالوريوس</label><br>
                <label style="font-weight: bold; font-size: 20px">شعبة نظم معلومات الأعمال باللغة {{($student['specialization']=='عربي')? 'العربية':'الانجليزية'}}</label>
              </div>

              <div class="names-block">
                <div>
                  <label>فصل <span>{{ $student['semester_graduated'] }}</span> <span class="fontcontent">{{ $student['year_graduated'] }}</span></label>
                </div>
                <div>
                <label>بتقدير عام <span class="fontcontent">{{ $grade }}  {{$student['note_graduation']}}</span></label>
        
                </div>
                <div>
                  <label>بنقاط تراكمية <span>{{ convertNumbersToArabic($student['cgpa']) ?? '---' }}</span> CGPA</label>
                </div>
              </div>
              <label class="fontcontent">من اجمالى النقاط التراكمية ٤ أربع نقاط</label><br>
        <label>وقد اعتمد السيد الأستاذ الدكتور وزير التعليم العالى والبحث العلمى نتيجة البكالوريوس بتاريخ <span>{{ convertNumbersToArabic(\Carbon\Carbon::create(2025, 7, 15)->locale('ar')->translatedFormat('d F Y')) }}</span></label>
              <div class="names-block" style="margin-top: 20px;">
                <label class="fontcontent">الموظف المختص</label>
                <label class="fontcontent">وكيل ادارة شئون الطلاب</label>
                <label class="fontcontent">امين المعهد</label>
              </div>
        <div class="signatures-block">
  <label class="fontcontent"style="margin-right: 28px;">أ/إيمان محمد</label>
  <label class="fontcontent" style="margin-right: 78px;">أ/يمان قدري</label>
  <label class="fontcontent" style="margin-right: 60px;">أ/فيصل شاهين</label>
</div>
<br>
<br>
<div style="display: flex; justify-content: space-between; align-items: flex-start; direction: rtl; margin-top: 1px;">
  
  
  <!--<label style="text-align: right; font-size: 15px;">-->
  <!--  تحريراً فى :-->
  <!--  <span>{{ convertNumbersToArabic(now()->format('Y/m/d')) }}</span>-->
  <!--</label>-->
<label style="text-align: right; font-size: 15px;">
  تحريراً فى :
  <span>{{ convertNumbersToArabic(\Carbon\Carbon::now()->translatedFormat('d F Y')) }}</span>

</label>

 
  <div style="text-align: left; font-size: 15px;">
    <label style="font-weight: bold; margin-left: 30px;" >عميد المعهد</label><br>
    <label style="font-weight: bold;margin-bottom: 30px;">أ.د/ شحاته السيد شحاته</label>
  </div>

</div>



              @if(request('show_extra'))
              <div>
                  <div style="border: 2px solid #000; margin-top: 15px; padding: 5px; direction: rtl; font-size: 14px; font-weight: normal;">
                  <label style="font-weight: normal;">
                    وقد صدر قرار رئيس المجلس الأعلى للجامعات رقم (٣٠٤) بتاريخ ٢٠٢٢/٨/٢٨ – بمعادلة درجة البكالوريوس في نظم المعلومات باللغة العربية التي يمنحها المعهد العالي للحاسب الآلي ونظم المعلومات  بأبي قير - الإسكندرية ج.م.ع - درجة بكالوريوس في "نظم المعلومات " التي تمنحها الجامعة المصرية الخاضعة للقانون تنظيم الجامعة رقم ٤٩ لسنة ١٩٧٢ ولائحته التنفيذية من كليات التجارة
                  </label>
                </div>

                <div class="names-block" style="margin-top: 8px;">
                  <label class="fontcontent">الموظف المختص</label>
                  <label class="fontcontent">مدير الإدارة</label>
                  <label class="fontcontent">المدير العام</label>
                </div>
              </div>
              @endif
               
              
            </div>
          </div>
        </div>

        <div class="footer"></div>
      </div>
    </div>
</form>
@endif
</div>
@endsection

@section('scripts')
<script>
  window.print();
  window.onbeforeprint = function () {
    document.querySelector('nav.main-header')?.style.setProperty('display', 'none');
    document.querySelector('aside.main-sidebar')?.style.setProperty('display', 'none');
    document.querySelector('footer.main-footer')?.style.setProperty('display', 'none');
    document.querySelector('.content-wrapper')?.classList.remove('content-wrapper');
  };
  window.onafterprint = function () {
    window.location.replace("{{ route('search.graduated.student') }}");
  };
</script>
@endsection
