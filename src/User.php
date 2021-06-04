<?php


namespace storfollo\adtools;


use InvalidArgumentException;
use Symfony\Component\Ldap;

class User extends Entry
{
    const objectClass = 'user';
    public static function from_dn(Ldap\Ldap $ldap, string $dn): User
    {
        return parent::from_dn($ldap, $dn);
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