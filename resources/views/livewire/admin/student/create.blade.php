<div>
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0 card-title">إضافة طالب جديد</h4>
        <button type="button" wire:click="toggleFullForm" class="btn {{ $showFullForm ? 'btn-label-primary' : 'btn-primary' }}">
            <i class="ti {{ $showFullForm ? 'tabler-eye-off' : 'tabler-eye' }} me-1"></i>
            {{ $showFullForm ? 'اكتفاء بالبيانات الأساسية' : 'إدخال كافة البيانات' }}
        </button>
    </div>

    <form wire:submit="save">
        <div class="card-body row">
            {{-- Section 1: Basic Information (Always Visible) --}}
            <div class="col-12 mb-4">
                <div class="divider text-start">
                    <div class="divider-text fw-bold text-primary">
                        <i class="ti tabler-user-check me-1"></i> البيانات الأساسية (مطلوبة للتسجيل السريع)
                    </div>
                </div>
            </div>

            <div class="form-group col-md-6 mb-3">
                <label for="name" class="form-label">اسم الطالب <span class="text-danger">*</span></label>
                <input type="text" wire:model.live="name" id="name"
                    class="form-control @error('name') is-invalid @enderror" placeholder="أدخل اسم الطالب الرباعي">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group col-md-6 mb-3">
                <label for="national_id" class="form-label">الرقم القومي / جواز السفر <span class="text-danger">*</span></label>
                <input type="text" wire:model="national_id" id="national_id"
                    class="form-control @error('national_id') is-invalid @enderror" placeholder="أدخل الرقم القومي">
                @error('national_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group col-md-4 mb-3" wire:ignore>
                <label class="form-label">التخصص <span class="text-danger">*</span></label>
                <select wire:model.live="department_id" id="department_id"
                    class="form-select select2 @error('department_id') is-invalid @enderror">
                    <option value="">اختر التخصص</option>
                    @foreach($this->departments as $department)
                        <option value="{{$department->id}}">{{$department->name}}</option>
                    @endforeach
                </select>
                @error('department_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group col-md-4 mb-3" wire:ignore>
                <label class="form-label">الشعبة <span class="text-danger">*</span></label>
                <select wire:model.live="section_id" id="section_id"
                    class="form-select select2 @error('section_id') is-invalid @enderror">
                    <option value="">اختر الشعبة</option>
                    @foreach($this->sections as $section)
                        <option value="{{$section->id}}">{{$section->name}}</option>
                    @endforeach
                </select>
                @error('section_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group col-md-4 mb-3" wire:ignore>
                <label class="form-label">الفرقة الدراسية <span class="text-danger">*</span></label>
                <select wire:model.live="level_id" id="level_id"
                    class="form-select select2 @error('level_id') is-invalid @enderror">
                    <option value="">اختر الفرقة</option>
                    @foreach($this->levels as $level)
                        <option value="{{$level->id}}">{{$level->name}}</option>
                    @endforeach
                </select>
                @error('level_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group col-md-6 mb-3" wire:ignore>
                <label class="form-label">الشهاده الحاصل عليها <span class="text-danger">*</span></label>
                <select wire:model.live="certificate_type_id" id="certificate_type_id"
                    class="form-select select2 @error('certificate_type_id') is-invalid @enderror">
                    <option value="">اختر شهاده</option>
                    @foreach($this->certificateTypes as $certificateType)
                        <option value="{{$certificateType->id}}">{{$certificateType->name}}</option>
                    @endforeach
                </select>
                @error('certificate_type_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group col-md-6 mb-3">
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="score" class="form-label">مجموع الطالب <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control @error('score') is-invalid @enderror"
                            wire:model.live="score" id="score" />
                        @error('score')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label class="form-label">النسبه المئويه</label>
                        <input type="text" class="form-control bg-light" disabled value="{{$this->percentageScore}}">
                    </div>
                </div>
            </div>

            <div class="form-group col-md-6 mb-3">
                <label class="form-label d-block">الجنس <span class="text-danger">*</span></label>
                <div class="d-flex gap-3 mt-1">
                    <div class="form-check custom-option custom-option-icon @if($gender === 'male') checked @endif"
                        style="width: 100px; cursor: pointer;" onclick="@this.set('gender', 'male')">
                        <label class="form-check-label custom-option-content p-2 text-center" for="genderMale">
                            <span class="custom-option-body">
                                <i class="ti tabler-man text-primary mb-1" style="font-size: 20px;"></i>
                                <span class="custom-option-title d-block fw-bold small">ذكر</span>
                            </span>
                            <input class="form-check-input d-none" type="radio" wire:model.live="gender" value="male"
                                id="genderMale" />
                        </label>
                    </div>
                    <div class="form-check custom-option custom-option-icon @if($gender === 'female') checked @endif"
                        style="width: 100px; cursor: pointer;" onclick="@this.set('gender', 'female')">
                        <label class="form-check-label custom-option-content p-2 text-center" for="genderFemale">
                            <span class="custom-option-body">
                                <i class="ti tabler-woman text-danger mb-1" style="font-size: 20px;"></i>
                                <span class="custom-option-title d-block fw-bold small">أنثى</span>
                            </span>
                            <input class="form-check-input d-none" type="radio" wire:model.live="gender" value="female"
                                id="genderFemale" />
                        </label>
                    </div>
                </div>
                @error('gender')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Credentials (Auto-generated) --}}
            <div class="form-group col-md-3 mb-3">
                <label class="form-label">اسم المستخدم</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ti tabler-user"></i></span>
                    <input type="text" class="form-control bg-light" value="{{$username}}" readonly>
                </div>
            </div>

            <div class="form-group col-md-3 mb-3">
                <label class="form-label">كلمة المرور</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ti tabler-lock"></i></span>
                    <input type="text" class="form-control bg-light" value="{{$password}}" readonly>
                </div>
            </div>

            {{-- Section 2: Full Form Data (Conditional) --}}
            @if($showFullForm)
                <div class="col-12 mb-4 mt-2">
                    <div class="divider text-start">
                        <div class="divider-text fw-bold text-info">
                            <i class="ti tabler-list-details me-1"></i> البيانات التكميلية
                        </div>
                    </div>
                </div>

                <div class="form-group col-md-6 mb-3" wire:ignore>
                    <label for="religion" class="form-label">الديانة <span class="text-danger">*</span></label>
                    <select wire:model="religion" id="religion"
                        class="form-select select2 @error('religion') is-invalid @enderror">
                        <option value="مسلم">مسلم</option>
                        <option value="مسيحي">مسيحي</option>
                    </select>
                    @error('religion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-6 mb-3">
                    <label for="birth_date" class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('birth_date') is-invalid @enderror"
                        wire:model="birth_date" id="birth_date" />
                    @error('birth_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-4 mb-3" wire:ignore>
                    <label for="nationality_id" class="form-label">الجنسية <span class="text-danger">*</span></label>
                    <select wire:model="nationality_id" id="nationality_id"
                        class="form-select select2 @error('nationality_id') is-invalid @enderror">
                        <option value="">اختر الجنسية</option>
                        @foreach ($this->nationalities as $nationality)
                            <option value="{{ $nationality->id }}">{{ $nationality->name }}</option>
                        @endforeach
                    </select>
                    @error('nationality_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-4 mb-3" wire:ignore>
                    <label for="country_id" class="form-label">الدولة <span class="text-danger">*</span></label>
                    <select wire:model.live="country_id" id="country_id"
                        class="form-select select2 @error('country_id') is-invalid @enderror">
                        <option value="">اختر الدولة</option>
                        @foreach ($this->countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                    @error('country_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-4 mb-3" wire:ignore>
                    <label for="city_id" class="form-label">المدينة <span class="text-danger">*</span></label>
                    <select wire:model.live="city_id" id="city_id"
                        class="form-select select2 @error('city_id') is-invalid @enderror">
                        <option value="">اختر المدينة</option>
                        @foreach ($this->cities as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                        @endforeach
                    </select>
                    @error('city_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-8 mb-3">
                    <label for="address" class="form-label">العنوان بالتفصيل <span class="text-danger">*</span></label>
                    <textarea wire:model="address" id="address" class="form-control @error('address') is-invalid @enderror"
                        placeholder="أدخل العنوان التفصيلي للطالب" rows="2"></textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-4 mb-3">
                    <label for="national_id_place" class="form-label">جهة صدور الرقم القومي <span class="text-danger">*</span></label>
                    <input type="text" wire:model="national_id_place" id="national_id_place"
                        class="form-control @error('national_id_place') is-invalid @enderror" placeholder="أدخل جهة الصدور">
                    @error('national_id_place')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-4 mb-3">
                    <label for="graduation_date" class="form-label">تاريخ الحصول علي الشهاده <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('graduation_date') is-invalid @enderror"
                        wire:model="graduation_date" id="graduation_date" />
                    @error('graduation_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-4 mb-3">
                    <label for="seat_number" class="form-label">رقم جلوس الشهادة <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('seat_number') is-invalid @enderror"
                        wire:model="seat_number" id="seat_number" placeholder="أدخل رقم الجلوس" />
                    @error('seat_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-4 mb-3 d-flex align-items-center mt-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" wire:model.live="is_foreign" id="is_foreign">
                        <label class="form-check-label fw-medium" for="is_foreign">طالب وافد؟</label>
                    </div>
                </div>

                <div class="col-12 mb-4 mt-2">
                    <div class="divider text-start">
                        <div class="divider-text fw-bold text-info">
                            <i class="ti tabler-phone me-1"></i> بيانات الاتصال وولي الأمر
                        </div>
                    </div>
                </div>

                <div class="form-group col-md-4 mb-3">
                    <label for="email" class="form-label">البريد الإلكتروني</label>
                    <input type="email" wire:model="email" id="email"
                        class="form-control @error('email') is-invalid @enderror" placeholder="example@mail.com">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-4 mb-3">
                    <label for="phone" class="form-label">موبايل الطالب</label>
                    <input type="text" wire:model="phone" id="phone"
                        class="form-control @error('phone') is-invalid @enderror" placeholder="01xxxxxxxxx">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-4 mb-3">
                    <label for="guardian_phone_1" class="form-label">تليفون ولي الأمر</label>
                    <input type="text" wire:model="guardian_phone_1" id="guardian_phone_1"
                        class="form-control @error('guardian_phone_1') is-invalid @enderror" placeholder="01xxxxxxxxx">
                    @error('guardian_phone_1')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group col-md-6 mb-3">
                    <label class="form-label">صوره الطالب</label>
                    <div id="fileUpload" class="border rounded-2 p-3 text-center bg-light cursor-pointer position-relative"
                        style="border: 2px dashed var(--bs-border-color); min-height: 120px;"
                        ondragover="event.preventDefault();" ondrop="handleDrop(event);">

                        @if(!$image)
                            <div class="d-flex flex-column align-items-center justify-content-center h-100">
                                <i class="icon-base ti tabler-cloud-upload text-primary mb-1" style="font-size: 32px;"></i>
                                <div class="fw-medium small">اسحب وأفلت الصورة هنا</div>
                            </div>
                        @else
                            <div class="mt-1">
                                <img src="{{$image->temporaryUrl()}}" class="img-thumbnail rounded" style="max-height: 80px;">
                                <div class="mt-1">
                                    <button type="button" class="btn btn-xs btn-label-danger"
                                        onclick="event.stopPropagation(); @this.set('image', null)">إزالة</button>
                                </div>
                            </div>
                        @endif
                        <input id="fileUploadInput" type="file" wire:model="image" class="d-none">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="row">
                        <div class="form-group col-12 mb-3">
                            <label class="form-label">الحالة الدراسية</label>
                            <select wire:model.live="study_status" id="study_status"
                                class="form-select select2 @error('study_status') is-invalid @enderror">
                                @foreach(\App\Enums\Student\StudyStatus::cases() as $studyStatus)
                                    <option value="{{$studyStatus->value}}">{{$studyStatus->label()}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-12 mb-3">
                            <label class="form-label">تصنيف التقديم</label>
                            <select wire:model.live="application_category" id="application_category"
                                class="form-select select2 @error('application_category') is-invalid @enderror">
                                @foreach(\App\Enums\Student\ApplicationCategory::cases() as $appCategory)
                                    <option value="{{$appCategory->value}}">{{$appCategory->label()}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Dynamic Requirements (Always show if relevant) --}}
            @if($this->departmentRequirements->count() > 0)
                <div class="col-12 mb-3 mt-3">
                    <h6 class="fw-bold"><i class="ti tabler-list me-1"></i> درجات المواد المطلوبة:</h6>
                    <div class="row">
                        @foreach($this->departmentRequirements as $requirement)
                            <div class="form-group col-md-3 mb-3">
                                <label class="form-label small">{{$requirement->subject_name}} (الأدنى: {{$requirement->min_score}})</label>
                                <input type="number" step="0.01" wire:model.live="requirements.{{$requirement->id}}"
                                    class="form-control @error('requirements.'.$requirement->id) is-invalid @enderror">
                                @error('requirements.'.$requirement->id)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="card-footer d-flex justify-content-end gap-2 bg-transparent border-top-0">
            <a href="{{ route('students.index') }}" class="btn btn-label-secondary">إلغاء</a>
            <button type="submit" class="btn btn-primary btn-lg" wire:loading.attr="disabled">
                <span wire:loading class="spinner-border spinner-border-sm me-1" role="status"></span>
                <i class="ti tabler-device-floppy me-1"></i> حفظ بيانات الطالب
            </button>
        </div>
    </form>

    @script
    <script>
        // File Upload Handling
        window.handleDrop = function (e) {
            e.preventDefault();
            if (e.dataTransfer.files.length > 0) {
                $wire.upload('image', e.dataTransfer.files[0]);
            }
        }

        const fileUpload = document.getElementById('fileUpload');
        const fileUploadInput = document.getElementById('fileUploadInput');

        if (fileUpload && fileUploadInput) {
            fileUpload.addEventListener('click', function (e) {
                if (e.target.closest('button')) return;
                fileUploadInput.click();
            });
        }

        // Select2 Initialization
        const initSelect2OnElement = (elementId) => {
            const $el = $('#' + elementId);
            if ($el.length) {
                $el.select2({
                    dropdownParent: $el.parent()
                }).on('change', function (e) {
                    $wire.set($(this).attr('wire:model.live') || $(this).attr('wire:model'), e.target.value);
                });
            }
        }

        const initSelect2 = () => {
            $('.select2').each(function () {
                const $this = $(this);
                // Skip if already initialized or handle it
                $this.select2({
                    dropdownParent: $this.parent()
                }).on('change', function (e) {
                    $wire.set($(this).attr('wire:model.live') || $(this).attr('wire:model'), e.target.value);
                });
            });
        }

        initSelect2();

        // Re-init Select2 after Livewire updates if necessary
        document.addEventListener('livewire:navigated', () => {
            initSelect2();
        });

        $wire.on('country-updated', () => {
            $('#city_id').val(null).trigger('change');
        });

        $wire.on('department-updated', () => {
            $('#section_id').val(null).trigger('change');
            $('#level_id').val(null).trigger('change');
        });

        $wire.on('section-updated', () => {
            $('#level_id').val(null).trigger('change');
        });

        $wire.on('cities-loaded', (event) => {
            const citySelect = $('#city_id');
            citySelect.empty();
            citySelect.append('<option value="">اختر المدينة</option>');

            if (event.cities && event.cities.length > 0) {
                event.cities.forEach(city => {
                    citySelect.append(`<option value="${city.id}">${city.name}</option>`);
                });
            }

            initSelect2OnElement('city_id');
        });

        $wire.on('sections-loaded', (event) => {
            const sectionSelect = $('#section_id');
            sectionSelect.empty();
            sectionSelect.append('<option value="">اختر الشعبة</option>');

            if (event.sections && event.sections.length > 0) {
                event.sections.forEach(section => {
                    sectionSelect.append(`<option value="${section.id}">${section.name}</option>`);
                });
            }

            initSelect2OnElement('section_id');
        });

        $wire.on('levels-loaded', (event) => {
            const levelSelect = $('#level_id');
            levelSelect.empty();
            levelSelect.append('<option value="">اختر الفرقة</option>');

            if (event.levels && event.levels.length > 0) {
                event.levels.forEach(level => {
                    levelSelect.append(`<option value="${level.id}">${level.name}</option>`);
                });
            }

            initSelect2OnElement('level_id');
        });
    </script>
    @endscript
</div>

@push('vendor-styles')
    <link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endpush

@push('vendor-scripts')
    <script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endpush
