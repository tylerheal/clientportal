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
                    <h2>Create template</h2>
                    <p>Set up a reusable email template.</p>
                </div>
            </header>
            <form action="<?= e(url_for('dashboard')); ?>" method="post" class="form-grid">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="redirect" value="admin/automations">
                <label>Name
                    <input type="text" name="name" required>
                </label>
                <label>Slug
                    <input type="text" name="slug" required placeholder="ticket_reply">
                </label>
                <label>Subject
                    <input type="text" name="subject" required>
                </label>
                <label>Body
                    <textarea name="body" rows="6" required></textarea>
                </label>
                <div class="form-actions">
                    <button type="submit" class="button button--primary">Save template</button>
                </div>
            </form>
        </article>
        <article class="card column">
            <header class="card-header">
                <div>
                    <h2>Templates</h2>
                    <p>Edit messaging for automated emails.</p>
                </div>
            </header>
            <div class="template-list">
                <?php foreach ($templates as $template): ?>
                    <details class="template-item">
                        <summary>
                            <div>
                                <strong><?= e($template['name']); ?></strong>
                                <span><?= e($template['slug']); ?></span>
                            </div>
                        </summary>
                        <form action="<?= e(url_for('dashboard')); ?>" method="post" class="form-grid">
                            <input type="hidden" name="action" value="save_template">
                            <input type="hidden" name="template_id" value="<?= (int) $template['id']; ?>">
                            <input type="hidden" name="redirect" value="admin/automations">
                            <label>Name
                                <input type="text" name="name" value="<?= e($template['name']); ?>" required>
                            </label>
                            <label>Slug
                                <input type="text" name="slug" value="<?= e($template['slug']); ?>" required>
                            </label>
                            <label>Subject
                                <input type="text" name="subject" value="<?= e($template['subject']); ?>" required>
                            </label>
                            <label>Body
                                <textarea name="body" rows="6" required><?= e($template['body']); ?></textarea>
                            </label>
                            <div class="form-actions">
                                <button type="submit" class="button button--primary">Update</button>
                                <button type="submit" form="delete-template-<?= (int) $template['id']; ?>" class="button button--ghost">Delete</button>
                            </div>
                        </form>
                        <form id="delete-template-<?= (int) $template['id']; ?>" action="<?= e(url_for('dashboard')); ?>" method="post" onsubmit="return confirm('Delete this template?');">
                            <input type="hidden" name="action" value="delete_template">
                            <input type="hidden" name="template_id" value="<?= (int) $template['id']; ?>">
                            <input type="hidden" name="redirect" value="admin/automations">
                        </form>
                    </details>
                <?php endforeach; ?>
                <?php if (!$templates): ?>
                    <p class="table-empty">No templates yet.</p>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>
