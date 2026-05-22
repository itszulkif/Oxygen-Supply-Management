<?php

require_once __DIR__ . '/auth.php';

/**
 * @param array{guest?: bool} $options
 */
function render_layout(string $title, string $content, array $options = []): void
{
    $config = app_config();
    $guest = (bool) ($options['guest'] ?? false);
    $module = $_GET['module'] ?? 'dashboard';
    $authUser = (!$guest && auth_user_id() > 0) ? auth_user_row() : null;
    $langTag = i18n_locale();
    $isRtl = i18n_is_rtl();
    $nav = [
        'dashboard' => ['label' => __('nav.dashboard'), 'key' => 'dashboard', 'icon' => 'M3 13h18M3 6h18M3 20h18'],
        'cash' => ['label' => __('nav.cash'), 'key' => 'cash', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'suppliers' => ['label' => __('nav.suppliers'), 'key' => 'suppliers', 'icon' => 'M3 7h18M6 7v13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7M8 7V5a4 4 0 0 1 8 0v2'],
        'customers' => ['label' => __('nav.customers'), 'key' => 'customers', 'icon' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8m8 14v-2a4 4 0 0 0-3-3.87'],
        'services' => ['label' => __('nav.services'), 'key' => 'services', 'icon' => 'M9 12h6M9 16h6M5 3h14a2 2 0 0 1 2 2v14l-4-3H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2'],
        'orders' => ['label' => __('nav.orders'), 'key' => 'orders', 'icon' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2'],
        'ledger' => ['label' => __('nav.ledger'), 'key' => 'ledger', 'icon' => 'M4 5h16M4 10h16M4 15h10M4 20h10'],
        'settings' => ['label' => __('nav.settings'), 'key' => 'settings', 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6m7.4-3a7.4 7.4 0 0 0-.13-1.35l2.11-1.65-2-3.46-2.54 1a7.44 7.44 0 0 0-2.34-1.35l-.38-2.7H9.84l-.38 2.7a7.44 7.44 0 0 0-2.34 1.35l-2.54-1-2 3.46 2.11 1.65A7.4 7.4 0 0 0 4.6 12c0 .46.05.91.13 1.35l-2.11 1.65 2 3.46 2.54-1c.7.58 1.49 1.03 2.34 1.35l.38 2.7h4.32l.38-2.7c.85-.32 1.64-.77 2.34-1.35l2.54 1 2-3.46-2.11-1.65c.08-.44.13-.89.13-1.35'],
    ];
    $langEnUrl = e(i18n_switch_url('en'));
    $langPsUrl = e(i18n_switch_url('ps'));
    ?>
    <!doctype html>
    <html lang="<?= e($langTag) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>" class="<?= $isRtl ? 'lang-ps-root' : '' ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> - <?= e(__('app.name')) ?></title>
        <?php if ($isRtl): ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
        <?php endif; ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.tailwindcss.min.css">
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            oxygen: '#0EA5E9',
                            oxygenDeep: '#0284C7',
                            oxygenAccent: '#22C55E',
                            primary: '#0EA5E9',
                            success: '#22C55E',
                            warning: '#F59E0B',
                            danger: '#EF4444',
                            info: '#0284C7',
                            soft: '#F8FAFC'
                        },
                        boxShadow: {
                            soft: '0 10px 30px -18px rgba(2,132,199,0.4)'
                        },
                    }
                }
            }
        </script>
        <style>
            .lang-ps-root body { font-family: 'Noto Naskh Arabic', 'Segoe UI', system-ui, sans-serif; }
            body { overflow-x: hidden; }
            [data-animate] { animation: pageIn .25s ease-in-out; }
            @keyframes pageIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
            .status-paid { background: #DCFCE7; color: #166534; }
            .status-partial { background: #FEF3C7; color: #92400E; }
            .status-due { background: #FEE2E2; color: #991B1B; }
            .app-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 10px 30px -18px rgba(2,132,199,0.4); }
            .app-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; padding: .55rem .75rem; background: #fff; }
            .app-input:focus { outline: 2px solid rgba(14,165,233,.15); border-color: #0EA5E9; }
            .btn { border-radius: 10px; padding: .5rem .9rem; font-size: .875rem; font-weight: 600; transition: all .2s ease; }
            .btn-primary { background: #0EA5E9; color: #fff; }
            .btn-primary:hover { background: #0284C7; }
            .btn-soft { background: #e0f2fe; color: #0369a1; }
            .btn-soft:hover { background: #bae6fd; }
            .table-wrap table tbody tr { transition: background-color .15s ease-in-out; }
            .table-wrap table tbody tr:hover { background: #f8fafc; }
            .dataTables_wrapper { width: 100%; clear: both; }
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter { margin-bottom: 0.5rem; font-size: 0.875rem; }
            .dataTables_wrapper .dataTables_filter input { border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.35rem 0.65rem; margin-inline-start: 0.35rem; }
            .dataTables_wrapper .dataTables_length select { border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.35rem 0.5rem; margin-inline-start: 0.35rem; margin-inline-end: 0.35rem; }
            .dataTables_wrapper .dt-controls-row { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 0.75rem; }
            @media (min-width: 640px) {
                .dataTables_wrapper .dt-controls-row { flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: wrap; }
            }
            .dataTables_wrapper .dt-footer-row { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e2e8f0; align-items: stretch; }
            @media (min-width: 640px) {
                .dataTables_wrapper .dt-footer-row { flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: wrap; }
            }
            .dataTables_wrapper .dataTables_info { font-size: 0.875rem; color: #64748b; padding: 0.25rem 0; }
            .dataTables_wrapper .dataTables_paginate { font-size: 0.875rem; display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center; justify-content: flex-end; }
            [dir="rtl"] .dataTables_wrapper .dataTables_paginate { justify-content: flex-start; }
            .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 0.5rem !important; padding: 0.25rem 0.6rem !important; margin: 0 !important; display: inline-block; min-width: 2rem; text-align: center; }
            .dataTables_wrapper .dataTables_paginate .paginate_button.current { border-radius: 8px !important; border: 1px solid #0EA5E9 !important; background: #e0f2fe !important; color: #0369a1 !important; }
            .overflow-x-auto { -webkit-overflow-scrolling: touch; }
            @media (max-width: 639px) {
                .dataTables_wrapper .dataTables_filter input,
                .dataTables_wrapper .dataTables_length select {
                    width: 100%;
                    margin-inline-start: 0;
                    margin-top: 0.35rem;
                }
                .dataTables_wrapper .dataTables_paginate {
                    justify-content: flex-start;
                }
            }
            /* RTL (Pashto): sidebar on visual right, mobile drawer slides from right */
            [dir="rtl"] #sidebar {
                left: auto;
                right: 0;
                border-right-width: 0;
                border-left: 1px solid #e2e8f0;
            }
            [dir="rtl"] #sidebar.-translate-x-full {
                transform: translateX(100%);
            }
            @media (min-width: 1024px) {
                [dir="rtl"] #sidebar {
                    transform: none !important;
                }
            }
            [dir="rtl"] main {
                text-align: start;
            }
            [dir="rtl"] .text-left { text-align: start; }
            [dir="rtl"] .text-right { text-align: end; }
        </style>
    </head>
    <body class="bg-soft text-slate-700 min-h-screen antialiased">
        <div class="min-h-screen flex w-full overflow-x-hidden">
            <?php if (!$guest): ?>
            <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 z-30 hidden lg:hidden"></div>
            <aside id="sidebar"
                   class="fixed lg:static inset-y-0 left-0 z-40 w-72 max-w-[85vw] bg-white border-r border-slate-200 p-5 transform -translate-x-full lg:translate-x-0 transition-transform duration-200 overflow-y-auto">
                <div class="mb-6 border-b border-slate-100 pb-4">
                    <h1 class="text-xl font-bold text-oxygenDeep"><?= e(__('app.name')) ?></h1>
                    <p class="text-xs text-slate-500"><?= e(__('app.tagline')) ?></p>
                    <div class="mt-3 flex gap-2 text-xs">
                        <a href="<?= $langEnUrl ?>" class="<?= $langTag === 'en' ? 'font-bold text-oxygenDeep' : 'text-slate-500' ?>"><?= e(__('lang.en')) ?></a>
                        <span class="text-slate-300">|</span>
                        <a href="<?= $langPsUrl ?>" class="<?= $langTag === 'ps' ? 'font-bold text-oxygenDeep' : 'text-slate-500' ?>"><?= e(__('lang.ps')) ?></a>
                    </div>
                </div>
                <nav class="space-y-1">
                    <?php foreach ($nav as $item): $key = $item['key']; ?>
                        <a href="?module=<?= e($key) ?><?= i18n_lang_query() ?>"
                           class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition <?= ($module === $key || ($key === 'cash' && $module === 'cash_log')) ? 'bg-sky-100 text-oxygenDeep font-semibold' : 'text-slate-600 hover:bg-slate-100' ?>">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= e($item['icon']) ?>"></path></svg>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>
            <?php endif; ?>
            <main class="flex-1 min-w-0 w-full p-4 sm:p-6 lg:p-6 <?= $guest ? 'max-w-lg mx-auto w-full flex flex-col justify-center' : '' ?>" data-animate>
                <?php if ($guest): ?>
                <div class="mb-6 flex justify-center gap-3 text-sm">
                    <a href="<?= $langEnUrl ?>" class="<?= $langTag === 'en' ? 'font-bold text-oxygenDeep' : 'text-slate-500' ?>"><?= e(__('lang.en')) ?></a>
                    <span class="text-slate-300">|</span>
                    <a href="<?= $langPsUrl ?>" class="<?= $langTag === 'ps' ? 'font-bold text-oxygenDeep' : 'text-slate-500' ?>"><?= e(__('lang.ps')) ?></a>
                </div>
                <div class="mb-8 text-center">
                    <h1 class="text-2xl font-bold text-oxygenDeep"><?= e(__('app.name')) ?></h1>
                    <p class="text-sm text-slate-500"><?= e(__('app.tagline')) ?></p>
                </div>
                <?= $content ?>
                <?php else: ?>
                <header class="mb-6 app-card px-4 py-3 sm:px-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <button id="menuToggle" type="button" class="inline-flex lg:hidden items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                            <?= e(__('layout.menu')) ?>
                            </button>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-400"><?= e(__('layout.operations')) ?></p>
                                <h2 class="text-lg sm:text-xl font-semibold text-oxygenDeep"><?= e($title) ?></h2>
                            </div>
                        </div>
                        <div class="flex w-full sm:w-auto flex-wrap items-center gap-2 justify-start sm:justify-end">
                            <div class="hidden md:flex items-center gap-1 text-xs border border-slate-200 rounded-lg px-2 py-1">
                                <a href="<?= $langEnUrl ?>" class="<?= $langTag === 'en' ? 'font-bold text-oxygenDeep' : 'text-slate-500' ?>"><?= e(__('lang.en')) ?></a>
                                <span class="text-slate-300">|</span>
                                <a href="<?= $langPsUrl ?>" class="<?= $langTag === 'ps' ? 'font-bold text-oxygenDeep' : 'text-slate-500' ?>"><?= e(__('lang.ps')) ?></a>
                            </div>
                            <?php if ($authUser): ?>
                                <span class="text-sm text-slate-600 hidden sm:inline"><span class="text-slate-400"><?= e(__('layout.signed_in_as')) ?></span> <strong class="text-slate-800"><?= e((string) ($authUser['username'] ?? '')) ?></strong></span>
                                <a href="?module=settings#profile<?= i18n_lang_query() ?>" class="btn btn-soft text-sm w-full sm:w-auto"><?= e(__('layout.profile')) ?></a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-soft w-full sm:w-auto" data-open-modal="quickAddModal"><?= e(__('layout.quick_add')) ?></button>
                            <a href="?module=dashboard<?= i18n_lang_query() ?>" class="btn btn-primary w-full sm:w-auto"><?= e(__('layout.overview')) ?></a>
                            <a href="?module=logout<?= i18n_lang_query() ?>" class="btn btn-soft text-sm text-rose-700 bg-rose-50 border border-rose-100 w-full sm:w-auto"><?= e(__('layout.log_out')) ?></a>
                        </div>
                    </div>
                </header>
                <?= $content ?>
                <?php endif; ?>
            </main>
        </div>
        <?php if (!$guest): ?>
        <div id="quickAddModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" data-close-modal="quickAddModal"></div>
            <div class="relative w-full max-w-lg app-card p-5">
                <h3 class="text-lg font-semibold text-oxygenDeep mb-2"><?= e(__('layout.quick_action')) ?></h3>
                <p class="text-sm text-slate-500 mb-4"><?= e(__('layout.quick_action_hint')) ?></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <a class="btn btn-soft text-center" href="?module=suppliers<?= i18n_lang_query() ?>"><?= e(__('layout.add_supplier')) ?></a>
                    <a class="btn btn-soft text-center" href="?module=customers<?= i18n_lang_query() ?>"><?= e(__('layout.add_customer')) ?></a>
                    <a class="btn btn-soft text-center" href="?module=services<?= i18n_lang_query() ?>"><?= e(__('layout.new_service')) ?></a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div id="toast" class="fixed bottom-4 <?= $isRtl ? 'left-4' : 'right-4' ?> hidden rounded-lg bg-slate-900 px-4 py-3 text-sm text-white shadow-lg z-50"></div>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.8/js/dataTables.tailwindcss.min.js"></script>
        <script>
            const i18nConfirmDefault = <?= json_encode(__('layout.confirm_default'), JSON_UNESCAPED_UNICODE) ?>;
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const toast = document.getElementById('toast');
            const closeMenu = () => {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            };
            const openMenu = () => {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('hidden');
            };
            if (menuToggle && sidebar && sidebarOverlay) {
                menuToggle.addEventListener('click', openMenu);
                sidebarOverlay.addEventListener('click', closeMenu);
                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 1024) {
                        sidebarOverlay.classList.add('hidden');
                    }
                });
            }

            document.querySelectorAll('[data-confirm]').forEach((element) => {
                element.addEventListener('click', (event) => {
                    if (!confirm(element.getAttribute('data-confirm') || i18nConfirmDefault)) {
                        event.preventDefault();
                    }
                });
            });

            const url = new URL(window.location.href);
            const toastMsg = url.searchParams.get('toast');
            if (toast && toastMsg) {
                toast.textContent = toastMsg;
                toast.classList.remove('hidden');
                setTimeout(() => toast.classList.add('hidden'), 2500);
            }

            document.querySelectorAll('.overflow-x-auto').forEach((wrap) => wrap.classList.add('table-wrap'));

            const dtLang = <?= json_encode([
                'decimal' => '',
                'thousands' => ',',
                'lengthMenu' => __('dt.length_menu'),
                'search' => __('dt.search'),
                'info' => __('dt.info'),
                'infoEmpty' => __('dt.info_empty'),
                'infoFiltered' => __('dt.info_filtered'),
                'zeroRecords' => __('dt.zero_records'),
                'emptyTable' => __('dt.empty'),
                'paginate' => [
                    'first' => __('dt.paginate.first'),
                    'last' => __('dt.paginate.last'),
                    'next' => __('dt.paginate.next'),
                    'previous' => __('dt.paginate.prev'),
                ],
            ], JSON_UNESCAPED_UNICODE) ?>;

            if (window.jQuery && $.fn.DataTable) {
                document.querySelectorAll('table[data-sortable="true"]').forEach((table) => {
                    if (table.dataset.dtInit === '1') return;
                    const firstBodyRow = table.querySelector('tbody tr');
                    const hasPlaceholderColspan =
                        firstBodyRow &&
                        firstBodyRow.children.length === 1 &&
                        firstBodyRow.querySelector('td[colspan]');
                    if (hasPlaceholderColspan) return;
                    table.dataset.dtInit = '1';
                    $(table).DataTable({
                        paging: true,
                        searching: true,
                        info: true,
                        pageLength: 10,
                        lengthChange: true,
                        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                        order: [],
                        language: dtLang,
                        dom: "<'dt-controls-row'lf>rt<'dt-footer-row'ip>",
                        autoWidth: false
                    });
                });
            }

            document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    const modal = document.getElementById(trigger.dataset.openModal || '');
                    if (!modal) return;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });
            document.querySelectorAll('[data-close-modal]').forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    const modal = document.getElementById(trigger.dataset.closeModal || '');
                    if (!modal) return;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                });
            });

            window.OxygenFinance = (() => {
                const channel = typeof BroadcastChannel !== 'undefined'
                    ? new BroadcastChannel('afghan-oxygen-finance')
                    : null;
                const notify = (detail) => {
                    if (channel) {
                        channel.postMessage(detail);
                    }
                    window.dispatchEvent(new CustomEvent('oxygen:finance-updated', { detail }));
                };
                const onUpdated = (fn) => {
                    window.addEventListener('oxygen:finance-updated', (event) => fn(event.detail));
                    if (channel) {
                        channel.onmessage = (event) => fn(event.data);
                    }
                };
                return { notify, onUpdated };
            })();

            window.App = window.App || {};
            window.App.ajaxForm = (form, options = {}) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const body = new FormData(form);
                    const response = await fetch(form.action || window.location.href, { method: form.method || 'POST', body });
                    if (options.onSuccess) options.onSuccess(response);
                });
            };
        </script>
    </body>
    </html>
    <?php
}
