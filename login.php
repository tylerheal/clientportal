<?php
require __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

if (is_post()) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        flash('error', 'Please enter both your email and password.');
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('error', 'Incorrect email or password.');
        } else {
            login($user);
            flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'Login';
include __DIR__ . '/templates/partials/header.php';
?>
<div class="mx-auto max-w-lg rounded-lg bg-white p-8 shadow">
    <h1 class="text-2xl font-semibold text-slate-900">Sign in to your account</h1>
    <p class="mt-2 text-sm text-slate-600">Don't have an account? <a href="signup.php" class="text-brand hover:underline">Create one</a>.</p>
    <form action="" method="post" class="mt-8 space-y-5">
        <label class="block text-sm font-medium text-slate-700">
            Email address
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? ''); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand">
        </label>
        <label class="block text-sm font-medium text-slate-700">
            Password
            <input type="password" name="password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand">
        </label>
        <button type="submit" class="w-full rounded bg-brand px-4 py-2 font-medium text-white hover:bg-brand-dark">Sign in</button>
    </form>
</div>
<?php include __DIR__ . '/templates/partials/footer.php'; ?>
