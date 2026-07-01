<div>
    <h4 class="mb-4">مرحباً بك، <strong>{{ $student->name }}</strong> 👋</h4>
    
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>إجمالي التسجيلات</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $totalRegistrations }}</h3>
                            </div>
                            <p class="mb-0 text-muted">تسجيل مواد في جميع الفصول</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti tabler-clipboard-list ti-sm"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">نظرة سريعة</h5>
        </div>
        <div class="card-body mt-3">
            <p>من خلال هذه البوابة يمكنك الوصول إلى:</p>
            <ul>
                <li><strong>تسجيل المواد:</strong> تسجيل مقررات دراسية للفصل الحالي.</li>
                <li><strong>سجلات التسجيل:</strong> مراجعة تاريخ تسجيل المواد والاطلاع على حالة الموافقة.</li>
                <li><strong>بيان الحالة:</strong> الاطلاع على بياناتك الأكاديمية.</li>
            </ul>
        </div>
    </div>
</div>
