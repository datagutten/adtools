<?php
/**
 * Created by PhpStorm.
 * User: abi
 * Date: 29.04.2019
 * Time: 11:52
 */
namespace storfollo\adtools\exceptions;
use Exception;

class LdapException extends Exception
{
    public function __construct($ad, $code = 0, Exception $previous = null) {
        $message = sprintf('LDAP error: %s', ldap_error($ad));
        parent::__construct($message, $code, $previous);
    }
}