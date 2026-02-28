<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'يجب قبول الحقل :attribute.',
    'accepted_if' => 'يجب قبول الحقل :attribute عندما يكون :other هو :value.',
    'active_url' => 'الحقل :attribute لا يمثل رابطًا صحيحًا.',
    'after' => 'يجب أن يكون الحقل :attribute تاريخًا لاحقًا للتاريخ :date.',
    'after_or_equal' => 'يجب أن يكون الحقل :attribute تاريخًا لاحقًا أو مطابقًا للتاريخ :date.',
    'alpha' => 'يجب أن لا يحتوي الحقل :attribute إلا على حروف.',
    'alpha_dash' => 'يجب أن لا يحتوي الحقل :attribute إلا على حروف، أرقام، شرطات وشرطات سفلية.',
    'alpha_num' => 'يجب أن لا يحتوي الحقل :attribute إلا على حروف وأرقام.',
    'array' => 'يجب أن يكون الحقل :attribute مصفوفة.',
    'ascii' => 'يجب أن يحتوي الحقل :attribute على حروف وأرقام ورموز أحادية البايت فقط.',
    'before' => 'يجب أن يكون الحقل :attribute تاريخًا سابقًا للتاريخ :date.',
    'before_or_equal' => 'يجب أن يكون الحقل :attribute تاريخًا سابقًا أو مطابقًا للتاريخ :date.',
    'between' => [
        'array' => 'يجب أن يحتوي الحقل :attribute على عدد من العناصر بين :min و :max.',
        'file' => 'يجب أن يكون حجم الملف :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة الحقل :attribute بين :min و :max.',
        'string' => 'يجب أن يكون عدد حروف الحقل :attribute بين :min و :max.',
    ],
    'boolean' => 'يجب أن تكون قيمة الحقل :attribute إما true أو false.',
    'can' => 'الحقل :attribute يحتوي على قيمة غير مصرح بها.',
    'confirmed' => 'حقل التأكيد :attribute غير متطابق.',
    'contains' => 'الحقل :attribute يفتقد إلى قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'الحقل :attribute ليس تاريخًا صحيحًا.',
    'date_equals' => 'يجب أن يكون الحقل :attribute تاريخًا مطابقًا للتاريخ :date.',
    'date_format' => 'الحقل :attribute لا يتوافق مع الشكل :format.',
    'decimal' => 'يجب أن يحتوي الحقل :attribute على :decimal أرقام عشرية.',
    'declined' => 'يجب رفض الحقل :attribute.',
    'declined_if' => 'يجب رفض الحقل :attribute عندما يكون :other هو :value.',
    'different' => 'يجب أن يكون الحقلان :attribute و :other مختلفين.',
    'digits' => 'يجب أن يحتوي الحقل :attribute على :digits رقمًا.',
    'digits_between' => 'يجب أن يكون عدد أرقام الحقل :attribute بين :min و :max.',
    'dimensions' => 'الحقل :attribute يحتوي على أبعاد صورة غير صالحة.',
    'distinct' => 'الحقل :attribute يحتوي على قيمة مكررة.',
    'doesnt_end_with' => 'يجب أن لا ينتهي الحقل :attribute بأحد القيم التالية: :values.',
    'doesnt_start_with' => 'يجب أن لا يبدأ الحقل :attribute بأحد القيم التالية: :values.',
    'email' => 'يجب أن يكون الحقل :attribute بريدًا إلكترونيًا صحيحًا.',
    'ends_with' => 'يجب أن ينتهي الحقل :attribute بأحد القيم التالية: :values.',
    'enum' => 'القيمة المختارة :attribute غير صالحة.',
    'exists' => 'القيمة المختارة :attribute غير صالحة.',
    'extensions' => 'يجب أن يكون للحقل :attribute أحد الامتدادات التالية: :values.',
    'file' => 'يجب أن يكون الحقل :attribute ملفًا.',
    'filled' => 'يجب أن يحتوي الحقل :attribute على قيمة.',
    'gt' => [
        'array' => 'يجب أن يحتوي الحقل :attribute على أكثر من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم الملف :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة الحقل :attribute أكبر من :value.',
        'string' => 'يجب أن يكون عدد حروف الحقل :attribute أكثر من :value.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي الحقل :attribute على :value عنصرًا أو أكثر.',
        'file' => 'يجب أن يكون حجم الملف :attribute أكبر من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة الحقل :attribute أكبر من أو يساوي :value.',
        'string' => 'يجب أن يكون عدد حروف الحقل :attribute أكثر من أو يساوي :value.',
    ],
    'hex_color' => 'يجب أن يكون الحقل :attribute لونًا سداسيًا عشريًا صحيحًا.',
    'image' => 'يجب أن يكون الحقل :attribute صورة.',
    'in' => 'القيمة المختارة :attribute غير صالحة.',
    'in_array' => 'الحقل :attribute غير موجود في :other.',
    'integer' => 'يجب أن يكون الحقل :attribute عددًا صحيحًا.',
    'ip' => 'يجب أن يكون الحقل :attribute عنوان IP صحيحًا.',
    'ipv4' => 'يجب أن يكون الحقل :attribute عنوان IPv4 صحيحًا.',
    'ipv6' => 'يجب أن يكون الحقل :attribute عنوان IPv6 صحيحًا.',
    'json' => 'يجب أن يكون الحقل :attribute نصًا بصيغة JSON صحيحًا.',
    'list' => 'يجب أن يكون الحقل :attribute قائمة.',
    'lowercase' => 'يجب أن يكون الحقل :attribute حروفًا صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي الحقل :attribute على أقل من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم الملف :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة الحقل :attribute أقل من :value.',
        'string' => 'يجب أن يكون عدد حروف الحقل :attribute أقل من :value.',
    ],
    'lte' => [
        'array' => 'يجب أن لا يحتوي الحقل :attribute على أكثر من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم الملف :attribute أقل من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة الحقل :attribute أقل من أو يساوي :value.',
        'string' => 'يجب أن يكون عدد حروف الحقل :attribute أقل من أو يساوي :value.',
    ],
    'mac_address' => 'يجب أن يكون الحقل :attribute عنوان MAC صحيحًا.',
    'max' => [
        'array' => 'يجب أن لا يحتوي الحقل :attribute على أكثر من :max عنصرًا.',
        'file' => 'يجب أن لا يتجاوز حجم الملف :attribute :max كيلوبايت.',
        'numeric' => 'يجب أن لا تكون قيمة الحقل :attribute أكبر من :max.',
        'string' => 'يجب أن لا يتجاوز عدد حروف الحقل :attribute :max حرفًا.',
    ],
    'max_digits' => 'يجب أن لا يحتوي الحقل :attribute على أكثر من :max أرقام.',
    'mimes' => 'يجب أن يكون الحقل :attribute ملفًا من نوع: :values.',
    'mimetypes' => 'يجب أن يكون الحقل :attribute ملفًا من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي الحقل :attribute على الأقل على :min عنصرًا.',
        'file' => 'يجب أن يكون حجم الملف :attribute على الأقل :min كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة الحقل :attribute على الأقل :min.',
        'string' => 'يجب أن يحتوي الحقل :attribute على الأقل على :min حرفًا.',
    ],
    'min_digits' => 'يجب أن يحتوي الحقل :attribute على الأقل على :min أرقام.',
    'missing' => 'يجب أن يكون الحقل :attribute مفقودًا.',
    'missing_if' => 'يجب أن يكون الحقل :attribute مفقودًا عندما يكون :other هو :value.',
    'missing_unless' => 'يجب أن يكون الحقل :attribute مفقودًا إلا إذا كان :other هو :value.',
    'missing_with' => 'يجب أن يكون الحقل :attribute مفقودًا عند وجود :values.',
    'missing_with_all' => 'يجب أن يكون الحقل :attribute مفقودًا عند وجود :values.',
    'multiple_of' => 'يجب أن يكون الحقل :attribute من مضاعفات :value.',
    'not_in' => 'القيمة المختارة :attribute غير صالحة.',
    'not_regex' => 'صيغة الحقل :attribute غير صالحة.',
    'numeric' => 'يجب أن يكون الحقل :attribute رقمًا.',
    'password' => [
        'letters' => 'يجب أن يحتوي الحقل :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي الحقل :attribute على حرف كبير وحرف صغير واحد على الأقل.',
        'numbers' => 'يجب أن يحتوي الحقل :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي الحقل :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'الحقل :attribute المختار ظهر في تسريب بيانات. يرجى اختيار :attribute مختلف.',
    ],
    'present' => 'يجب تقديم الحقل :attribute.',
    'present_if' => 'يجب تقديم الحقل :attribute عندما يكون :other هو :value.',
    'present_unless' => 'يجب تقديم الحقل :attribute إلا إذا كان :other هو :value.',
    'present_with' => 'يجب تقديم الحقل :attribute عند وجود :values.',
    'present_with_all' => 'يجب تقديم الحقل :attribute عند وجود :values.',
    'prohibited' => 'الحقل :attribute محظور.',
    'prohibited_if' => 'الحقل :attribute محظور عندما يكون :other هو :value.',
    'prohibited_unless' => 'الحقل :attribute محظور إلا إذا كان :other في :values.',
    'prohibits' => 'الحقل :attribute يمنع :other من التواجد.',
    'regex' => 'صيغة الحقل :attribute غير صالحة.',
    'required' => 'الحقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي الحقل :attribute على مدخلات لـ: :values.',
    'required_if' => 'الحقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted' => 'الحقل :attribute مطلوب عندما يتم قبول :other.',
    'required_unless' => 'الحقل :attribute مطلوب إلا إذا كان :other في :values.',
    'required_with' => 'الحقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'الحقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'الحقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'الحقل :attribute مطلوب عند عدم وجود أي من :values.',
    'same' => 'يجب أن يتطابق الحقل :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي الحقل :attribute على :size عنصرًا.',
        'file' => 'يجب أن يكون حجم الملف :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة الحقل :attribute مساوية لـ :size.',
        'string' => 'يجب أن يحتوي الحقل :attribute على :size حرفًا.',
    ],
    'starts_with' => 'يجب أن يبدأ الحقل :attribute بأحد القيم التالية: :values.',
    'string' => 'يجب أن يكون الحقل :attribute نصًا.',
    'timezone' => 'يجب أن يكون الحقل :attribute نطاقًا زمنيًا صحيحًا.',
    'unique' => 'قيمة الحقل :attribute مُستخدمة من قبل.',
    'uploaded' => 'فشل في تحميل الحقل :attribute.',
    'uppercase' => 'يجب أن يكون الحقل :attribute حروفًا كبيرة.',
    'url' => 'يجب أن يكون الحقل :attribute رابطًا صحيحًا.',
    'ulid' => 'يجب أن يكون الحقل :attribute عنوان ULID صحيحًا.',
    'uuid' => 'يجب أن يكون الحقل :attribute عنوان UUID صحيحًا.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
