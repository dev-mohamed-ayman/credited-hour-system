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

        @can('dashboard.view')
        <li class="menu-item {{isActiveRoute('dashboard')}}">
            <a href="{{route('dashboard')}}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-smart-home"></i>
                <div data-i18n="الصفحة الرئيسية">الصفحة الرئيسية</div>
            </a>
        </li>
        @endcan

        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">الطلاب والأكاديميا</span>
        </li>

        @canany(['students.view', 'students.create', 'student_warnings.view', 'finance.view'])
    <li
        class="menu-item {{isActiveRoute(['students.*', 'print.student.cards.*', 'print.seat.numbers.*', 'print.certificates.*', 'student-warnings.*', 'student-search.*', 'admin.finance.fee-issuance', 'admin.finance.student-financial-status', 'admin.student-affairs.student-status'], true)}}"
    >
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-users"></i>
                <div data-i18n="شئون الطلبه">شئون الطلبه</div>
            </a>
            <ul class="menu-sub">
                @can('students.create')
                <li class="menu-item {{isActiveRoute('students.create')}}">
                    <a href="{{route('students.create')}}" class="menu-link">
                        <div data-i18n="اضافه طالب">اضافه طالب</div>
                    </a>
                </li>
                @endcan
                @can('students.view')
                <li class="menu-item {{isActiveRoute('students.index')}}">
                    <a href="{{route('students.index')}}" class="menu-link">
                        <div data-i18n="قائمة الطلاب">قائمة الطلاب</div>
                    </a>
                </li>
                <li class="menu-item {{isActiveRoute('student-search.index')}}">
                    <a href="{{route('student-search.index')}}" class="menu-link">
                        <div data-i18n="البحث عن طالب">البحث عن طالب</div>
                    </a>
                </li>
                <li class="menu-item {{isActiveRoute('admin.student-affairs.student-status')}}">
                    <a href="{{route('admin.student-affairs.student-status')}}" class="menu-link">
                        <div data-i18n="بيان حالة الطالب">بيان حالة الطالب</div>
                    </a>
                </li>
                @endcan
                @can('finance.view')
                <li class="menu-item {{isActiveRoute('admin.finance.fee-issuance')}}">
                    <a href="{{route('admin.finance.fee-issuance')}}" class="menu-link">
                        <div data-i18n="إصدار حافظة مصاريف">إصدار حافظة مصاريف</div>
                    </a>
                </li>
                <li class="menu-item {{isActiveRoute('admin.finance.student-financial-status')}}">
                    <a href="{{route('admin.finance.student-financial-status')}}" class="menu-link">
                        <div data-i18n="بيان الحالة المالية">بيان الحالة المالية</div>
                    </a>
                </li>
                @endcan

                @can('students.view')
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
                @endcan
                @can('student_warnings.view')
                <li class="menu-item {{isActiveRoute('student-warnings.*')}}">
                    <a href="{{route('student-warnings.index')}}" class="menu-link">
                        <div data-i18n="تنبيه الطلاب">تنبيه الطلاب</div>
                    </a>
                </li>
                @endcan
            </ul>
        </li>
        @endcanany

        @canany(['course_registrations.view', 'courses.view', 'grades.view', 'academic_advisors.view', 'military_education.view', 'transfer_equivalency.view'])
        <li class="menu-item {{isActiveRoute(['course-registrations.*', 'registration-records.*', 'courses.*', 'grades.*', 'academic-advisors.*', 'military-education-courses.*', 'admin.transfer-equivalency.*'], true)}}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-book"></i>
                <div data-i18n="الشئون الأكاديمية">الشئون الأكاديمية</div>
            </a>
            <ul class="menu-sub">
                @can('course_registrations.view')
                <li class="menu-item {{isActiveRoute('course-registrations.*')}}">
                    <a href="{{route('course-registrations.index')}}" class="menu-link">
                        <div data-i18n="تسجيل المواد">تسجيل المواد</div>
                    </a>
                </li>
                <li class="menu-item {{isActiveRoute('registration-records.*')}}">
                    <a href="{{route('registration-records.index')}}" class="menu-link">
                        <div data-i18n="سجلات التسجيل">سجلات التسجيل</div>
                    </a>
                </li>
                @endcan
                
                @can('courses.view')
                <li class="menu-item {{isActiveRoute('courses.*')}}">
                    <a href="{{route('courses.index')}}" class="menu-link">
                        <div data-i18n="المواد الدراسية">المواد الدراسية</div>
                    </a>
                </li>
                @endcan

                @can('grades.view')
                <li class="menu-item {{isActiveRoute('grades.*')}}">
                    <a href="{{route('grades.index')}}" class="menu-link">
                        <div data-i18n="التقييمات">التقييمات</div>
                    </a>
                </li>
                @endcan

                @can('academic_advisors.view')
                <li class="menu-item {{isActiveRoute('academic-advisors.*')}}">
                    <a href="{{route('academic-advisors.index')}}" class="menu-link">
                        <div data-i18n="المرشدين الأكاديميين">المرشدين الأكاديميين</div>
                    </a>
                </li>
                @endcan

                @can('military_education.view')
                <li class="menu-item {{isActiveRoute('military-education-courses.*')}}">
                    <a href="{{route('military-education-courses.index')}}" class="menu-link">
                        <div data-i18n="التربية العسكرية">التربية العسكرية</div>
                    </a>
                </li>
                @endcan

                @can('transfer_equivalency.view')
                <li class="menu-item {{isActiveRoute('admin.transfer-equivalency.*')}}">
                    <a href="{{route('admin.transfer-equivalency.index')}}" class="menu-link">
                        <div data-i18n="معادلة المحولين">معادلة المحولين</div>
                    </a>
                </li>
                @endcan
            </ul>
        </li>
        @endcanany

        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">الإدارة المالية</span>
        </li>

        @can('finance.view')
        <li class="menu-item {{isActiveRoute(['admin.finance.fee-payment', 'admin.finance.student-financial-status'], true)}}">
            <a href="javascript:void(0)" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-currency-dollar"></i>
                <div data-i18n="المالية">المالية</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ isActiveRoute('admin.finance.fee-payment') }}">
                    <a href="{{ route('admin.finance.fee-payment') }}" class="menu-link">
                        <div data-i18n="سداد الحافظة">سداد الحافظة</div>
                    </a>
                </li>
                <li class="menu-item {{ isActiveRoute('admin.finance.daily-payments') }}">
                    <a href="{{ route('admin.finance.daily-payments') }}" class="menu-link">
                        <div data-i18n="اليوميات المالية">اليوميات المالية</div>
                    </a>
                </li>
                <li class="menu-item {{ isActiveRoute('admin.finance.student-financial-status') }}">
                    <a href="{{ route('admin.finance.student-financial-status') }}" class="menu-link">
                        <div data-i18n="بيان الحالة المالية">بيان الحالة المالية</div>
                    </a>
                </li>
            </ul>
        </li>
        @endcan

        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">إعدادات النظام</span>
        </li>

        @canany(['departments.view', 'sections.view', 'levels.view', 'certificate_types.view', 'countries.view', 'cities.view', 'nationalities.view'])
        <li class="menu-item {{isActiveRoute(['departments.*', 'sections.*', 'levels.*', 'certificate-types.*', 'countries.*', 'cities.*', 'nationalities.*'], true)}}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-database"></i>
                <div data-i18n="البيانات الأساسية">البيانات الأساسية</div>
            </a>
            <ul class="menu-sub">
                @can('departments.view')
                <li class="menu-item {{isActiveRoute('departments.*')}}">
                    <a href="{{route('departments.index')}}" class="menu-link">
                        <div data-i18n="التخصصات">التخصصات</div>
                    </a>
                </li>
                @endcan
                @can('sections.view')
                <li class="menu-item {{isActiveRoute('sections.*')}}">
                    <a href="{{route('sections.index')}}" class="menu-link">
                        <div data-i18n="الشعب">الشعب</div>
                    </a>
                </li>
                @endcan
                @can('levels.view')
                <li class="menu-item {{isActiveRoute('levels.*')}}">
                    <a href="{{route('levels.index')}}" class="menu-link">
                        <div data-i18n="الفرق الدراسية">الفرق الدراسية</div>
                    </a>
                </li>
                @endcan
                @can('certificate_types.view')
                <li class="menu-item {{isActiveRoute('certificate-types.*')}}">
                    <a href="{{route('certificate-types.index')}}" class="menu-link">
                        <div data-i18n="الشهادات">الشهادات</div>
                    </a>
                </li>
                @endcan
                @can('countries.view')
                <li class="menu-item {{isActiveRoute('countries.*')}}">
                    <a href="{{route('countries.index')}}" class="menu-link">
                        <div data-i18n="الدول">الدول</div>
                    </a>
                </li>
                @endcan
                @can('cities.view')
                <li class="menu-item {{isActiveRoute('cities.*')}}">
                    <a href="{{route('cities.index')}}" class="menu-link">
                        <div data-i18n="المدن">المدن</div>
                    </a>
                </li>
                @endcan
                @can('nationalities.view')
                <li class="menu-item {{isActiveRoute('nationalities.*')}}">
                    <a href="{{route('nationalities.index')}}" class="menu-link">
                        <div data-i18n="الجنسيات">الجنسيات</div>
                    </a>
                </li>
                @endcan
            </ul>
        </li>
        @endcanany

        @canany(['registration_fees.view', 'additional_fees.view', 'years.view', 'users.view', 'settings.view', 'course_registration_settings.view'])
        <li class="menu-item {{isActiveRoute(['registration-fees.*', 'additional-fees.*', 'years.*', 'users.*', 'setting.*', 'course-registration-settings.*'], true)}}">
            <a href="javascript:void(0)" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-settings"></i>
                <div data-i18n="الإعدادات المتقدمة">الإعدادات المتقدمة</div>
            </a>
            <ul class="menu-sub">
                @can('settings.view')
                <li class="menu-item {{isActiveRoute('setting.*')}}">
                    <a href="{{route('setting.index')}}" class="menu-link">
                        <div data-i18n="الإعدادات العامة">الإعدادات العامة</div>
                    </a>
                </li>
                @endcan

                @can('course_registration_settings.view')
                <li class="menu-item {{isActiveRoute('course-registration-settings.*')}}">
                    <a href="{{route('course-registration-settings.index')}}" class="menu-link">
                        <div data-i18n="إعدادات تسجيل المواد">إعدادات تسجيل المواد</div>
                    </a>
                </li>
                @endcan

                @can('users.view')
                <li class="menu-item {{isActiveRoute('users.*')}}">
                    <a href="{{route('users.index')}}" class="menu-link">
                        <div data-i18n="إدارة المستخدمين">إدارة المستخدمين</div>
                    </a>
                </li>
                @endcan

                @canany(['registration_fees.view', 'additional_fees.view', 'years.view'])
                <li class="menu-item {{isActiveRoute(['registration-fees.*', 'additional-fees.*', 'years.*', 'fee-templates.*'], true)}}">
                    <a href="javascript:void(0)" class="menu-link menu-toggle">
                        <div data-i18n="إعدادات النظام">إعدادات النظام</div>
                    </a>
                    <ul class="menu-sub">
                        @can('registration_fees.view')
                        <li class="menu-item {{isActiveRoute('registration-fees.*')}}">
                            <a href="{{route('registration-fees.index')}}" class="menu-link">
                                <div data-i18n="مصروفات التسجيل">مصروفات التسجيل</div>
                            </a>
                        </li>
                        @endcan
                        @can('additional_fees.view')
                        <li class="menu-item {{isActiveRoute('additional-fees.*')}}">
                            <a href="{{route('additional-fees.index')}}" class="menu-link">
                                <div data-i18n="رسوم إضافية">رسوم إضافية</div>
                            </a>
                        </li>
                        <li class="menu-item {{isActiveRoute('fee-templates.*')}}">
                            <a href="{{route('fee-templates.index')}}" class="menu-link">
                                <div data-i18n="قوالب المصاريف">قوالب المصاريف</div>
                            </a>
                        </li>
                        @endcan
                        @can('years.view')
                        <li class="menu-item {{isActiveRoute('years.*')}}">
                            <a href="{{route('years.index')}}" class="menu-link">
                                <div data-i18n="السنوات الدراسية">السنوات الدراسية</div>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcanany

    </ul>
</aside>

<div class="menu-mobile-toggler d-xl-none rounded-1">
    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
        <i class="ti tabler-menu icon-base"></i>
        <i class="ti tabler-chevron-right icon-base"></i>
    </a>
</div>