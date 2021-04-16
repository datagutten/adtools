<?php
/**
 * Created by PhpStorm.
 * User: Anders
 * Date: 29.04.2019
 * Time: 18.09
 */

namespace storfollo\adtools\exceptions;
use Exception;

class NoHitsException extends AdToolsException
{
    public $query;
    public function __construct($query, $code = 0, Exception $previous = null) {
        $message = sprintf('No hits for query %s', $query);
        $this->query = $query;
        parent::__construct($message, $code, $previous);
    }
}