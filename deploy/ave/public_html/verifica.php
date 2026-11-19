<?php

// Verificação única do servidor. Apagar depois de usar.
header('Content-Type: text/plain; charset=utf-8');
$app = dirname(__DIR__).'/clinica';
echo 'PHP '.PHP_VERSION.' ('.PHP_SAPI.")\n";
echo 'Corre como: '.(function_exists('posix_geteuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user())."\n\n";
foreach (['pdo_mysql', 'mbstring', 'bcmath', 'fileinfo', 'openssl', 'tokenizer', 'ctype', 'dom', 'session'] as $ext) {
    echo str_pad($ext, 12).(extension_loaded($ext) ? 'ok' : 'EM FALTA')."\n";
}
echo "\n";
foreach (['upload_max_filesize', 'post_max_size', 'memory_limit'] as $chave) {
    echo str_pad($chave, 22).ini_get($chave)."\n";
}
echo "\n";
foreach (['storage/logs', 'storage/framework/sessions', 'storage/framework/views', 'storage/app/private', 'bootstrap/cache'] as $pasta) {
    $caminho = "$app/$pasta";
    echo str_pad($pasta, 30).(is_dir($caminho) ? (is_writable($caminho) ? 'gravável' : 'SEM ESCRITA') : 'não existe')."\n";
}
