<?php
class adtools
{
	public $ad=false;
	public $dn=false;
	public $error;
	public $domain; //Domain name
	public $dc; //Domain controller
	public $config=false;
	function __construct($domain=false)
	{
		if($domain!==false)
		{
			$status=$this->connect($domain);
			if($status===false)
				throw new Exception($this->error);
		}
	}
	//Escape invalid characters in ldap query
	function ldap_query_escape($string)
	{
		return str_replace(array('\\','*','(',')',),array('\\00','\\2A','\\28','\\29'),$string);
	}

	//Connect and bind using config file
	function connect($domain_key)
	{
		require 'domains.php';
		if(!isset($domains[$domain_key]))
		{
			$this->error=sprintf(_('Domain key %s not found in config file'),$domain_key);
			return false;
		}
		$this->config=$domains[$domain_key];

		if(!isset($this->config['dc']) && !isset($this->config['domain']))
		{
			$this->error=_('DC and/or domain must be specified in config file');
			return false;
		}
		elseif(!isset($this->config['dc']))
			$this->config['dc']=$this->config['domain'];
		elseif(!isset($this->config['domain']))
			$this->config['domain']=$this->config['dc'];
		//Use default values if options not set
		if(!isset($this->config['ldaps']))
			$this->config['ldaps']=false;
		if(!isset($this->config['port']))
			$this->config['port']=false;

		if(isset($this->config['username']) && isset($this->config['password']))
			return $this->connect_and_bind($this->config['domain'],$this->config['username'],$this->config['password'],$this->config['ldaps'],$this->config['port'],$this->config['dc']);
	}
	//Connect and bind using specified credentials
	function connect_and_bind($domain=false,$username,$password,$ldaps=null,$port=false,$dc=false)
	{
		//http://php.net/manual/en/function.ldap-bind.php#73718
		if(preg_match('/[^a-zA-Z@\.\-0-9]/',$username) || preg_match('/[^a-zA-Z0-9\x20!@#$%^&*()+\-]/',$password))
		{
			$this->error=_('Invalid characters in username or password');
			return false;
		}
		if(!empty($port) && !is_numeric($port))
		{
			unset($port);
			trigger_error('Port number must be numeric',E_USER_WARNING);
		}

		//https://github.com/adldap/adLDAP/wiki/LDAP-over-SSL
		//http://serverfault.com/questions/136888/ssl-certifcate-request-s2003-dc-ca-dns-name-not-avaiable/705724#705724
		//print_r(array($domain,$username,$password));
		if($domain===false)
		{
			if(isset($this->config['domain']))
				$domain=$this->config['domain'];
			else
				throw new Exception('Domain not specified');
		}
		if($ldaps===null && isset($this->config['ldaps'])) //Use value from config file
			$ldaps=$this->config['ldaps'];
		if($ldaps===true)
			$protocol='ldaps';
		else
			$protocol='ldap';

		if($dc===false)
		{
			if(isset($this->config['dc']))
				$dc=$this->config['dc'];
			else
				$dc=$domain;
		}
		//PHP/OpenLDAP will default to port 389 even if ldaps is specified
		if($protocol=='ldaps' && (empty($port) || !is_numeric($port)))
			$port=636;

		$url=sprintf('%s://%s',$protocol,$dc);
		if(!empty($port))
			$url.=':'.$port;

		$this->ad=ldap_connect($url);
		if($this->ad===false)
		{
			$this->error=_('Unable to connect');
			return false;
		}
		ldap_set_option($this->ad, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($this->ad, LDAP_OPT_NETWORK_TIMEOUT, 1);
		if (!ldap_set_option($this->ad, LDAP_OPT_REFERRALS, 0))
		{
			$this->error='Failed to set opt referrals to 0';
			return false;
		}

		if(!$bind=ldap_bind($this->ad,$username,$password))
		{
			//http://php.net/manual/en/function.ldap-bind.php#103034
			$this->error=ldap_error($this->ad);
			$this->error=str_replace(array('Invalid credentials'),array(_('Invalid user name or password')),$this->error);
			return false;
		}
		return true;
	}

	//Do a ldap query and get results
	function query($query,$base_dn,$fields,$single_result=true)
	{
		$result=ldap_search($this->ad,$base_dn,$query,$fields);
		if($result===false)
		{
			$this->error=sprintf(_('Search for %s returned false'),$query);
			return false;
		}
		$entries=ldap_get_entries($this->ad,$result);
		if($entries['count']>1)
		{
			$this->error=sprintf(_('Multiple hits for %s'),$query);
			return false;
		}
		if($entries['count']==0)
		{
			$this->error=sprintf(_('No hits for query %s in %s'),$query,$base_dn);
			return false;
		}
		if($single_result)
		{
			if($fields===false)
				return $entries[0]['dn'];
			elseif(count($fields)==1)
			{
				if(!empty($entries[0][$fields[0]]))
				{
					if(is_array($entries[0][$fields[0]]))
						return $entries[0][$fields[0]][0];
					else
						return $entries[0][$fields[0]];
				}
				else
				{
					$this->error=sprintf(_('Field %s is empty'),$fields[0]);
					return false;
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
			$result=ldap_search($this->ad,$base_dn,$q="(displayName=$name)",($fields===false ? array('sAMAccountName'):$fields));
		elseif($type=='upn')
			$result=ldap_search($this->ad,$base_dn,$q="(userPrincipalName=$name)",($fields===false ? array('userPrincipalName'):$fields));
		elseif($type=='username')
			$result=ldap_search($this->ad,$base_dn,$q="(sAMAccountName=$name)",($fields===false ? array('sAMAccountName'):$fields));
		elseif($type=='computer')
			$result=ldap_search($this->ad,$base_dn,$q="(name=$name)",array('name'));
		else
			return false;
		$entries=ldap_get_entries($this->ad,$result);

		if($entries['count']>1)
		{
			$this->error=sprintf(_('Multiple hits for %s'),$name);
			return false;
		}
		if($entries['count']==0)
		{
			$this->error=sprintf(_('No hits for query %s in %s'),$q,$base_dn);
			return false;
		}
		if($fields===false)
			return $entries[0]['dn'];
		elseif(count($fields)==1)
		{
			$fields[0]=strtolower($fields[0]);
			if(!empty($entries[0][$fields[0]]))
				return $entries[0][$fields[0]];
			else
			{
				$this->error=sprintf(_('Field %s is empty'),$fields[0]);
				return false;
			}
		}
		else
			return $entries[0];
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
    foreach($flags as $k=>&$v) {
        $v = $v . ' '  . $flaglist[$v];
    }
    return $flags;
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

	//Reset password for user
	function change_passord($dn,$password,$must_change_password=false)
	{
		if(empty($dn) || empty($password))
		{
			$this->error='DN eller passord er ikke opgitt';
			return false;
		}
		$fields=array('unicodePwd'=>$this->pwd_encryption($password));
		if($must_change_password!==false)
			$fields['pwdLastSet']=0;
		$result=ldap_mod_replace($this->ad,$dn,$fields);
		if($result===false)
		{
			$this->error=ldap_error($this->ad);
			return false;
		}
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
?>