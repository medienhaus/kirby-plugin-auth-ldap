<?php

// Assuming you have access to the user object
$ldapDN = LdapUtility::getUtility()->getLdapDn(kirby()->user()->email());
$ldapUID = LdapUtility::getUtility()->getLdapUid(kirby()->user()->email());
$ldapMail = LdapUtility::getUtility()->getLdapMail(kirby()->user()->email());
$ldapName = LdapUtility::getUtility()->getLdapName(kirby()->user()->email());

return [
    'title' => 'LdapUser',
    'description' => 'Account is externally managed via LDAP server',
    'sections' => [
        'ldap_dn' => [
            'type' => 'info',
            'label' => 'DN',
            'text' => $ldapDN,
        ],
        'ldap_uid' => [
            'type' => 'info',
            'label' => 'UID',
            'text' => $ldapUID,
        ],
        'ldap_mail' => [
            'type' => 'info',
            'label' => 'Mail',
            'text' => $ldapMail,
        ],
        'ldap_name' => [
            'type' => 'info',
            'label' => 'Name',
            'text' => $ldapName,
        ],

    ],
];