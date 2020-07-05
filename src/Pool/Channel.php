<?php


namespace ZTask\Pool;


class Channel
{
    protected $chanel;

    public function __construct(int $size)
    {
        $this->chanel = new \Swoole\Coroutine\Channel($size);
    }

    public function Pop(float $timeout = -1): MysqlConnection
    {
       return $this->chanel->pop($timeout);
    }

    public function Push($data)
    {
        return $this->chanel->push($data);
    }

    public function Length() : int
    {
        return $this->chanel->length();
    }
}