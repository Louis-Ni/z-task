<?php
namespace ZTask\Pool;

class Pool
{
    protected $channel;
    /**
     * pool config
     */
    protected $poolConfig;
    /**
     * database config
     */
    protected $dbConfig;
    /**
     * current connections
     */
    protected $currentConnections = 0;
    /**
     * for check pool initialized
     */
    protected $initialized;
    /**
     * for check connection alive
     */
    protected $interval = 10000;

    /**
     * handle frequency
     * @var Frequency
     */
    protected $frequency;

    public function __construct(array $poolConfig, array $dbConfig)
    {
        $this->poolConfig = $poolConfig;
        $this->dbConfig = $dbConfig;
        $this->channel = new Channel(isset($poolConfig['min_connection']) ? $poolConfig['min_connection'] : 10);
        $this->frequency = new Frequency($this, $poolConfig);
        $this->initPool();
    }

    public function initPool()
    {
        for ($i=0; $i<$this->poolConfig['min_connection']; $i++){
            $connection = new MysqlConnection($this);
            $connection->connect($this->dbConfig);
            ++$this->currentConnections;
            $this->channel->Push($connection);
        }
    }

    public function getPoolConfig()
    {
        return $this->poolConfig;
    }

    public function getConnection() : MysqlConnection
    {
        $length = $this->channel->Length();

        try{
            if ($length == 0 && $this->currentConnections < $this->poolConfig['max_connection']){
                ++$this->currentConnections;
                return $this->createConnection();
            }
        }catch (\Throwable $throwable){
            throw $throwable;
        }

        $connection = $this->channel->Pop($this->poolConfig['wait_timeout']);

        return $connection;
    }

    public function getPoolLength()
    {
        return $this->channel->Length();
    }

    public function release($connection)
    {
        if (!$connection instanceof MysqlConnection){
            throw new \RuntimeException('connection type incorrect');
        }

        if (!$this->initialized){
            throw new \RuntimeException('pool did not initialize');
        }

        if ($this->getPoolLength() > $this->poolConfig['max_connection']){
            $connection->disconnect();
        }

        $connection->setLastActiveTime();
        $res = $this->channel->Push($connection);
        if ($res === false){
            $connection->disconnect();
        }else{
            $this->check();
        }
    }

    private function createConnection()
    {
        $connection = new MysqlConnection($this);
        $con = $connection->connect($this->dbConfig);
        $this->channel->Push($con);
        return $con;
    }

    private function check()
    {
        \Swoole\Timer::tick($this->interval, function (){
            $num = $this->getPoolLength();
            $con = $this->channel->Pop(0.01);
            if ($num > 0 && $con){
                if (!$con->checkAlive()){
                    $con->disconnect();
                    --$this->currentConnections;
                }
            }else{
                $this->channel->Push($con);
            }
        });
    }
}