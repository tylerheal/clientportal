<section class="page-section">
    <?php foreach (['error', 'success'] as $flashType): ?>
        <?php if ($message = flash($flashType)): ?>
            <div class="alert alert--<?= $flashType; ?>"><?= e($message); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="page-columns">
        <article class="card column">
            <header class="card-header">
                <div>
                    <h2>Add administrator</h2>
                    <p>Create a new admin account with dashboard access.</p>
                </div>
            </header>
            <form action="<?= e(url_for('dashboard')); ?>" method="post" class="form-grid">
                <input type="hidden" name="action" value="create_admin_user">
                <input type="hidden" name="redirect" value="admin/administrators">
                <label>Full name
                    <input type="text" name="name" required>
                </label>
                <label>Email
                    <input type="email" name="email" required>
                </label>
                <label>Password
                    <input type="password" name="password" minlength="8" required>
                </label>
                <div class="form-actions">
                    <button type="submit" class="button button--primary">Create admin</button>
                </div>
            </form>
        </article>
        <article class="card column">
            <header class="card-header">
                <div>
                    <h2>Existing admins</h2>
                    <p>Current team members with admin access.</p>
                </div>
            </header>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $adminUser): ?>
                            <tr>
                                <td><?= e($adminUser['name']); ?></td>
                                <td><?= e($adminUser['email']); ?></td>
                                <td><?= e(format_datetime($adminUser['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$admins): ?>
                            <tr><td colspan="3" class="table-empty">No other administrators.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>
