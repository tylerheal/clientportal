<section class="page-section">
    <header class="page-header">
        <div>
            <h2>Clients</h2>
            <p>All active client accounts.</p>
        </div>
    </header>
    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?= e($client['name']); ?></td>
                            <td><?= e($client['email']); ?></td>
                            <td><?= e($client['company']); ?></td>
                            <td><?= e(format_datetime($client['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$clients): ?>
                        <tr><td colspan="4" class="table-empty">No clients yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
