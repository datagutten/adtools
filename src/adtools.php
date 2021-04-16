<?php
namespace storfollo\adtools;
use Exception;
use InvalidArgumentException;
use storfollo\adtools\exceptions\MultipleHitsException;
use storfollo\adtools\exceptions\NoHitsException;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Ldap;

class adtools
{
    /**
     * @var $config array Configuration loaded from config file
     */
    public $config=array();
    /**
     * @var Ldap
     */
    public $ldap;
    /**
     * adtools constructor.
     */
    function __construct()
	{
		set_locale('nb_NO.utf8', 'adtools');
    }

    /**
     * Connect and bind using an array of configuration parameters
     * @param array $config Configuration parameters
     * @return adtools
     */
    public static function connect_config(array $config): adtools
    {
        if (!isset($config['dc']))
            throw new InvalidArgumentException(_('DC must be specified in config file'));
        $config = array_merge(['protocol'=>'ldap', 'port'=>389], $config);

        $adtools = new static();
        $adtools->config = $config;

        if (isset($config['username']) && isset($config['password']))
            $adtools->connect_and_bind($config['username'], $config['password'], $config['dc'], $config['protocol'], $config['port']);
        return $adtools;
    }

    /**
     * Connect and bind using specified credentials
     * @param string $username
     * @param string $password
     * @param string $dc
     * @param string $protocol Set to ldap, ldaps or leave blank to use config file
     * @param int|null $port
     */
    function connect_and_bind(string $username, string $password, string $dc, $protocol='ldap', int $port=null)
	{
		//http://php.net/manual/en/function.ldap-bind.php#73718
		if(empty($username) || empty($password))
            throw new InvalidArgumentException(_('Username and/or password are not specified'));
		if(preg_match('/[^a-zA-Z@\.\,\-0-9\=]/',$username) || preg_match('/[^a-zA-Z0-9\x20!@#$%^&*()+\-]/',$password))
            throw new InvalidArgumentException(_('Invalid characters in username or password'));

		//https://github.com/adldap/adLDAP/wiki/LDAP-over-SSL
		//http://serverfault.com/questions/136888/ssl-certifcate-request-s2003-dc-ca-dns-name-not-avaiable/705724#705724

        if(!is_string($protocol) || ($protocol!='ldap' && $protocol!='ldaps'))
            throw new InvalidArgumentException('Invalid protocol specified');

        //PHP/OpenLDAP will default to port 389 even if ldaps is specified
        if($protocol=='ldaps' && (empty($port) || !is_numeric($port)))
            $port=636;

		$url=sprintf('%s://%s',$protocol,$dc);
		if(!empty($port))
			$url.=':'.$port;

        $this->ldap = Ldap::create('ext_ldap', ['connection_string' => $url]);
        $this->ldap->bind($username, $password);
	}

    /**
     * Do a ldap query and get results
     * @param $query
     * @param array $options {
     * Query options
     *      @type bool $single_result Assume there should be only one result, throw exception if multiple is found
     *      @type bool $subtree Search sub tree
     *      @type array $attributes Attributes to be returned
     *      @type string $base_dn Base DN
     * }
     * @return array|string Array with data. If there is one result and one field the string value is returned
     * @throws NoHitsException No hits found
     * @throws MultipleHitsException Multiple hits when single was expected
     */
    function ldap_query($query, $options=array('single_result' => true, 'subtree' => true, 'attributes' => array('dn')))
    {
        $options_default = array('single_result' => true, 'subtree' => true, 'attributes' => array('dn'));
        $options = array_merge($options_default, $options);

        if(empty($options['base_dn']))
        {
            if(!empty($this->config['dn']))
                $options['base_dn']=$this->config['dn'];
            else
                throw new InvalidArgumentException('Base DN empty and not set in config');
        }
        if(!is_array($options['attributes']))
            throw new InvalidArgumentException('attributes must be array');

        if($options['subtree'])
            $scope = QueryInterface::SCOPE_SUB;
        else
            $scope = QueryInterface::SCOPE_ONE;

        $result = $this->ldap->query($options['base_dn'], $query, ['scope'=>$scope])->execute();

        if($result->count()==0)
        {
            throw new NoHitsException($query);
        }
        if($options['single_result']===true)
        {
            if($result->count()>1)
                throw new MultipleHitsException($query);
            $entries = $result->toArray();

            if(count($options['attributes'])==1)
            {
                $entry = $entries[0];
                if($options['attributes'][0]=='dn')
                    return $entries[0]->getDn();

                if($entry->hasAttribute($options['attributes'][0]))
                {
                    $attribute_data = $entry->getAttribute($options['attributes'][0]);
                    if(is_array($attribute_data)) //Field is array
                        return $attribute_data[0];
                    else
                        return $attribute_data;
                }
                else
                {
                    throw new InvalidArgumentException('Entry has no attribute named ' . $options['attributes'][0]);
                }
            }
            else
                return $entries[0];
        }
        else
            return $result->toArray();
    }

    /**
     * Find an object in AD
     * @param $name
     * @param bool $base_dn
     * @param string $type
     * @param bool $fields
     * @return array
     * @throws Exception
     */
	function find_object($name,$base_dn=false,$type='user',$fields=false)
	{
		if($base_dn===false)
			$base_dn=$this->config['dn'];

		if($fields!==false && !is_array($fields))
			throw new InvalidArgumentException("Fields must be array or false");

		$options = array(
		    'base_dn'=>$base_dn,
            'single_result'=>true
        );

		if(!empty($fields))
		    $options['attributes'] = $fields;

		if($type=='user')
        {
            if(empty($options['attributes']))
                $options['attributes'] = array('sAMAccountName');

            return $this->ldap_query("(&(displayName=$name)(objectClass=user))", $options);
        }
		elseif($type=='upn')
        {
            if(empty($options['attributes']))
                $options['attributes'] = array('userPrincipalName');
            return $this->ldap_query("(&(userPrincipalName=$name)(objectClass=user))", $options);
        }
		elseif($type=='username')
        {
            if(empty($options['attributes']))
                $options['attributes'] = array('sAMAccountName');
            return $this->ldap_query("(&(sAMAccountName=$name)(objectClass=user))", $options);
        }
		elseif($type=='computer')
        {
            if(empty($options['attributes']))
                $options['attributes'] = array('name');
            return $this->ldap_query("(&(name=$name)(objectClass=computer))",$options);
        }
		else
			throw new InvalidArgumentException('Invalid type');
	}

    /**
     * Get user from DN
     * @param $dn
     * @return User
     */
	public function user($dn): User
    {
        return new User($this->ldap, $dn);
    }

    /**
     * Get group from DN
     * @param $dn
     * @return Group
     */
	public function group($dn): Group
    {
        return new Group($this->ldap, $dn);
    }
}
