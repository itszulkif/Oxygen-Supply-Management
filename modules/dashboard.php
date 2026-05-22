<?php

declare(strict_types=1);

$pdo = db();
ensure_cylinder_daka_tash_columns($pdo);

$langQ = i18n_lang_query();
$todayYmd = date('Y-m-d');

$todayReceived = financial_net_received_for_range($pdo, $todayYmd, $todayYmd);

$supplierPending = supplier_global_pending_payments($pdo);

$stockTotals = cylinder_daka_tash_totals($pdo);
$stockDaka = (int) ($stockTotals['daka'] ?? 0);
$stockTash = (int) ($stockTotals['tash'] ?? 0);
$stockTotal = $stockDaka + $stockTash;

$customerCount = table_exists($pdo, 'customers')
    ? (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn()
    : 0;

ob_start();
?>
<div class="max-w-7xl mx-auto space-y-6 sm:space-y-8 pb-8">
    <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900"><?= e(__('dashboard.home_title')) ?></h1>
            <p class="text-sm text-slate-500 mt-1 max-w-2xl"><?= e(__('dashboard.home_intro')) ?></p>
            <p class="text-xs text-slate-400 mt-2"><?= e(__('dashboard.as_of_today', ['date' => format_date_pk($todayYmd)])) ?></p>
        </div>
        <a href="?module=cash<?= $langQ ?>" class="btn btn-soft text-sm w-full sm:w-auto text-center shrink-0"><?= e(__('cash.open_log')) ?></a>
    </header>

    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5" aria-label="<?= e(__('dashboard.section_key_figures')) ?>">
        <a href="?module=cash<?= $langQ ?>" class="group rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-5 sm:p-6 shadow-sm transition hover:shadow-md hover:border-emerald-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
            <div class="flex items-start justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700"><?= e(__('dashboard.kpi_today_received')) ?></p>
                <span class="shrink-0 rounded-lg bg-emerald-100 p-2 text-emerald-700" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                </span>
            </div>
            <p id="dashTodayReceived" class="text-2xl sm:text-3xl font-bold text-emerald-800 mt-3 tabular-nums break-all"><?= e(format_currency($todayReceived)) ?></p>
            <p class="text-xs text-emerald-600/90 mt-2"><?= e(__('dashboard.kpi_today_received_sub')) ?></p>
        </a>

        <a href="?module=suppliers<?= $langQ ?>" class="group rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50/80 to-white p-5 sm:p-6 shadow-sm transition hover:shadow-md hover:border-rose-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400">
            <div class="flex items-start justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700"><?= e(__('dashboard.kpi_supplier_pending')) ?></p>
                <span class="shrink-0 rounded-lg bg-rose-100 p-2 text-rose-700" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                </span>
            </div>
            <p id="dashSupplierPending" class="text-2xl sm:text-3xl font-bold text-rose-700 mt-3 tabular-nums break-all"><?= e(format_currency($supplierPending)) ?></p>
            <p class="text-xs text-rose-600/90 mt-2"><?= e(__('dashboard.kpi_supplier_pending_sub')) ?></p>
        </a>

        <a href="?module=suppliers<?= $langQ ?>" class="group rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50/80 to-white p-5 sm:p-6 shadow-sm transition hover:shadow-md hover:border-sky-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400">
            <div class="flex items-start justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-800"><?= e(__('dashboard.kpi_inventory_stock')) ?></p>
                <span class="shrink-0 rounded-lg bg-sky-100 p-2 text-sky-700" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </span>
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-sky-900 mt-3 tabular-nums"><?= (int) $stockTotal ?></p>
            <p class="text-xs text-slate-500 mt-1"><?= e(__('dashboard.kpi_inventory_stock_sub')) ?></p>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1.5 text-sm font-semibold text-emerald-900">
                    <?= e(__('suppliers.stock_daka')) ?>
                    <span class="tabular-nums"><?= $stockDaka ?></span>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-950">
                    <?= e(__('suppliers.stock_tash')) ?>
                    <span class="tabular-nums"><?= $stockTash ?></span>
                </span>
            </div>
        </a>

        <a href="?module=customers<?= $langQ ?>" class="group rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition hover:shadow-md hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
            <div class="flex items-start justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600"><?= e(__('dashboard.total_customers')) ?></p>
                <span class="shrink-0 rounded-lg bg-slate-100 p-2 text-slate-600" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-slate-900 mt-3 tabular-nums"><?= $customerCount ?></p>
            <p class="text-xs text-slate-500 mt-2"><?= e(__('dashboard.kpi_customers_sub')) ?></p>
        </a>
    </section>
</div>
<script>
(() => {
    if (!window.OxygenFinance?.onUpdated) return;
    const todayEl = document.getElementById('dashTodayReceived');
    const supplierPendingEl = document.getElementById('dashSupplierPending');
    if (!todayEl && !supplierPendingEl) return;
    window.OxygenFinance.onUpdated(async () => {
        try {
            const u = new URL(window.location.href);
            u.searchParams.set('module', 'ledger');
            u.searchParams.set('ajax', 'finance_pulse');
            const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!data.ok || !data.pulse) return;
            if (todayEl && data.pulse.today_received_formatted) {
                todayEl.textContent = data.pulse.today_received_formatted;
            }
            if (supplierPendingEl && data.pulse.supplier_pending_formatted) {
                supplierPendingEl.textContent = data.pulse.supplier_pending_formatted;
            }
        } catch (_) { /* ignore */ }
    });
})();
</script>
<?php
$content = ob_get_clean();
render_layout(__('meta.dashboard'), $content);
