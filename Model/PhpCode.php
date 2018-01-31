<?php
namespace MSP\CodeMonkey\Model;

class PhpCode
{
    /**
     * Convert to camel case
     * @param $string
     * @return mixed
     */
    public function toCamelCase($string)
    {
        return str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $string)));
    }
}
