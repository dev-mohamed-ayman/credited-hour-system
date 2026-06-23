@extends('admin.layouts.app')
@section('title', 'التقييمات')
@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة التقييمات</h5>
            @can('grades.create')
                <a class="btn btn-primary waves-effect waves-light" href="{{ route('grades.create') }}">
                    <i class="ti tabler-plus me-1"></i> إضافة تقييم
                </a>
            @endcan
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>التقييم</th>
                        <th class="text-center">الترتيب</th>
                        <th class="text-center">افتراضي (Pending)</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($grades as $grade)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td><span class="fw-bold text-primary">{{ $grade->name }}</span></td>
                            <td class="text-center">{{ $grade->order }}</td>
                            <td class="text-center">
                                @if($grade->is_pending_default)
                                    <span class="badge bg-label-success">نعم</span>
                                @else
                                    <span class="badge bg-label-secondary">لا</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-inline-block">
                                    <a href="javascript:;" class="btn btn-sm btn-icon dropdown-toggle hide-arrow"
                                       data-bs-toggle="dropdown">
                                        <i class="ti tabler-dots-vertical"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @can('grades.edit')
                                            <a class="dropdown-item" href="{{ route('grades.edit', $grade->id) }}">
                                                <i class="ti tabler-edit me-1"></i> تعديل
                                            </a>
                                        @endcan
                                        @can('grades.delete')
                                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal"
                                                data-bs-target="#deleteModal{{ $grade->id }}">
                                                <i class="ti tabler-trash me-1"></i> حذف
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @can('grades.delete')
                            <div class="modal fade" id="deleteModal{{ $grade->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">تأكيد الحذف</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="{{ route('grades.destroy', $grade->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <div class="modal-body text-center">
                                                <p>هل أنت متأكد من حذف التقييم: <strong class="text-danger">{{ $grade->name }}</strong>؟</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                <button type="submit" class="btn btn-danger">تأكيد الحذف</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endcan
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">لا توجد تقييمات مضافة حالياً</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
