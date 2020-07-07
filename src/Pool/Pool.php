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
    protected $interval = 2000;

    /**
     * handle frequency
     * @var Frequency
     */
    public $frequency;

    /**
     * @var array
     */
    protected $timer;

    public function __construct(array $poolConfig, array $dbConfig)
    {
        $this->poolConfig = $poolConfig;
        $this->dbConfig = $dbConfig;
        $this->channel = new Channel(isset($poolConfig['min_connection']) ? $poolConfig['min_connection'] : 10);
        $this->frequency = new Frequency($this, $this->poolConfig);
        $this->initPool();
        $this->initialized = true;
        $this->check();
        dump('我被初始化了');
        $this->frequency->detect();
    }

    public function dynamicExtension($frequency = 'low')
    {
        switch ($frequency){
            case 'low':
                $this->setLowUsage();
                break;
            case 'high':
                $this->setHighUsage();
                break;
        }
    }

    public function initPool()
    {
//        for ($i=0; $i<$this->poolConfig['min_connection']; $i++){
//        }
        $this->createConnection();
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

    public function setTimer($id)
    {
       $this->timer[] = $id;
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
            $this->frequency->hit();
        }
    }

    private function createConnection()
    {
        $connection = new MysqlConnection($this);
        $con = $connection->connect($this->dbConfig);
        $this->channel->Push($con);
        ++$this->currentConnections;
        return $con;
    }

    private function check()
    {
       $in = \Swoole\Timer::tick($this->interval, function (){
            go(function (){
                dump(['currentTickCID'=>\Co::getCid()]);
                $num = $this->getPoolLength();
                $con = $this->channel->Pop(0.01);
                if ($num > 0 && $con){
                    if (!$con->checkAlive()){
                        $con->disconnect();
                        --$this->currentConnections;
                    }else{
                        $this->channel->Push($con);
                    }
                }
            });
        });
       $this->timer[] = $in;
    }

    private function setLowUsage()
    {
        go(function (){
            $num = $this->getPoolConfig();
            $do = true;
            while ($do){
                if ($num > $this->poolConfig['low_frequency'] && $con = $this->channel->Pop(0.01)){
                    if($con->checkAlive()){
                        $this->release($con);
                        --$this->currentConnections;
                    }
                }else{
                    $do = false;
                }
            }
        });
    }

    private function setHighUsage()
    {
        go(function (){
            $num = $this->getPoolConfig();
            $do = true;
            while ($do){
                if ($num < $this->poolConfig['high_frequency']){
                    $this->createConnection();
                }else{
                    $do = false;
                }
            }
        });
    }
}