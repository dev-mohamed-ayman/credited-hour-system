@extends('admin.layouts.app')
@section('title', 'الاعدادات')
@section('content')
    <div class="card">
        <form action="{{ route('setting.update') }}" method="POST">
            @csrf
            
            <div class="card-header">
                <h3 class="card-title">إعدادات طباعة الكارنيه</h3>
            </div>
            
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="card_show_photo" name="card_show_photo" {{ optional($settings)->card_show_photo ? 'checked' : '' }}>
                            <label class="custom-control-label" for="card_show_photo">إظهار الصورة</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="card_show_name" name="card_show_name" {{ optional($settings)->card_show_name ? 'checked' : '' }}>
                            <label class="custom-control-label" for="card_show_name">إظهار الاسم</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="card_show_code" name="card_show_code" {{ optional($settings)->card_show_code ? 'checked' : '' }}>
                            <label class="custom-control-label" for="card_show_code">إظهار كود الطالب</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="card_show_barcode" name="card_show_barcode" {{ optional($settings)->card_show_barcode ? 'checked' : '' }}>
                            <label class="custom-control-label" for="card_show_barcode">إظهار الباركود</label>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mt-3">
                        <div class="form-group custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="card_show_department" name="card_show_department" {{ optional($settings)->card_show_department ? 'checked' : '' }}>
                            <label class="custom-control-label" for="card_show_department">إظهار التخصص</label>
                        </div>
                    </div>
                    <div class="col-md-3 mt-3">
                        <div class="form-group custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="card_show_section" name="card_show_section" {{ optional($settings)->card_show_section ? 'checked' : '' }}>
                            <label class="custom-control-label" for="card_show_section">إظهار الشعبة</label>
                        </div>
                    </div>
                    <div class="col-md-3 mt-3">
                        <div class="form-group custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="card_show_level" name="card_show_level" {{ optional($settings)->card_show_level ? 'checked' : '' }}>
                            <label class="custom-control-label" for="card_show_level">إظهار الفرقة</label>
                        </div>
                    </div>
                    <div class="col-md-3 mt-3">
                        <div class="form-group custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="card_show_national_id" name="card_show_national_id" {{ optional($settings)->card_show_national_id ? 'checked' : '' }}>
                            <label class="custom-control-label" for="card_show_national_id">إظهار الرقم القومي</label>
                        </div>
                    </div>
                </div>

            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary btn-sm">حفظ التعديلات</button>
            </div>
        </form>
    </div>
@endsection
