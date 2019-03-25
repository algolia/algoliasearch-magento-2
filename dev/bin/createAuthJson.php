<?php

$json['http-basic']['repo.magento.com'] = [
    'username' => getenv('MAGENTO_AUTH_USERNAME'),
    'password' => getenv('MAGENTO_AUTH_PASSWORD'),
];

$dirname = dirname(getenv('AUTH_DIR'));

if (!is_dir($dirname)) {
    mkdir($dirname, 0777, true);
}

if (file_put_contents(getenv('AUTH_DIR'), json_encode($json))) {
    echo 'Auth.json file created in directory "' . $dirname . '"';
    exit(0);
}

echo 'Error while creating auth.json file';
exit(1);
