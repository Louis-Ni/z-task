<?php
namespace ZTask\Pool;

use Illuminate\Database\Connectors\MySqlConnector;

class MysqlConnection
{
    protected $lastActiveTime = '0.0';

    /**
     * @var \Illuminate\Database\MySqlConnection
     */
    protected $connection;

    public function connect($config)
    {
        $mysql = new MySqlConnector();
        $connection = $mysql->connect($config);
        $this->connection = new \Illuminate\Database\MySqlConnection($connection);
        $this->lastActiveTime = microtime(true);
        return $this;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function disconnect()
    {
        $this->getConnection()->disconnect();
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

    public function getLastActiveTime()
    {
        return $this->lastActiveTime;
    }
}