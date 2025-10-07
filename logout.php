<?php
require __DIR__ . '/bootstrap.php';
logout();
flash('success', 'You have been signed out.');
redirect('login');
