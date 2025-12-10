# 📦 Prerequisites: Make, PHP, Composer, Laravel, Docker, Claude Code
.PHONY: all lint rector stan test quality-check clean docker-clean docker-deep-clean docker-restart docker-clean-worktree docs help test-with-report test-optimized test-parallel test-failed test-voucher-recovery test-amilon claude-todo-wt claude-todo-br claude-doc claude-confluence claude-quick-fix claude-analyze claude-resume claude-init claude-help restore git-cache-clear migrate migrate-fresh migrate-for-fullmetrics migrate-parallel-fresh queue-start amilon-sync seed-amilon-order seed-metrics reverb-start reverb-stop reverb-install reverb-restart reverb-logs reverb-status reverb-test reverb-test-all broadcast-list broadcast-user broadcast-financer broadcast-division broadcast-team broadcast-public docs-open ngrok ngrok-setup ngrok-status ngrok-stop ngrok-logs ngrok-background ngrok-url slack-send slack-test slack-send-file slack-send-error slack-send-success test-groups-check

# Default shell for better error handling
SHELL := /bin/bash

# Docker compose command (can be overridden)
DOCKER_COMPOSE := docker-compose
DOCKER_EXEC := $(DOCKER_COMPOSE) exec -T app_engage

# Claude Code command
CLAUDE := claude --dangerously-skip-permissions

# Colors for output
RED := \033[0;31m
GREEN := \033[0;32m
YELLOW := \033[1;33m
BLUE := \033[0;34m
PURPLE := \033[0;35m
CYAN := \033[0;36m
NC := \033[0m # No Color

## 🎯 Default target
all: help

# ============================================================================
# 🔧 CODE QUALITY COMMANDS
# ============================================================================

## 🔧 Format code with Laravel Pint
lint:
	@echo -e "$(BLUE)🚀 Launching Pint...$(NC)"
	@./vendor/bin/pint

## 🛠 Apply PHP modernization rules with Rector
rector:
	@echo -e "$(BLUE)🔄 Launching Rector...$(NC)"
	@./vendor/bin/rector process

## 🔍 Static analysis with PHPStan (level 9)
stan:
	@clear
	@echo -e "$(BLUE)🔬 Launching PHPStan...$(NC)"
	@./vendor/bin/phpstan analyse --memory-limit=2G

# ============================================================================
# 🧪 TESTING COMMANDS
# ============================================================================

## 🔍 Verify test environment configuration
test-verify:
	@echo -e "$(BLUE)🔍 Verifying test environment configuration...$(NC)"
	@./scripts/verify-test-environment.sh

## 🧪 Launch the complete test suite with PHPUnit
test:
	@echo -e "$(BLUE)🧪 Launching PHPUnit tests...$(NC)"
	@$(DOCKER_EXEC) ./vendor/bin/phpunit --no-coverage

## 🧪 Prepare database and run tests with failed tests export
test-with-report:
	@./scripts/run-tests-simple.sh

## 🧪 Run optimized tests with options (--stop-on-failure, --parallel)
test-optimized:
	@./scripts/run-tests-optimized.sh $(ARGS)

## ⚡ Run Unit and Feature tests in parallel (like GitLab CI)
test-parallel:
	@./scripts/run-tests-parallel.sh $(ARGS)

## 🧪 Run tests for specific groups (usage: make test-group GROUPS="user,apideck")
test-group:
	@./scripts/run-tests-optimized.sh --group "$(GROUPS)" $(ARGS)

## 🔎 Check that all tests have #[Group(...)], fails if any missing
test-groups-check:
	@echo -e "$(BLUE)🔎 Checking #[Group] annotations on tests...$(NC)"
	@bash scripts/check-missing-test-groups.sh

## 🧪 Run only failed tests from previous run
test-failed:
	@echo -e "$(BLUE)🧪 Running previously failed tests...$(NC)"
	@$(DOCKER_EXEC) bash -c 'if [ -f storage/logs/failed-tests.txt ]; then \
		php artisan test --filter="$$(cat storage/logs/failed-tests.txt | tr "\n" "|" | sed "s/|$$//")" --parallel --processes=4; \
	else \
		echo -e "$(YELLOW)No failed tests found. Run make test-with-report first.$(NC)"; \
	fi'

## 🎫 Run voucher recovery tests
test-voucher-recovery:
	@echo -e "$(BLUE)🎫 Running voucher recovery tests...$(NC)"
	@$(DOCKER_EXEC) php artisan test --filter="VoucherRecovery" --no-coverage

## 🎫 Run all voucher/amilon tests
test-amilon:
	@echo -e "$(BLUE)🎫 Running all Amilon voucher tests...$(NC)"
	@$(DOCKER_EXEC) php artisan test --group=amilon --no-coverage

## 🔄 Run multiple test iterations for stability check
# Usage: make test-multiple [ITERATIONS=5] [GROUPS=amilon,voucher]
test-multiple:
	@echo -e "$(BLUE)🔄 Running multiple test iterations...$(NC)"
	@./scripts/run-multiple-tests.sh $(or $(ITERATIONS),5) "$(GROUPS)"

# ============================================================================
# 🐳 DOCKER COMMANDS
# ============================================================================

## 🐳 Docker cleanup (safe - preserves databases)
docker-clean:
	@echo -e "$(BLUE)🐳 Docker cleanup starting...$(NC)"
	@echo -e "$(YELLOW)⚠️  This will preserve your databases and active containers$(NC)"
	@echo -e "$(BLUE)📊 Current Docker usage:$(NC)"
	@docker system df
	@echo ""
	@echo -e "$(BLUE)🧹 Cleaning build cache...$(NC)"
	@docker builder prune -af
	@echo -e "$(BLUE)🧹 Cleaning dangling images...$(NC)"
	@docker image prune -f
	@echo -e "$(BLUE)🧹 Cleaning stopped containers...$(NC)"
	@docker container prune -f
	@echo -e "$(BLUE)🧹 Cleaning unused networks...$(NC)"
	@docker network prune -f
	@echo -e "$(BLUE)🧹 Cleaning unused volumes (preserving active ones)...$(NC)"
	@docker volume prune -f
	@echo ""
	@echo -e "$(BLUE)📊 New Docker usage:$(NC)"
	@docker system df
	@echo -e "$(GREEN)✅ Docker cleanup complete!$(NC)"

## 🚨 Docker deep clean (DANGER - removes all unused resources)
docker-deep-clean:
	@echo -e "$(RED)🚨 WARNING: This will remove ALL unused Docker resources!$(NC)"
	@echo -e "$(YELLOW)⚠️  Your databases will be preserved if containers are running$(NC)"
	@read -p "Are you sure? (y/N) " -n 1 -r; \
	echo ""; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker system prune -af --volumes; \
		docker builder prune -af; \
		echo -e "$(GREEN)✅ Deep cleanup complete!$(NC)"; \
	else \
		echo -e "$(RED)❌ Cleanup cancelled$(NC)"; \
	fi

## 🔄 Complete Docker restart (removes orphaned containers and networks)
docker-restart:
	@echo -e "$(YELLOW)🔄 Starting complete Docker restart...$(NC)"
	@echo -e "$(BLUE)Step 1/4: Stopping all containers...$(NC)"
	@$(DOCKER_COMPOSE) down || true
	@echo -e "$(BLUE)Step 2/4: Removing orphaned containers...$(NC)"
	@docker ps -a | grep "up-engage-api" | awk '{print $$1}' | xargs -r docker rm -f 2>/dev/null || true
	@echo -e "$(BLUE)Step 3/4: Removing the network...$(NC)"
	@docker network rm up-engage-api_app_engage_network 2>/dev/null || true
	@echo -e "$(BLUE)Step 4/4: Starting fresh containers...$(NC)"
	@$(DOCKER_COMPOSE) up -d
	@echo -e "$(GREEN)✅ Docker restart complete!$(NC)"
	@echo -e "$(BLUE)📊 Container status:$(NC)"
	@$(DOCKER_COMPOSE) ps

## 🧹 Clean worktree Docker containers and redis clusters
docker-clean-worktree:
	@echo -e "$(BLUE)🧹 Cleaning worktree and redis cluster containers...$(NC)"
	@echo -e "$(YELLOW)⚠️  This will remove:$(NC)"
	@echo -e "$(YELLOW)   - All containers with 'worktree' in their name$(NC)"
	@echo -e "$(YELLOW)   - All redis_cluster_* containers (but NOT redis-cluster)$(NC)"
	@echo -e ""
	@echo -e "$(BLUE)📊 Containers to be removed:$(NC)"
	@echo -e "$(CYAN)Worktree containers:$(NC)"
	@docker ps -a | grep -i "worktree" || echo "  No worktree containers found"
	@echo -e "$(CYAN)Redis cluster containers:$(NC)"
	@docker ps -a | grep "redis_cluster_" || echo "  No redis cluster containers found"
	@echo ""
	@read -p "Continue with cleanup? (y/N) " -n 1 -r; \
	echo ""; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		echo -e "$(BLUE)Stopping worktree containers...$(NC)"; \
		docker ps -a | grep -i "worktree" | awk '{print $$1}' | xargs -r docker stop 2>/dev/null || true; \
		echo -e "$(BLUE)Stopping redis cluster containers...$(NC)"; \
		docker ps -a | grep "redis_cluster_" | awk '{print $$1}' | xargs -r docker stop 2>/dev/null || true; \
		echo -e "$(BLUE)Removing worktree containers...$(NC)"; \
		docker ps -a | grep -i "worktree" | awk '{print $$1}' | xargs -r docker rm -f 2>/dev/null || true; \
		echo -e "$(BLUE)Removing redis cluster containers...$(NC)"; \
		docker ps -a | grep "redis_cluster_" | awk '{print $$1}' | xargs -r docker rm -f 2>/dev/null || true; \
		echo -e "$(BLUE)Removing worktree networks...$(NC)"; \
		docker network ls | grep -i "worktree" | awk '{print $$1}' | xargs -r docker network rm 2>/dev/null || true; \
		echo -e "$(BLUE)Removing worktree volumes...$(NC)"; \
		docker volume ls | grep -i "worktree" | awk '{print $$2}' | xargs -r docker volume rm 2>/dev/null || true; \
		echo -e "$(GREEN)✅ Cleanup complete!$(NC)"; \
	else \
		echo -e "$(RED)❌ Cleanup cancelled$(NC)"; \
	fi

# ============================================================================
# 🚀 ARTISAN COMMANDS
# ============================================================================

# Database Commands
## 📤 Run database migrations
migrate:
	@echo -e "$(BLUE)📤 Running database migrations...$(NC)"
	@$(DOCKER_EXEC) php artisan migrate
	@echo -e "$(GREEN)✅ Migrations completed successfully!$(NC)"

## 🔄 Fresh database migration with seeders
migrate-fresh:
	@echo -e "$(BLUE)🔄 Running fresh migration with seeders...$(NC)"
	@$(DOCKER_EXEC) php artisan migrate:fresh --seed
	@echo -e "$(GREEN)✅ Database migrated and seeded successfully!$(NC)"

## 🔄 Fresh migration for all parallel test schemas
migrate-parallel-fresh:
	@echo -e "$(BLUE)🔄 Running fresh migration on all parallel test schemas...$(NC)"
	@echo -e "$(YELLOW)⚠️  This will reset all test worker schemas (test_1 to test_12)$(NC)"
	@read -p "Are you sure? (y/N) " -n 1 -r; \
	echo ""; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		echo -e "$(BLUE)📦 Starting parallel test schemas refresh...$(NC)"; \
		$(DOCKER_EXEC) php scripts/refresh-parallel-test-schemas.php; \
	else \
		echo -e "$(RED)❌ Migration cancelled$(NC)"; \
	fi

## 🌱 Seed Metric test data
seed-metrics:
	@echo -e "$(BLUE)🌱 Seeding Fake Metrics data...$(NC)"
	@$(DOCKER_EXEC) php artisan db:seed --class=EngagementMetricsSeeder
	@echo -e "$(GREEN)✅ Fake Metrics data seeded!$(NC)"

## 🚀 Migrate fresh and seed metrics test data
migrate-for-fullmetrics:
	@echo -e "$(BLUE)🚀 Starting full metrics setup...$(NC)"
	@echo -e "$(YELLOW)⚠️  This will reset your database!$(NC)"
	@$(MAKE) migrate-fresh
	@echo -e "$(BLUE)🎫 Seeding Amilon orders...$(NC)"
	@$(MAKE) seed-amilon-order
	@echo -e "$(BLUE)📊 Seeding metrics data...$(NC)"
	@$(MAKE) seed-metrics
	@echo -e "$(GREEN)✅ Full metrics setup completed!$(NC)"

# 🌱 Seed Amilon test data
seed-amilon-order:
	@echo -e "$(BLUE)🌱 Seeding Amilon test orders...$(NC)"
	@$(DOCKER_EXEC) php artisan db:seed --class=App\\Integrations\\Vouchers\\Amilon\\Database\\seeds\\AmilonOrderSeeder
	@echo -e "$(GREEN)✅ Amilon test data seeded!$(NC)"

## 🧾 Seed Invoice test data
seed-invoices:
	@echo -e "$(BLUE)🧾 Seeding invoice test data...$(NC)"
	@$(DOCKER_EXEC) php artisan db:seed --class=InvoiceSeeder
	@echo -e "$(GREEN)✅ Invoice test data seeded!$(NC)"

# Queue & Real-time Commands
## 🛑 Stop all queue workers
queue-stop:
	@echo -e "$(RED)🛑 Stopping all queue workers...$(NC)"
	@$(DOCKER_EXEC) php artisan queue:restart

## 🚀 Start queue worker without timeout (for batch jobs)
queue-start:
	@echo -e "$(PURPLE)🚀 Starting queue worker for batch processing (no timeout, 512MB memory)...$(NC)"
	@echo -e "$(YELLOW)Press Ctrl+C to stop$(NC)"
	@$(DOCKER_EXEC) php artisan queue:listen \
		--queue=default \
		--memory=512 \
		--timeout=0 \
		--tries=3 \
		--backoff=30
	@echo -e "$(GREEN)✅ Queue workers stopped$(NC)"

## 🧹 Clear all queued jobs
queue-clear:
	@echo -e "$(YELLOW)🧹 Clearing all queued jobs...$(NC)"
	@$(DOCKER_EXEC) php artisan queue:clear
	@$(DOCKER_EXEC) php artisan queue:flush
	@echo -e "$(GREEN)✅ All queued jobs cleared$(NC)"

## 🔌 Start Reverb WebSocket server
reverb-start:
	@echo -e "$(BLUE)🔌 Starting Reverb WebSocket server...$(NC)"
	@echo -e "$(YELLOW)Note: Reverb runs via Docker service reverb_engage$(NC)"
	@$(DOCKER_COMPOSE) up -d reverb_engage
	@echo -e "$(GREEN)✅ Reverb started on port 8080$(NC)"

## 🔧 Install Reverb and configure environment
reverb-install:
	@echo -e "$(BLUE)🔧 Installing Laravel Reverb...$(NC)"
	@$(DOCKER_EXEC) php artisan reverb:install
	@echo -e "$(GREEN)✅ Reverb installed! Check your .env file for the new configuration.$(NC)"

## 🛑 Stop Reverb WebSocket server
reverb-stop:
	@echo -e "$(RED)🛑 Stopping Reverb WebSocket server...$(NC)"
	@$(DOCKER_COMPOSE) stop reverb_engage
	@echo -e "$(GREEN)✅ Reverb stopped$(NC)"

## 🔄 Restart Reverb WebSocket server
reverb-restart:
	@echo -e "$(BLUE)🔄 Restarting Reverb WebSocket server...$(NC)"
	@$(DOCKER_COMPOSE) restart reverb_engage
	@echo -e "$(GREEN)✅ Reverb restarted$(NC)"

## 📊 Show Reverb logs
reverb-logs:
	@echo -e "$(BLUE)📊 Showing Reverb logs (Ctrl+C to exit)...$(NC)"
	@$(DOCKER_COMPOSE) logs -f reverb_engage

## 🔍 Check Reverb status
reverb-status:
	@echo -e "$(BLUE)🔍 Checking Reverb status...$(NC)"
	@if $(DOCKER_COMPOSE) ps reverb_engage | grep -q "Up"; then \
		echo -e "$(GREEN)✅ Reverb is running$(NC)"; \
		echo -e "$(CYAN)WebSocket URL: ws://localhost:8080$(NC)"; \
		echo -e "$(CYAN)App ID: $$(grep REVERB_APP_ID .env | cut -d'=' -f2)$(NC)"; \
		echo -e "$(CYAN)Key: $$(grep REVERB_APP_KEY .env | cut -d'=' -f2)$(NC)"; \
	else \
		echo -e "$(RED)❌ Reverb is not running$(NC)"; \
		echo -e "$(YELLOW)Run 'make reverb-start' to start it$(NC)"; \
	fi

## 🧪 Test Reverb with a simple public event
reverb-test:
	@echo -e "$(BLUE)🧪 Testing Reverb with a public message...$(NC)"
	@$(DOCKER_EXEC) php artisan tinker --execute="broadcast(new \App\Events\Testing\PublicMessageEvent('Test', 'Hello from Reverb!', 'success'))"
	@echo -e "$(GREEN)✅ Test event broadcasted!$(NC)"
	@echo -e "$(YELLOW)Check your dashboard at http://localhost:1310/reverb-test-complete.html$(NC)"

## 🎭 Run comprehensive Reverb tests
reverb-test-all:
	@echo -e "$(BLUE)🎭 Running comprehensive Reverb tests...$(NC)"
	@$(DOCKER_EXEC) php test-reverb-all-channels.php
	@echo -e "$(GREEN)✅ All tests completed!$(NC)"
	@echo -e "$(YELLOW)Dashboard: http://localhost:1310/reverb-test-complete.html$(NC)"
	@echo -e "$(YELLOW)Simple test: http://localhost:1310/reverb-test-dashboard.html$(NC)"

## 📡 List all available broadcastable events
broadcast-list:
	@echo -e "$(BLUE)📋 Available Broadcastable Events:$(NC)"
	@echo -e ""
	@echo -e "$(GREEN)👤 User Channel Events (use EMAIL):$(NC)"
	@echo -e "  - $(CYAN)PrivateUserNotification$(NC)     - Send notification to user"
	@echo -e "  - $(CYAN)VoucherPurchaseError$(NC)        - Voucher purchase error"
	@echo -e "  - $(CYAN)VoucherPurchaseNotification$(NC) - Voucher purchase success"
	@echo -e "  - $(CYAN)VoucherPaymentStatusUpdate$(NC)  - Payment status update"
	@echo -e "  - $(CYAN)StripePaymentSucceeded$(NC)      - Stripe payment success"
	@echo -e "  - $(CYAN)StripePaymentFailed$(NC)         - Stripe payment failure"
	@echo -e ""
	@echo -e "$(GREEN)🏢 Financer Channel Events (use FINANCER_ID):$(NC)"
	@echo -e "  - $(CYAN)PrivateFinancerActivity$(NC)     - Financer activity update"
	@echo -e "  - $(CYAN)ApideckSyncCompleted$(NC)        - Apideck sync completed"
	@echo -e "  - $(CYAN)ApideckSyncEvent$(NC)            - Apideck sync event"
	@echo -e ""
	@echo -e "$(GREEN)🏛️ Division Channel Events (use DIVISION_ID):$(NC)"
	@echo -e "  - $(CYAN)ApideckSyncCompleted$(NC)        - Apideck sync completed"
	@echo -e ""
	@echo -e "$(GREEN)👥 Team Channel Events (use TEAM_ID):$(NC)"
	@echo -e "  - $(CYAN)PrivateTeamUpdate$(NC)           - Team update event"
	@echo -e ""
	@echo -e "$(GREEN)🌍 Public Channel Events:$(NC)"
	@echo -e "  - $(CYAN)PublicMessageEvent$(NC)          - Public message"
	@echo -e "  - $(CYAN)PublicStatsEvent$(NC)            - Public stats update"
	@echo -e "  - $(CYAN)StatsUpdatedEvent$(NC)           - Stats updated"
	@echo -e "  - $(CYAN)TestPublicEvent$(NC)             - Test public event"
	@echo -e ""
	@echo -e "$(YELLOW)Usage Examples:$(NC)"
	@echo -e "  make broadcast-user EVENT=PrivateUserNotification EMAIL=user@test.com"
	@echo -e "  make broadcast-financer EVENT=PrivateFinancerActivity FINANCER_ID=123"
	@echo -e "  make broadcast-division EVENT=ApideckSyncCompleted DIVISION_ID=456"
	@echo -e "  make broadcast-team EVENT=PrivateTeamUpdate TEAM_ID=789"
	@echo -e "  make broadcast-public EVENT=PublicMessageEvent"

## 👤 Broadcast event to user channel (using email)
broadcast-user:
	@if [ -z "$(EVENT)" ] || [ -z "$(EMAIL)" ]; then \
		echo -e "$(RED)❌ Error: EVENT and EMAIL parameters are required$(NC)"; \
		echo -e "$(YELLOW)Usage: make broadcast-user EVENT=PrivateUserNotification EMAIL=user@example.com$(NC)"; \
		echo -e "$(YELLOW)Run 'make broadcast-list' to see available events$(NC)"; \
		exit 1; \
	fi; \
	echo -e "$(BLUE)👤 Broadcasting $(EVENT) to user $(EMAIL)...$(NC)"; \
	case "$(EVENT)" in \
		"PrivateUserNotification") \
			TITLE=$${TITLE:-"Test Notification"}; \
			MESSAGE=$${MESSAGE:-"This is a test broadcast"}; \
			TYPE=$${TYPE:-"info"}; \
			$(DOCKER_EXEC) php artisan tinker --execute="\$$user = App\\Models\\User::where('email', '$(EMAIL)')->first(); if (\$$user) { broadcast(new App\\Events\\Testing\\PrivateUserNotification(\$$user, '$$TITLE', '$$MESSAGE', '$$TYPE')); echo '✅ Event broadcasted to user ' . \$$user->id; } else { echo '❌ User not found: $(EMAIL)'; }"; \
			;; \
		"VoucherPurchaseError") \
			$(DOCKER_EXEC) php artisan tinker --execute="\$$user = App\\Models\\User::where('email', '$(EMAIL)')->first(); if (\$$user) { broadcast(new App\\Events\\Vouchers\\VoucherPurchaseError(\$$user->id, 'Test error message', ['error_code' => 'TEST_ERROR'])); echo '✅ VoucherPurchaseError broadcasted to user ' . \$$user->id; } else { echo '❌ User not found: $(EMAIL)'; }"; \
			;; \
		"VoucherPurchaseNotification") \
			$(DOCKER_EXEC) php artisan tinker --execute="\$$user = App\\Models\\User::where('email', '$(EMAIL)')->first(); if (\$$user) { broadcast(new App\\Events\\Vouchers\\VoucherPurchaseNotification(\$$user->id, 'Test voucher purchased', ['voucher_id' => 'TEST123'])); echo '✅ VoucherPurchaseNotification broadcasted to user ' . \$$user->id; } else { echo '❌ User not found: $(EMAIL)'; }"; \
			;; \
		"VoucherPaymentStatusUpdate") \
			$(DOCKER_EXEC) php artisan tinker --execute="\$$user = App\\Models\\User::where('email', '$(EMAIL)')->first(); if (\$$user) { broadcast(new App\\Events\\Vouchers\\VoucherPaymentStatusUpdate(\$$user->id, 'paid', ['payment_id' => 'PAY_TEST123'])); echo '✅ VoucherPaymentStatusUpdate broadcasted to user ' . \$$user->id; } else { echo '❌ User not found: $(EMAIL)'; }"; \
			;; \
		*) \
			echo -e "$(RED)❌ Unsupported event: $(EVENT)$(NC)"; \
			echo -e "$(YELLOW)Run 'make broadcast-list' for available user events$(NC)"; \
			exit 1; \
			;; \
	esac

## 🏢 Broadcast event to financer channel (using ID)
broadcast-financer:
	@if [ -z "$(EVENT)" ] || [ -z "$(FINANCER_ID)" ]; then \
		echo -e "$(RED)❌ Error: EVENT and FINANCER_ID parameters are required$(NC)"; \
		echo -e "$(YELLOW)Usage: make broadcast-financer EVENT=PrivateFinancerActivity FINANCER_ID=123$(NC)"; \
		echo -e "$(YELLOW)Run 'make broadcast-list' to see available events$(NC)"; \
		exit 1; \
	fi; \
	echo -e "$(BLUE)🏢 Broadcasting $(EVENT) to financer $(FINANCER_ID)...$(NC)"; \
	case "$(EVENT)" in \
		"PrivateFinancerActivity") \
			ACTIVITY=$${ACTIVITY:-"test_activity"}; \
			USER_ID=$${USER_ID:-"null"}; \
			$(DOCKER_EXEC) php artisan tinker --execute="broadcast(new App\\Events\\Testing\\PrivateFinancerActivity('$(FINANCER_ID)', '$$ACTIVITY', ['test' => true, 'timestamp' => now()->toISOString()], $$USER_ID)); echo '✅ Event broadcasted to financer.$(FINANCER_ID)';"; \
			;; \
		"ApideckSyncCompleted") \
			$(DOCKER_EXEC) php artisan tinker --execute="broadcast(new App\\Events\\ApideckSyncCompleted('$(FINANCER_ID)', ['status' => 'completed', 'test' => true, 'records_synced' => 10])); echo '✅ ApideckSyncCompleted broadcasted to financer.$(FINANCER_ID)';"; \
			;; \
		"ApideckSyncEvent") \
			$(DOCKER_EXEC) php artisan tinker --execute="broadcast(new App\\Events\\ApideckSyncEvent('$(FINANCER_ID)', ['action' => 'sync_started', 'test' => true])); echo '✅ ApideckSyncEvent broadcasted to financer.$(FINANCER_ID)';"; \
			;; \
		*) \
			echo -e "$(RED)❌ Unsupported event: $(EVENT)$(NC)"; \
			echo -e "$(YELLOW)Run 'make broadcast-list' for available financer events$(NC)"; \
			exit 1; \
			;; \
	esac

## 🏛️ Broadcast event to division channel (using ID)
broadcast-division:
	@if [ -z "$(EVENT)" ] || [ -z "$(DIVISION_ID)" ]; then \
		echo -e "$(RED)❌ Error: EVENT and DIVISION_ID parameters are required$(NC)"; \
		echo -e "$(YELLOW)Usage: make broadcast-division EVENT=ApideckSyncCompleted DIVISION_ID=123$(NC)"; \
		echo -e "$(YELLOW)Run 'make broadcast-list' to see available events$(NC)"; \
		exit 1; \
	fi; \
	echo -e "$(BLUE)🏛️ Broadcasting $(EVENT) to division $(DIVISION_ID)...$(NC)"; \
	case "$(EVENT)" in \
		"ApideckSyncCompleted") \
			$(DOCKER_EXEC) php artisan tinker --execute="broadcast(new App\\Events\\ApideckSyncCompleted('test-financer', ['division_id' => $(DIVISION_ID), 'status' => 'completed', 'test' => true, 'records_synced' => 25])); echo '✅ ApideckSyncCompleted broadcasted to division.$(DIVISION_ID)';"; \
			;; \
		*) \
			echo -e "$(RED)❌ Unsupported event: $(EVENT)$(NC)"; \
			echo -e "$(YELLOW)Run 'make broadcast-list' for available division events$(NC)"; \
			exit 1; \
			;; \
	esac

## 👥 Broadcast event to team channel (using ID)
broadcast-team:
	@if [ -z "$(EVENT)" ] || [ -z "$(TEAM_ID)" ]; then \
		echo -e "$(RED)❌ Error: EVENT and TEAM_ID parameters are required$(NC)"; \
		echo -e "$(YELLOW)Usage: make broadcast-team EVENT=PrivateTeamUpdate TEAM_ID=123$(NC)"; \
		echo -e "$(YELLOW)Run 'make broadcast-list' to see available events$(NC)"; \
		exit 1; \
	fi; \
	echo -e "$(BLUE)👥 Broadcasting $(EVENT) to team $(TEAM_ID)...$(NC)"; \
	case "$(EVENT)" in \
		"PrivateTeamUpdate") \
			UPDATE_TYPE=$${UPDATE_TYPE:-"test_update"}; \
			$(DOCKER_EXEC) php artisan tinker --execute="\$$team = App\\Models\\Team::find($(TEAM_ID)); if (\$$team) { broadcast(new App\\Events\\Testing\\PrivateTeamUpdate(\$$team, '$$UPDATE_TYPE', ['test' => true, 'timestamp' => now()->toISOString()])); echo '✅ Event broadcasted to team ' . \$$team->id; } else { echo '❌ Team not found: $(TEAM_ID)'; }"; \
			;; \
		*) \
			echo -e "$(RED)❌ Unsupported event: $(EVENT)$(NC)"; \
			echo -e "$(YELLOW)Run 'make broadcast-list' for available team events$(NC)"; \
			exit 1; \
			;; \
	esac

## 🌍 Broadcast event to public channels
broadcast-public:
	@if [ -z "$(EVENT)" ]; then \
		echo -e "$(RED)❌ Error: EVENT parameter is required$(NC)"; \
		echo -e "$(YELLOW)Usage: make broadcast-public EVENT=PublicMessageEvent$(NC)"; \
		echo -e "$(YELLOW)Run 'make broadcast-list' to see available events$(NC)"; \
		exit 1; \
	fi; \
	echo -e "$(BLUE)🌍 Broadcasting $(EVENT) to public channels...$(NC)"; \
	case "$(EVENT)" in \
		"PublicMessageEvent") \
			TITLE=$${TITLE:-"Test Public Message"}; \
			MESSAGE=$${MESSAGE:-"This is a test public broadcast"}; \
			TYPE=$${TYPE:-"info"}; \
			$(DOCKER_EXEC) php artisan tinker --execute="broadcast(new App\\Events\\Testing\\PublicMessageEvent('$$TITLE', '$$MESSAGE', '$$TYPE')); echo '✅ PublicMessageEvent broadcasted';"; \
			;; \
		"PublicStatsEvent") \
			$(DOCKER_EXEC) php artisan tinker --execute="broadcast(new App\\Events\\Testing\\PublicStatsEvent(['users' => 100, 'orders' => 50, 'test' => true])); echo '✅ PublicStatsEvent broadcasted';"; \
			;; \
		"StatsUpdatedEvent") \
			$(DOCKER_EXEC) php artisan tinker --execute="broadcast(new App\\Events\\StatsUpdatedEvent(['metric' => 'test_metric', 'value' => 42, 'timestamp' => now()->toISOString()])); echo '✅ StatsUpdatedEvent broadcasted';"; \
			;; \
		"TestPublicEvent") \
			$(DOCKER_EXEC) php artisan tinker --execute="broadcast(new App\\Events\\Testing\\TestPublicEvent('Test data from make command')); echo '✅ TestPublicEvent broadcasted';"; \
			;; \
		*) \
			echo -e "$(RED)❌ Unsupported event: $(EVENT)$(NC)"; \
			echo -e "$(YELLOW)Run 'make broadcast-list' for available public events$(NC)"; \
			exit 1; \
			;; \
	esac

# Integration Commands
## 🔗 Sync Amilon voucher data
amilon-sync:
	@echo -e "$(BLUE)🔗 Syncing Amilon voucher data...$(NC)"
	@$(DOCKER_EXEC) php artisan amilon:sync-data
	@echo -e "$(GREEN)✅ Amilon data synchronized!$(NC)"

# ============================================================================
# 🛠 UTILITY COMMANDS
# ============================================================================

## 🧹 Cleans cache and temporary files
clean:
	@echo -e "$(BLUE)🧹 Cover cleaning...$(NC)"
	@$(DOCKER_EXEC) php artisan cache:clear
	@$(DOCKER_EXEC) php artisan view:clear
	@$(DOCKER_EXEC) php artisan route:clear
	@$(DOCKER_EXEC) php artisan config:clear
	@$(DOCKER_EXEC) php artisan optimize:clear
	@$(DOCKER_EXEC) php artisan permission:cache-reset
	@echo -e "$(GREEN)✅ Cleaning complete!$(NC)"

## 🔄 Restore all Git changes (unstage and discard)
restore:
	@echo -e "$(YELLOW)⚠️  WARNING: This will discard ALL uncommitted changes!$(NC)"
	@read -p "Are you sure you want to restore all files? (y/N) " -n 1 -r; \
	echo ""; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		echo -e "$(BLUE)🔄 Unstaging all files...$(NC)"; \
		git restore --staged . ; \
		echo -e "$(BLUE)🔄 Discarding all changes...$(NC)"; \
		git restore . ; \
		echo -e "$(GREEN)✅ All changes have been restored!$(NC)"; \
		git status --short; \
	else \
		echo -e "$(RED)❌ Restore cancelled$(NC)"; \
	fi

## 🗑️ Clear Git cache (apply new .gitignore rules)
git-cache-clear:
	@echo -e "$(YELLOW)⚠️  This will clear Git cache to apply new .gitignore rules$(NC)"
	@echo -e "$(YELLOW)   All tracked files will be re-evaluated against .gitignore$(NC)"
	@read -p "Continue? (y/N) " -n 1 -r; \
	echo ""; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		echo -e "$(BLUE)🗑️  Removing all files from Git cache...$(NC)"; \
		git rm -r --cached . 2>/dev/null || true; \
		echo -e "$(BLUE)📝 Re-adding all files (respecting .gitignore)...$(NC)"; \
		git add . ; \
		echo -e "$(GREEN)✅ Git cache cleared successfully!$(NC)"; \
		echo -e "$(CYAN)📊 Files now ignored:$(NC)"; \
		git status --short | grep '^D' | head -10 || echo "  No files removed from tracking"; \
		echo -e "$(YELLOW)💡 Remember to commit these changes if needed$(NC)"; \
	else \
		echo -e "$(RED)❌ Git cache clear cancelled$(NC)"; \
	fi

# ============================================================================
# 🌐 NGROK COMMANDS
# ============================================================================

## 🌐 Start ngrok tunnel for local development
ngrok:
	@echo -e "$(BLUE)🌐 Starting ngrok tunnel...$(NC)"
	@if ! command -v ngrok &> /dev/null; then \
		echo -e "$(RED)❌ ngrok is not installed!$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-setup' to install it$(NC)"; \
		exit 1; \
	fi
	@echo -e "$(CYAN)📡 Starting tunnel on port 1310...$(NC)"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo -e "$(GREEN)The tunnel URL will appear below once connected:$(NC)"
	@echo -e "$(YELLOW)Press Ctrl+C to stop the tunnel$(NC)"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@ngrok http 1310

## 🚀 Start ngrok in background and display URL
ngrok-background:
	@echo -e "$(BLUE)🚀 Starting ngrok tunnel in background...$(NC)"
	@if ! command -v ngrok &> /dev/null; then \
		echo -e "$(RED)❌ ngrok is not installed!$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-setup' to install it$(NC)"; \
		exit 1; \
	fi
	@if pgrep -x "ngrok" > /dev/null; then \
		echo -e "$(YELLOW)⚠️  Stopping existing ngrok process...$(NC)"; \
		killall ngrok 2>/dev/null || true; \
		sleep 2; \
	fi
	@echo -e "$(CYAN)📡 Starting tunnel on port 1310 in background...$(NC)"
	@nohup ngrok http 1310 > /dev/null 2>&1 &
	@echo -e "$(YELLOW)⏳ Waiting for tunnel to establish...$(NC)"
	@sleep 3
	@if curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -q "tunnels"; then \
		echo -e "$(GREEN)✅ Tunnel established successfully!$(NC)"; \
		echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"; \
		URL=$$(curl -s http://localhost:4040/api/tunnels | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['tunnels'][0]['public_url'] if data['tunnels'] else 'No URL found')"); \
		echo -e "$(GREEN)🌐 Your public URL:$(NC)"; \
		echo -e "$(CYAN)   $$URL$(NC)"; \
		echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"; \
		echo -e "$(BLUE)📊 Web interface: http://localhost:4040$(NC)"; \
		echo -e "$(YELLOW)💡 Run 'make ngrok-url' to get the URL again$(NC)"; \
		echo -e "$(YELLOW)💡 Run 'make ngrok-stop' to stop the tunnel$(NC)"; \
	else \
		echo -e "$(RED)❌ Failed to establish tunnel$(NC)"; \
		echo -e "$(YELLOW)Check if port 1310 is accessible$(NC)"; \
	fi

## 🔗 Get current ngrok URL
ngrok-url:
	@if curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -q "tunnels"; then \
		URL=$$(curl -s http://localhost:4040/api/tunnels | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['tunnels'][0]['public_url'] if data['tunnels'] else 'No URL found')"); \
		echo -e "$(GREEN)🌐 Current ngrok URL:$(NC)"; \
		echo -e "$(CYAN)   $$URL$(NC)"; \
		echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"; \
		echo -e "$(BLUE)📊 Web interface: http://localhost:4040$(NC)"; \
	else \
		echo -e "$(RED)❌ No active ngrok tunnel found$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-background' to start a tunnel$(NC)"; \
	fi

# ============================================================================
# 💬 SLACK COMMANDS
# ============================================================================

## 💬 Send a message to Slack default channel
slack-send:
	@if [ -z "$(MSG)" ]; then \
		echo -e "$(RED)❌ Error: Message is required$(NC)"; \
		echo -e "$(YELLOW)Usage: make slack-send MSG=\"Your message here\"$(NC)"; \
		echo -e "$(CYAN)Optional: CHANNEL=\"#channel-name\"$(NC)"; \
		exit 1; \
	fi
	@echo -e "$(BLUE)💬 Sending message to Slack...$(NC)"
	@if [ -n "$(CHANNEL)" ]; then \
		$(DOCKER_EXEC) php artisan slack:send "$(MSG)" --channel="$(CHANNEL)"; \
	else \
		$(DOCKER_EXEC) php artisan slack:send "$(MSG)"; \
	fi
	@echo -e "$(GREEN)✅ Message sent successfully!$(NC)"

## 📎 Send a file to Slack with optional message
slack-send-file:
	@if [ -z "$(FILE)" ]; then \
		echo -e "$(RED)❌ Error: File path is required$(NC)"; \
		echo -e "$(YELLOW)Usage: make slack-send-file FILE=\"/path/to/file\" MSG=\"Optional message\"$(NC)"; \
		echo -e "$(CYAN)Optional: CHANNEL=\"#channel-name\" TITLE=\"File title\"$(NC)"; \
		exit 1; \
	fi
	@echo -e "$(BLUE)📎 Sending file to Slack...$(NC)"
	@CMD="php artisan slack:send \"$${MSG:-File attached}\" --file=\"$(FILE)\""; \
	[ -n "$(CHANNEL)" ] && CMD="$$CMD --channel=\"$(CHANNEL)\""; \
	[ -n "$(TITLE)" ] && CMD="$$CMD --title=\"$(TITLE)\""; \
	$(DOCKER_EXEC) $$CMD
	@echo -e "$(GREEN)✅ File sent successfully!$(NC)"

## ❌ Send an error notification to Slack
slack-send-error:
	@if [ -z "$(MSG)" ]; then \
		echo -e "$(RED)❌ Error: Error message is required$(NC)"; \
		echo -e "$(YELLOW)Usage: make slack-send-error MSG=\"Error description\"$(NC)"; \
		exit 1; \
	fi
	@echo -e "$(RED)🚨 Sending error notification to Slack...$(NC)"
	@$(DOCKER_EXEC) php artisan slack:send "❌ ERROR: $(MSG)" --channel="$${CHANNEL:-#errors}"
	@echo -e "$(GREEN)✅ Error notification sent!$(NC)"

## ✅ Send a success notification to Slack
slack-send-success:
	@if [ -z "$(MSG)" ]; then \
		echo -e "$(RED)❌ Error: Success message is required$(NC)"; \
		echo -e "$(YELLOW)Usage: make slack-send-success MSG=\"Success description\"$(NC)"; \
		exit 1; \
	fi
	@echo -e "$(GREEN)🎉 Sending success notification to Slack...$(NC)"
	@$(DOCKER_EXEC) php artisan slack:send "✅ SUCCESS: $(MSG)" --channel="$${CHANNEL:-#general}"
	@echo -e "$(GREEN)✅ Success notification sent!$(NC)"

## 🔍 Test Slack connection and configuration
slack-test:
	@echo -e "$(BLUE)🔍 Testing Slack connection...$(NC)"
	@$(DOCKER_EXEC) php artisan slack:test
	@echo -e "$(GREEN)✅ Slack test completed!$(NC)"

## 🔧 Install and configure ngrok
ngrok-setup:
	@echo -e "$(BLUE)🔧 Setting up ngrok...$(NC)"
	@if command -v ngrok &> /dev/null; then \
		echo -e "$(YELLOW)✓ ngrok is already installed$(NC)"; \
		ngrok version; \
	else \
		echo -e "$(CYAN)📦 Installing ngrok...$(NC)"; \
		if [[ "$$(uname)" == "Darwin" ]]; then \
			if command -v brew &> /dev/null; then \
				brew install ngrok/ngrok/ngrok; \
			else \
				echo -e "$(YELLOW)Installing via download...$(NC)"; \
				curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.zip -o ngrok.zip; \
				unzip -o ngrok.zip; \
				sudo mv ngrok /usr/local/bin/; \
				rm ngrok.zip; \
			fi; \
		elif [[ "$$(uname)" == "Linux" ]]; then \
			curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null; \
			echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | sudo tee /etc/apt/sources.list.d/ngrok.list; \
			sudo apt update && sudo apt install ngrok; \
		else \
			echo -e "$(RED)❌ Unsupported OS. Please install ngrok manually from https://ngrok.com$(NC)"; \
			exit 1; \
		fi; \
		echo -e "$(GREEN)✅ ngrok installed successfully!$(NC)"; \
	fi
	@echo -e ""
	@echo -e "$(CYAN)🔑 Authentication:$(NC)"
	@if ngrok config check 2>/dev/null | grep -q "Valid"; then \
		echo -e "$(GREEN)✓ ngrok is already authenticated$(NC)"; \
	else \
		echo -e "$(YELLOW)⚠️  You need to authenticate ngrok$(NC)"; \
		echo -e "$(CYAN)1. Create a free account at: https://dashboard.ngrok.com/signup$(NC)"; \
		echo -e "$(CYAN)2. Get your authtoken from: https://dashboard.ngrok.com/get-started/your-authtoken$(NC)"; \
		echo -e "$(CYAN)3. Run: ngrok config add-authtoken YOUR_TOKEN$(NC)"; \
	fi
	@echo -e ""
	@echo -e "$(GREEN)📖 Quick start:$(NC)"
	@echo -e "  $(BLUE)make ngrok$(NC)        - Start tunnel on port 1310"
	@echo -e "  $(BLUE)make ngrok-status$(NC) - Check tunnel status"
	@echo -e "  $(BLUE)make ngrok-stop$(NC)   - Stop all tunnels"

## 🔍 Check ngrok status and active tunnels
ngrok-status:
	@echo -e "$(BLUE)🔍 Checking ngrok status...$(NC)"
	@if ! command -v ngrok &> /dev/null; then \
		echo -e "$(RED)❌ ngrok is not installed!$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-setup' to install it$(NC)"; \
		exit 1; \
	fi
	@echo -e "$(CYAN)📊 ngrok version:$(NC)"
	@ngrok version
	@echo -e ""
	@if curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -q "tunnels"; then \
		echo -e "$(GREEN)✅ ngrok is running$(NC)"; \
		echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"; \
		URL=$$(curl -s http://localhost:4040/api/tunnels | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['tunnels'][0]['public_url'] if data['tunnels'] else 'No URL found')"); \
		echo -e "$(GREEN)🌐 Public URL:$(NC)"; \
		echo -e "$(CYAN)   $$URL$(NC)"; \
		echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"; \
		echo -e "$(BLUE)📡 Local endpoint: http://localhost:1310$(NC)"; \
		echo -e "$(BLUE)📊 Web interface: http://localhost:4040$(NC)"; \
		echo -e ""; \
		echo -e "$(CYAN)📈 Tunnel details:$(NC)"; \
		curl -s http://localhost:4040/api/tunnels | python3 -c "import sys, json; data = json.load(sys.stdin); t = data['tunnels'][0] if data['tunnels'] else {}; print(f\"  Protocol: {t.get('proto', 'N/A')}\"); print(f\"  Started: {t.get('start_time', 'N/A')}\"); print(f\"  Region: {t.get('region', 'N/A')}\")"; \
	else \
		echo -e "$(RED)❌ ngrok is not running$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-background' to start a tunnel$(NC)"; \
	fi

## 🛑 Stop all ngrok tunnels
ngrok-stop:
	@echo -e "$(RED)🛑 Stopping all ngrok tunnels...$(NC)"
	@if pgrep -x "ngrok" > /dev/null; then \
		killall ngrok 2>/dev/null || true; \
		echo -e "$(GREEN)✅ All ngrok tunnels stopped$(NC)"; \
	else \
		echo -e "$(YELLOW)No active ngrok processes found$(NC)"; \
	fi

## 📊 Show ngrok logs
ngrok-logs:
	@echo -e "$(BLUE)📊 Opening ngrok web interface...$(NC)"
	@if curl -s http://localhost:4040 2>/dev/null | grep -q "ngrok"; then \
		echo -e "$(GREEN)✅ Web interface available at: http://localhost:4040$(NC)"; \
		echo -e "$(CYAN)Opening in browser...$(NC)"; \
		if command -v open > /dev/null; then \
			open http://localhost:4040; \
		elif command -v xdg-open > /dev/null; then \
			xdg-open http://localhost:4040; \
		else \
			echo -e "$(YELLOW)Please open http://localhost:4040 in your browser$(NC)"; \
		fi; \
	else \
		echo -e "$(RED)❌ No active ngrok tunnel found$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok' first to start a tunnel$(NC)"; \
	fi

## 📑 Generate API documentation with Scramble
docs:
	@echo -e "$(BLUE)📑 Generating API documentation...$(NC)"
	@$(DOCKER_EXEC) php artisan scramble:export
	@echo -e "$(GREEN)✅ Documentation generated!$(NC)"

## 📖 Open project documentation in browser
docs-open:
	@echo -e "$(BLUE)📖 Opening documentation...$(NC)"
	@if command -v open > /dev/null; then \
		open docs/index.html; \
	elif command -v xdg-open > /dev/null; then \
		xdg-open docs/index.html; \
	else \
		echo -e "$(YELLOW)Please open docs/index.html in your browser$(NC)"; \
	fi
	@echo -e "$(GREEN)✅ Documentation opened!$(NC)"

## 🧭 Audit docs migration (read-only scan, generates RAPPORT.md & audit.json)
audit-docs:
	@echo -e "$(BLUE)🧭 Auditing docs migration (source: docs, dest: docs/reorganized)...$(NC)"
	@python3 scripts/audit_docs.py
	@echo -e "$(GREEN)✅ Audit completed. See RAPPORT.md and audit.json at repo root.$(NC)"

## 🔁 Chain Pint, Rector, PHPStan and tests
quality-check: lint rector stan clean test docs
	@echo -e "$(GREEN)✅ Quality control successfully completed!$(NC)"

# ============================================================================
# 🤖 CLAUDE CODE WORKFLOW COMMANDS
# ============================================================================

## 🌳 Claude: Development with Worktrees (TDD)
claude-todo-wt:
	@echo -e "$(PURPLE)🤖 Starting Claude Code Worktree Workflow...$(NC)"
	@echo -e "$(CYAN)📋 This workflow will:$(NC)"
	@echo -e "  • Create isolated git worktrees"
	@echo -e "  • Enforce TDD with RED-GREEN-REFACTOR cycles"
	@echo -e "  • Connect to Todoist, Jira, Sentry, or local todos"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@$(CLAUDE) "Execute Workflows/todo-worktree-mcp workflow starting from INIT phase. Read CLAUDE.MD first."

## 🌿 Claude: Development on current branch (TDD)
claude-todo-br:
	@echo -e "$(PURPLE)🤖 Starting Claude Code Branch Workflow...$(NC)"
	@echo -e "$(CYAN)📋 This workflow will:$(NC)"
	@echo -e "  • Work on current branch"
	@echo -e "  • Follow GitFlow naming conventions"
	@echo -e "  • Create a single final commit"
	@echo -e "  • Connect to Todoist, Jira, Sentry, or local todos"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@$(CLAUDE) "Execute Workflows/todo-branch-mcp workflow starting from INIT phase. Read CLAUDE.MD first."

## 📚 Claude: Generate documentation (no code changes)
claude-doc:
	@echo -e "$(PURPLE)🤖 Starting Claude Documentation Workflow...$(NC)"
	@echo -e "$(CYAN)📋 This workflow will:$(NC)"
	@echo -e "  • Analyze completed tasks from external sources"
	@echo -e "  • Generate technical docs, user manuals, or API guides"
	@echo -e "  • Add diagrams and convert to PDF if needed"
	@echo -e "  • No code modifications"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@$(CLAUDE) "Execute Workflows/todo-documentation-mcp workflow starting from INIT phase. Generate documentation without modifying code."

## 🚀 Claude: Migrate docs to Confluence
claude-confluence:
	@echo -e "$(PURPLE)🤖 Starting Claude Confluence Migration Workflow...$(NC)"
	@echo -e "$(CYAN)📋 This workflow will:$(NC)"
	@echo -e "  • Analyze and classify Markdown documents"
	@echo -e "  • Check for duplicates in Notion"
	@echo -e "  • Migrate to Confluence with UpEngage structure"
	@echo -e "  • Handle assets and maintain links"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@$(CLAUDE) "Execute Workflows/confluence-migration-workflow starting from INIT phase. Prepare to migrate documentation to Confluence."

## ⚡ Claude: Quick fix workflow (< 30 min)
claude-quick-fix:
	@echo -e "$(PURPLE)🤖 Starting Claude Quick Fix Workflow...$(NC)"
	@echo -e "$(CYAN)📋 This workflow will:$(NC)"
	@echo -e "  • Handle urgent hotfixes and small corrections"
	@echo -e "  • Complete fixes in under 30 minutes"
	@echo -e "  • Optional branch creation and tests"
	@echo -e "  • Fast, focused changes only"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@$(CLAUDE) "Execute Workflows/quick-fix-workflow starting from INIT phase. Focus on rapid corrections only."

## 🔍 Claude: Analyze task without coding
claude-analyze:
	@echo -e "$(PURPLE)🤖 Starting Claude Analysis Workflow...$(NC)"
	@echo -e "$(CYAN)📋 This workflow will:$(NC)"
	@echo -e "  • Analyze tasks from Todoist, Jira, Sentry, or local files"
	@echo -e "  • Create detailed development strategies"
	@echo -e "  • Generate TDD test plans and estimations"
	@echo -e "  • No code modifications - analysis only"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@$(CLAUDE) "Execute Workflows/todo-analysis-mcp workflow starting from INIT phase. Analyze and plan without modifying code. start by reading CLAUDE.md"

## 🎯 Claude: Resume an orphaned task
claude-resume:
	@echo -e "$(PURPLE)🤖 Checking for orphaned Claude tasks...$(NC)"
	@$(CLAUDE) "Check for orphaned tasks in todos/worktrees/, todos/work/, todos/analysis, todos/documentation directories. If found, list them and ask which one to resume."

## 🔧 Claude: Initialize project configuration
claude-init:
	@echo -e "$(PURPLE)🤖 Initializing Claude Code configuration...$(NC)"
	@if [ ! -f CLAUDE.MD ]; then \
		echo -e "$(CYAN)Creating CLAUDE.MD configuration file...$(NC)"; \
		echo "# Instructions pour Claude Code" > CLAUDE.MD; \
		echo "" >> CLAUDE.MD; \
		echo "## Configuration projet" >> CLAUDE.MD; \
		echo "- Framework: Laravel" >> CLAUDE.MD; \
		echo "- Tests: PHPUnit" >> CLAUDE.MD; \
		echo "- Build: Docker Compose" >> CLAUDE.MD; \
		echo "- Database: MySQL/PostgreSQL" >> CLAUDE.MD; \
		echo "" >> CLAUDE.MD; \
		echo "## Standards de code" >> CLAUDE.MD; \
		echo "- PSR-12 pour PHP" >> CLAUDE.MD; \
		echo "- Laravel conventions" >> CLAUDE.MD; \
		echo "- TDD obligatoire" >> CLAUDE.MD; \
		echo "" >> CLAUDE.MD; \
		echo "## Structure du projet" >> CLAUDE.MD; \
		echo "- app/ : Application logic" >> CLAUDE.MD; \
		echo "- tests/ : Test suites" >> CLAUDE.MD; \
		echo "- database/ : Migrations and seeders" >> CLAUDE.MD; \
		echo "- resources/ : Views and assets" >> CLAUDE.MD; \
		echo "" >> CLAUDE.MD; \
		echo "## Commandes importantes" >> CLAUDE.MD; \
		echo "- Tests: make test" >> CLAUDE.MD; \
		echo "- Quality: make quality-check" >> CLAUDE.MD; \
		echo "- Docker: make docker-restart" >> CLAUDE.MD; \
		echo -e "$(GREEN)✅ CLAUDE.MD created successfully!$(NC)"; \
	else \
		echo -e "$(YELLOW)⚠️  CLAUDE.MD already exists$(NC)"; \
	fi
	@echo -e "$(CYAN)📋 You can now edit CLAUDE.MD to customize your project configuration$(NC)"

## ❓ Claude: Interactive workflow selection
claude-help:
	@echo -e "$(PURPLE)🤖 Claude Code Workflow Helper$(NC)"
	@echo -e "$(CYAN)Which workflow would you like to use?$(NC)"
	@echo -e ""
	@echo -e "  $(BLUE)1$(NC) - Development with Worktrees (isolated branches)"
	@echo -e "  $(BLUE)2$(NC) - Development on Current Branch (single commit)"
	@echo -e "  $(BLUE)3$(NC) - Generate Documentation (no code changes)"
	@echo -e "  $(BLUE)4$(NC) - Migrate Docs to Confluence"
	@echo -e "  $(BLUE)5$(NC) - Quick Fix (<30 min corrections)"
	@echo -e "  $(BLUE)6$(NC) - Analyze Task (planning only)"
	@echo -e "  $(BLUE)7$(NC) - Resume Orphaned Task"
	@echo -e ""
	@read -p "Select workflow (1-7): " choice; \
	case $$choice in \
		1) make claude-todo-wt ;; \
		2) make claude-todo-br ;; \
		3) make claude-doc ;; \
		4) make claude-confluence ;; \
		5) make claude-quick-fix ;; \
		6) make claude-analyze ;; \
		7) make claude-resume ;; \
		*) echo -e "$(RED)Invalid choice$(NC)" ;; \
	esac

# ============================================================================
# 📋 HELP
# ============================================================================

## 📋 Display help
help:
	@echo -e "$(BLUE)📋 IC-Manager Makefile Commands$(NC)"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo -e ""
	@echo -e "$(GREEN)🔧 Code Quality Commands:$(NC)"
	@echo -e "  $(BLUE)make lint$(NC)                    - Format code with Laravel Pint"
	@echo -e "  $(BLUE)make rector$(NC)                  - Apply PHP modernization rules with Rector"
	@echo -e "  $(BLUE)make stan$(NC)                    - Static analysis with PHPStan (level 9)"
	@echo -e "  $(BLUE)make quality-check$(NC)           - Run all quality checks (lint, rector, stan, test, docs)"
	@echo -e ""
	@echo -e "$(GREEN)🧪 Testing Commands:$(NC)"
	@echo -e "  $(BLUE)make test$(NC)                    - Run complete test suite"
	@echo -e "  $(BLUE)make test-with-report$(NC)        - Run tests with optimized DB handling and failure export"
	@echo -e "  $(BLUE)make test-optimized$(NC)          - Run optimized tests (use ARGS=\"--stop-on-failure\" or ARGS=\"--parallel\")"
	@echo -e "  $(BLUE)make test-parallel$(NC)           - Run Unit and Feature tests in parallel (like GitLab CI)"
	@echo -e "  $(BLUE)make test-group$(NC)              - Run tests for specific groups (use GROUPS=\"user,apideck\")"
	@echo -e "  $(BLUE)make test-multiple$(NC)           - Run multiple test iterations (use ITERATIONS=10 GROUPS=\"article,voucher\")"
	@echo -e "  $(BLUE)make test-failed$(NC)             - Run only previously failed tests"
	@echo -e "  $(BLUE)make test-voucher-recovery$(NC)   - Run voucher recovery tests only"
	@echo -e "  $(BLUE)make test-amilon$(NC)             - Run all Amilon voucher tests"
	@echo -e "  $(BLUE)make test-groups-check$(NC)       - Check that all tests have #[Group(...)] annotations"
	@echo -e ""
	@echo -e "$(GREEN)🐳 Docker Commands:$(NC)"
	@echo -e "  $(BLUE)make docker-restart$(NC)          - Complete restart (removes orphaned containers/networks)"
	@echo -e "  $(BLUE)make docker-clean$(NC)            - Safe cleanup (preserves databases and active containers)"
	@echo -e "  $(BLUE)make docker-clean-worktree$(NC)   - Clean worktree containers and redis_cluster_* containers"
	@echo -e "  $(BLUE)make docker-deep-clean$(NC)       - Deep cleanup ($(RED)DANGER$(NC): removes all unused resources)"
	@echo -e ""
	@echo -e "$(GREEN)🚀 Artisan Commands:$(NC)"
	@echo -e "  $(CYAN)Database:$(NC)"
	@echo -e "  $(BLUE)make migrate$(NC)                 - Run pending database migrations"
	@echo -e "  $(BLUE)make migrate-fresh$(NC)           - Fresh database migration with seeders (resets DB)"
	@echo -e "  $(BLUE)make migrate-parallel-fresh$(NC)  - Fresh migration for all parallel test schemas (test_1 to test_12)"
	@echo -e "  $(BLUE)make migrate-for-fullmetrics$(NC) - Fresh migration + seed Amilon orders + metrics"
	@echo -e ""
	@echo -e "  $(CYAN)Background Services:$(NC)"
	@echo -e "  $(BLUE)make queue-start$(NC)                   - Start queue worker (jobs, emails, etc.)"
	@echo -e ""
	@echo -e "  $(CYAN)WebSocket (Reverb):$(NC)"
	@echo -e "  $(BLUE)make reverb-start$(NC)            - Start Reverb WebSocket server"
	@echo -e "  $(BLUE)make reverb-stop$(NC)             - Stop Reverb server"
	@echo -e "  $(BLUE)make reverb-restart$(NC)          - Restart Reverb server"
	@echo -e "  $(BLUE)make reverb-logs$(NC)             - View Reverb logs (real-time)"
	@echo -e "  $(BLUE)make reverb-status$(NC)           - Check Reverb status and configuration"
	@echo -e "  $(BLUE)make reverb-test$(NC)             - Send a test public event"
	@echo -e "  $(BLUE)make reverb-test-all$(NC)         - Run comprehensive channel tests"
	@echo -e "  $(BLUE)make reverb-install$(NC)          - Install Reverb (initial setup only)"
	@echo -e ""
	@echo -e "  $(CYAN)Broadcasting Events:$(NC)"
	@echo -e "  $(BLUE)make broadcast-list$(NC)          - List all available broadcastable events"
	@echo -e "  $(BLUE)make broadcast-user$(NC)          - Broadcast to user channel (EVENT + EMAIL)"
	@echo -e "  $(BLUE)make broadcast-financer$(NC)      - Broadcast to financer channel (EVENT + FINANCER_ID)"
	@echo -e "  $(BLUE)make broadcast-division$(NC)      - Broadcast to division channel (EVENT + DIVISION_ID)"
	@echo -e "  $(BLUE)make broadcast-team$(NC)          - Broadcast to team channel (EVENT + TEAM_ID)"
	@echo -e "  $(BLUE)make broadcast-public$(NC)        - Broadcast to public channels (EVENT)"
	@echo -e ""
	@echo -e "  $(CYAN)Integrations:$(NC)"
	@echo -e "  $(BLUE)make amilon-sync$(NC)             - Sync reel Amilon data(Categories,Merchants,Product,...) from API"
	@echo -e "  $(BLUE)make seed-amilon-order$(NC)       - Seed Amilon fake test orders data"
	@echo -e "  $(BLUE)make seed-invoices$(NC)           - Seed invoice test data (Hexeko→Division & Division→Financer)"
	@echo -e ""
	@echo -e "$(PURPLE)🤖 Claude Code Workflows:$(NC)"
	@echo -e "  $(BLUE)make claude-todo-wt$(NC)          - Start development with worktrees (TDD, isolated branches)"
	@echo -e "  $(BLUE)make claude-todo-br$(NC)          - Start development on current branch (TDD, single commit)"
	@echo -e "  $(BLUE)make claude-doc$(NC)              - Generate documentation from completed tasks"
	@echo -e "  $(BLUE)make claude-confluence$(NC)       - Migrate Markdown docs to Confluence"
	@echo -e "  $(BLUE)make claude-quick-fix$(NC)        - Quick fix workflow (<30 min corrections)"
	@echo -e "  $(BLUE)make claude-analyze$(NC)          - Analyze task without coding (planning only)"
	@echo -e "  $(BLUE)make claude-resume$(NC)           - Resume an orphaned Claude task"
	@echo -e "  $(BLUE)make claude-init$(NC)             - Initialize CLAUDE.MD configuration file"
	@echo -e "  $(BLUE)make claude-help$(NC)             - Interactive Claude workflow selection"
	@echo -e ""
	@echo -e "$(GREEN)💬 Slack Commands:$(NC)"
	@echo -e "  $(BLUE)make slack-send MSG=\"text\"$(NC)   - Send message to default channel"
	@echo -e "  $(BLUE)make slack-send-file FILE=\"path\"$(NC) - Send file with optional message"
	@echo -e "  $(BLUE)make slack-send-error MSG=\"error\"$(NC) - Send error notification to #errors"
	@echo -e "  $(BLUE)make slack-send-success MSG=\"ok\"$(NC) - Send success notification"
	@echo -e "  $(BLUE)make slack-test$(NC)              - Test Slack connection and config"
	@echo -e "    $(CYAN)Options: CHANNEL=\"#name\" TITLE=\"title\" MSG=\"message\"$(NC)"
	@echo -e ""
	@echo -e "$(GREEN)🌐 Ngrok Commands:$(NC)"
	@echo -e "  $(BLUE)make ngrok$(NC)                   - Start ngrok tunnel on port 1310 (foreground)"
	@echo -e "  $(BLUE)make ngrok-background$(NC)        - Start ngrok in background and show URL"
	@echo -e "  $(BLUE)make ngrok-url$(NC)               - Get current tunnel URL"
	@echo -e "  $(BLUE)make ngrok-setup$(NC)             - Install and configure ngrok"
	@echo -e "  $(BLUE)make ngrok-status$(NC)            - Check ngrok status and active tunnels"
	@echo -e "  $(BLUE)make ngrok-stop$(NC)              - Stop all ngrok tunnels"
	@echo -e "  $(BLUE)make ngrok-logs$(NC)              - Open ngrok web interface (localhost:4040)"
	@echo -e ""
	@echo -e "$(GREEN)🛠 Utility Commands:$(NC)"
	@echo -e "  $(BLUE)make clean$(NC)                   - Clear all Laravel caches"
	@echo -e "  $(BLUE)make restore$(NC)                 - Restore all Git changes (unstage and discard)"
	@echo -e "  $(BLUE)make git-cache-clear$(NC)         - Clear Git cache to apply new .gitignore rules"
	@echo -e "  $(BLUE)make docs$(NC)                    - Generate API documentation with Scramble"
	@echo -e "  $(BLUE)make help$(NC)                    - Display this help message"
	@echo -e ""
	@echo -e "$(YELLOW)💡 Tips:$(NC)"
	@echo -e "  • Use $(BLUE)make docker-restart$(NC) when you encounter network conflicts"
	@echo -e "  • Run $(BLUE)make quality-check$(NC) before committing code"
	@echo -e "  • Use $(BLUE)make test-optimized ARGS=\"--parallel\"$(NC) for faster test execution"
	@echo -e "  • Use $(BLUE)make claude-help$(NC) for interactive workflow selection"
	@echo -e "  • Run $(BLUE)make claude-init$(NC) to create initial CLAUDE.MD configuration"
	@echo -e "  • Use $(BLUE)make restore$(NC) to quickly discard all uncommitted changes"
	@echo -e ""
	@echo -e "$(CYAN)🤖 Claude Code Integration:$(NC)"
	@echo -e "  • Worktree workflow creates isolated branches for each task"
	@echo -e "  • Branch workflow works directly on current branch"
	@echo -e "  • All workflows enforce TDD (Test-Driven Development)"
	@echo -e "  • Workflows can fetch tasks from Todoist, Jira, Sentry, or local files"
	@echo -e "$(YELLOW)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
