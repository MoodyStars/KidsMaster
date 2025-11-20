<?php
// websockets/chat-server.php (updated)
// Room-aware Ratchet WebSocket server that subscribes to Redis pub/sub for channel chat messages.
// It optionally persists messages received from WebSocket clients into DB (so WS-only clients are persisted).
//
// Requirements:
//  - composer require cboden/ratchet
//  - phpredis extension recommended
//
// Run: php websockets/chat-server.php

require __DIR__ . '/vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class RoomChatServer implements MessageComponentInterface {
    protected $clients; // all connections
    protected $rooms;   // channel_id => SplObjectStorage
    protected $redisSub; // Redis subscriber for pub/sub
    protected $pdo; // optional DB connection

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->initDb();
        $this->initRedisSubscriber();
        echo "RoomChatServer started\n";
    }

    protected function initDb() {
        try {
            // mirror db() settings from api.php
            $host = '127.0.0.1';
            $db   = 'kidsmaster';
            $user = 'km_user';
            $pass = 'km_pass';
            $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
            $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
            $this->pdo = new PDO($dsn, $user, $pass, $opts);
        } catch (Exception $e) {
            $this->pdo = null;
            echo "DB init failed: " . $e->getMessage() . "\n";
        }
    }

    protected function initRedisSubscriber() {
        if (!extension_loaded('redis')) return;
        try {
            $r = new Redis();
            $r->connect('127.0.0.1', 6379, 0.5);
            // subscribe in background thread-like loop using a dedicated connection
            $this->redisSub = $r;
            $that = $this;
            // Run subscription in a non-blocking way: use a separate process in real deployments.
            // Here we spawn a background thread via pcntl_fork if available (best-effort).
            if (function_exists('pcntl_fork')) {
                $pid = pcntl_fork();
                if ($pid == -1) {
                    // fork failed, continue without pubsub
                    echo "pcntl_fork failed for redis subscriber\n";
                } elseif ($pid === 0) {
                    // child: subscribe and loop forever to push messages to parent socket processes via Redis channel
                    $r->subscribe(['kidsmaster:channel_chat'], function($r, $chan, $msg) use ($that) {
                        // publish is handled back in parent by the same Redis instance; this child just keeps process alive.
                        // In this simplified script, we echo messages so operator can see them.
                        echo "Redis pubsub received: $msg\n";
                    });
                    exit(0);
                } else {
                    // parent continues; child subscribed
                }
            } else {
                // no pcntl available - we'll not run background subscribe here
                echo "pcntl_fork not available; Redis pub/sub background subscribe not started. HTTP writer still publishes for WS to pick up if another worker handles pubsub.\n";
            }
        } catch (Exception $e) {
            echo "Redis init failed: " . $e->getMessage() . "\n";
            $this->redisSub = null;
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->room = null;
        echo "New WS connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!is_array($data)) return;

        $channel = isset($data['channel_id']) ? (string)$data['channel_id'] : null;
        $payload = [
            'channel_id' => $channel ?? null,
            'user_name' => $data['user_name'] ?? 'guest',
            'user_avatar' => $data['user_avatar'] ?? ($data['avatar'] ?? null),
            'country_code' => $data['country'] ?? null,
            'message' => $data['message'] ?? '',
            'ts' => $data['ts'] ?? date('c')
        ];

        // Persist message to DB if possible (so WS-only clients are stored)
        if ($this->pdo && $channel) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO chat_messages (channel_id, media_id, user_id, user_name, user_avatar, country_code, message, created_at) VALUES (:cid, NULL, :uid, :un, :ua, :cc, :msg, :ts)");
                $stmt->execute([
                    ':cid' => $channel,
                    ':uid' => $data['user_id'] ?? null,
                    ':un'  => $payload['user_name'],
                    ':ua'  => $payload['user_avatar'],
                    ':cc'  => $payload['country_code'],
                    ':msg' => substr($payload['message'],0,1000),
                    ':ts'  => date('Y-m-d H:i:s')
                ]);
                $payload['id'] = (int)$this->pdo->lastInsertId();
            } catch (Exception $e) {
                // ignore DB write failures but log
                echo "DB write failed: " . $e->getMessage() . "\n";
            }
        }

        // publish to redis so other WS instances can pick up
        if (extension_loaded('redis')) {
            try {
                $r = new Redis();
                $r->connect('127.0.0.1', 6379, 0.5);
                $r->publish('kidsmaster:channel_chat', json_encode($payload));
            } catch (Exception $e) {
                // ignore
            }
        }

        // Broadcast to local room
        if ($channel) {
            if ($from->room !== $channel) {
                if ($from->room && isset($this->rooms[$from->room])) {
                    $this->rooms[$from->room]->detach($from);
                }
                $from->room = $channel;
                if (!isset($this->rooms[$channel])) $this->rooms[$channel] = new \SplObjectStorage;
                $this->rooms[$channel]->attach($from);
            }
            foreach ($this->rooms[$channel] as $client) {
                if ($from !== $client) {
                    $client->send(json_encode($payload));
                }
            }
        } else {
            foreach ($this->clients as $client) {
                if ($from !== $client) $client->send(json_encode($payload));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        if ($conn->room && isset($this->rooms[$conn->room])) {
            $this->rooms[$conn->room]->detach($conn);
        }
        echo "Connection {$conn->resourceId} disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Launch server
$port = 8080;
$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new RoomChatServer()
        )
    ),
    $port
);
echo "WebSocket server listening on port {$port}\n";
$server->run();