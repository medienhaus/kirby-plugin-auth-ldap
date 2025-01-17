> [!NOTE]
> This is repository was forked off of: [datamints/kirby-plugin_ldap](https://github.com/datamints/kirby-plugin_ldap)

> [!IMPORTANT]
> The original repository / source code is licensed under: [GNU General Public License v3.0](https://github.com/datamints/kirby-plugin_ldap/blob/master/LICENSE)

> [!TIP]
> We might want to contact the original authors and ask for approval before publication.

---

## Kirby LDAP plugin

The Kirby LDAP plugin enables you to log in with your LDAP credentials, authenticating against your LDAP server. On first login, the plugin creates a user account for you, and adds your full name and mail address into your user profile. By default, new users created via the LDAP plugin are given the `admin` role; see [Configure](#Configure) below. The language for newly created users is `en` by default; this can be changed in the Kirby panel.

## Installation

To install the Kirby LDAP plugin, clone the plugin repository into your `<kirby_document_root>/site/plugins/` directory. You can also install the plugin via `composer` or as a `git submodule` if you want to.

## Configuration

Configure LDAP server access via: `<kirby_document_root>/site/config/config.php`

```php
<?php
    return [
        ...,
        'medienhaus.kirby-ldap-plugin' => [
            'hostname' => 'ldap://ldap.example.org:389',
            'bind_dn' => 'cn=admin,dc=example,dc=org',
            'bind_pw' => '*****************************',
            'base_dn' => 'ou=people,dc=example,dc=org',
            'is_admin' => false, // assign every LDAP user the `admin` role in Kirby (default: false)
            'start_tls' => true, // enable TLS-secured connection instead of secure `ldaps://` protocol via port `636`
        ],
    ];
?>
```

If you want to change the default permissions to be more granular than admin `true`/`false`, copy [`blueprints/users/LdapUser.yml`](/blueprints/users/LdapUser.yml) to `<kirby_document_root>/site/blueprints/users/LdapUser.yml` and modify the file according to your needs as described in the [Kirby permission docs](https://getkirby.com/docs/guide/users/permissions).

## License

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

- This Kirby-Plugin is licensed under the [GNU General Public License v3.0 (GPLv3)](https://www.gnu.org/licenses/gpl-3.0)
- Copyright 2020 © <a href="https://www.datamints.com/" target="_blank">datamints GmbH</a>
- Copyright 2025 © <a href="https://medienhaus.dev/" target="_blank">medienhaus/</a>
