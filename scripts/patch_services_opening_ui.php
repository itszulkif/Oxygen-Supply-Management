<?php

$path = __DIR__ . '/../modules/services.php';
$content = file_get_contents($path);
$marker = '        <motion.div id="customerCylinderBalance" class="hidden rounded-lg border px-3 py-3 text-sm" role="status" aria-live="polite"></motion.div>';
$marker = '        <div id="customerCylinderBalance" class="hidden rounded-lg border px-3 py-3 text-sm" role="status" aria-live="polite"></div>';
$section = <<<'HTML'
        <section id="customerOpeningSection" class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3">
            <h4 class="text-sm font-semibold text-slate-800"><?= e(__('services.customer_opening_section')) ?></h4>
            <div id="quickAddCustomerBlock" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <label class="block sm:col-span-2"><span class="text-xs text-slate-600"><?= e(__('services.new_customer_name')) ?></span><input type="text" id="quickCustomerName" class="border rounded-lg p-2 w-full"></label>
                <label class="block"><span class="text-xs text-slate-600"><?= e(__('services.new_customer_phone')) ?></span><input type="text" id="quickCustomerPhone" class="border rounded-lg p-2 w-full"></label>
                <label class="block"><span class="text-xs text-slate-600"><?= e(__('customers.opening_balance')) ?></span><input type="number" id="openingBalanceField" step="0.01" min="0" class="border rounded-lg p-2 w-full" placeholder="<?= e(__('customers.ph_opening_balance')) ?>"></label>
                <label class="block"><span class="text-xs text-slate-600"><?= e(__('customers.opening_cylinders')) ?></span><input type="number" id="openingCylindersField" step="1" min="0" class="border rounded-lg p-2 w-full" placeholder="<?= e(__('customers.ph_opening_cylinders')) ?>"></label>
                <motion.div class="sm:col-span-2 lg:col-span-4 flex flex-wrap gap-2"><button type="button" id="saveQuickCustomerBtn" class="bg-slate-800 text-white rounded-lg px-3 py-2 text-sm"><?= e(__('services.save_new_customer')) ?></button><p id="quickCustomerMessage" class="text-sm text-slate-600 self-center hidden" role="status"></p></motion.div>
            </motion.div>
            <div id="selectedCustomerOpening" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                <div><span class="text-slate-500"><?= e(__('customers.opening_balance')) ?>:</span> <strong id="dispOpeningBalance">—</strong></div>
                <div><span class="text-slate-500"><?= e(__('customers.opening_cylinders')) ?>:</span> <strong id="dispOpeningCylinders">—</strong></div>
                <div><span class="text-slate-500"><?= e(__('ledger.net_receivable')) ?>:</span> <strong id="dispReceivable" class="text-amber-700">—</strong></div>
                <div><span class="text-slate-500"><?= e(__('ledger.cylinders_owed_now')) ?>:</span> <strong id="dispCylindersOwed" class="text-amber-700">—</strong></motion.div>
            </motion.div>
        </section>
        <div id="customerCylinderBalance" class="hidden rounded-lg border px-3 py-3 text-sm" role="status" aria-live="polite"></div>
HTML;
$section = str_replace(['<motion.div', '</motion.div>'], ['<div', '</div>'], $section);

if (strpos($content, 'customerOpeningSection') !== false) {
    echo "Already patched\n";
    exit(0);
}
if (strpos($content, $marker) === false) {
    fwrite(STDERR, "Marker not found\n");
    exit(1);
}
$content = str_replace($marker, $section, $content);
file_put_contents($path, $content);
echo "Patched services.php UI\n";
