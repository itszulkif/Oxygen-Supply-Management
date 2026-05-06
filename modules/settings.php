<?php

$pdo = db();
$hasSettingsTable = table_exists($pdo, 'settings');
$settings = [];
if ($hasSettingsTable) {
    $settings = $pdo->query('SELECT * FROM settings ORDER BY id ASC LIMIT 1')->fetch() ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $newPwRaw = trim((string) ($_POST['new_password'] ?? ''));
    $err = auth_update_profile(
        auth_user_id(),
        request_value('profile_name'),
        request_value('profile_username'),
        request_value('profile_email'),
        request_value('current_password'),
        $newPwRaw !== '' ? $newPwRaw : null
    );
    if ($err !== '') {
        header('Location: ?module=settings&toast=' . urlencode($err) . i18n_lang_query() . '#profile');
    } else {
        header('Location: ?module=settings&toast=' . urlencode(__('toast.profile_saved')) . i18n_lang_query() . '#profile');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasSettingsTable && isset($_POST['save_company'])) {
    $companyName = request_value('company_name');
    $phone = request_value('company_phone');
    $email = request_value('company_email');
    $address = request_value('company_address');

    if ($settings) {
        $stmt = $pdo->prepare(
            'UPDATE settings SET company_name = ?, company_phone = ?, company_email = ?, company_address = ? WHERE id = ?'
        );
        $stmt->execute([$companyName, $phone, $email, $address, (int) $settings['id']]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO settings (company_name, company_phone, company_email, company_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$companyName, $phone, $email, $address]);
    }
    redirect_to('settings');
}

if ($hasSettingsTable) {
    $settings = $pdo->query('SELECT * FROM settings ORDER BY id ASC LIMIT 1')->fetch() ?: [];
}

$profileUser = auth_user_row();

ob_start();
?>
<section id="profile" class="max-w-3xl bg-white border border-slate-200 rounded-xl p-5 mb-6 scroll-mt-24">
    <h3 class="font-semibold text-slate-700 mb-3"><?= e(__('settings.profile_title')) ?></h3>
    <p class="text-sm text-slate-500 mb-4"><?= e(__('settings.profile_hint')) ?></p>
    <?php if ($profileUser): ?>
    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="hidden" name="save_profile" value="1">
        <label class="block text-sm md:col-span-2"><span class="text-slate-600"><?= e(__('settings.full_name')) ?></span><input name="profile_name" required class="border rounded-lg p-2 w-full mt-1" value="<?= e((string) ($profileUser['name'] ?? '')) ?>"></label>
        <label class="block text-sm"><span class="text-slate-600"><?= e(__('settings.username')) ?></span><input name="profile_username" required class="border rounded-lg p-2 w-full mt-1" pattern="[a-zA-Z0-9._\-]{3,60}" title="<?= e(__('settings.username_pattern_title')) ?>" value="<?= e((string) ($profileUser['username'] ?? '')) ?>"></label>
        <label class="block text-sm"><span class="text-slate-600"><?= e(__('settings.email')) ?></span><input name="profile_email" type="email" required class="border rounded-lg p-2 w-full mt-1" value="<?= e((string) ($profileUser['email'] ?? '')) ?>"></label>
        <label class="block text-sm md:col-span-2"><span class="text-slate-600"><?= e(__('settings.current_password')) ?></span><input name="current_password" type="password" required class="border rounded-lg p-2 w-full mt-1" autocomplete="current-password"></label>
        <label class="block text-sm"><span class="text-slate-600"><?= e(__('settings.new_password_opt')) ?></span><input name="new_password" type="password" minlength="8" class="border rounded-lg p-2 w-full mt-1" autocomplete="new-password" placeholder="<?= e(__('settings.placeholder_keep_password')) ?>"></label>
        <div class="md:col-span-2">
            <button type="submit" class="bg-primary text-white rounded-lg py-2 px-4 text-sm"><?= e(__('settings.save_profile')) ?></button>
        </div>
    </form>
    <?php endif; ?>
</section>
<section class="max-w-2xl bg-white border border-slate-200 rounded-xl p-5">
    <h3 class="font-semibold mb-4"><?= e(__('settings.company_title')) ?></h3>
    <?php if (!$hasSettingsTable): ?>
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
            <?= e(__('settings.company_missing')) ?>
        </p>
    <?php else: ?>
        <form method="post" class="space-y-3">
            <input type="hidden" name="save_company" value="1">
            <input name="company_name" required placeholder="<?= e(__('settings.company_name')) ?>" value="<?= e((string) ($settings['company_name'] ?? '')) ?>" class="w-full border rounded-lg p-2">
            <input name="company_phone" placeholder="<?= e(__('settings.phone')) ?>" value="<?= e((string) ($settings['company_phone'] ?? '')) ?>" class="w-full border rounded-lg p-2">
            <input name="company_email" placeholder="<?= e(__('settings.email')) ?>" value="<?= e((string) ($settings['company_email'] ?? '')) ?>" class="w-full border rounded-lg p-2">
            <textarea name="company_address" placeholder="<?= e(__('settings.address')) ?>" class="w-full border rounded-lg p-2"><?= e((string) ($settings['company_address'] ?? '')) ?></textarea>
            <button class="bg-oxygen text-white px-5 py-2 rounded-lg"><?= e(__('settings.save_company')) ?></button>
        </form>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
render_layout(__('meta.settings'), $content);
