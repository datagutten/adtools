<?php
/**
 * Created by PhpStorm.
 * User: Anders
 * Date: 29.04.2019
 * Time: 18.09
 */

class MultipleHitsException extends Exception
{
    public $query;
    public function __construct($query, $code = 0, Exception $previous = null) {
        $message = sprintf('Multiple hits for query %s', $query);
        $this->query = $query;
        parent::__construct($message, $code, $previous);
    }
}