@extends('admin.layouts.app')
@section('title', 'السنوات الدراسية')
@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة السنوات الدراسية</h5>
            <a class="btn btn-primary waves-effect waves-light" href="{{ route('years.create') }}">
                <i class="fa-solid fa-plus me-1"></i> إضافة سنة
            </a>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>السنة الدراسية</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($years as $year)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-bold text-primary">{{ $year->year }}</span>
                            </td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-success" href="{{ route('years.edit', $year->id) }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal{{ $year->id }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <!-- Modal Delete -->
                        <div class="modal fade" id="deleteModal{{ $year->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">تأكيد الحذف</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('years.destroy', $year->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <div class="modal-body">
                                            <div class="text-center mb-3">
                                                <i class="fa-solid fa-triangle-exclamation text-warning fs-1"></i>
                                            </div>
                                            <div class="text-center">
                                                <p>هل أنت متأكد من حذف السنة: <br>
                                                    <strong class="text-danger">{{ $year->year }}</strong>؟
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
                            <td colspan="3" class="text-center py-5">
                                <div class="text-muted">لا توجد سنوات دراسية مضافة حالياً</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
