<article class="metric-card metric-card-primary household-consumption-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-household-consumption-card aria-busy="true">
    <div class="household-consumption-card__top metric-card-top flex min-w-0 items-start">
        <div class="metric-card-heading flex min-w-0 items-center gap-3">
            <span class="metric-card-icon household-consumption-card__icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300" aria-hidden="true">@include('components.ui-icon', ['name' => 'usage'])</span>
            <p class="household-consumption-card__label metric-card-label text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300" data-household-consumption-label>TODAY'S CONSUMPTION</p>
        </div>
    </div>

    <div class="household-consumption-card__value mt-1 text-3xl font-bold text-slate-900 dark:text-white" aria-live="polite">
        <span data-household-consumption-value hidden></span>
        <span class="household-consumption-card__unit text-lg font-semibold text-slate-500 dark:text-slate-400" data-household-consumption-unit hidden>m³</span>
        <span class="household-consumption-card__skeleton block h-10 w-36 max-w-full animate-pulse rounded-lg bg-slate-200/80 dark:bg-slate-700/70" data-household-consumption-skeleton aria-hidden="true"></span>
    </div>

    <p class="household-consumption-card__state mt-1 text-sm text-slate-500 dark:text-slate-400" data-household-consumption-state role="status" aria-live="polite">Loading consumption…</p>

    <div class="household-consumption-selector inline-flex w-full rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800" role="group" aria-label="Consumption period">
        <button type="button" class="flex-1 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold" data-household-consumption-period="daily" aria-pressed="true">Today</button>
        <button type="button" class="flex-1 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold" data-household-consumption-period="weekly" aria-pressed="false">This Week</button>
        <button type="button" class="flex-1 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold" data-household-consumption-period="monthly" aria-pressed="false">This Month</button>
    </div>
</article>
