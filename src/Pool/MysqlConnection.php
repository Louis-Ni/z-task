<?php
namespace ZTask\Pool;

use Illuminate\Database\Connectors\MySqlConnector;

class MysqlConnection
{
    protected $lastActiveTime;

    /**
     * @var \Illuminate\Database\MySqlConnection
     */
    protected $connection;

    public function __construct()
    {
        $this->lastActiveTime = microtime(true);
    }

    public function connect($config)
    {
        $mysql = new MySqlConnector();
        $connection = $mysql->connect($config);
        $this->connection = new \Illuminate\Database\MySqlConnection($connection);
        return $this;
    }

    public function select($query)
    {
        return $this->connection->select($query);
    }

    public function update($query)
    {
        return $this->connection->update($query);
    }

    public function insert($query)
    {
        return $this->connection->insert($query);
    }
}