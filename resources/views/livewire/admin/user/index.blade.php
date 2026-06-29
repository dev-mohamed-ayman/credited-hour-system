<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">إدارة المستخدمين والصلاحيات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">المستخدمين</li>
                </ol>
            </nav>
        </div>
        @can('users.create')
            <div>
                <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <i class="ti tabler-plus me-1"></i> إضافة مستخدم جديد
                </a>
            </div>
        @endcan
    </div>

    <div class="card">
        <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <select wire:model.live="perPage" class="form-select form-select-sm w-auto">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-muted small">صفحة</span>
            </div>
            <div class="col-md-4">
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti tabler-search"></i></span>
                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="بحث باسم المستخدم أو البريد الإلكتروني...">
                </div>
            </div>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead>
                    <tr>
                    <th wire:click="sortBy('name')" style="cursor: pointer;">
                        الاسم
                        @if($sortField === 'name')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th wire:click="sortBy('email')" style="cursor: pointer;">
                        البريد الإلكتروني
                        @if($sortField === 'email')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th>النوع</th>
                    <th>عدد الصلاحيات</th>
                    <th wire:click="sortBy('created_at')" style="cursor: pointer;">
                        تاريخ الإنشاء
                        @if($sortField === 'created_at')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th>الإجراءات</th>
                </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2">
                                        <span class="avatar-initial rounded-circle bg-label-primary">
                                            {{ mb_substr($user->name, 0, 2) }}
                                        </span>
                                    </div>
                                    <span class="fw-medium">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if ($user->is_super_admin)
                                    <span class="badge bg-label-danger">مدير النظام (Super Admin)</span>
                                @else
                                    <span class="badge bg-label-primary">مستخدم مخصص</span>
                                @endif
                            </td>
                            <td>
                                @if ($user->is_super_admin)
                                    <span class="text-muted">جميع الصلاحيات</span>
                                @else
                                    <span class="badge bg-label-secondary">{{ $user->permissions->count() }} صلاحية</span>
                                @endif
                            </td>
                            <td>{{ $user->created_at->format('Y-m-d') }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @can('users.edit')
                                        <a href="{{ route('users.edit', $user->id) }}" class="btn btn-sm btn-icon btn-label-primary" title="تعديل">
                                            <i class="ti tabler-edit"></i>
                                        </a>
                                    @endcan
                                    
                                    @can('users.delete')
                                        @if ($user->id !== auth()->id())
                                            <button type="button" class="btn btn-sm btn-icon btn-label-danger"
                                                onclick="confirmAction('حذف المستخدم', 'هل أنت متأكد من حذف هذا المستخدم؟ لا يمكن التراجع عن هذا الإجراء.', () => @this.call('delete', {{ $user->id }}))"
                                                title="حذف">
                                                <i class="ti tabler-trash"></i>
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted mb-2">
                                    <i class="ti tabler-users fs-1"></i>
                                </div>
                                <h5>لا يوجد مستخدمين</h5>
                                <p class="text-muted mb-0">لم يتم العثور على أي مستخدمين يطابقون معايير البحث.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="card-footer border-top d-flex justify-content-center">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
