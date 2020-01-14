<?php

namespace Mix\JsonRpc\Message;

/**
 * Class Response
 * @package Mix\JsonRpc\Message
 */
class Response
{

    /**
     * @var string
     */
    public $jsonrpc;

    /**
     * @var null|Error
     */
    public $error;

    /**
     * @var string
     */
    public $method;

    /**
     * @var array
     */
    public $params;

    /**
     * @var array
     */
    public $result;

    /**
     * @var int|null
     */
    public $id;

}
