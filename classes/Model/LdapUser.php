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
        if (empty($password)) {
            throw new InvalidArgumentException(['key' => 'user.password.missing']);
        }

        // `UserRules` enforces a minimum length of 8 characters,
        // so everything below that is a typo
        if (Str::length($password) < 8) {
            throw new InvalidArgumentException(
                key: 'user.password.invalid',
            );
        }

        // too long passwords can cause DoS attacks
        if (Str::length($password) > 1000) {
            throw new InvalidArgumentException(
                key: 'user.password.excessive',
            );
        }

        if ((LdapUtility::getUtility()->validatePassword($this->email(), $password)) !== true) {
            http_response_code(403);
            throw new InvalidArgumentException(['key' => 'user.password.notSame']);
        }

        return true;
    }

    /**
     * Conditionally applies the Kirby `admin` role to LDAP users on login
     *
     * NOTE: this is conditionally applied on _every_ login, hence this setting could be
     * changed at any time and would apply the updated value to each subsequent login !!
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

        // find user by provided email address
        $user = kirby()->users()->findByKey($email);

        // if user already exists, and is not role `LdapUser`, return
        // user object and continue auth for local Kirby user account
        if ($user != null && $user->role() != 'LdapUser') {
            return $user;
        }

        // find user in LDAP user directory by provided email address
        $ldapUser = LdapUtility::getUtility()->getLdapUser($email);

        // if user does not exist in LDAP user directory, return null
        if (!$ldapUser) {
            return null;
        }

        // set user attributes (provided by LDAP server)
        $userProps = [
            'id' => 'LDAP_' . $ldapUser['uid'],
            'email' => $ldapUser['mail'],
            'name' => $ldapUser['name'],
            'language' => 'en',
            'role' => 'LdapUser',
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
