# Laravel Reverb - WebSocket Server Documentation

Laravel Reverb provides real-time WebSocket capabilities for broadcasting events to connected clients. This project includes a fully configured Reverb service running in Docker.

## 📡 Configuration

Reverb is pre-configured with the following settings:

- **WebSocket URL**: ws://localhost:8080
- **App ID**: 681983
- **App Key**: `qvou8nwiyg3h4rjbp3q7`
- **Host**: reverb_engage (internal Docker network)
- **Port**: 8080

## 🚀 Quick Start

### 1. Initial Setup (First Time Only)

If Reverb is not yet installed in your project:

```bash
make reverb-install
```

This command will:
- Install the Laravel Reverb package
- Publish the configuration files
- Generate unique app credentials (APP_ID, KEY, SECRET)
- Update your `.env` file with the necessary variables

**Note:** This step is only needed once. The project already includes Reverb configuration.

### 2. Start Reverb Service

```bash
make reverb-start
make reverb-status  # Verify it's running
```

### 3. Test WebSocket Events

```bash
# Quick test
make reverb-test

# Test specific event types
make reverb-test-stats      # Statistics on public-stats channel
make reverb-test-message    # Messages on public-messages channel
make reverb-test-notification  # Notifications on public-notifications

# See all available test commands
make help | grep reverb-test
```

### 4. View Test Dashboard

- Open http://localhost:1310/reverb-test-complete.html
- App Key: `qvou8nwiyg3h4rjbp3q7` (may change with make reverb-install)
- Subscribe to channels and watch events in real-time

## 📋 Available Commands

### Server Management

| Command | Description |
|---------|-------------|
| `make reverb-install` | Install Laravel Reverb (initial setup only) |
| `make reverb-start` | Start the Reverb WebSocket server |
| `make reverb-stop` | Stop the Reverb server |
| `make reverb-restart` | Restart the Reverb server |
| `make reverb-logs` | View real-time Reverb logs |
| `make reverb-status` | Check if Reverb is running |

### Public Event Tests

| Command | Description | Channels | Event Name |
|---------|-------------|----------|------------|
| `make reverb-test` | Quick test with public message | `public-messages` | `.message.received` |
| `make reverb-test-stats` | Test statistics event | `public-stats`, `dashboard` | `.stats.updated` |
| `make reverb-test-message` | Test message event | `public-messages`, `notifications` | `.message.received` |
| `make reverb-test-notification` | Test notification event | `public-notifications` | `.notification` |
| `make reverb-test-apideck` | Test Apideck sync event | `public-notifications`, `apideck-sync` | `.apideck.sync.completed` |
| `make reverb-test-simple` | Test simple public event | `public-test` | `.test.message` |
| `make reverb-test-custom` | Custom channel/event test | Configurable | Configurable |

### Private Event Tests

| Command | Description | Channels | Event Name |
|---------|-------------|----------|------------|
| `make reverb-test-user-private` | Test private user notification | `private-user.{userId}` | `.notification.received` |
| `make reverb-test-team-private` | Test private team update | `private-team.{teamId}` | `.team.updated` |
| `make reverb-test-financer-private` | Test private financer activity | `private-financer.{financerId}` | `.activity.{type}` |

### Utilities

| Command | Description |
|---------|-------------|
| `make reverb-test-all` | Run all test events |

## 📨 Broadcasting Events

### Public Channels

Public channels don't require authentication and are perfect for broadcasting general information:

```php
// Broadcast a public message
broadcast(new \App\Events\Testing\PublicMessageEvent(
    'Title',
    'Message content',
    'info' // type: info, success, warning, error
));

// Broadcast statistics
broadcast(new \App\Events\Testing\PublicStatsEvent([
    'users_online' => 100,
    'revenue' => 5000
]));
```

### Private Channels

Private channels require authentication and are used for user-specific data:

```php
// User notification (requires authenticated user)
$user = User::find(1);
broadcast(new \App\Events\Testing\PrivateUserNotification(
    $user,
    'Private Notification',
    'This is a private message'
));

// Team updates
$team = Team::find(1);
broadcast(new \App\Events\Testing\PrivateTeamUpdate(
    $team,
    'members_updated',
    ['added' => 2, 'removed' => 1]
));

// Financer activity
$financer = Financer::find(1);
broadcast(new \App\Events\Testing\PrivateFinancerActivity(
    $financer,
    'credit_allocated',
    ['amount' => 1000, 'user_id' => 123]
));
```

## 🧪 Testing WebSocket Connections

### Available Test Channels

**Public Channels** (no authentication required):
- `notifications` - General notifications
- `public-messages` - Public messages (`.message.received`)
- `public-stats` - Statistics updates (`.stats.updated`)
- `dashboard` - Dashboard updates (`.stats.updated`)
- `apideck-sync` - Apideck synchronization events

**Private Channels** (require authentication):
- `private-user.{userId}` - User-specific notifications
- `private-team.{teamId}` - Team updates
- `private-financer.{financerId}` - Financer activities

### Testing Workflow

1. **Start Reverb Server**:
   ```bash
   make reverb-start
   make reverb-status  # Verify it's running
   ```

2. **Open Test Dashboard**:
   - Navigate to http://localhost:1310/reverb-test-complete.html
   - Enter App Key: `qvou8nwiyg3h4rjbp3q7`
   - Click "Se connecter"

3. **Subscribe to Channels**:
   - Click the "+" button next to channels you want to test
   - For private channels, ensure you're authenticated and set bearer token

4. **Send Test Events**:
   ```bash
   # Test all public events
   make reverb-test-stats
   make reverb-test-message
   make reverb-test-notification
   
   # Test private events (requires data in DB)
   make reverb-test-user-private
   make reverb-test-team-private
   make reverb-test-financer-private
   ```

5. **Verify Reception**:
   - Check the dashboard for received events
   - Events appear in real-time with full payload data

### Manual Testing with Tinker

```bash
# Test Statistics Event
docker compose exec app_engage php artisan tinker --execute="broadcast(new \App\Events\Testing\PublicStatsEvent(['users' => 100, 'revenue' => 5000]))"

# Test Message Event
docker compose exec app_engage php artisan tinker --execute="broadcast(new \App\Events\Testing\PublicMessageEvent('Test', 'Hello Reverb!', 'success'))"

# Test Private User Notification
docker compose exec app_engage php artisan tinker --execute="
    \$user = \App\Models\User::first();
    broadcast(new \App\Events\Testing\PrivateUserNotification(\$user, 'Private Test', 'This is a private message'));
"
```

## 🔐 Testing Private Channels

### Prerequisites

1. **Database with test data**:
   ```bash
   make migrate-fresh  # This will seed test data
   ```

2. **Authentication required** - Private channels need JWT authentication

### Option 1: Backend Testing Only

```bash
# These commands automatically use the first user/team/financer in DB
make reverb-test-user-private
make reverb-test-team-private
make reverb-test-financer-private
```

### Option 2: Full Frontend Testing with Auth

1. **Get a valid JWT token** from AWS Cognito

2. **Configure Echo client**:
   ```javascript
   const echo = new Echo({
       broadcaster: 'reverb',
       key: 'qvou8nwiyg3h4rjbp3q7',
       wsHost: 'localhost',
       wsPort: 8080,
       forceTLS: false,
       auth: {
           headers: {
               Authorization: `Bearer ${yourJWTToken}`,
           },
       },
       authEndpoint: 'http://localhost:1310/api/v1/broadcasting/auth',
   });
   ```

3. **Subscribe to private channel**:
   ```javascript
   echo.private('user.123')
       .listen('.notification.received', (data) => {
           console.log('Private notification received:', data);
       });
   ```

### Private Channel Authorization Flow

1. Client sends request to `/broadcasting/auth` with Bearer token
2. Laravel validates the JWT token
3. Laravel checks user permissions for the channel:
   - `private-user.{id}` - only that specific user
   - `private-team.{id}` - only team members
   - `private-financer.{id}` - only users with financer permissions
4. Returns authorization response

## 📝 Event Structure

### Frontend Event Listening

```javascript
// Public channels
echo.channel('public-stats')
    .listen('.stats.updated', (data) => {
        console.log('Stats updated:', data);
        // data: { stats: {...}, period: 'daily', timestamp: '...' }
    });

echo.channel('public-messages')
    .listen('.message.received', (data) => {
        console.log('Message received:', data);
        // data: { title: '...', message: '...', type: 'info|success|warning|error', timestamp: '...' }
    });

// Private channels (requires authentication)
echo.private(`user.${userId}`)
    .listen('.notification.received', (data) => {
        console.log('Private notification:', data);
        // data: { title: '...', message: '...', type: '...', actions: [...], user_id: '...', timestamp: '...' }
    });

echo.private(`team.${teamId}`)
    .listen('.team.updated', (data) => {
        console.log('Team updated:', data);
        // data: { team_id: '...', team_name: '...', update_type: '...', changes: {...}, timestamp: '...' }
    });
```

## 🐳 Docker Integration

Reverb runs as a separate Docker service configured in `docker-compose.yml`:

```yaml
reverb_engage:
  build:
    context: .
    dockerfile: ./docker/php/Dockerfile
  container_name: reverb_engage
  command: php artisan reverb:start --host=0.0.0.0 --port=8080 --debug
  ports:
    - "8080:8080"
  depends_on:
    - db_engage
    - redis-cluster
  networks:
    - engage_network
  volumes:
    - .:/var/www
  environment:
    - BROADCAST_CONNECTION=reverb
    - REVERB_APP_ID=681983
    - REVERB_APP_KEY=qvou8nwiyg3h4rjbp3q7
    - REVERB_HOST=reverb_engage
    - REVERB_PORT=8080
```

## 🔧 Troubleshooting

### Reverb not starting

```bash
# Check if port 8080 is already in use
lsof -i :8080

# Restart the service
make reverb-restart

# Check logs
make reverb-logs
```

### Events not received

```bash
# Check queue is processing
make queue

# View Reverb logs
make reverb-logs

# Verify Redis connection
docker compose exec app_engage php artisan tinker --execute="Redis::ping()"
```

### Connection refused

- Ensure Reverb is running: `make reverb-status`
- Check `.env` has correct `BROADCAST_CONNECTION=reverb`
- Verify `REVERB_HOST=reverb_engage` in `.env`
- Check firewall/network settings for port 8080

### Authentication issues with private channels

- Verify JWT token is valid
- Check user has necessary permissions
- Ensure `/broadcasting/auth` endpoint is accessible
- Check CORS settings if frontend is on different domain

## 🚀 Integration with Your Application

### Creating a Broadcast Event

```php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
            new PrivateChannel('user.' . $this->order->user_id)
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'total' => $this->order->total,
            'status' => $this->order->status,
        ];
    }
}
```

### Broadcasting the Event

```php
// In your controller or service
$order = Order::create($data);

// Broadcast immediately
broadcast(new OrderCreated($order));

// Or dispatch to queue
OrderCreated::dispatch($order);
```

### Frontend Integration

```javascript
// Listen for the event
echo.channel('orders')
    .listen('.order.created', (e) => {
        console.log('New order created:', e);
        // Update UI accordingly
    });

// For private channel
echo.private(`user.${userId}`)
    .listen('.order.created', (e) => {
        console.log('Your order was created:', e);
        // Show notification to user
    });
```

## 📚 Additional Resources

- [Laravel Broadcasting Documentation](https://laravel.com/docs/broadcasting)
- [Laravel Reverb Documentation](https://laravel.com/docs/reverb)
- [Echo JavaScript Library](https://github.com/laravel/echo)
- Internal Confluence: "Real-time Features with Reverb"

---

**Last Updated**: 2025-09-06  
**Maintainer**: Équipe Hexeko
