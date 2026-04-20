<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <div class="app-brand demo ">
        <a href="index.html" class="app-brand-link">
            <span class="app-brand-logo demo">
                <span class="text-primary">
                    <svg width="32" height="22" viewBox="0 0 32 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z"
                            fill="currentColor" />
                        <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                            d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616" />
                        <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                            d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616" />
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z"
                            fill="currentColor" />
                    </svg>
                </span>
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-3">Vuexy</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
            <i class="icon-base ti tabler-x d-block d-xl-none"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">

        <li class="menu-item {{isActiveRoute('dashboard')}}">
            <a href="{{route('dashboard')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-smart-home"></i>
                <div data-i18n="الصفحة الرئيسية">الصفحة الرئيسية</div>
            </a>
        </li>


        <li class="menu-item {{isActiveRoute('setting.*')}}">
            <a href="{{route('setting.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-settings"></i>
                <div data-i18n="الإعدادات">الإعدادات</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('departments.*')}}">
            <a href="{{route('departments.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-layout-grid"></i>
                <div data-i18n="التخصصات">التخصصات</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('sections.*')}}">
            <a href="{{route('sections.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-subtask"></i>
                <div data-i18n="الشعب">الشعب</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('levels.*')}}">
            <a href="{{route('levels.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-stairs"></i>
                <div data-i18n="الفرق الدراسية">الفرق الدراسية</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('years.*')}}">
            <a href="{{route('years.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-calendar-stats"></i>
                <div data-i18n="السنوات الدراسية">السنوات الدراسية</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('courses.*')}}">
            <a href="{{route('courses.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-book"></i>
                <div data-i18n="المواد الدراسية">المواد الدراسية</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('certificate-types.*')}}">
            <a href="{{route('certificate-types.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-certificate"></i>
                <div data-i18n="الشهادات">الشهادات</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('countries.*')}}">
            <a href="{{route('countries.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-world"></i>
                <div data-i18n="الدول">الدول</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('cities.*')}}">
            <a href="{{route('cities.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-map-pin"></i>
                <div data-i18n="المدن">المدن</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('nationalities.*')}}">
            <a href="{{route('nationalities.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-flag"></i>
                <div data-i18n="الجنسيات">الجنسيات</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute('academic-advisors.*')}}">
            <a href="{{route('academic-advisors.index')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-flag"></i>
                <div data-i18n="المرشدين الأكاديميين">المرشدين الأكاديميين</div>
            </a>
        </li>

        <li class="menu-item {{isActiveRoute(['students.*', 'print.student.cards.*', 'print.seat.numbers.*', 'print.certificates.*'], true)}}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-users"></i>
                <div data-i18n="شئون الطلبه">شئون الطلبه</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{isActiveRoute('students.create')}}">
                    <a href="{{route('students.create')}}" class="menu-link">
                        <div data-i18n="اضافه طالب">اضافه طالب</div>
                    </a>
                </li>
                <li class="menu-item {{isActiveRoute('students.index')}}">
                    <a href="{{route('students.index')}}" class="menu-link">
                        <div data-i18n="قائمة الطلاب">قائمة الطلاب</div>
                    </a>
                </li>
                <li class="menu-item {{isActiveRoute('print.student.cards.index')}}">
                    <a href="{{route('print.student.cards.index')}}" class="menu-link">
                        <div data-i18n="طباعة الكارنيهات">طباعة الكارنيهات</div>
                    </a>
                </li>
                <li class="menu-item {{isActiveRoute('print.seat.numbers.index')}}">
                    <a href="{{route('print.seat.numbers.index')}}" class="menu-link">
                        <div data-i18n="طباعة أرقام الجلوس">طباعة أرقام الجلوس</div>
                    </a>
                </li>
                <li class="menu-item {{isActiveRoute('print.certificates.index')}}">
                    <a href="{{route('print.certificates.index')}}" class="menu-link">
                        <div data-i18n="طباعة شهادات التخرج">طباعة شهادات التخرج</div>
                    </a>
                </li>
            </ul>
        </li>

    </ul>
</aside>

<div class="menu-mobile-toggler d-xl-none rounded-1">
    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
        <i class="ti tabler-menu icon-base"></i>
        <i class="ti tabler-chevron-right icon-base"></i>
    </a>
</div>