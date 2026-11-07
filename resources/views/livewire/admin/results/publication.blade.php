<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">النتائج /</span> إعلان النتيجة
    </h4>

    <div class="alert alert-secondary">
        الطالب مبيشوفش تقديرات الترم ولا معدله غير بعد إعلان نتيجته. الإدارة والكشوف والتقارير بتشوف كل حاجة على طول.
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الترم</th>
                        <th class="text-center">نتائج لسه Pending</th>
                        <th>الحالة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($terms as $term)
                        <tr wire:key="term-{{ $term['year']->id }}-{{ $term['semester']->value }}">
                            <td>{{ $term['semester']->label() }} {{ $term['year']->year }}</td>
                            <td class="text-center">
                                <span class="badge bg-label-{{ $term['pending'] > 0 ? 'warning' : 'success' }}">{{ $term['pending'] }}</span>
                            </td>
                            <td>
                                @if($term['publication'])
                                    <span class="badge bg-label-success">معلنة</span>
                                    <span class="small text-muted">{{ $term['publication']->publishedBy?->name }} {{ $term['publication']->created_at?->format('Y-m-d') }}</span>
                                @else
                                    <span class="badge bg-label-secondary">غير معلنة</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($term['publication'])
                                    <button type="button" class="btn btn-sm btn-label-danger"
                                            wire:click="unpublish({{ $term['year']->id }}, '{{ $term['semester']->value }}')"
                                            wire:confirm="الطلاب مش هيشوفوا تقديرات الترم ده لحد ما تعلنها تاني. متأكد؟">إخفاء النتيجة</button>
                                @else
                                    <button type="button" class="btn btn-sm btn-primary"
                                            wire:click="publish({{ $term['year']->id }}, '{{ $term['semester']->value }}')"
                                            wire:confirm="{{ $term['pending'] > 0 ? 'لسه فيه '.$term['pending'].' نتيجة Pending في الترم ده. ' : '' }}هيتم إعلان النتيجة لكل الطلاب. متأكد؟">إعلان النتيجة</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">لا توجد ترمات فيها تسجيلات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
