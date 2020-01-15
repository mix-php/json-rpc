<?php

namespace Mix\JsonRpc;

use Mix\Concurrent\Sync\WaitGroup;
use Mix\JsonRpc\Factory\ResponseFactory;
use Mix\JsonRpc\Helper\JsonRpcHelper;
use Mix\JsonRpc\Message\Request;
use Mix\Server\Connection;
use Mix\Server\Exception\ReceiveException;
use Swoole\Coroutine\Channel;

/**
 * Class Server
 * @package Mix\JsonRpc
 */
class Server
{

    /**
     * @var string
     */
    public $host = '';

    /**
     * @var int
     */
    public $port = 0;

    /**
     * @var bool
     */
    public $reusePort = false;

    /**
     * @var \Mix\Server\Server
     */
    protected $server;

    /**
     * 服务集合
     * @var callable[]
     */
    protected $services = [];

    /**
     * Server constructor.
     * @param string $host
     * @param int $port
     * @param bool $reusePort
     */
    public function __construct(string $host, int $port, bool $reusePort = false)
    {
        $this->host      = $host;
        $this->port      = $port;
        $this->reusePort = $reusePort;
    }

    /**
     * Register
     * @param object $service
     */
    public function register(object $service)
    {
        $name    = str_replace('/', '\\', basename(str_replace('\\', '/', get_class($service))));
        $methods = get_class_methods($service);
        foreach ($methods as $method) {
            $this->services[sprintf('%s.%s', $name, $method)] = [$service, $method];
        }
    }

    /**
     * Start
     * @throws \Swoole\Exception
     */
    public function start()
    {
        $server = $this->server = new \Mix\Server\Server($this->host, $this->port, false, $this->reusePort);
        $server->set([
            'open_eof_check' => true,
            'package_eof'    => Constants::EOF,
        ]);
        $server->handle(function (Connection $conn) {
            $this->handle($conn);
        });
        $server->start();
    }

    /**
     * 连接处理
     * @param Connection $conn
     * @throws \Throwable
     */
    protected function handle(Connection $conn)
    {
        // 消息发送
        $sendChan = new Channel();
        xdefer(function () use ($sendChan) {
            $sendChan->close();
        });
        xgo(function () use ($sendChan, $conn) {
            while (true) {
                $data = $sendChan->pop();
                if (!$data) {
                    return;
                }
                try {
                    $conn->send($data);
                } catch (\Throwable $e) {
                    $conn->close();
                    throw $e;
                }
            }
        });
        // 消息读取
        while (true) {
            try {
                $data = $conn->recv();
                $this->call($sendChan, $data);
            } catch (\Throwable $e) {
                // 忽略服务器主动断开连接异常
                if ($e instanceof ReceiveException) {
                    return;
                }
                // 抛出异常
                throw $e;
            }
        }
    }

    /**
     * 执行功能
     * @param Channel $sendChan
     * @param string $data
     */
    protected function call(Channel $sendChan, string $data)
    {
        /**
         * 解析
         * @var Request[] $requests
         * @var bool $single
         */
        try {
            list($single, $requests) = JsonRpcHelper::parseRequests($data);
        } catch (\Throwable $ex) {
            $response = ResponseFactory::createError(-32700, 'Parse error', null);
            JsonRpcHelper::send($sendChan, true, $response);
            return;
        }
        // 处理
        $waitGroup = WaitGroup::new();
        $waitGroup->add(count($requests));
        $responses = [];
        foreach ($requests as $request) {
            xgo(function () use ($request, &$responses, $waitGroup) {
                xdefer(function () use ($waitGroup) {
                    $waitGroup->done();
                });
                // 验证
                if (!JsonRpcHelper::validRequest($request)) {
                    $responses[] = ResponseFactory::createError(-32600, 'Invalid Request', $request->id);
                    return;
                }
                if (!isset($this->services[$request->method])) {
                    $responses[] = ResponseFactory::createError(-32601, 'Method not found', $request->id);
                    return;
                }
                // 执行
                try {
                    $result      = call_user_func($this->services[$request->method], ...$request->params);
                    $result      = is_scalar($result) ? [$result] : $result;
                    $responses[] = ResponseFactory::createResult($result, $request->id);
                } catch (\Throwable $ex) {
                    $responses[] = ResponseFactory::createError($ex->getCode(), $ex->getMessage(), $request->id);
                }
            });
        }
        $waitGroup->wait();
        JsonRpcHelper::send($sendChan, $single, ...$responses);
    }

    /**
     * Shutdown
     * @throws \Swoole\Exception
     */
    public function shutdown()
    {
        $this->server->shutdown();
    }

}
