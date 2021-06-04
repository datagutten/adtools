<?php


namespace storfollo\adtools;


use Symfony\Component\Ldap;

class Computer extends Entry
{
    public static function from_dn(Ldap\Ldap $ldap, string $dn): Computer
    {
        return parent::from_dn($ldap, $dn);
    }

    const objectClass = 'computer';
}