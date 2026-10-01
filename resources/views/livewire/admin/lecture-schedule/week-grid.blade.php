<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">الجدول الأسبوعي — {{ $course->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('lecture-schedules.index', ['course' => $course->id]) }}">جدول المحاضرات</a></li>
                    <li class="breadcrumb-item active">الشبكة الأسبوعية</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('lecture-schedules.index', ['course' => $course->id]) }}" class="btn btn-label-secondary btn-sm">رجوع لقائمة الجلسات</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered text-center align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 90px;">الوقت</th>
                        @foreach($days as $day)
                            <th>{{ $day->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($times as $time)
                        <tr>
                            <th class="text-nowrap">{{ $time }}</th>
                            @foreach($days as $day)
                                <td>
                                    @foreach($cells[$day->value][$time] ?? [] as $session)
                                        <div class="badge bg-label-primary d-inline-block p-2 mb-1 text-wrap">
                                            {{ $session->venue->name }}
                                            <span class="d-block small fw-normal">
                                                {{ $session->start_time }} — {{ $session->end_time }}
                                                ({{ $session->sectionNumbersLabel() }})
                                            </span>
                                        </div>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $days->count() + 1 }}" class="text-center text-muted py-4">لا توجد جلسات لعرضها في الجدول الأسبوعي.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
