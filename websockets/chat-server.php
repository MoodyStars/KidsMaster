<?php
// websockets/chat-server.php
// Ratchet WebSocket server scaffold for real-time chat. Requires composer:
// composer require cboden/ratchet
// Run: php websockets/chat-server.php
require __DIR__ . '/vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "ChatServer started\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Expect JSON payload with media_id, user, message
        $data = json_decode($msg, true);
        if (!is_array($data)) return;
        // Broadcast to clients (could filter by room/media_id)
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send(json_encode($data));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

$port = 8080;
$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new ChatServer()
        )
    ),
    $port
);

echo "WebSocket server listening on port {$port}\n";
$server->run();