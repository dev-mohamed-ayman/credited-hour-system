<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <div class="app-brand demo ">
        <a href="javascript:void(0);" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img src="{{ asset('images/logo-icon.png') }}" alt="شعار المعهد" class="brand-logo-img">
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-3">بوابة المرشدين</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
            <i class="icon-base ti tabler-x d-block d-xl-none"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">

        <li class="menu-item {{isActiveRoute('advisor.dashboard')}}">
            <a href="{{route('advisor.dashboard')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-smart-home"></i>
                <div data-i18n="الصفحة الرئيسية">الصفحة الرئيسية</div>
            </a>
        </li>

        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">إدارة الطلاب</span>
        </li>

        <li class="menu-item {{isActiveRoute('advisor.course-registrations.*')}}">
            <a href="{{route('advisor.course-registrations.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-clipboard-list"></i>
                <div data-i18n="تسجيل المواد">تسجيل المواد</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('advisor.registration-records.*')}}">
            <a href="{{route('advisor.registration-records.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-history"></i>
                <div data-i18n="سجلات التسجيل">سجلات التسجيل</div>
            </a>
        </li>

    </ul>
</aside>

<div class="menu-mobile-toggler d-xl-none rounded-1">
    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
        <i class="ti tabler-menu icon-base"></i>
        <i class="ti tabler-chevron-right icon-base"></i>
    </a>
</div>