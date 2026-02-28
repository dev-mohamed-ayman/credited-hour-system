@extends('admin.layouts.app')
@section('title', 'الشهادات')
@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة الشهادات</h5>
            <a class="btn btn-primary waves-effect waves-light" href="{{ route('certificate-types.create') }}">
                <i class="fa-solid fa-plus me-1"></i> إضافة شهادة
            </a>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>اسم الشهادة</th>
                        <th>المجموع الكلي</th>
                        <th>المتطلبات المرتبطة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($certificateTypes as $type)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-bold text-primary">{{ $type->name }}</span>
                            </td>
                            <td>
                                <span class="badge bg-label-success">{{ $type->total_score }}</span>
                            </td>
                            <td>
                                @if($type->requirements->count() > 0)
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($type->requirements as $requirement)
                                            <span class="badge bg-label-info border" data-bs-toggle="tooltip"
                                                data-bs-placement="top" title="الدرجة الصغرى: {{ $requirement->min_score }} - القسم: {{ $requirement->department->name }}">
                                                {{ $requirement->subject_name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted small">لا توجد متطلبات</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-success" href="{{ route('certificate-types.edit', $type->id) }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal{{ $type->id }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- Modal Delete -->
                        <div class="modal fade" id="deleteModal{{ $type->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">تأكيد الحذف</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('certificate-types.destroy', $type->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <div class="modal-body">
                                            <div class="text-center mb-3">
                                                <i class="fa-solid fa-triangle-exclamation text-warning fs-1"></i>
                                            </div>
                                            <div class="text-center">
                                                <p>هل أنت متأكد من حذف الشهادة: <br>
                                                    <strong class="text-danger">{{ $type->name }}</strong>؟
                                                </p>
                                                <small class="text-muted">هذا الإجراء سيؤدي لحذف الشهادة وفك ارتباطها بالمتطلبات.</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
                                                إلغاء
                                            </button>
                                            <button type="submit" class="btn btn-danger">تأكيد الحذف</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">لا توجد شهادات مضافة حالياً</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('page-scripts')
    <script>
        $(function () {
            // تفعيل الـ Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
    @endpush
@endsection
