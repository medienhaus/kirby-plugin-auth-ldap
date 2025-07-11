<?php

class LdapUtility
{
    private $ldapConn = null;
    private static $utility = null;

    /**
     * LdapUtility constructor; stores itself in a static variable
     */
    public function __construct()
    {
        global $utility;
        $utility = $this;
    }

    /**
     * Return saved LdapUtility, or create new LdapUtility if none is stored
     *
     * @return LdapUtility
     */
    public static function getUtility(): LdapUtility
    {
        global $utility;
        if ($utility == null) {
            return new LdapUtility();
        }
        return $utility;
    }

    /**
     * Retrieve account information from the LDAP server; returns `$user` or `false`
     *
     * `$user` is an array of strings [dn, uid, mail, name]
     *
     * @param string $mail
     *
     * @return array|false
     */
    public function getLdapUser($mail): array|false
    {
        if (empty($mail)) {
            return false;
        }

        if (option('medienhaus.kirby-plugin-auth-ldap.base_dn')) {
            $ldap_base_dn = option('medienhaus.kirby-plugin-auth-ldap.base_dn');
        } else {
            throw new Exception("LDAP base_dn not set in config");
        }

        $ldap = $this->getLdapConnection();

        // search for matching user by provided mail address
        if (option('medienhaus.kirby-plugin-auth-ldap.attributes.mail')) {
            $filter = "(" . option('medienhaus.kirby-plugin-auth-ldap.attributes.mail') . "=$mail)";
        } else {
            $filter = "(mail=$mail)";
        }

        $result = ldap_search($ldap, $ldap_base_dn, $filter);

        // get user entry
        $entries = ldap_get_entries($ldap, $result);

        // create user object; if that fails, `$user` remains set to `false`
        $user = false;

        // check if user is found
        $count = $entries["count"];

        if (!empty($count)) {
            $entry = $entries[0];

            // conditionally set LDAP `uid` attribute
            if (option('medienhaus.kirby-plugin-auth-ldap.attributes.uid')) {
                $ldap_uid = option('medienhaus.kirby-plugin-auth-ldap.attributes.uid');
            } else {
                $ldap_uid = "uid";
            }

            // conditionally set LDAP `mail` attribute
            if (option('medienhaus.kirby-plugin-auth-ldap.attributes.mail')) {
                $ldap_mail = option('medienhaus.kirby-plugin-auth-ldap.attributes.mail');
            } else {
                $ldap_mail = "mail";
            }

            // conditionally set LDAP `name` attribute
            if (option('medienhaus.kirby-plugin-auth-ldap.attributes.name')) {
                $ldap_name = option('medienhaus.kirby-plugin-auth-ldap.attributes.name');
            } else {
                $ldap_name = "cn";
            }

            // beautify user-array
            //
            // NOTE: attributes need to be lowercase here !!
            //
            // The attribute index is converted to lowercase.
            // (Attributes are case-insensitive for directory servers,
            // but not when used as array indices.)
            //
            // docs: https://www.php.net/manual/en/function.ldap-get-entries.php
            //
            $user = [
                "dn" => $entry["dn"],
                "uid" => $entry[strtolower($ldap_uid)][0],
                "mail" => $entry[strtolower($ldap_mail)][0],
                "name" => $entry[strtolower($ldap_name)][0],
            ];
        }

        // return user array or false on fail
        return $user;
    }

    /**
     * Return LDAP connection, or create new connection if none is established
     *
     * @return mixed
     */
    private function getLdapConnection(): mixed
    {
        global $ldapConn;
        if ($ldapConn != null) {
            return $ldapConn;
        }
        $this->getNewLdapConnection();
        return $ldapConn;
    }

    /**
     * Create new LDAP connection; set LDAP options, TLS options, and authorize/bind user for LDAP search
     */
    private function getNewLdapConnection(): void
    {
        global $ldapConn;

        // LDAP credentials
        $ldap_host = option("medienhaus.kirby-plugin-auth-ldap.host");
        $ldap_bind_dn = option("medienhaus.kirby-plugin-auth-ldap.bind_dn");
        $ldap_bind_pw = option("medienhaus.kirby-plugin-auth-ldap.bind_pw");

        // establish connection to LDAP server
        $ldapConn = ldap_connect($ldap_host) or die("Invalid LDAP host: " . $ldap_host);

        // conditionally enable debugging for LDAP connection
        if (option('debug') === true || option('medienhaus.kirby-plugin-auth-ldap.debug') === true) {
            ldap_set_option(null, LDAP_OPT_DEBUG_LEVEL, 7);
        }

        // LDAP options (docs: https://www.php.net/manual/en/function.ldap-set-option.php)
        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

        // TLS options (optional)
        if (option('medienhaus.kirby-plugin-auth-ldap.tls_options')) {
            if (option('medienhaus.kirby-plugin-auth-ldap.tls_options.validate')) {
                ldap_set_option($ldapConn, LDAP_OPT_X_TLS_REQUIRE_CERT, option('medienhaus.kirby-plugin-auth-ldap.tls_options.validate'));
            }
            if (option('medienhaus.kirby-plugin-auth-ldap.tls_options.version')) {
                ldap_set_option($ldapConn, LDAP_OPT_X_TLS_PROTOCOL_MIN, option('medienhaus.kirby-plugin-auth-ldap.tls_options.version'));
            }
            if (option('medienhaus.kirby-plugin-auth-ldap.tls_options.ciphers')) {
                ldap_set_option($ldapConn, LDAP_OPT_X_TLS_CIPHER_SUITE, option('medienhaus.kirby-plugin-auth-ldap.tls_options.ciphers'));
            }
        }

        // upgrade to TLS-secured connection
        if (option('medienhaus.kirby-plugin-auth-ldap.start_tls') !== false) {
            ldap_start_tls($ldapConn) or die("Can’t connect via TLS: " . $ldap_host);
        }

        // authorize/bind user for LDAP search
        $this->getLdapBind($ldap_bind_dn, $ldap_bind_pw);
    }

    /**
     * Authorize/bind the user for LDAP search
     *
     * @param string $user
     * @param string $password
     * @return bool
     */
    private function getLdapBind($user, $password): bool
    {
        set_error_handler(function () { $bind = false; });
        $bind = ldap_bind($this->getLdapConnection(), $user, $password);
        restore_error_handler();
        return $bind;
    }

    /**
     * Retrieve LDAP attribute `dn` of user by provided mail address
     *
     * @param string $mail
     * @return string
     * @throws Exception
     */
    public function getLdapDn($mail): string
    {
        if (empty($mail)) {
            throw new Exception("get LDAP DN without mail");
        }
        $user = $this->getLdapUser($mail);
        return $user["dn"];
    }

    /**
     * checks if the user credentials are correct.
     * params are mail and plain-text password
     * returns boolean if the credentials are correct
     *
     * @param $mail
     * @param $ldap_user_pw
     * @return bool
     * @throws Exception
     */
    public function validatePassword($mail, $ldap_user_pw): bool
    {
        if (empty($mail)) {
            throw new Exception("validate password without mail");
        }
        $ldap_user_dn = $this->getLdapDn($mail);
        $bind = $this->getLdapBind($ldap_user_dn, $ldap_user_pw);
        if ($bind != false) {
            $bind = true;
        }
        return $bind;
    }
}
