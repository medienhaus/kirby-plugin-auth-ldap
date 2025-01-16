<?php

use Kirby\Cms\User;

class LdapUser extends User
{
    /**
     * Compares the given password with ldap
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
        if($this->password() === null) {
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
     * Checks if this user has the admin role
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return option('datamints.ldap.is_admin');
    }

    /**
     * 
     * 
     * @return string
     */
    public function getLdapDn() {
        return LdapUtility::getUtility()->getLdapDn($this->email());
    }

    /**
     * creates a LdapUser in Kirby, if it does not exist in Kirby but in Ldap
     * if (!kirbyUser && ldapUser) new kirbyUser
     *
     * @param string $email
     *
     * @return \Kirby\Cms\User
     */
    public static function findOrCreateIfLdap($email) {
        //if email not set, return null
        if (empty($email)) {
            return null;
        }

        // if user already exists, return that user
        $user = kirby()->users()->findByKey($email);
        if($user != null) {
            return $user;
        }

        //if user does not exist in Kirby, search in Ldap
        $ldapUser = LdapUtility::getUtility()->getLdapUser($email);

        //if user does not exist in Ldap too, return null
        if(!$ldapUser) {
            return null;
        }

        //if user exists in Ldap
        //create that user in Kirby
        $userProps = [
            'id'        => "LDAP_".$ldapUser['lastname']."_".substr($ldapUser['uid'], 0, 5),
            'name'      => $ldapUser['name'],
            'email'     => $ldapUser['mail'],
            'language'  => 'en',
            'role'      => 'LdapUser'
        ];
        $user = new LdapUser($userProps);

        //save the user
        $user->writeCredentials($userProps);

        // add the user to users collection
        $user->kirby()->users()->add($user);

        //return it
        return $user;
    }

    /**
     * Finds a user by username in LDAP. If the user is not found, it creates the user
     * in the system (e.g., locally or elsewhere).
     *
     * @param string $username
     * @return array|null An array of user details if successful, or null if the user could not be found or created.
     */
    public static function findOrCreateIfLdapUsername($username) {
        if (empty($username)) {
            throw new Exception("Username or password cannot be empty.");
        }

        // Try finding the user via LDAP
        $ldapUtility = self::getUtility();

        // Validate the username and password
        $isAuthenticated = $ldapUtility->validatePasswordByUsername($username);
        if (!$isAuthenticated) {
            throw new Exception("Invalid username or password.");

        }
            // If valid credentials, retrieve user information from LDAP
        $ldapUser = $ldapUtility->getLdapUserByUsername($username);


            //if user exists in Ldap
            //create that user in Kirby
            $userProps = [
                'id'        => "LDAP_".$ldapUser['lastname']."_".substr($ldapUser['uid'], 0, 5),
                'name'      => $ldapUser['name'],
                'email'     => $ldapUser['mail'],
                'language'  => 'en',
                'role'      => 'LdapUser'
            ];
            $user = new LdapUser($userProps);

            //save the user
            $user->writeCredentials($userProps);

            // add the user to users collection
            $user->kirby()->users()->add($user);

            //return it
            return $user;


    }
}