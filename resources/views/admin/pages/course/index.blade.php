@extends('admin.layouts.app')
@section('title', 'المواد الدراسية')
@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة المواد الدراسية</h5>
            <a class="btn btn-primary waves-effect waves-light" href="{{ route('courses.create') }}">
                <i class="fa-solid fa-plus me-1"></i> إضافة مادة
            </a>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>كود المادة</th>
                        <th>اسم المادة</th>
                        <th>الساعات</th>
                        <th>التخصص</th>
                        <th>الترم</th>
                        <th>الشعب</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($courses as $course)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>
                                <span class="badge bg-label-secondary fw-bold text-uppercase">{{ $course->code }}</span>
                            </td>
                            <td>
                                <span class="fw-bold text-primary">{{ $course->name }}</span>
                            </td>
                            <td>
                                <span class="badge bg-label-info">{{ $course->hours }} ساعة</span>
                            </td>
                            <td>
                                <span class="badge bg-label-dark">{{ $course->department->name }}</span>
                            </td>
                            <td>
                                <span class="badge bg-label-warning">{{ $course->semester }}</span>
                            </td>
                            <td>
                                @foreach($course->sections as $section)
                                    <span class="badge bg-label-primary mb-1">{{ $section->name }}</span>
                                @endforeach
                            </td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-success" href="{{ route('courses.edit', $course->id) }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal{{ $course->id }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <!-- Modal Delete -->
                        <div class="modal fade" id="deleteModal{{ $course->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">تأكيد الحذف</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('courses.destroy', $course->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <div class="modal-body">
                                            <div class="text-center mb-3">
                                                <i class="fa-solid fa-triangle-exclamation text-warning fs-1"></i>
                                            </div>
                                            <div class="text-center">
                                                <p>هل أنت متأكد من حذف المادة: <br>
                                                    <strong class="text-danger">{{ $course->name }}</strong>؟
                                                </p>
                                                <small class="text-muted">هذا الإجراء لا يمكن التراجع عنه.</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer text-end">
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
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">لا توجد مواد دراسية مضافة حالياً</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
