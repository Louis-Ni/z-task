<?php


namespace ZTask\Pool;


class Frequency
{
    /**
     * pool begin time
     */
    protected $beginTime;

    /**
     * pool
     * @var Pool
     */
    protected $pool;

    /**
     * define number of low frequency
     */
    protected $lowFrequency;

    /**
     * define high frequency
     */
    protected $highFrequency;

    /**
     * count hits interval
     */
    protected $intervalTime;

    /**
     * pop connection times
     */
    protected $hits = 0;

    public function __construct($pool, $config)
    {
        $this->pool = $pool;
        $this->lowFrequency = isset($config['low_frequency'])?? 10;
        $this->highFrequency = isset($config['high_frequency'])?? 50;
        $this->intervalTime = isset($config['frequency_interval_time']) ?? 60;
        $this->beginTime = time();
    }

    public function hit()
    {
        $this->hits++;
    }

    public function isHighFrequency() : bool
    {
        return true;
    }

    public function isLowFrequency() : bool
    {
        $now = microtime();
        return true;
    }

    public function detect()
    {
        \Swoole\Timer::tick($this->intervalTime, function (){
            if ($this->isLowFrequency()){
                $this->pool->dynamicExtension('low');
                $this->initialConfig();
                return ;
            }

            if ($this->isHighFrequency()){
                $this->pool->dynamicExtension('high');
                $this->initialConfig();
                return ;
            }
        });
    }

    private function initialConfig()
    {
        $this->beginTime = time();
        $this->hits = 0;
    }
}