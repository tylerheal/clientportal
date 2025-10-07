            </div>
        </div>
    </main>
    <footer class="border-t border-white/60 bg-white/80 py-8 text-sm text-slate-600 backdrop-blur">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 px-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="font-medium text-slate-500">&copy; <?= date('Y'); ?> <?= e(get_setting('company_name', 'Service Portal')); ?>. All rights reserved.</p>
            <p class="text-slate-500">Need help? <a href="mailto:<?= e(get_setting('support_email', 'support@example.com')); ?>" class="font-semibold text-brand hover:underline">Contact support</a></p>
        </div>
    </footer>
</div>
</body>
</html>
