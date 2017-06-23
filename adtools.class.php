<?php
class adtools
{
	public $ad=false;
	public $dn=false;
	public $error;
	public $domain; //Domain name
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
	function connect($domain)
	{
		require 'domains.php';
		if(!isset($domains[$domain]))
		{
			$this->error=sprintf(_('Domain %s not found in config file'),$domain);
			return false;
		}
		$domain=$domains[$domain];
		if(isset($domain['ldaps']))
			$ldaps=true;
		else
			$ldaps=false;

		$this->dn=$domain['dn'];
		$this->domain=$domain['domain'];
		return $this->connect_and_bind($domain['domain'],$domain['username'],$domain['password'],$ldaps);
	}
	//Connect and bind using specified credentials
	function connect_and_bind($domain,$username,$password,$ldaps=false)
	{
		//http://php.net/manual/en/function.ldap-bind.php#73718
		if (preg_match('/[^a-zA-Z@\.\-]/',$username) || preg_match('/[^a-zA-Z0-9\x20!@#$%^&*()+\-]/',$password))
		{
			$this->error=_('Invalid characters in username or password');
			return false;
		}

		//https://github.com/adldap/adLDAP/wiki/LDAP-over-SSL
		//http://serverfault.com/questions/136888/ssl-certifcate-request-s2003-dc-ca-dns-name-not-avaiable/705724#705724
		//print_r(array($domain,$username,$password));
		if($ldaps)
			$this->ad=ldap_connect("ldaps://".$domain);
		else
			$this->ad=ldap_connect("ldap://".$domain);
		if($this->ad===false)
		{
			$this->error=ldap_error($this->ad);
			return false;
		}
		ldap_set_option($this->ad, LDAP_OPT_PROTOCOL_VERSION, 3);
		if (!ldap_set_option($this->ad, LDAP_OPT_REFERRALS, 0))
		{
			$this->error='Failed to set opt referrals to 0';
			return false;
		}

		if(!$bind=ldap_bind($this->ad,$username,$password))
		{
			//http://php.net/manual/en/function.ldap-bind.php#103034
			if(!ldap_get_option($handle, LDAP_OPT_DIAGNOSTIC_MESSAGE, $this->error)) //Try to get extended error message
				$this->error=ldap_error($this->ad);
			return false;
		}
		return true;
	}

	//Do a ldap query and get results
	function query($query,$base_dn,$fields)
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
	//Find an object in AD
	function find_object($name,$base_dn=false,$type='user',$fields=false)
	{
		if($base_dn===false)
			$base_dn=$this->dn;

		if($fields!==false && !is_array($fields))
			throw new Exception("Fields must be array or false");
		if($type=='user')
			$result=ldap_search($this->ad,$base_dn,$q="(displayName=$name)",($fields===false ? array('sAMAccountName'):$fields));
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
	
	//-----------old---------------

	//http://www.morecavalier.com/index.php?whom=Apps%2FLDAP+timestamp+converter
	function convert_AD_date ($ad_date) {
	
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
		$myDate = date("Y-m-d H:i:s", $unixTimeStamp); // formatted date
	
		return $myDate;
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
	function change_passord($dn,$password)
	{
		return ldap_mod_replace($this->ad,$dn,array('unicodePwd'=>$this->pwd_encryption($password)));
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