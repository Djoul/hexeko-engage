# Financer Metrics API Documentation

The Financer Metrics API provides comprehensive analytics and insights for financers to monitor beneficiary engagement and platform usage.

## 🔒 Authentication & Permissions

- **Required Permission**: `view_financer_metrics`
- **Required Header**: `X-Financer-Context` (financer UUID)
- **Authentication**: JWT Bearer token

## 📊 Available Endpoints

### 1. Dashboard Metrics

```
GET /api/v1/financer/metrics/dashboard
```

Returns a comprehensive overview of all metrics including active beneficiaries, activation rates, session times, module usage, and HR communications.

**Headers:**
- `Authorization: Bearer YOUR_JWT_TOKEN`

**Query Parameters:**
- `period` (optional): Time period for metrics calculation
  - Options: `7_days`, `30_days`, `3_months`, `6_months`, `12_months`
  - Default: `7_days`

### 2. Active Beneficiaries

```
GET /api/v1/financer/metrics/active-beneficiaries
```

Returns daily active user counts and trends for the specified period.

**Response includes:**
- Total active users
- Daily breakdown with counts
- Percentage change from previous period

### 3. Activation Rate

```
GET /api/v1/financer/metrics/activation-rate
```

Returns the percentage of users who have activated their accounts with trend analysis.

**Response includes:**
- Activation rate percentage
- Total users count
- Activated users count
- Trend analysis

### 4. Session Time Analytics

```
GET /api/v1/financer/metrics/session-time
```

Returns median session duration and total session counts with time-based breakdowns.

**Response includes:**
- Median session duration (minutes)
- Total sessions count
- Time-based distribution
- Peak usage hours

### 5. Module Usage Statistics

```
GET /api/v1/financer/metrics/module-usage
```

Returns usage metrics for different modules including unique users and total uses.

**Response includes:**
- Module name
- Unique users per module
- Total usage count
- Most popular features

### 6. HR Communications Metrics

```
GET /api/v1/financer/metrics/hr-communications
```

Returns metrics for article views, tool clicks, and total interactions in the HR communications module.

**Response includes:**
- Article views and unique readers
- Tool clicks and unique users
- Total interactions count
- Most popular content

## 📝 Example Requests

### Dashboard Metrics Request

```bash
curl -X GET "http://localhost:1310/api/v1/financer/metrics/dashboard?period=30_days" \
     -H "Authorization: Bearer YOUR_JWT_TOKEN" \
     -H "X-Financer-Context: 123e4567-e89b-12d3-a456-426614174000" \
     -H "Accept: application/json"
```

### Active Beneficiaries Request

```bash
curl -X GET "http://localhost:1310/api/v1/financer/metrics/active-beneficiaries?period=7_days" \
     -H "Authorization: Bearer YOUR_JWT_TOKEN" \
     -H "X-Financer-Context: 123e4567-e89b-12d3-a456-426614174000" \
     -H "Accept: application/json"
```

## 📤 Example Response

```json
{
  "financer_id": "123e4567-e89b-12d3-a456-426614174000",
  "period": "30_days",
  "metrics": {
    "active_beneficiaries": {
      "total": 150,
      "daily": [
        {"date": "2025-07-01", "count": 45},
        {"date": "2025-07-02", "count": 52},
        {"date": "2025-07-03", "count": 48}
      ],
      "change_percentage": 12.5,
      "trend": "increasing"
    },
    "activation_rate": {
      "rate": 78.5,
      "total_users": 200,
      "activated_users": 157,
      "pending_users": 43,
      "change_percentage": 5.2
    },
    "median_session_time": {
      "median_minutes": 18,
      "total_sessions": 1250,
      "average_minutes": 22,
      "peak_hour": 14
    },
    "module_usage": [
      {
        "name": "vouchers",
        "unique_users": 85,
        "total_uses": 320,
        "average_per_user": 3.76
      },
      {
        "name": "wellbeing",
        "unique_users": 62,
        "total_uses": 180,
        "average_per_user": 2.90
      }
    ],
    "article_viewed": {
      "articles": {
        "views": 450,
        "unique_users": 125,
        "most_viewed": "Benefits Guide"
      },
      "tools": {
        "clicks": 280,
        "unique_users": 90,
        "most_used": "Salary Calculator"
      },
      "total_interactions": 730
    }
  },
  "cache_info": {
    "cached_at": "2025-07-24T12:00:00.000000Z",
    "ttl_seconds": 3600,
    "next_refresh": "2025-07-24T13:00:00.000000Z"
  }
}
```

## ⚙️ Generating Metrics

### Automatic Generation

Metrics are automatically calculated daily via a scheduled command at 2:00 AM UTC:

```php
// In app/Console/Kernel.php
$schedule->command('metrics:generate-financer --all')
         ->dailyAt('02:00')
         ->withoutOverlapping();
```

### Manual Generation

You can manually generate metrics using artisan commands:

```bash
# Generate metrics for all financers
docker-compose exec app_engage php artisan metrics:generate-financer --all

# Generate metrics for a specific financer
docker-compose exec app_engage php artisan metrics:generate-financer --financer=FINANCER_UUID

# Generate metrics for a date range
docker-compose exec app_engage php artisan metrics:generate-financer \
    --all \
    --date-from=2025-07-01 \
    --date-to=2025-07-31

# Dry run to preview what would be generated
docker-compose exec app_engage php artisan metrics:generate-financer --all --dry-run

# Force regeneration (ignores cache)
docker-compose exec app_engage php artisan metrics:generate-financer --all --force

# Verbose output for debugging
docker-compose exec app_engage php artisan metrics:generate-financer --all -vvv
```

## 🗄️ Cache Configuration

### Cache Strategy

The Financer Metrics API uses Redis Cluster for caching with the following configuration:

- **Default TTL**: 1 hour (3600 seconds)
- **Cache Driver**: Redis Cluster
- **Cache Prefix**: `financer_metrics`

### Cache Key Structure

```
{financer_metrics:FINANCER_ID}:METRIC_TYPE:START_DATE:END_DATE
```

Examples:
```
financer_metrics:123e4567:dashboard:2025-07-01:2025-07-31
financer_metrics:123e4567:active_beneficiaries:2025-07-01:2025-07-07
financer_metrics:123e4567:module_usage:2025-07-01:2025-07-31
```

### Cache Management

```bash
# Clear cache for specific financer
docker-compose exec app_engage php artisan cache:forget "financer_metrics:FINANCER_UUID"

# Clear all financer metrics cache
docker-compose exec app_engage php artisan cache:tags financer_metrics --flush

# View cache statistics
docker-compose exec app_engage php artisan cache:stats financer_metrics

# Monitor cache hits/misses
docker-compose exec engage_redis redis-cli monitor | grep financer_metrics
```

## 🔍 Filtering & Pagination

### Available Filters

When querying metrics, you can apply various filters:

```bash
# Filter by period
?period=30_days

# Filter by date range
?date_from=2025-07-01&date_to=2025-07-31

# Filter by module (for module usage endpoint)
?module=vouchers

# Limit results
?limit=100

# Pagination
?page=1&per_page=50
```

### Sorting Options

```bash
# Sort by date (ascending/descending)
?sort=date&order=desc

# Sort by count
?sort=count&order=desc

# Sort by change percentage
?sort=change&order=asc
```

## 📈 Performance Considerations

### Optimization Tips

1. **Use appropriate periods**: Shorter periods (7_days) are faster than longer ones (12_months)
2. **Leverage caching**: Data is cached for 1 hour by default
3. **Batch requests**: Use dashboard endpoint for multiple metrics instead of individual calls
4. **Off-peak generation**: Schedule metric generation during low-traffic hours

### Rate Limiting

- **Default limit**: 60 requests per minute per financer
- **Dashboard endpoint**: 30 requests per minute
- **Bulk operations**: 10 requests per minute

## 🐛 Troubleshooting

### Common Issues

#### No metrics data returned

```bash
# Check if metrics have been generated
docker-compose exec app_engage php artisan metrics:check --financer=FINANCER_UUID

# Force regeneration
docker-compose exec app_engage php artisan metrics:generate-financer --financer=FINANCER_UUID --force
```

#### Stale data

```bash
# Clear cache and regenerate
docker-compose exec app_engage php artisan cache:forget "financer_metrics:FINANCER_UUID"
docker-compose exec app_engage php artisan metrics:generate-financer --financer=FINANCER_UUID
```

#### Permission denied

Ensure the user has the `view_financer_metrics` permission:

```php
// Check in tinker
docker-compose exec app_engage php artisan tinker
>>> $user = User::find($userId);
>>> $user->hasPermissionTo('view_financer_metrics');
```

### Debug Mode

Enable debug mode for detailed metric calculation logs:

```bash
# In .env
METRICS_DEBUG=true
METRICS_LOG_CHANNEL=daily

# View logs
tail -f storage/logs/metrics-*.log
```

## 🔄 Data Sources

Metrics are calculated from the following data sources:

1. **User Activity Logs** - Login times, session durations
2. **Module Usage Tracking** - Feature access, action counts
3. **Content Interactions** - Article views, tool usage
4. **User Profiles** - Activation status, registration dates
5. **Transaction History** - Voucher usage, credit allocations

## 📊 Metric Definitions

### Active Beneficiaries
- **Definition**: Users who have logged in at least once during the period
- **Calculation**: Unique user IDs with session records in the period

### Activation Rate
- **Definition**: Percentage of registered users who have completed profile setup
- **Calculation**: (Activated Users / Total Users) × 100

### Median Session Time
- **Definition**: Middle value of all session durations in the period
- **Calculation**: Sorted session durations, taking the median value

### Module Usage
- **Definition**: Number of unique users and total interactions per module
- **Calculation**: Aggregated from module access logs

## 🔐 Security Considerations

1. **Financer Isolation**: Each financer can only access their own metrics
2. **Permission Checks**: All endpoints require `view_financer_metrics` permission
3. **Data Sanitization**: All inputs are validated and sanitized
4. **Audit Logging**: All metric access is logged for audit purposes
5. **Rate Limiting**: Prevents abuse and ensures fair resource usage

## 📚 Related Documentation

- [API Authentication](./API.md#authentication)
- [Permissions System](./ARCHITECTURE.md#permissions)
- [Redis Cache Configuration](./DOCKER.md#redis-cluster)
- [Monitoring & Logging](./TROUBLESHOOTING.md#monitoring)

---

**Last Updated**: 2025-09-06  
**Maintainer**: Équipe Hexeko  
**API Version**: v1
