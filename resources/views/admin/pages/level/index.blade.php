@extends('admin.layouts.app')
@section('title', 'الفرق الدراسية')
@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة الفرق الدراسية</h5>
            <a class="btn btn-primary waves-effect waves-light" href="{{ route('levels.create') }}">
                <i class="fa-solid fa-plus me-1"></i> إضافة فرقة
            </a>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>الفرقة الدراسية</th>
                        <th>الشعب المرتبطة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($levels as $level)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-bold text-primary">{{ $level->name }}</span>
                            </td>
                            <td>
                                @forelse($level->sections as $section)
                                    <span class="badge bg-label-info me-1">{{ $section->name }} ({{ $section->department->name }})</span>
                                @empty
                                    <span class="text-muted small">لا توجد شعب</span>
                                @endforelse
                            </td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-success" href="{{ route('levels.edit', $level->id) }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal{{ $level->id }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <!-- Modal Delete -->
                        <div class="modal fade" id="deleteModal{{ $level->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">تأكيد الحذف</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('levels.destroy', $level->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <div class="modal-body">
                                            <div class="text-center mb-3">
                                                <i class="fa-solid fa-triangle-exclamation text-warning fs-1"></i>
                                            </div>
                                            <div class="text-center">
                                                <p>هل أنت متأكد من حذف الفرقة: <br>
                                                    <strong class="text-danger">{{ $level->name }}</strong>؟
                                                </p>
                                                <small class="text-muted">هذا الإجراء لا يمكن التراجع عنه.</small>
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
                            <td colspan="4" class="text-center py-5">
                                <div class="text-muted">لا توجد فرق دراسية مضافة حالياً</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
