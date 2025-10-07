<?php
$brandName = get_setting('company_name', 'Service Portal');
$logo = get_setting('brand_logo_url', '');
$navItems = $portalNav ?? [];
$user = current_user();
$displayName = $user['name'] ?? $user['email'] ?? null;
$initials = null;
if ($displayName) {
    $slice = function_exists('mb_substr') ? mb_substr($displayName, 0, 2) : substr($displayName, 0, 2);
    $initials = strtoupper($slice);
}
$brandInitials = strtoupper(function_exists('mb_substr') ? mb_substr($brandName, 0, 2) : substr($brandName, 0, 2));
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
<body class="min-h-full bg-transparent font-[var(--brand-font)] text-slate-800">
<div class="min-h-screen">
    <header class="portal-header">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-6 py-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <?php if ($logo): ?>
                    <span class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border border-white/20 bg-white/10 backdrop-blur"><img src="<?= e($logo); ?>" alt="<?= e($brandName); ?> logo" class="h-full w-full object-cover"></span>
                <?php else: ?>
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-lg font-semibold uppercase tracking-wide"><?= $brandInitials; ?></span>
                <?php endif; ?>
                <div>
                    <p class="text-2xl font-semibold tracking-tight text-white"><?= e($brandName); ?></p>
                    <p class="text-sm font-medium uppercase tracking-[0.28em] text-white/70">Client Portal</p>
                </div>
            </div>
            <div class="flex flex-1 flex-col gap-4 lg:flex-row lg:items-center lg:justify-end">
                <?php if ($user): ?>
                    <div class="flex items-center justify-between gap-4">
                        <div class="hidden text-right text-xs font-semibold uppercase tracking-[0.32em] text-white/60 sm:block">Signed in as</div>
                        <?php if ($initials): ?>
                            <div class="portal-avatar shadow-inner shadow-black/10"><?= e($initials); ?></div>
                        <?php endif; ?>
                        <div class="flex flex-col text-white">
                            <span class="text-sm font-semibold tracking-wide text-white/95"><?= e($displayName ?? ''); ?></span>
                            <span class="text-xs uppercase tracking-[0.35em] text-white/60"><?= $user['role'] === 'admin' ? 'Administrator' : 'Client'; ?></span>
                        </div>
                        <div class="hidden h-10 w-px bg-white/20 lg:block"></div>
                        <div class="flex items-center gap-2">
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="dashboard.php" class="rounded-full border border-white/30 px-4 py-2 text-sm font-semibold text-white/90 backdrop-blur transition hover:bg-white/10 hover:text-white">Admin view</a>
                            <?php else: ?>
                                <a href="dashboard.php" class="rounded-full border border-white/30 px-4 py-2 text-sm font-semibold text-white/90 backdrop-blur transition hover:bg-white/10 hover:text-white">Dashboard</a>
                            <?php endif; ?>
                            <a href="logout.php" class="rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-black/10 backdrop-blur transition hover:bg-white/25">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <nav class="flex items-center gap-3 text-sm">
                        <a href="login.php" class="rounded-full border border-white/30 px-4 py-2 font-semibold text-white/85 transition hover:bg-white/10 hover:text-white">Login</a>
                        <a href="signup.php" class="btn-primary">Sign up</a>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($navItems)): ?>
            <div class="portal-header__nav">
                <div class="mx-auto max-w-7xl px-6">
                    <nav class="portal-nav">
                        <?php foreach ($navItems as $item): ?>
                            <a href="<?= e($item['href']); ?>" class="portal-nav-link">
                                <span><?= e($item['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    </header>
    <main class="portal-main mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="portal-content">
            <?php if ($message = flash('success')): ?>
                <div class="mb-6 flash flash-success"><?= e($message); ?></div>
            <?php endif; ?>
            <?php if ($error = flash('error')): ?>
                <div class="mb-6 flash flash-error"><?= e($error); ?></div>
            <?php endif; ?>
            <div class="space-y-10">
