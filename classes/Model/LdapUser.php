<?php

use Kirby\Cms\User;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Toolkit\Str;

class LdapUser extends User
{
    /**
     * Tries to authenticate against LDAP server with the given password
     *
     * @throws \Kirby\Exception\NotFoundException If the user has no password
     * @throws \Kirby\Exception\InvalidArgumentException If the entered password is not valid or does not match the user password
     */
    public function validatePassword(
        #[SensitiveParameter]
        string|null $password = null,
    ): bool {
        if (empty($password)) {
            throw new NotFoundException(
                key: 'user.password.undefined',
            );
        }

        // `UserRules` enforces a minimum length of 8 characters,
        // so everything below that is marked/shown as invalid
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
            throw new InvalidArgumentException(
                key: 'user.password.wrong',
                httpCode: 401,
            );
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
    public function getLdapDn(): string
    {
        return LdapUtility::getUtility()->getLdapDn($this->email());
    }

    /**
     * Conditionally create new user account if it does not already exist in Kirby
     *
     * @param string $email
     *
     * @return \Kirby\Cms\User
     */
    public static function findOrCreateIfLdap($email): null|\Kirby\Cms\User
    {
        // if email not set, return null
        if (empty($email)) {
            return null;
        }

        // find user by provided email address
        $user = kirby()->users()->findByKey($email);
        if ($user != null) {
            return $user;
        }

        // find user in LDAP user directory by provided email address
        $ldapUser = LdapUtility::getUtility()->getLdapUser($email);

        // if the user does not exist in the LDAP user directory, return null
        if (!$ldapUser) {
            return null;
        }

        // set user attributes (provided by LDAP server)
        $userProps = [
            'id' => 'LDAP_' . $ldapUser['uid'],
            'email' => $ldapUser['mail'],
            // if the user already exists, and has a custom name set, then prevent
            // overwriting the custom name with the canonical LDAP name attribute
            'name' => $user != null ? $user->name()->toString() : $ldapUser['name'],
            'language' => 'en',
            'role' => 'LdapUser',
            'ldap_dn' => $ldapUser['dn'],
            'ldap_uid' => $ldapUser['uid'],
            'ldap_mail' => $ldapUser['mail'],
            'ldap_name' => $ldapUser['name'],
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
