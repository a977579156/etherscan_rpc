<?php
/**
 * This file is a part of "furqansiddiqui/ethereum-rpc" package.
 * https://github.com/furqansiddiqui/ethereum-rpc
 *
 * Copyright (c) 2020 Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/furqansiddiqui/ethereum-rpc/blob/master/LICENSE
 */

declare(strict_types=1);

namespace EthereumRPC\API;

use EthereumRPC\API\Personal\RawTransaction;
use EthereumRPC\BcMath;
use EthereumRPC\EthereumRPC;
use EthereumRPC\Exception\GethException;
use EthereumRPC\Validator;
use HttpClient\Response\JSONResponse;

/**
 * Class Personal
 * @package EthereumRPC\API
 */
class Personal
{
    /** @var EthereumRPC */
    private $client;

    /**
     * Personal constructor.
     * @param EthereumRPC $ethereum
     */
    public function __construct(EthereumRPC $ethereum)
    {
        $this->client = $ethereum;
    }

    /**
     * @param string $command
     * @param array|null $params
     * @return JSONResponse
     * @throws GethException
     * @throws \EthereumRPC\Exception\ConnectionException
     * @throws \HttpClient\Exception\HttpClientException
     */
    private function accountsRPC(string $command, ?array $params = null): JSONResponse
    {
        return $this->client->jsonRPC($command, null, $params);
    }

    /**
     * @param string $password
     * @return string
     * @throws GethException
     * @throws \EthereumRPC\Exception\ConnectionException
     * @throws \HttpClient\Exception\HttpClientException
     */
    public function newAccount(string $password): string
    {
        $request = $this->accountsRPC("personal_newAccount", [$password]);
        $account = $request->get("result");
        if (!is_string($account)) {
            throw GethException::unexpectedResultType("personal_newAccount", "string", gettype($account));
        } elseif (!preg_match('/^(0x)?[a-f0-9]{40,42}$/', $account)) {
            throw new GethException('Invalid newly created ETH address');
        }

        return $account;
    }

    public function importRawKey(string $private,string $password):string
    {
        $request = $this->accountsRPC("personal_importRawKey", [$private,$password]);
        $address = $request->get("result");
        if (!is_string($address)) {
            throw GethException::unexpectedResultType("personal_importRawKey", "string", gettype($address));
        }
        return $address;
    }


    /**
     * @param string $from
     * @param string $to
     * @param string $ethAmount
     * @param string $nonce
     * @param string $password
     * @return string
     * @throws GethException
     * @throws \EthereumRPC\Exception\ConnectionException
     * @throws \HttpClient\Exception\HttpClientException
     */
    public function sendEthereum(array $data,string $password): string
    {
        $transaction = [
            "from" => $data['from'],
            "to" => $data['to'],
            "value" => "0x" . dechex(intval(bcmul($data['amount'], bcpow("10", "18"), 0))), // Convert ETH to WEI
            "gas"  =>"0x". dechex($data['gas']),
            "gasPrice"  =>"0x". dechex(bcmul($data['gasPrice'],bcpow("10", "9"))),
        ];
        if(isset($data['nonce']) && $data['nonce'] != ''){
            $transaction['nonce'] = '0x'.dechex($data['nonce']);
        }
        $request = $this->accountsRPC("personal_sendTransaction", [$transaction, $password]);
        $send = $request->get("result");
        if (!is_string($send)) {
            throw GethException::unexpectedResultType("personal_sendTransaction", "string", gettype($send));
        }

        return $send;
    }

    /**
     * @param string $from
     * @param string $to
     * @return RawTransaction
     * @throws \EthereumRPC\Exception\RawTransactionException
     */
    public function transaction(string $from, string $to): RawTransaction
    {
        return new RawTransaction($this, $from, $to);
    }

    /**
     * @param RawTransaction $tx
     * @param string $password
     * @return string
     * @throws GethException
     * @throws \EthereumRPC\Exception\ConnectionException
     * @throws \HttpClient\Exception\HttpClientException
     */
    public function send(RawTransaction $tx, string $password): string
    {
        // Required params
        $transaction = [
            "from" => $tx->from,
            "to" => $tx->to,
            "value" => $tx->value
        ];

        // Optional params
        if ($tx->data) {
            $transaction["data"] = $tx->data;
        }

        // Optional gas params
        if ($tx->gas) {
            $transaction["gas"] = "0x" . dechex($tx->gas);
        }

        if (is_string($tx->gasPrice) && Validator::BcAmount($tx->gasPrice)) {
            $transaction["gasPrice"] = "0x" . BcMath::DecHex(bcmul($tx->gasPrice, bcpow("10", "9", 0), 0));
        }
        if ($tx->nonce) {
            $transaction["nonce"] = "0x" . dechex($tx->nonce);
        }
        $request = $this->accountsRPC("personal_sendTransaction", [$transaction, $password]);
        $send = $request->get("result");
        if (!is_string($send)) {
            throw GethException::unexpectedResultType("personal_sendTransaction", "string", gettype($send));
        }

        return $send;
    }

    public function unlock($address,$password)
    {
        $request = $this->accountsRPC("personal_unlockAccount", [$address, $password]);
        $result = $request->get("result");
        if (!is_bool($result)) {
            throw GethException::unexpectedResultType("personal_sendTransaction", "bool", gettype($result));
        }
        return $result;
    }
}
