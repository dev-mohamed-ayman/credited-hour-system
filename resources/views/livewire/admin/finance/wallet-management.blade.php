<div>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">محفظة الطالب</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <form wire:submit.prevent="searchStudent">
                        <div class="input-group">
                            <input type="text" class="form-control" wire:model.live.debounce.300ms="searchQuery" placeholder="ابحث بكود أو اسم الطالب...">
                            <button class="btn btn-primary" type="submit">
                                <i class="ti ti-search me-1"></i> بحث
                            </button>
                            @if($student || strlen($searchQuery) > 0)
                                <button class="btn btn-outline-secondary" type="button" wire:click="clearSearch">
                                    <i class="ti ti-x me-1"></i> مسح
                                </button>
                            @endif
                        </div>
                    </form>
                    
                    @if(empty($student) && $recentStudents->isNotEmpty())
                        <div class="mt-2 list-group position-absolute w-100 shadow-sm" style="z-index: 1000; max-height: 200px; overflow-y: auto;">
                            @foreach($recentStudents as $rs)
                                <button type="button" class="list-group-item list-group-item-action" wire:click="selectStudent({{ $rs->id }})">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">{{ $rs->name }}</h6>
                                        <small>{{ $rs->username }}</small>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($student)
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">الطالب: {{ $student->name }}</h4>
                                <p class="text-muted mb-0">الكود: {{ $student->username }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-white mb-2">رصيد المحفظة الحالي</h5>
                            <h2 class="text-white mb-0">{{ number_format($walletBalance, 2) }} ج.م</h2>
                        </div>
                        <div class="avatar avatar-lg bg-white text-primary rounded d-flex align-items-center justify-content-center">
                            <i class="ti ti-wallet ti-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">سجل الحركات</h5>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>العام/الترم</th>
                            <th>النوع</th>
                            <th>المبلغ</th>
                            <th>البيان</th>
                            <th>المستخدم</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($student->walletTransactions as $transaction)
                            <tr>
                                <td>{{ $transaction->created_at->format('Y-m-d h:i A') }}</td>
                                <td>
                                    {{ $transaction->year?->name ?? '—' }} <br>
                                    <small class="text-muted">{{ $transaction->semester?->label() ?? '—' }}</small>
                                </td>
                                <td>
                                    <span class="badge {{ $transaction->type->value === 'deposit' ? 'bg-label-success' : 'bg-label-danger' }}">
                                        {{ $transaction->type->label() }}
                                    </span>
                                </td>
                                <td class="fw-bold {{ $transaction->type->value === 'deposit' ? 'text-success' : 'text-danger' }}">
                                    {{ $transaction->type->value === 'deposit' ? '+' : '-' }}{{ number_format($transaction->amount, 2) }}
                                </td>
                                <td>{{ $transaction->reason }}</td>
                                <td>
                                    @if($transaction->performedBy)
                                        {{ $transaction->performedBy->name }}
                                        <br><small class="text-muted">{{ class_basename($transaction->performedBy) }}</small>
                                    @else
                                        <span class="text-muted">النظام</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="mb-0 text-muted">لا توجد حركات سابقة في محفظة هذا الطالب.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
