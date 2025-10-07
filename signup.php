<?php
require __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

if (is_post()) {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        flash('error', 'Please complete all required fields.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please use a valid email address.');
    } elseif ($password !== $confirm) {
        flash('error', 'Your passwords do not match.');
    } else {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $exists->execute(['email' => $email]);
        if ($exists->fetchColumn()) {
            flash('error', 'An account already exists with that email address.');
        } else {
            $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, company, phone, role, created_at, updated_at)
                VALUES (:email, :password_hash, :name, :company, :phone, :role, :created_at, :updated_at)');
            $stmt->execute([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'company' => trim($_POST['company'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'role' => 'client',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $userId = (int) $pdo->lastInsertId();
            $user = $pdo->query('SELECT * FROM users WHERE id = ' . $userId)->fetch();
            login($user);
            flash('success', 'Welcome aboard! Your client portal is ready.');
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'Create your account';
include __DIR__ . '/templates/partials/header.php';
?>
<div class="mx-auto max-w-3xl rounded-lg bg-white p-8 shadow">
    <h1 class="text-2xl font-semibold text-slate-900">Create your client portal account</h1>
    <p class="mt-2 text-sm text-slate-600">Already registered? <a href="login.php" class="text-brand hover:underline">Sign in</a>.</p>
    <form action="" method="post" class="mt-8 grid gap-6 sm:grid-cols-2">
        <label class="text-sm font-medium text-slate-700">Full name
            <input type="text" name="name" required value="<?= e($_POST['name'] ?? ''); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand">
        </label>
        <label class="text-sm font-medium text-slate-700">Company
            <input type="text" name="company" value="<?= e($_POST['company'] ?? ''); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand">
        </label>
        <label class="text-sm font-medium text-slate-700">Email address
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? ''); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand">
        </label>
        <label class="text-sm font-medium text-slate-700">Phone number
            <input type="text" name="phone" value="<?= e($_POST['phone'] ?? ''); ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand">
        </label>
        <label class="text-sm font-medium text-slate-700">Password
            <input type="password" name="password" minlength="8" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand">
        </label>
        <label class="text-sm font-medium text-slate-700">Confirm password
            <input type="password" name="password_confirm" minlength="8" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand">
        </label>
        <div class="sm:col-span-2 flex justify-end gap-3">
            <a href="login.php" class="rounded border border-slate-300 px-4 py-2 text-slate-600 hover:bg-slate-100">Cancel</a>
            <button type="submit" class="rounded bg-brand px-5 py-2 font-medium text-white hover:bg-brand-dark">Create account</button>
        </div>
    </form>
</div>
<?php include __DIR__ . '/templates/partials/footer.php'; ?>
