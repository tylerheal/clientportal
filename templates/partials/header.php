<?php
if (!defined('APP_BOOTSTRAPPED')) {
    require __DIR__ . '/../../bootstrap.php';
}
$company = get_setting('company_name', 'Service Portal');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $company); ?></title>
    <link rel="stylesheet" href="<?= e(url_for('static/css/app.css')); ?>">
    <style><?= theme_styles(); ?></style>
</head>
<body>
