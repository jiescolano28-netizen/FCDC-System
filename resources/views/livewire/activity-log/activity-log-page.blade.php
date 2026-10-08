<section class="activity-log-page" aria-labelledby="activity-log-heading">
    <header class="activity-log-heading">
        <div>
            <p class="activity-log-eyebrow">ADMINISTRATION <span aria-hidden="true">·</span> AUDIT TRAIL</p>
            <h1 id="activity-log-heading">Activity log</h1>
            <p class="activity-log-subtitle">A record of changes made to inventory, employee, and role data.</p>
        </div>
        <div class="activity-log-summary" aria-label="Activity records">
            <span class="activity-log-summary-icon" aria-hidden="true">◷</span>
            <span><strong>{{ number_format($activities->total()) }}</strong><small>recorded events</small></span>
        </div>
    </header>

    <section class="activity-log-card" aria-labelledby="activity-log-records-heading">
        <header class="activity-log-card-heading">
            <div>
                <h2 id="activity-log-records-heading">Recent activity</h2>
                <p>Showing {{ $activities->firstItem() ?? 0 }}–{{ $activities->lastItem() ?? 0 }} of {{ number_format($activities->total()) }} events</p>
            </div>
            <span class="activity-log-live"><span aria-hidden="true"></span> Audit history</span>
        </header>

        <div class="activity-log-table-wrap">
            <table class="activity-log-table">
                <thead>
                    <tr>
                        <th scope="col">Date &amp; time</th>
                        <th scope="col">Performed by</th>
                        <th scope="col">Action</th>
                        <th scope="col">Affected record</th>
                        <th scope="col">Change details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        @php
                            $attributes = $activity->properties['attributes'] ?? [];
                            $old = $activity->properties['old'] ?? [];
                            $fields = array_unique([...array_keys($old), ...array_keys($attributes)]);
                            $actor = $activity->causer?->username ?? $activity->causer?->name;
                            $actor ??= $activity->causer_id ? 'Former employee #'.$activity->causer_id : 'System';
                            $subject = $activity->subject?->username ?? $activity->subject?->name;
                            $subject ??= $activity->subject_type
                                ? class_basename($activity->subject_type).' #'.$activity->subject_id
                                : '—';
                            $formatValue = static fn ($value) => is_scalar($value) || $value === null
                                ? ($value === null ? '—' : (string) $value)
                                : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            $initials = collect(preg_split('/\s+/', trim($actor)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                            $event = $activity->event ?: 'activity';
                        @endphp
                        <tr>
                            <td class="activity-log-date">
                                <strong>{{ $activity->created_at?->format('M d, Y') }}</strong>
                                <span>{{ $activity->created_at?->format('H:i:s') }}</span>
                            </td>
                            <td>
                                <div class="activity-log-person">
                                    <span class="activity-log-avatar" aria-hidden="true">{{ $initials ?: '·' }}</span>
                                    <span>{{ $actor }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="activity-log-event activity-log-event-{{ str($event)->slug() }}">{{ str($event)->replace('_', ' ')->title() }}</span>
                                <span class="activity-log-description">{{ $activity->description }}</span>
                            </td>
                            <td><span class="activity-log-subject">{{ $subject }}</span></td>
                            <td>
                                @if ($fields === [])
                                    <span class="activity-log-no-changes">No field details</span>
                                @else
                                    <ul class="activity-log-changes">
                                        @foreach ($fields as $field)
                                            <li>
                                                <strong>{{ str($field)->replace('_', ' ')->title() }}</strong>
                                                <span>{{ $formatValue($old[$field] ?? null) }}</span>
                                                <span class="activity-log-change-arrow" aria-label="changed to">→</span>
                                                <span>{{ $formatValue($attributes[$field] ?? null) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="activity-log-empty" colspan="5">
                                <span class="activity-log-empty-icon" aria-hidden="true">◷</span>
                                <strong>No activity recorded yet</strong>
                                <span>Changes to tracked records will appear here.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <nav class="activity-log-pagination" aria-label="Activity log pages">
        {{ $activities->links() }}
    </nav>
</section>
