<?php

namespace App;

use PDO;

class Database extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite:' . __DIR__ . '/../storage/database.sqlite');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}
