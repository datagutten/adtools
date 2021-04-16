<?php


namespace storfollo\adtools;

use ArrayAccess;
use Symfony\Component\Ldap;

abstract class Entry implements ArrayAccess
{
    /**
     * @var Ldap\Ldap
     */
    protected $ldap;
    /**
     * @var Ldap\Entry
     */
    public $entry;

    protected $entryManager;

    function __construct(Ldap\Ldap $ldap, string $dn, string $query)
    {
        $this->ldap = $ldap;
        $this->entryManager = $this->ldap->getEntryManager();
        $query = $this->ldap->query($dn, $query);
        $this->entry = $query->execute()->toArray()[0];

    }

    /**
     * @param Ldap\Ldap $ldap
     * @param $dn
     * @param $attributes
     * @return static
     */
    public static function add(Ldap\Ldap $ldap, $dn, $attributes)
    {
        $entry = new Ldap\Entry($dn, $attributes);
        $ldap->getEntryManager()->add($entry);
        return new static($ldap, $dn);
    }

    public function offsetExists($offset): bool
    {
        return $this->entry->hasAttribute($offset);
    }

    public function offsetGet($offset): ?array
    {
        return $this->entry->getAttribute($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->entry->setAttribute($offset, $value);
        $this->entryManager->update($this->entry);
    }

    public function offsetUnset($offset)
    {
        $this->entry->removeAttribute($offset);
        $this->entryManager->update($this->entry);
    }

    function move(string $newParent): string
    {
        $this->entryManager->move($this->entry, $newParent);
        $cn = preg_replace('/CN=(.+?),[A-Z]{2}.+/i', '$1', $this->entry->getDn());
        return sprintf('CN=%s,%s', $cn, $newParent);
    }
}