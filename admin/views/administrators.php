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
                            <th>Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $totalAdminCount = count($admins); ?>
                        <?php foreach ($admins as $adminUser): ?>
                            <?php $isEditing = isset($editAdmin['id']) && (int) $editAdmin['id'] === (int) $adminUser['id']; ?>
                            <tr>
                                <td><?= e($adminUser['name']); ?></td>
                                <td><?= e($adminUser['email']); ?></td>
                                <td><?= e(format_datetime($adminUser['created_at'])); ?></td>
                                <td class="table-actions">
                                    <a class="button button--ghost" href="<?= e(url_for('admin/administrators?edit_admin=' . (int) $adminUser['id'])); ?>">Edit</a>
                                    <?php if ($isEditing): ?>
                                        <span class="badge muted">Editing</span>
                                    <?php endif; ?>
                                    <?php if ($totalAdminCount > 1 && (int) $adminUser['id'] !== (int) $user['id']): ?>
                                        <form action="<?= e(url_for('dashboard')); ?>" method="post" onsubmit="return confirm('Remove this administrator?');">
                                            <input type="hidden" name="action" value="delete_admin_user">
                                            <input type="hidden" name="redirect" value="admin/administrators">
                                            <input type="hidden" name="admin_id" value="<?= (int) $adminUser['id']; ?>">
                                            <button type="submit" class="button button--ghost button--danger">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$admins): ?>
                            <tr><td colspan="4" class="table-empty">No other administrators.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($editAdmin)): ?>
                <div class="card-section">
                    <h3>Update administrator</h3>
                    <p class="card-note">
                        Order, ticket, and invoice emails are delivered to every admin listed above along with the support email configured in Settings.
                    </p>
                    <form action="<?= e(url_for('dashboard')); ?>" method="post" class="form-grid">
                        <input type="hidden" name="action" value="update_admin_user">
                        <input type="hidden" name="redirect" value="admin/administrators?edit_admin=<?= (int) $editAdmin['id']; ?>">
                        <input type="hidden" name="admin_id" value="<?= (int) $editAdmin['id']; ?>">
                        <label>Full name
                            <input type="text" name="name" value="<?= e($editAdmin['name']); ?>" required>
                        </label>
                        <label>Email
                            <input type="email" name="email" value="<?= e($editAdmin['email']); ?>" required>
                        </label>
                        <label>New password
                            <input type="password" name="password" minlength="8">
                            <span class="hint">Leave blank to keep the current password.</span>
                        </label>
                        <div class="form-actions">
                            <button type="submit" class="button button--primary">Save changes</button>
                        </div>
                    </form>
                    <?php if ($totalAdminCount > 1 && (int) $editAdmin['id'] !== (int) $user['id']): ?>
                        <form action="<?= e(url_for('dashboard')); ?>" method="post" class="form-grid" onsubmit="return confirm('Remove this administrator?');">
                            <input type="hidden" name="action" value="delete_admin_user">
                            <input type="hidden" name="redirect" value="admin/administrators">
                            <input type="hidden" name="admin_id" value="<?= (int) $editAdmin['id']; ?>">
                            <div class="form-actions">
                                <button type="submit" class="button button--ghost button--danger">Remove <?= e($editAdmin['name']); ?></button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>
</section>
