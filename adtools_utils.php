<?php
class adtools_utils
{
    /**
     * Extract the OU from a DN
     * @param string $dn DN
     * @return string OU
     */
    public static function ou($dn)
    {
        return preg_replace('/CN=.+?,(OU=.+)/', '$1',$dn);
    }

    /**
     * Extract the name from an OU DN
     * @param string $ou OU DN
     * @return string OU name
     */
    public static function ou_name($ou)
    {
        return preg_replace('/OU=(.+?),[A-Z]{2}=.+/','$1',$ou);
    }

    /**
     * Replace LDAP field names with readable names
     * @param string $field LDAP field name
     * @return string Human friendly field name
     */
    public static function field_names($field)
    {
        $replacements = array('givenName' => _('First Name'),
            'sn' => _('Last Name'),
            'initials' => _('Initials'),
            'displayName' => _('Display Name'),
            'description' => _('Description'),
            'physicalDeliveryOfficeName' => _('Office'),
            'telephoneNumber' => _('Telephone Number'),
            'otherTelephone' => _('Telephone: Other'),
            'E-mail-Addresses' => _('E-Mail'),
            'wWWHomePage' => _('Web Page'),
            'url' => _('Web Page: Other'),
            'userPrincipalName' => _('UserLogon Name'),
            'sAMAccountname' => _('User logon name'), // (pre-Windows 2000)
            'logonHours' => _('Logon Hours'),
            'logonWorkstation' => _('Log On To'),
            'lockoutTime and lockoutDuration' => _('Account is locked out'),
            'pwdLastSet' => _('Password last set'),
            'userAccountControl' => _('Other Account Options'),
            'accountExpires' => _('Account Expires'),
            'streetAddress' => _('Street'),
            'postOfficeBox' => _('P.O.Box'),
            'postalCode' => _('Zip/Postal Code'),
            'memberOf' => _('Member of'),
            'profilePath' => _('Profile Path'),
            'scriptPath' => _('Logon Script'),
            'homeDirectory' => _('Home Folder: Local Path'),
            'homeDrive' => _('Home Folder: Connect'),
            'homePhone' => _('Home'),
            'otherHomePhone' => _('Home: Other'),
            'pager' => _('Pager'),
            'otherPager' => _('Pager: Other'),
            'mobile' => _('Mobile'),
            'otherMobile' => _('Mobile: Other'),
            'facsimileTelephoneNumber' => _('Fax'),
            'otherFacsimileTelephoneNumber' => _('Fax: Other'),
            'ipPhone' => _('IP phone'),
            'otherIpPhone' => _('IP phone: Other'),
            'info' => _('Notes'),
            'l' => _('City'),
            'st' => _('State/Province'));

        foreach ($replacements as $find => $replace) {
            $field = str_replace(strtolower($find), $replace, strtolower($field), $count);
            if ($count > 0)
                return $field;
        }
        return $field;
    }

    /**
     * Convert a microsoft timestamp to UNIX timestamp
     * http://www.morecavalier.com/index.php?whom=Apps%2FLDAP+timestamp+converter
     * @param int $ad_date Microsoft timestamp
     * @return int UNIX timestamp
     */
    public static function microsoft_timestamp_to_unix($ad_date)
    {

        if ($ad_date == 0) {
            return '0000-00-00';
        }

        $secsAfterADEpoch = $ad_date / (10000000);
        $AD2Unix = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4)) * 86400;

        // Why -3 ?
        // "If the year is the last year of a century, eg. 1700, 1800, 1900, 2000,
        // then it is only a leap year if it is exactly divisible by 400.
        // Therefore, 1900 wasn't a leap year but 2000 was."

        $unixTimeStamp = intval($secsAfterADEpoch - $AD2Unix);

        return $unixTimeStamp;
    }

    /**
     * Convert a UNIX timestamp to microsoft timestamp
     * @param int $unix_timestamp UNIX timestamp
     * @return int Microsoft timestamp
     */
    public static function unix_timestamp_to_microsoft($unix_timestamp)
    {
        $microsoft = $unix_timestamp + 11644473600;
        $microsoft = $microsoft . '0000000';
        $microsoft = number_format($microsoft, 0, '', '');
        return $microsoft;
    }

    /**
     * Encode the password for AD
     * http://www.youngtechleads.com/how-to-modify-active-directory-passwords-through-php/
     * @param string $newPassword Plain text password
     * @return string Encoded password
     */
    public static function pwd_encryption($newPassword)
    {
        $newPassword = "\"" . $newPassword . "\"";
        $len = strlen($newPassword);
        $newPassw = "";
        for ($i = 0; $i < $len; $i++) {
            $newPassw .= "{$newPassword{$i}}\000";
        }
        return $newPassw;
    }

    /**
     * Generate a dsmod command to change a users password
     * @param string $dn User DN
     * @param string $password Password to set
     * @param string $mustchpwd User must change password (yes/no)
     * @param string $pwdnewerexpires Password never expires (yes/no)
     * @return string
     */
    public static function dsmod_password($dn, $password, $mustchpwd = 'no', $pwdnewerexpires = 'no')
    {
        return sprintf('dsmod user "%s" -pwd %s -mustchpwd %s -pwdneverexpires %s', $dn, $password, $mustchpwd, $pwdnewerexpires) . "\r\n";
    }

    /**
     * Parse the flags on a user
     * https://stackoverflow.com/a/43791392/2630074
     * @param int $flag Flag int from AD
     * @return array Flags
     */
    public static function findFlags($flag)
    {

        $flags = array();
        $flaglist = array(
            1 => 'SCRIPT',
            2 => 'ACCOUNTDISABLE',
            8 => 'HOMEDIR_REQUIRED',
            16 => 'LOCKOUT',
            32 => 'PASSWD_NOTREQD',
            64 => 'PASSWD_CANT_CHANGE',
            128 => 'ENCRYPTED_TEXT_PWD_ALLOWED',
            256 => 'TEMP_DUPLICATE_ACCOUNT',
            512 => 'NORMAL_ACCOUNT',
            2048 => 'INTERDOMAIN_TRUST_ACCOUNT',
            4096 => 'WORKSTATION_TRUST_ACCOUNT',
            8192 => 'SERVER_TRUST_ACCOUNT',
            65536 => 'DONT_EXPIRE_PASSWORD',
            131072 => 'MNS_LOGON_ACCOUNT',
            262144 => 'SMARTCARD_REQUIRED',
            524288 => 'TRUSTED_FOR_DELEGATION',
            1048576 => 'NOT_DELEGATED',
            2097152 => 'USE_DES_KEY_ONLY',
            4194304 => 'DONT_REQ_PREAUTH',
            8388608 => 'PASSWORD_EXPIRED',
            16777216 => 'TRUSTED_TO_AUTH_FOR_DELEGATION',
            67108864 => 'PARTIAL_SECRETS_ACCOUNT'
        );
        for ($i = 0; $i <= 26; $i++) {
            if ($flag & (1 << $i)) {
                array_push($flags, 1 << $i);
            }
        }
        $flags_output = array();
        foreach ($flags as $v) {
            $flags_output[$v] = $flaglist[$v];
        }
        return $flags_output;
    }

}