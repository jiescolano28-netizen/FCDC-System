<section class="card" id="completed-demo-sales" style="margin-top:16px;" aria-label="Completed demonstration sales">
    <h2 class="card-title">Completed demonstration sales</h2>
    <p class="print-note">Session-only demo activity. These details are not recorded business sales; the chart above aggregates their totals.</p>

    @forelse ($sales as $sale)
        <article class="list-row" style="display:block;">
            <strong>Demo {{ $sale['id'] }} · {{ $sale['date'] }} · {{ $sale['items'] }} item(s) · ₱{{ number_format($sale['total'], 2) }}</strong>
            <ul style="margin:8px 0 0; padding-left:20px;">
                @foreach ($sale['lines'] as $line)
                    <li>{{ $line['quantity'] }} × {{ $line['name'] }} · ₱{{ number_format($line['sellingPrice'], 2) }} each</li>
                @endforeach
            </ul>
        </article>
    @empty
        <p class="muted">No completed demo sales in this session.</p>
    @endforelse
</section>
