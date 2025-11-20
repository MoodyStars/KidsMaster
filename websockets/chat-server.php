<?php
// websockets/chat-server.php (UPDATED)
// Room-aware Ratchet WebSocket server. Messages should include channel_id to be broadcast only to that room.
// composer require cboden/ratchet

require __DIR__ . '/vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class RoomChatServer implements MessageComponentInterface {
    protected $clients; // SplObjectStorage of connections
    protected $rooms;   // map channel_id => SplObjectStorage

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        echo "RoomChatServer started\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->room = null; // no room until first message or query param
        echo "New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!is_array($data)) return;

        // expect channel_id in payload
        $channel = isset($data['channel_id']) ? (string)$data['channel_id'] : null;
        if ($channel) {
            // attach to room if not already
            if ($from->room !== $channel) {
                // remove from old
                if ($from->room && isset($this->rooms[$from->room])) {
                    $this->rooms[$from->room]->detach($from);
                }
                $from->room = $channel;
                if (!isset($this->rooms[$channel])) $this->rooms[$channel] = new \SplObjectStorage;
                $this->rooms[$channel]->attach($from);
            }
            // broadcast to room
            foreach ($this->rooms[$channel] as $client) {
                if ($from !== $client) {
                    $client->send(json_encode($data));
                }
            }
        } else {
            // no channel specified: broadcast to all (legacy)
            foreach ($this->clients as $client) {
                if ($from !== $client) $client->send($msg);
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