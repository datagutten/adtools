<?php
require 'LdapException.php';
class adtools
{
    /**
     * @var $ad resource LDAP link identifier
     */
    public $ad;
    /**
     * @var $error string Error message
     */
	public $error;
    /**
     * @var $config array Configuration loaded from config file
     */
    public $config=array();

    /**
     * adtools constructor.
     * @param string $domain domain key from config file to connect to
     * @throws Exception
     */
    function __construct($domain=null)
	{
		if(!empty($domain))
			$this->connect($domain);
	}

    /**
     * Escape invalid characters in ldap query
     * @param $string string Query string to escape
     * @return mixed
     */
    function ldap_query_escape($string)
	{
		return str_replace(array('\\','*','(',')',),array('\\00','\\2A','\\28','\\29'),$string);
	}

    /**
     * Connect and bind using config file
     * @param $domain_key
     * @return bool
     * @throws Exception
     */
    function connect($domain_key)
	{
		$domains = require 'domains.php';
		if(!isset($domains[$domain_key]))
			throw new Exception(sprintf(_('Domain key %s not found in config file'),$domain_key));

		$this->config=$domains[$domain_key];

		if(!isset($this->config['dc']) && !isset($this->config['domain']))
			throw new Exception(_('DC and/or domain must be specified in config file'));
		elseif(!isset($this->config['dc']))
			$this->config['dc']=$this->config['domain'];
		elseif(!isset($this->config['domain']))
			$this->config['domain']=$this->config['dc'];
		//Use default values if options not set
		if(!isset($this->config['protocol']))
			$this->config['protocol']=null;
		if(!isset($this->config['port']))
			$this->config['port']=null;

		if(isset($this->config['username']) && isset($this->config['password']))
			return $this->connect_and_bind($this->config['domain'],$this->config['username'],$this->config['password'],$this->config['protocol'],$this->config['port'],$this->config['dc']);
	}

    /**
     * Connect and bind using specified credentials
     * @param string $domain
     * @param $username
     * @param $password
     * @param string $protocol Set to ldap, ldaps or leave blank to use config file
     * @param int $port
     * @param string $dc
     * @return bool
     * @throws Exception
     */
    function connect_and_bind($domain=null, $username, $password, $protocol=null, $port=null, $dc=null)
	{
		//http://php.net/manual/en/function.ldap-bind.php#73718
		if(empty($username) || empty($password))
            throw new Exception(_('Username and/or password are not specified'));
		if(preg_match('/[^a-zA-Z@\.\,\-0-9\=]/',$username) || preg_match('/[^a-zA-Z0-9\x20!@#$%^&*()+\-]/',$password))
            throw new Exception(_('Invalid characters in username or password'));
		if(!empty($port) && !is_numeric($port))
			throw new Exception('Port number must be numeric');


		//https://github.com/adldap/adLDAP/wiki/LDAP-over-SSL
		//http://serverfault.com/questions/136888/ssl-certifcate-request-s2003-dc-ca-dns-name-not-avaiable/705724#705724
		//print_r(array($domain,$username,$password));
		if(empty($domain))
		{
			if(isset($this->config['domain']))
				$domain=$this->config['domain'];
			else
				throw new Exception('Domain not specified');
		}
		if(empty($protocol))
        {
            if(!empty($this->config['protocol'])) //Use value from config file
                $protocol = $this->config['protocol'];
            else
                $protocol = 'ldap';
        }

        if(!is_string($protocol) || ($protocol!='ldap' && $protocol!='ldaps'))
            throw new Exception('Invalid protocol specified');

        //PHP/OpenLDAP will default to port 389 even if ldaps is specified
        if($protocol=='ldaps' && (empty($port) || !is_numeric($port)))
            $port=636;

		if(empty($dc))
		{
			if(isset($this->config['dc']))
				$dc=$this->config['dc'];
			else
				$dc=$domain;
		}

		$url=sprintf('%s://%s',$protocol,$dc);
		if(!empty($port))
			$url.=':'.$port;

		$this->ad=ldap_connect($url);
		if($this->ad===false)
            throw new Exception(_('Unable to connect'));

		ldap_set_option($this->ad, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($this->ad, LDAP_OPT_NETWORK_TIMEOUT, 1);
		if (!ldap_set_option($this->ad, LDAP_OPT_REFERRALS, 0))
            throw new Exception('Failed to set opt referrals to 0');

		if(ldap_bind($this->ad,$username,$password)===false)
		{
			//http://php.net/manual/en/function.ldap-bind.php#103034
			if(ldap_errno($this->ad)===49)
                throw new Exception(_('Invalid user name or password'));
			else
			    throw new Exception(ldap_error($this->ad));
		}

		return true;
	}

    /**
     * Do a ldap query and get results
     * @param $query
     * @param array $options
     * @return array
     * @throws InvalidArgumentException
     * @throws LdapException Error from LDAP
     * @throws NoHitsException No hits found
     * @throws MultipleHitsException Multiple hits when single was expected
     * @throws Exception
     */
    function ldap_query($query, $options=array())
    {
        $options_default = array('single_result' => true, 'subtree' => true, 'attributes' => array('dn'));
        $options = array_merge($options_default, $options);

        if(!is_resource($this->ad))
            throw new Exception('Not connected to AD');
        if(empty($options['base_dn']))
        {
            if(!empty($this->config['dn']))
                $options['base_dn']=$this->config['dn'];
            else
                throw new InvalidArgumentException('Base DN empty and not set in config');
        }

        if($options['subtree'])
            $result=ldap_search($this->ad,$options['base_dn'],$query,$options['attributes']);
        else
            $result=ldap_list($this->ad,$options['base_dn'],$query,$options['attributes']);

        if($result===false)
            throw new LdapException($this->ad);

        $entries=ldap_get_entries($this->ad,$result);

        if($entries['count']==0)
        {
            //$this->error=sprintf(_('No hits for query %s in %s'),$query,$options['base_dn']);
            throw new NoHitsException($query);
        }
        if($options['single_result']===true)
        {
            if($entries['count']>1)
                throw new MultipleHitsException($query);

            if($options['attributes']==1)
            {
                $field=strtolower($options['attributes'][0]);
                if(!empty($entries[0][$field]))
                {
                    if(is_array($entries[0][$field])) //Field is array
                        return $entries[0][$field][0];
                    else
                        return $entries[0][$field];
                }
                else
                {
                    throw new Exception(sprintf(_('Field %s is empty'),$field));
                }
            }
            else
                return $entries[0];
        }
        else
            return $entries;
    }

    /**
     * Do a ldap query and get results
     * @param $query
     * @param string $base_dn
     * @param $fields
     * @param bool $single_result
     * @param bool $subtree
     * @return array
     * @throws Exception
     */
    function query($query, $base_dn=null, $fields, $single_result=true, $subtree=true)
	{
		if(!is_resource($this->ad))
			throw new Exception('Not connected to AD');
		if(empty($base_dn))
		{
			if(!empty($this->config['dn']))
				$base_dn=$this->config['dn'];
			else
				throw new Exception('Base DN empty and not set in config');
		}

		if($subtree)
			$result=ldap_search($this->ad,$base_dn,$query,$fields);
		else
			$result=ldap_list($this->ad,$base_dn,$query,$fields);
		if($result===false)
		{
		    throw new Exception(ldap_error($this->ad));
		}
		$entries=ldap_get_entries($this->ad,$result);
		if($entries['count']>1 && $single_result===true)
		{
			throw new Exception(sprintf(_('Multiple hits for %s, but single result was expected'),$query));
		}
		if($entries['count']==0)
		{
		    //TODO: Create custom exception for no hits?
			$this->error=sprintf(_('No hits for query %s in %s'),$query,$base_dn);
			return null;
		}
		if($single_result)
		{
			if($fields===false)
				return $entries[0]['dn'];
			elseif(count($fields)==1)
			{
				$field=strtolower($fields[0]);
				if(!empty($entries[0][$field]))
				{
					if(is_array($entries[0][$field])) //Field is array
						return $entries[0][$field][0];
					else
						return $entries[0][$field];
				}
				else
				{
					throw new Exception(sprintf(_('Field %s is empty'),$fields[0]));
				}
			}
			else
				return $entries[0];
		}
		else
			return $entries;
	}

	//Find an object in AD
	function find_object($name,$base_dn=false,$type='user',$fields=false)
	{
		if($base_dn===false)
			$base_dn=$this->config['dn'];

		if($fields!==false && !is_array($fields))
			throw new Exception("Fields must be array or false");

		if($type=='user')
			return $this->query("(&(displayName=$name)(objectClass=user))",$base_dn,($fields===false ? array('sAMAccountName'):$fields),true);
		elseif($type=='upn')
			return $this->query("(&(userPrincipalName=$name)(objectClass=user))",$base_dn,($fields===false ? array('userPrincipalName'):$fields),true);
		elseif($type=='username')
			return $this->query("(&(sAMAccountName=$name)(objectClass=user))",$base_dn,($fields===false ? array('sAMAccountName'):$fields),true);
		elseif($type=='computer')
			return $this->query("(&(name=$name)(objectClass=computer))",$base_dn,($fields===false ? array('name'):$fields),true);
		else
			throw new Exception('Invalid type');
	}

	//Create a login form
	function login_form()
	{
		return '<form id="form1" name="form1" method="post">
  <p>
    <label for="username">'._('Username').':</label>
    <input type="text" name="username" id="username">
  </p>
  <p>
    <label for="password">'._('Password').':</label>
    <input type="password" name="password" id="password">
  </p>
  <p>
    <input type="submit" name="submit" id="submit" value="Submit">
  </p>
</form>';
	}
	//Move an object to another OU
	function move($dn,$newparent)
	{
		$cn=preg_replace('/(CN=.+?),[A-Z]{2}.+/','$1',$dn);
		return ldap_rename($this->ad,$dn,$cn,$newparent,true);
	}

	//https://stackoverflow.com/a/43791392/2630074
	public function findFlags($flag) {

    $flags    = array();
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
    for ($i=0; $i<=26; $i++){
        if ($flag & (1 << $i)){
            array_push($flags, 1 << $i);
        }
    }
    $flags_output = array();
    foreach($flags as $v) {
		$flags_output[$v]=$flaglist[$v];
    }
    return $flags_output;
}

	//Replace LDAP field names with readable names
	function field_names($field)
	{
		$replace=array('givenName'=>_('First Name'),
						'sn'=>_('Last Name'),
						'initials'=>_('Initials'),
						'displayName'=>_('Display Name'),
						'description'=>_('Description'),
						'physicalDeliveryOfficeName'=>_('Office'),
						'telephoneNumber'=>_('Telephone Number'),
						'otherTelephone'=>_('Telephone: Other'),
						'E-mail-Addresses'=>_('E-Mail'),
						'wWWHomePage'=>_('Web Page'),
						'url'=>_('Web Page: Other'),
						'userPrincipalName'=>_('UserLogon Name'),
						'sAMAccountname'=>_('User logon name'), // (pre-Windows 2000)
						'logonHours'=>_('Logon Hours'),
						'logonWorkstation'=>_('Log On To'),
						'lockoutTime and lockoutDuration'=>_('Account is locked out'),
						'pwdLastSet'=>_('Password last set'),
						'userAccountControl'=>_('Other Account Options'),
						'accountExpires'=>_('Account Expires'),
						'streetAddress'=>_('Street'),
						'postOfficeBox'=>_('P.O.Box'),
						'postalCode'=>_('Zip/Postal Code'),
						'memberOf'=>_('Member of'),
						'profilePath'=>_('Profile Path'),
						'scriptPath'=>_('Logon Script'),
						'homeDirectory'=>_('Home Folder: Local Path'),
						'homeDrive'=>_('Home Folder: Connect'),
						'homeDirectory'=>_('Home Folder: To'),
						'homePhone'=>_('Home'),
						'otherHomePhone'=>_('Home: Other'),
						'pager'=>_('Pager'),
						'otherPager'=>_('Pager: Other'),
						'mobile'=>_('Mobile'),
						'otherMobile'=>_('Mobile: Other'),
						'facsimileTelephoneNumber'=>_('Fax'),
						'otherFacsimileTelephoneNumber'=>_('Fax: Other'),
						'ipPhone'=>_('IP phone'),
						'otherIpPhone'=>_('IP phone: Other'),
						'info'=>_('Notes'),
						'l'=>_('City'),
						'st'=>_('State/Province'));

		foreach($replace as $find=>$replace)
		{
			$field=str_replace(strtolower($find),$replace,strtolower($field),$count);
			if($count>0)
				return $field;
		}
		return $field;
	}
	
	//-----------old---------------

	//http://www.morecavalier.com/index.php?whom=Apps%2FLDAP+timestamp+converter
	function microsoft_timestamp_to_unix ($ad_date) {
	
		if ($ad_date == 0) {
			return '0000-00-00';
		}
	
		$secsAfterADEpoch = $ad_date / (10000000);
		$AD2Unix=((1970-1601) * 365 - 3 + round((1970-1601)/4) ) * 86400;
	
		// Why -3 ?
		// "If the year is the last year of a century, eg. 1700, 1800, 1900, 2000,
		// then it is only a leap year if it is exactly divisible by 400.
		// Therefore, 1900 wasn't a leap year but 2000 was."
	
		$unixTimeStamp=intval($secsAfterADEpoch-$AD2Unix);
	
		return $unixTimeStamp;
	}
	function unix_timestamp_to_microsoft($unix_timestamp)
	{
		$microsoft=$unix_timestamp+11644473600;
		$microsoft=$microsoft.'0000000';
		$microsoft=number_format($microsoft, 0, '', '');
		return $microsoft;
	}

	function extract_field($objects,$field)
	{
	    $extract = array();
		foreach($objects as $key=>$object)
		{
			$extract[$key]=$object[$field][0];
		}
		return $extract;
	}

	//Encode the password for AD
	//Source: http://www.youngtechleads.com/how-to-modify-active-directory-passwords-through-php/
	function pwd_encryption( $newPassword ) {
		$newPassword = "\"" . $newPassword . "\"";
		$len = strlen( $newPassword );
		$newPassw = "";
		for ( $i = 0; $i < $len; $i++ ){
			$newPassw .= "{$newPassword{$i}}\000";
		}
		return $newPassw;
	}

    /**
     * Reset password for user
     * @param string $dn User DN
     * @param string $password Password
     * @param bool $must_change_password
     * @throws InvalidArgumentException
     * @throws LdapException
     */
	function change_password($dn,$password,$must_change_password=false)
	{
		if(empty($dn) || empty($password))
			throw new InvalidArgumentException('DN or password is empty or not specified');

		$fields=array('unicodePwd'=>$this->pwd_encryption($password));
		if($must_change_password!==false)
			$fields['pwdLastSet']=0;
		$result=ldap_mod_replace($this->ad,$dn,$fields);
		if($result===false)
		    throw new LdapException($this->ad);
	}

	function dsmod_password($dn,$password,$mustchpwd='no',$pwdnewerexpires='no')
	{
		return sprintf('dsmod user "%s" -pwd %s -mustchpwd %s -pwdneverexpires %s',$dn,$password,$mustchpwd,$pwdnewerexpires)."\r\n";
	}
	function __destruct()
	{
		if(is_object($this->ad))
			ldap_unbind($this->ad);
	}
}
