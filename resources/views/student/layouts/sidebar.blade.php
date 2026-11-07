<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <div class="app-brand demo ">
        <a href="javascript:void(0);" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img src="{{ asset('images/logo-icon.png') }}" alt="شعار المعهد" class="brand-logo-img">
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-3">بوابة الطلاب</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
            <i class="icon-base ti tabler-x d-block d-xl-none"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">

        <li class="menu-item {{isActiveRoute('student.dashboard')}}">
            <a href="{{route('student.dashboard')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-smart-home"></i>
                <div data-i18n="الصفحة الرئيسية">الصفحة الرئيسية</div>
            </a>
        </li>

        @php($isPortalReadOnly = auth('student')->user()?->isPortalReadOnly())

        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">الخدمات الأكاديمية</span>
        </li>

        @unless ($isPortalReadOnly)
        <li class="menu-item {{isActiveRoute('student.course-registrations.*')}}">
            <a href="{{route('student.course-registrations.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-clipboard-list"></i>
                <div data-i18n="تسجيل المواد">تسجيل المواد</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('student.registration-records.*')}}">
            <a href="{{route('student.registration-records.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-history"></i>
                <div data-i18n="سجلات التسجيل">سجلات التسجيل</div>
            </a>
        </li>
        @endunless

        <li class="menu-item {{isActiveRoute('student.status-statement')}}">
            <a href="{{route('student.status-statement')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-file-text"></i>
                <div data-i18n="بيان الحالة">بيان الحالة</div>
            </a>
        </li>

        @unless ($isPortalReadOnly)
        <li class="menu-item {{isActiveRoute('student.print-seat-number')}}">
            <a href="{{route('student.print-seat-number')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-identity-card"></i>
                <div data-i18n="طباعة رقم الجلوس">طباعة رقم الجلوس</div>
            </a>
        </li>

        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">الحساب</span>
        </li>

        <li class="menu-item {{isActiveRoute('student.change-password')}}">
            <a href="{{route('student.change-password')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-lock"></i>
                <div data-i18n="تغيير كلمة المرور">تغيير كلمة المرور</div>
            </a>
        </li>
        @endunless

    </ul>
</aside>

<div class="menu-mobile-toggler d-xl-none rounded-1">
    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
        <i class="ti tabler-menu icon-base"></i>
        <i class="ti tabler-chevron-right icon-base"></i>
    </a>
</div>
