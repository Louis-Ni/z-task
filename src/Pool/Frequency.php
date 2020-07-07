<?php


namespace ZTask\Pool;


class Frequency
{
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
    public $hits = 0;

    public function __construct($pool, $config)
    {
        $this->pool = $pool;
        $this->lowFrequency = isset($config['low_frequency']) == true? $config['low_frequency']: 10;
        $this->highFrequency = isset($config['high_frequency'])==true ? $config['high_frequency']: 50;
        $this->intervalTime = isset($config['frequency_interval_time'])== true? $config['frequency_interval_time']: 60;
    }

    public function hit()
    {
        $this->hits++;
    }

    public function isHighFrequency() : bool
    {
        if ($this->hits > $this->highFrequency){
            return true;
        }
        return false;
    }

    public function isLowFrequency() : bool
    {
        if ($this->hits < $this->lowFrequency){
            return true;
        }
        return false;
    }

    public function isBalanceFrequency()
    {
        if ($this->hits > $this->lowFrequency && $this->hits < $this->highFrequency || $this->hits == 0){
            return true;
        }
        return false;
    }
    public function detect()
    {
       $in =  \Swoole\Timer::tick($this->intervalTime * 1000, function (){
            go(function (){
                if ($this->isLowFrequency() && !$this->isBalanceFrequency()){
                    dump('setLowExtension');
                    $this->pool->dynamicExtension('low');
                    $this->initialConfig();
                    return ;
                }

                if ($this->isHighFrequency() && !$this->isBalanceFrequency()){
                    dump('setHighExtension');
                    $this->pool->dynamicExtension('high');
                    $this->initialConfig();
                    return ;
                }
            });
        });
       $this->pool->setTimer($in);
    }

    private function initialConfig()
    {
        $this->hits = 0;
    }
}