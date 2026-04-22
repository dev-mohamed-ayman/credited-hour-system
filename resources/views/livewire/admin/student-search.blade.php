<div>
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">البحث عن طالب</h4>
        </div>
        <div class="card-body">
            <div class="input-group mb-3">
                <input type="text" class="form-control" placeholder="أدخل كود الطالب" wire:model.defer="studentCode">
                <button class="btn btn-outline-primary" type="button" wire:click="searchStudent">بحث</button>
            </div>

            @if ($student)
                <div class="mt-4">
                    <h5>بيانات الطالب:</h5>
                    <p><strong>اسم الطالب:</strong> {{ $student->name }}</p>
                    <p><strong>الفرقة الدراسية:</strong> {{ $student->level->name ?? 'N/A' }}</p>
                    <p><strong>كود الطالب:</strong> {{ $student->username }}</p>
                    <p><strong>التخصص:</strong> {{ $student->section->name ?? 'N/A' }}</p>
                    <p><strong>كلمة المرور:</strong> {{ $student->password_text }}</p>
                    <p><strong>الحالة الدراسية:</strong> {{ $student->status }}</p>
                    <p><strong>المرشد الأكاديمي:</strong> {{ $student->academicAdvisor->name ?? 'N/A' }}</p>
                    <p><strong>الإنذارات:</strong> {{ $student->warnings->count() }}</p>
                    <p><strong>حالة الطالب:</strong> {{ $student->is_active ? 'نشط' : 'غير نشط' }}</p>
                    <p><strong>الملاحظات:</strong> {{ $student->notes ?? 'لا توجد ملاحظات' }}</p>
                </div>
            @elseif ($studentCode && !$student)
                <div class="mt-4 alert alert-warning">
                    لم يتم العثور على طالب بهذا الكود.
                </div>
            @endif
        </div>
    </div>
</div>
