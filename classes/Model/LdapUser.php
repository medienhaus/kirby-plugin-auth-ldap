<?php

use Kirby\Cms\User;

class LdapUser extends User
{
    /**
     * Tries to authenticate against LDAP server with the given password
     *
     * @param string $password
     * @return bool
     *
     * @throws \Kirby\Exception\NotFoundException If the user has no password
     * @throws \Kirby\Exception\InvalidArgumentException If the entered password is not valid
     * @throws \Kirby\Exception\InvalidArgumentException If the entered password does not match the user password
     */
    public function validatePassword(?string $password = null): bool
    {
        if ($this->password() === null) {
            http_response_code(403);
            throw new NotFoundException(['key' => 'user.password.missing']);
        }

        if ((LdapUtility::getUtility()->validatePassword($this->email(), $password)) !== true) {
            http_response_code(403);
            throw new InvalidArgumentException(['key' => 'user.password.notSame']);
        }

        return true;
    }

    /**
     * Conditionally applies `admin` role to new LDAP users
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return option('medienhaus.kirby-plugin-auth-ldap.is_admin');
    }

    /**
     * Retrieve LDAP attribute `dn` of user by provided mail address
     *
     * @return string
     */
    public function getLdapDn()
    {
        return LdapUtility::getUtility()->getLdapDn($this->email());
    }

    /**
     * Retrieve LDAP attribute `uid` of user by provided mail address
     *
     * @return string
     */
    public function getLdapUid()
    {
        return LdapUtility::getUtility()->getLdapUid($this->email());
    }

    /**
     * Retrieve LDAP attribute `mail` of user by provided mail address
     *
     * @return string
     */
    public function getLdapMail()
    {
        return LdapUtility::getUtility()->getLdapMail($this->email());
    }

    /**
     * Retrieve LDAP attribute `name` of user by provided mail address
     *
     * @return string
     */
    public function getLdapName()
    {
        return LdapUtility::getUtility()->getLdapName($this->email());
    }

    /**
     * Conditionally create new user account if it does not already exist in Kirby
     *
     * @param string $email
     *
     * @return \Kirby\Cms\User
     */
    public static function findOrCreateIfLdap($email)
    {
        // if email not set, return null
        if (empty($email)) {
            return null;
        }

        // if user already exists, return that user
        $user = kirby()->users()->findByKey($email);
        if ($user != null) {
            return $user;
        }

        // if user does not exist in Kirby, search in LDAP
        $ldapUser = LdapUtility::getUtility()->getLdapUser($email);

        // if user does not exist in LDAP, return null
        if (!$ldapUser) {
            return null;
        }

        // conditionally set LDAP uid attribute
        // if (option('medienhaus.kirby-plugin-auth-ldap.attributes.uid')) {
        //     $ldap_uid = option('medienhaus.kirby-plugin-auth-ldap.attributes.uid');
        // } else {
        //     $ldap_uid = 'uid';
        // }

        // conditionally set LDAP name attribute
        // if (option('medienhaus.kirby-plugin-auth-ldap.attributes.name')) {
        //     $ldap_name = option('medienhaus.kirby-plugin-auth-ldap.attributes.name');
        // } else {
        //     $ldap_name = 'cn';
        // }

        // conditionally set LDAP mail attribute
        // if (option('medienhaus.kirby-plugin-auth-ldap.attributes.mail')) {
        //     $ldap_mail = option('medienhaus.kirby-plugin-auth-ldap.attributes.mail');
        // } else {
        //     $ldap_mail = 'mail';
        // }

        // set user attributes
        $userProps = [
            'id'        => 'LDAP_' . $ldapUser['uid'],
            'name'      => $ldapUser['name'],
            'email'     => $ldapUser['mail'],
            // 'id'        => 'LDAP_' . $ldapUser[$ldap_uid],
            // 'name'      => $ldapUser[$ldap_name],
            // 'email'     => $ldapUser[$ldap_mail],
            'language'  => 'en',
            'role'      => 'LdapUser',
        ];

        // create new user with user attributes
        $user = new LdapUser($userProps);

        // save the new user account to Kirby
        $user->writeCredentials($userProps);

        // add the user to users collection
        $user->kirby()->users()->add($user);

        // return the user account
        return $user;
    }
}

