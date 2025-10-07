</main>
<footer class="border-t border-slate-200 bg-white py-6 text-sm text-slate-500">
    <div class="mx-auto flex max-w-6xl flex-col gap-2 px-6 sm:flex-row sm:items-center sm:justify-between">
        <p>&copy; <?= date('Y'); ?> <?= e(get_setting('company_name', 'Service Portal')); ?>. All rights reserved.</p>
        <p>Need help? <a href="mailto:<?= e(get_setting('support_email', 'support@example.com')); ?>" class="text-brand hover:underline">Contact support</a></p>
    </div>
</footer>
</body>
</html>
