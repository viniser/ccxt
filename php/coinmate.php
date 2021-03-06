<?php

namespace ccxt;

class coinmate extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'coinmate',
            'name' => 'CoinMate',
            'countries' => array ( 'GB', 'CZ', 'EU' ), // UK, Czech Republic
            'rateLimit' => 1000,
            'hasCORS' => true,
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/27811229-c1efb510-606c-11e7-9a36-84ba2ce412d8.jpg',
                'api' => 'https://coinmate.io/api',
                'www' => 'https://coinmate.io',
                'doc' => array (
                    'http://docs.coinmate.apiary.io',
                    'https://coinmate.io/developers',
                ),
            ),
            'requiredCredentials' => array (
                'apiKey' => true,
                'secret' => true,
                'uid' => true,
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        'orderBook',
                        'ticker',
                        'transactions',
                    ),
                ),
                'private' => array (
                    'post' => array (
                        'balances',
                        'bitcoinWithdrawal',
                        'bitcoinDepositAddresses',
                        'buyInstant',
                        'buyLimit',
                        'cancelOrder',
                        'cancelOrderWithInfo',
                        'createVoucher',
                        'openOrders',
                        'redeemVoucher',
                        'sellInstant',
                        'sellLimit',
                        'transactionHistory',
                        'unconfirmedBitcoinDeposits',
                    ),
                ),
            ),
            'markets' => array (
                'BTC/EUR' => array ( 'id' => 'BTC_EUR', 'symbol' => 'BTC/EUR', 'base' => 'BTC', 'quote' => 'EUR', 'precision' => array ( 'amount' => 4, 'price' => 2 )),
                'BTC/CZK' => array ( 'id' => 'BTC_CZK', 'symbol' => 'BTC/CZK', 'base' => 'BTC', 'quote' => 'CZK', 'precision' => array ( 'amount' => 4, 'price' => 2 )),
                'LTC/BTC' => array ( 'id' => 'LTC_BTC', 'symbol' => 'LTC/BTC', 'base' => 'LTC', 'quote' => 'BTC', 'precision' => array ( 'amount' => 4, 'price' => 5 )),
            ),
            'fees' => array (
                'trading' => array (
                    'maker' => 0.0005,
                    'taker' => 0.0035,
                ),
            ),
        ));
    }

    public function fetch_balance ($params = array ()) {
        $response = $this->privatePostBalances ();
        $balances = $response['data'];
        $result = array ( 'info' => $balances );
        $currencies = is_array ($this->currencies) ? array_keys ($this->currencies) : array ();
        for ($i = 0; $i < count ($currencies); $i++) {
            $currency = $currencies[$i];
            $account = $this->account ();
            if (is_array ($balances) && array_key_exists ($currency, $balances)) {
                $account['free'] = $balances[$currency]['available'];
                $account['used'] = $balances[$currency]['reserved'];
                $account['total'] = $balances[$currency]['balance'];
            }
            $result[$currency] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $params = array ()) {
        $response = $this->publicGetOrderBook (array_merge (array (
            'currencyPair' => $this->market_id($symbol),
            'groupByPriceLimit' => 'False',
        ), $params));
        $orderbook = $response['data'];
        $timestamp = $orderbook['timestamp'] * 1000;
        return $this->parse_order_book($orderbook, $timestamp, 'bids', 'asks', 'price', 'amount');
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $response = $this->publicGetTicker (array_merge (array (
            'currencyPair' => $this->market_id($symbol),
        ), $params));
        $ticker = $response['data'];
        $timestamp = $ticker['timestamp'] * 1000;
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => floatval ($ticker['high']),
            'low' => floatval ($ticker['low']),
            'bid' => floatval ($ticker['bid']),
            'ask' => floatval ($ticker['ask']),
            'vwap' => null,
            'open' => null,
            'close' => null,
            'first' => null,
            'last' => floatval ($ticker['last']),
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => floatval ($ticker['amount']),
            'quoteVolume' => null,
            'info' => $ticker,
        );
    }

    public function parse_trade ($trade, $market = null) {
        if (!$market)
            $market = $this->markets_by_id[$trade['currencyPair']];
        return array (
            'id' => $trade['transactionId'],
            'info' => $trade,
            'timestamp' => $trade['timestamp'],
            'datetime' => $this->iso8601 ($trade['timestamp']),
            'symbol' => $market['symbol'],
            'type' => null,
            'side' => null,
            'price' => $trade['price'],
            'amount' => $trade['amount'],
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        $market = $this->market ($symbol);
        $response = $this->publicGetTransactions (array_merge (array (
            'currencyPair' => $market['id'],
            'minutesIntoHistory' => 10,
        ), $params));
        return $this->parse_trades($response['data'], $market, $since, $limit);
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $method = 'privatePost' . $this->capitalize ($side);
        $order = array (
            'currencyPair' => $this->market_id($symbol),
        );
        if ($type == 'market') {
            if ($side == 'buy')
                $order['total'] = $amount; // $amount in fiat
            else
                $order['amount'] = $amount; // $amount in fiat
            $method .= 'Instant';
        } else {
            $order['amount'] = $amount; // $amount in crypto
            $order['price'] = $price;
            $method .= $this->capitalize ($type);
        }
        $response = $this->$method (array_merge ($order, $params));
        return array (
            'info' => $response,
            'id' => (string) $response['data'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        return $this->privatePostCancelOrder (array ( 'orderId' => $id ));
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'] . '/' . $path;
        if ($api == 'public') {
            if ($params)
                $url .= '?' . $this->urlencode ($params);
        } else {
            $this->check_required_credentials();
            $nonce = (string) $this->nonce ();
            $auth = $nonce . $this->uid . $this->apiKey;
            $signature = $this->hmac ($this->encode ($auth), $this->encode ($this->secret));
            $body = $this->urlencode (array_merge (array (
                'clientId' => $this->uid,
                'nonce' => $nonce,
                'publicKey' => $this->apiKey,
                'signature' => strtoupper ($signature),
            ), $params));
            $headers = array (
                'Content-Type' => 'application/x-www-form-urlencoded',
            );
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function request ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (is_array ($response) && array_key_exists ('error', $response))
            if ($response['error'])
                throw new ExchangeError ($this->id . ' ' . $this->json ($response));
        return $response;
    }
}
