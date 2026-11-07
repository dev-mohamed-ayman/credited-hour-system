<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">النتائج /</span> تقارير النتائج
    </h4>

    <ul class="nav nav-pills mb-4">
        @foreach(['pending' => 'مواد نتيجتها ناقصة', 'ranking' => 'ترتيب الطلاب والأوائل', 'distribution' => 'توزيع التقديرات'] as $key => $label)
            <li class="nav-item">
                <button type="button" class="nav-link {{ $report === $key ? 'active' : '' }}" wire:click="$set('report', '{{ $key }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    <div class="card mb-4">
        <div class="card-body row g-3">
            @if($report !== 'ranking')
                <div class="col-md-3">
                    <label class="form-label" for="yearId">السنة</label>
                    <select id="yearId" wire:model.live="yearId" class="form-select">
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="semester">الترم</label>
                    <select id="semester" wire:model.live="semester" class="form-select">
                        @foreach($semesters as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if($report !== 'pending')
                <div class="col-md-3">
                    <label class="form-label" for="departmentId">التخصص</label>
                    <select id="departmentId" wire:model.live="departmentId" class="form-select">
                        <option value="">كل التخصصات</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if($report === 'ranking')
                <div class="col-md-3">
                    <label class="form-label" for="levelId">الفرقة</label>
                    <select id="levelId" wire:model.live="levelId" class="form-select">
                        <option value="">كل الفرق</option>
                        @foreach($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="limit">عدد الأوائل (0 = الكل)</label>
                    <input type="number" min="0" id="limit" wire:model.live.debounce.500ms="limit" class="form-control">
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            @if($report === 'pending')
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>المادة</th><th class="text-center">الطلاب المعتمدين</th><th class="text-center">لسه Pending</th></tr></thead>
                    <tbody>
                        @forelse($pending as $row)
                            <tr wire:key="pending-{{ $row['course']->id }}">
                                <td>{{ $row['course']->code }} — {{ $row['course']->name }}</td>
                                <td class="text-center">{{ $row['enrolled'] }}</td>
                                <td class="text-center"><span class="badge bg-label-warning">{{ $row['pending'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">كل نتائج الترم اترفعت.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($report === 'ranking')
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>الكود</th><th>الاسم</th><th>التخصص</th><th>الفرقة</th><th class="text-center">CGPA</th><th class="text-center">الساعات المكتسبة</th><th>التقدير العام</th></tr>
                    </thead>
                    <tbody>
                        @forelse($ranking as $row)
                            <tr wire:key="rank-{{ $row['student']->id }}">
                                <td class="fw-bold">{{ $row['rank'] }}</td>
                                <td>{{ $row['student']->username }}</td>
                                <td>{{ $row['student']->name }}</td>
                                <td>{{ $row['student']->section?->department?->name }}</td>
                                <td>{{ $row['student']->level?->name }}</td>
                                <td class="text-center fw-bold">{{ number_format($row['gpa'], 2) }}</td>
                                <td class="text-center">{{ $row['earned_hours'] }}</td>
                                <td>{{ $row['classification'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">لا يوجد طلاب لهم معدل.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="table table-bordered table-sm mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">المادة</th>
                            <th>الطلاب</th>
                            @foreach($distribution['grades'] as $grade)
                                <th>{{ $grade->name }}</th>
                            @endforeach
                            <th>Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($distribution['courses'] as $row)
                            @php($counted = collect($distribution['grades'])->sum(fn ($grade) => $row['counts'][$grade->id] ?? 0))
                            <tr wire:key="distribution-{{ $row['course']->id }}">
                                <td class="text-start">{{ $row['course']->code }} — {{ $row['course']->name }}</td>
                                <td>{{ $row['total'] }}</td>
                                @foreach($distribution['grades'] as $grade)
                                    <td>{{ $row['counts'][$grade->id] ?? 0 }}</td>
                                @endforeach
                                <td>{{ $row['total'] - $counted }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($distribution['grades']) + 3 }}" class="text-center text-muted py-4">لا توجد مواد في الترم ده.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
