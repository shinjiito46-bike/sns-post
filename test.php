<?php
// 簡易テストファイル
echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Files exist check:<br>";
echo "- config.php: " . (file_exists(__DIR__ . '/config.php') ? 'YES' : 'NO') . "<br>";
echo "- includes/functions.php: " . (file_exists(__DIR__ . '/includes/functions.php') ? 'YES' : 'NO') . "<br>";
