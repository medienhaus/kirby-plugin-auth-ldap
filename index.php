<?php

load([
    'LdapUser' => 'classes/Model/LdapUser.php',
    'LdapUtility' => 'classes/Utility/LdapUtility.php',
], __DIR__);

Kirby::plugin('medienhaus/kirby-plugin-auth-ldap', [
    'userModels' => [
        'ldapuser' => 'LdapUser',
    ],
    'blueprints' => [
        'users/LdapUser' => __DIR__ . '/blueprints/users/LdapUser.yml',
    ],
    'hooks' => [
        'route:before' => function ($route, $path, $method) {
            if ($path == 'api/auth/login' && $method == "POST") {
                LdapUser::findOrCreateIfLdap($this->request()->get('email'));
            }
        },
    ],
    'options' => [
        'is_admin' => false,
    ],
]);
