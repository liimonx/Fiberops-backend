const http = require("http");
const { WebSocketServer } = require("ws");
const Redis = require("ioredis");

const PORT = Number(process.env.WS_PORT || 8080);
const REDIS_HOST = process.env.REDIS_HOST || "127.0.0.1";
const REDIS_PORT = Number(process.env.REDIS_PORT || 6379);
const MOCK_MODE = process.env.MIKROTIK_MOCK === "true";

const server = http.createServer((_req, res) => {
  res.writeHead(200, { "Content-Type": "text/plain" });
  res.end("FiberOps WebSocket gateway");
});

const wss = new WebSocketServer({ server, path: "/ws" });
const clients = new Set();

function buildHeartbeat() {
  return {
    type: "heartbeat",
    data: {
      serverTime: new Date().toISOString(),
      connectedClients: clients.size,
    },
  };
}

function sendHeartbeat(socket) {
  socket.send(JSON.stringify(buildHeartbeat()));
}

function broadcast(message) {
  const payload = JSON.stringify(message);

  for (const socket of clients) {
    if (socket.readyState === socket.OPEN) {
      socket.send(payload);
    }
  }
}

wss.on("connection", (socket) => {
  clients.add(socket);
  sendHeartbeat(socket);

  const heartbeatInterval = setInterval(() => {
    if (socket.readyState === socket.OPEN) {
      sendHeartbeat(socket);
    }
  }, 30_000);

  socket.on("close", () => {
    clients.delete(socket);
    clearInterval(heartbeatInterval);
  });
});

const subscriber = new Redis({
  host: REDIS_HOST,
  port: REDIS_PORT,
});

subscriber.psubscribe("org:*:network", (error) => {
  if (error) {
    console.error("Failed to subscribe to Redis network channels:", error);
  } else {
    console.log("Subscribed to org:*:network");
  }
});

subscriber.on("pmessage", (_pattern, _channel, message) => {
  try {
    const parsed = JSON.parse(message);
    broadcast(parsed);
  } catch (error) {
    console.error("Invalid Redis network message:", error);
  }
});

if (MOCK_MODE) {
  const assetIds = ["pop-dhaka-01", "onu-cust-001", "jb-gulshan-01", "cust-001", "cust-002"];
  const statuses = ["active", "warning", "error", "inactive"];

  setInterval(() => {
    const assetId = assetIds[Math.floor(Math.random() * assetIds.length)];
    const status = statuses[Math.floor(Math.random() * statuses.length)];

    broadcast({
      type: "status_broadcast",
      data: {
        nodeId: assetId,
        status,
        timestamp: new Date().toISOString(),
      },
    });
  }, 10_000);
}

server.listen(PORT, () => {
  console.log(`FiberOps WebSocket gateway listening on ws://localhost:${PORT}/ws`);
});
