<?php

class Test
{
    public function __construct()
    {
        echo "Test class instantiated.";
    }
}

$test = new Test();

var_dump($test);
die();

echo "\nHello World!\n";