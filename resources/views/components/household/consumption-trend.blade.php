<article class="panel chart-panel household-usage-panel" data-household-trend-card>
    <div class="panel-head household-trend-head">
        <div class="household-trend-heading">
            <p class="eyebrow">Usage overview</p>
            <h2>Water Consumption Trend</h2>
            <p class="household-trend-description">Track how your household water usage changes over time.</p>
        </div>
        <div class="household-trend-actions">
            <div class="household-trend-selector" role="group" aria-label="Water consumption trend period">
                <button type="button" data-household-trend-period="daily" aria-pressed="false">Daily</button>
                <button type="button" data-household-trend-period="weekly" aria-pressed="false">Weekly</button>
                <button type="button" class="is-active" data-household-trend-period="monthly" aria-pressed="true">Monthly</button>
            </div>
            <a class="household-trend-history-link" href="{{ route('household.page') }}#history">View full consumption history <span aria-hidden="true">→</span></a>
        </div>
    </div>

    <div class="household-trend-summary" data-household-trend-summary hidden aria-label="Consumption trend summary">
        <div><span data-household-trend-current-label>Current month</span><strong data-household-trend-current>--</strong></div>
        <div><span data-household-trend-previous-label>Previous month</span><strong data-household-trend-previous>--</strong></div>
        <div><span>Change</span><strong data-household-trend-change>--</strong></div>
        <div><span>Average</span><strong data-household-trend-average>--</strong></div>
    </div>
    <p class="household-trend-text" data-household-trend-text role="status" aria-live="polite" hidden></p>

    <div class="household-usage-content is-loading" data-household-usage-content>
        <div class="chart-legend" data-household-trend-legend>
            <span><i class="legend-dot usage-dot"></i>Consumption (m³)</span>
        </div>
        <div class="usage-chart" data-household-monthly-chart data-household-trend-chart>
            <div class="loading-skeleton-card" aria-hidden="true">
                <span class="loading-skeleton-line loading-skeleton-line-lg"></span>
                <span class="loading-skeleton-line"></span>
                <span class="loading-skeleton-line"></span>
                <span class="loading-skeleton-line"></span>
            </div>
        </div>
    </div>
    <div class="household-trend-state" data-household-trend-state role="status" aria-live="polite" hidden></div>
</article>
