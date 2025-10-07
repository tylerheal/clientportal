<?php
$brandName = get_setting('company_name', 'Service Portal');
$logo = get_setting('brand_logo_url', '');
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? $brandName); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css">
    <link rel="stylesheet" href="static/css/style.css">
    <style><?= theme_styles(); ?></style>
</head>
<body class="min-h-full bg-slate-100 font-[var(--brand-font)] text-slate-800">
<header class="bg-white shadow-sm">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <div class="flex items-center gap-3">
            <?php if ($logo): ?>
                <img src="<?= e($logo); ?>" alt="<?= e($brandName); ?> logo" class="h-10 w-10 rounded object-cover">
            <?php endif; ?>
            <span class="text-xl font-semibold text-slate-900"><?= e($brandName); ?></span>
        </div>
        <?php if ($user = current_user()): ?>
            <nav class="flex items-center gap-4 text-sm">
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="dashboard.php" class="text-slate-600 hover:text-brand">Admin</a>
                <?php else: ?>
                    <a href="dashboard.php" class="text-slate-600 hover:text-brand">Dashboard</a>
                <?php endif; ?>
                <a href="logout.php" class="inline-flex items-center rounded bg-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-300">Logout</a>
            </nav>
        <?php else: ?>
            <nav class="flex items-center gap-3 text-sm">
                <a href="login.php" class="text-slate-600 hover:text-brand">Login</a>
                <a href="signup.php" class="rounded bg-brand px-4 py-2 font-medium text-white shadow hover:bg-brand-dark">Sign up</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
<main class="mx-auto w-full max-w-6xl px-6 py-10">
<?php if ($message = flash('success')): ?>
    <div class="mb-6 rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800"><?= e($message); ?></div>
<?php endif; ?>
<?php if ($error = flash('error')): ?>
    <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($error); ?></div>
<?php endif; ?>
