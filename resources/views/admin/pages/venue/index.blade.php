@extends('admin.layouts.app')
@section('title', 'الأماكن والمدرجات')
@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة الأماكن</h5>
            @can('venues.create')
                <a class="btn btn-primary waves-effect waves-light" href="{{ route('venues.create') }}">
                    <i class="fa-solid fa-plus me-1"></i> إضافة مكان
                </a>
            @endcan
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>اسم المكان</th>
                        <th>النوع</th>
                        <th>السعة</th>
                        <th>الحالة</th>
                        <th>عدد الجلسات</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($venues as $venue)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td><span class="fw-bold text-primary">{{ $venue->name }}</span></td>
                            <td><span class="badge bg-label-info">{{ $venue->type->label() }}</span></td>
                            <td>
                                @if($venue->capacity === null)
                                    <span class="badge bg-label-warning">غير محددة</span>
                                @else
                                    {{ $venue->capacity }} طالب
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $venue->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ $venue->is_active ? 'مفعّل' : 'متوقف' }}
                                </span>
                            </td>
                            <td>{{ $venue->lecture_schedules_count }}</td>
                            <td class="text-center">
                                @can('venues.edit')
                                    <a class="btn btn-sm btn-success" href="{{ route('venues.edit', $venue->id) }}">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                @endcan
                                @can('venues.delete')
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                        data-bs-target="#deleteModal{{ $venue->id }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>

                                    <div class="modal fade" id="deleteModal{{ $venue->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <form action="{{ route('venues.destroy', $venue->id) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <div class="modal-body text-center">
                                                        <i class="fa-solid fa-triangle-exclamation text-warning fs-1 mb-3"></i>
                                                        <p>هل أنت متأكد من حذف المكان: <strong class="text-danger">{{ $venue->name }}</strong>؟</p>
                                                        <small class="text-muted">هذا الإجراء لا يمكن التراجع عنه.</small>
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
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">لا توجد أماكن مسجلة بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
