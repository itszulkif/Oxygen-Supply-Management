<?php

declare(strict_types=1);

if (auth_user_id() > 0) {
    header('Location: ?module=' . auth_safe_return_module((string) ($_GET['return'] ?? 'dashboard')) . i18n_lang_query());
    exit;
}

$pdo = db();
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$setupMode = $userCount === 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($setupMode) {
        $err = auth_create_first_admin(
            request_value('name'),
            request_value('username'),
            request_value('email'),
            request_value('password')
        );
        if ($err !== '') {
            $error = $err;
        } else {
            header('Location: ?module=' . auth_safe_return_module('dashboard') . i18n_lang_query());
            exit;
        }
    } else {
        $login = request_value('username');
        $password = request_value('password');
        if (auth_attempt_login($login, $password)) {
            header('Location: ?module=' . auth_safe_return_module((string) ($_GET['return'] ?? 'dashboard')) . i18n_lang_query());
            exit;
        }
        $error = __('login.error_invalid');
    }
}

ob_start();
?>
<div class="app-card p-6 shadow-soft">
    <?php if ($setupMode): ?>
        <h2 class="text-lg font-semibold text-oxygenDeep mb-1"><?= e(__('login.create_admin')) ?></h2>
        <p class="text-sm text-slate-500 mb-4"><?= e(__('login.setup_hint')) ?></p>
        <?php if ($error !== ''): ?><p class="mb-3 text-sm text-rose-600"><?= e($error) ?></p><?php endif; ?>
        <form method="post" class="space-y-3">
            <label class="block text-sm"><span class="text-slate-600"><?= e(__('login.full_name')) ?></span><input name="name" required class="app-input mt-1" autocomplete="name" value="<?= e(request_value('name')) ?>"></label>
            <label class="block text-sm"><span class="text-slate-600"><?= e(__('login.username')) ?></span><input name="username" required class="app-input mt-1" autocomplete="username" value="<?= e(request_value('username')) ?>"></label>
            <label class="block text-sm"><span class="text-slate-600"><?= e(__('login.email')) ?></span><input name="email" type="email" required class="app-input mt-1" autocomplete="email" value="<?= e(request_value('email')) ?>"></label>
            <label class="block text-sm"><span class="text-slate-600"><?= e(__('login.password')) ?></span><input name="password" type="password" required minlength="8" class="app-input mt-1" autocomplete="new-password"></label>
            <button type="submit" class="btn btn-primary w-full"><?= e(__('login.create_continue')) ?></button>
        </form>
    <?php else: ?>
        <h2 class="text-lg font-semibold text-oxygenDeep mb-1"><?= e(__('login.sign_in')) ?></h2>
        <p class="text-sm text-slate-500 mb-4"><?= e(__('login.sign_in_hint')) ?></p>
        <?php if ($error !== ''): ?><p class="mb-3 text-sm text-rose-600"><?= e($error) ?></p><?php endif; ?>
        <form method="post" class="space-y-3">
            <label class="block text-sm"><span class="text-slate-600"><?= e(__('login.username_or_email')) ?></span><input name="username" required class="app-input mt-1" autocomplete="username" value="<?= e(request_value('username')) ?>"></label>
            <label class="block text-sm"><span class="text-slate-600"><?= e(__('login.password')) ?></span><input name="password" type="password" required class="app-input mt-1" autocomplete="current-password"></label>
            <button type="submit" class="btn btn-primary w-full"><?= e(__('login.submit')) ?></button>
        </form>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
render_layout($setupMode ? __('meta.setup') : __('meta.login'), $content, ['guest' => true]);
