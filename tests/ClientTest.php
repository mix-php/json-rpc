<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{

    public function testRequest(): void
    {
        $_this     = $this;
        $scheduler = new \Swoole\Coroutine\Scheduler;
        $scheduler->set([
            'hook_flags' => SWOOLE_HOOK_ALL,
        ]);
        $scheduler->add(function () use ($_this) {
            // server
            $server  = new \Mix\JsonRpc\Server('127.0.0.1', 9234);
            $service = new Calculator();
            $server->register($service);
            go(function () use ($server) {
                $server->start();
            });
            // client
            $client = new \Mix\JsonRpc\Client([
                'connection' => new \Mix\JsonRpc\Connection('127.0.0.1', 9234),
            ]);

            $response = $client->call(\Mix\JsonRpc\Factory\RequestFactory::create('Calculator.sum', [1, 3], 0));
            $_this->assertEquals($response->result[0], 4);

            $responses = $client->callMultiple(\Mix\JsonRpc\Factory\RequestFactory::create('Calculator.sum', [1, 3], 0), \Mix\JsonRpc\Factory\RequestFactory::create('Calculator.sum', [2, 3], 0));
            $_this->assertEquals($responses[0]->result[0], 4);
            $_this->assertEquals($responses[1]->result[0], 5);

            $server->shutdown();
        });
        $scheduler->start();
    }

}

class Calculator
{
    public function sum(\Mix\JsonRpc\Message\Request $request): \Mix\JsonRpc\Message\Response
    {
        $sum = array_sum($request->params);
        return \Mix\JsonRpc\Factory\ResponseFactory::createResult([$sum], $request->id);
    }
}
