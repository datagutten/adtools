<?php


namespace storfollo\adtools;


use InvalidArgumentException;

class User extends Entry
{
    public function __construct($ldap, $dn)
    {
        parent::__construct($ldap, $dn, '(objectClass=user)');
    }

    /**
     * Reset password for user
     * @param string $password Password
     * @param bool $must_change_password
     */
    function change_password(string $password, $must_change_password = false)
    {
        if (empty($password))
            throw new InvalidArgumentException('DN or password is empty or not specified');

        $this['unicodePwd'] = [adtools_utils::pwd_encryption($password)];
        if ($must_change_password !== false)
            $this['pwdLastSet'] = [0];
    }
}