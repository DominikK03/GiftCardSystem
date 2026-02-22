workspace "Gift Card System" "Multi-tenant gift card lifecycle management with Event Sourcing, CQRS, and async messaging" {

    model {
        # === ACTORS ===
        tenant = person "Tenant" "External API client (business partner). Manages gift cards via HMAC-authenticated REST API."
        admin = person "Admin User" "Backoffice operator (OWNER/ADMIN/SUPPORT). Manages system via web dashboard."
        cron = person "Cron Scheduler" "Automated scheduler for periodic tasks like gift card expiration."

        # === EXTERNAL SYSTEMS ===
        webhookEndpoint = softwareSystem "Tenant Webhook Endpoint" "Tenant's external HTTP endpoint receiving event notifications via HMAC-signed webhooks." "External"

        # === GIFT CARD SYSTEM ===
        giftCardSystem = softwareSystem "Gift Card System" "Multi-tenant gift card lifecycle management with Event Sourcing, CQRS, and async messaging. PHP 8.4, Symfony 7, FrankenPHP." {

            # --- CONTAINERS ---
            app = container "Symfony Web Application" "Handles HTTP requests, domain logic, Event Sourcing, CQRS command/query handling, Messenger async workers" "FrankenPHP, PHP 8.4, Symfony 7" {

                # Interface Layer
                giftCardController = component "GiftCardController" "REST API for gift card operations. 13 endpoints: create, redeem, activate, suspend, reactivate, cancel, expire, adjustBalance, decreaseBalance, list, get, history, health" "Symfony Controller"
                tenantController = component "TenantController" "REST API for tenant management. 7 endpoints: create, suspend, reactivate, cancel, regenerateCredentials, get, list" "Symfony Controller"
                userController = component "UserController" "REST API for user management. 7 endpoints: register, get, list, changeRole, deactivate, activate, changePassword" "Symfony Controller"
                requestDtos = component "Request DTOs" "10 request classes with Symfony Validator constraints and fromArray() factories" "PHP Classes"

                # Application - GiftCard
                gcCmdHandlers = component "GiftCard Command Handlers" "9 handlers: Create, Redeem, Activate, Suspend, Reactivate, Cancel, Expire, AdjustBalance, DecreaseBalance. All #[AsMessageHandler]" "Symfony Messenger"
                gcQueryHandlers = component "GiftCard Query Handlers" "3 handlers: GetGiftCard, GetGiftCards, GetGiftCardHistory. Synchronous reads from ReadModel and EventStore" "Symfony Handler"
                gcProvider = component "GiftCardProvider" "Loads GiftCard aggregate from event store. Enforces tenant ownership via TenantContext" "Service"
                gcPersister = component "GiftCardPersister" "Saves GiftCard aggregate via GiftCardRepository domain port" "Service"
                gcReadModel = component "GiftCardReadModel" "Doctrine entity on gift_cards_read table. Denormalized CQRS projection" "Doctrine Entity"
                webhookNotifier = component "TenantWebhookNotifier" "#[AsMessageHandler] for all 10 domain events. Sends HMAC-signed HTTP webhooks to tenant endpoints" "Symfony Messenger"

                # Application - Tenant
                tenantCmdHandlers = component "Tenant Command Handlers" "7 handlers: CreateTenant, SuspendTenant, ReactivateTenant, CancelTenant, GenerateInvoice, GenerateAgreement, RegenerateApiCredentials" "Symfony Messenger"
                tenantQueryHandlers = component "Tenant Query Handlers" "2 handlers: GetTenant, GetTenants" "Symfony Handler"
                tenantProvPers = component "Tenant Provider/Persister" "TenantProviderInterface + TenantPersisterInterface implementations" "Service"

                # Application - User
                userCmdHandlers = component "User Command Handlers" "5 handlers: RegisterUser, ChangePassword, ChangeUserRole, DeactivateUser, ActivateUser" "Symfony Messenger"
                userQueryHandlers = component "User Query Handlers" "2 handlers: GetUser, GetUsers" "Symfony Handler"
                userProvPers = component "User Provider/Persister" "UserProviderInterface + UserPersisterInterface implementations" "Service"

                # Domain Layer
                gcAggregate = component "GiftCard Aggregate" "Event-sourced aggregate root (Broadway). 10 business methods + 10 apply*() event handlers. State: INACTIVE->ACTIVE->SUSPENDED/EXPIRED/DEPLETED/CANCELLED" "Broadway AggregateRoot"
                domainEvents = component "Domain Events" "10 immutable readonly event classes: Created, Activated, Redeemed, Depleted, Suspended, Reactivated, Cancelled, Expired, BalanceAdjusted, BalanceDecreased" "Readonly Classes"
                valueObjects = component "Value Objects" "GiftCardId (UUID), Money (amount+currency), TenantId, TenantName, TenantEmail, NIP, ApiKey, ApiSecret, Address, UserId, UserEmail, UserRole" "PHP Classes"
                tenantEntity = component "Tenant Entity" "Doctrine ORM entity. Status: ACTIVE/SUSPENDED/CANCELLED. Has TenantWebhook child entities for event subscriptions" "Doctrine ORM"
                userEntity = component "User Entity" "Doctrine ORM entity. Roles: OWNER/ADMIN/SUPPORT. RBAC: canManageUsers(), canManageTenants(), canManageGiftCards()" "Doctrine ORM"
                domainPorts = component "Domain Ports" "Interfaces: GiftCardRepository, TenantRepositoryInterface, TenantQueryRepositoryInterface, UserRepository, TenantDocumentRepositoryInterface" "Interfaces"

                # Infrastructure Layer
                broadwayEs = component "Broadway Event Sourcing" "GiftCardRepositoryBroadway (adapter), TenantAwareEventStore (decorator), DBALEventStore, ReflectionSerializer, SimpleEventBus" "Broadway"
                eventBridge = component "DomainEventToMessengerListener" "Broadway EventListener. Bridges Broadway EventBus to Symfony Messenger. Dispatches domain events to async_events transport" "Broadway EventListener"
                readModelProj = component "ReadModel Projection" "GiftCardReadModelProjection: 10 #[AsMessageHandler] methods updating gift_cards_read table from domain events" "Symfony Messenger"
                tenantSecurity = component "Tenant Security" "HmacAuthenticationMiddleware (EventSubscriber), HmacSignatureBuilder, HmacSignatureVerifier, TenantAuthenticator, NonceStore" "Symfony Security"
                userSecurity = component "User Security" "SecurityUser (Symfony UserInterface adapter), SecurityUserProvider, form_login authentication" "Symfony Security"
                doctrineRepos = component "Doctrine Repositories" "TenantRepository, TenantQueryRepository, TenantDocumentRepository, DoctrineUserRepository, GiftCardReadModel repos" "Doctrine"
                adminControllers = component "Admin Controllers" "EasyAdmin dashboard: DashboardController, TenantController, GiftCardAdminController, UserAdminController, LoginController, TenantWizardController" "EasyAdmin"
                consoleCmds = component "Console Commands" "5 CLI commands: app:create-admin, app:gift-card:create-test, load-test, expire-cards, rebuild-read-model" "Symfony Console"
                docGen = component "Document Generation" "DomPdfGenerator (cooperation agreements, invoices), FilesystemDocumentStorage" "DomPDF"
            }

            postgres = container "PostgreSQL" "Stores events (Event Store), gift_cards_read (CQRS projections), tenants, users, tenant_webhooks, tenant_documents, messenger_messages" "PostgreSQL 16, port 5432" "Database"
            rabbitmq = container "RabbitMQ" "Message broker with exchanges: gift_card_commands (direct), gift_card_events (fanout). Retry with exponential backoff" "RabbitMQ 3, ports 5672/15672" "Queue"
            redis = container "Redis" "Application cache and nonce store for HMAC replay prevention" "Redis 7, port 6379" "Database"
        }

        # === LEVEL 1 RELATIONSHIPS (System Context) ===
        tenant -> giftCardSystem "Manages gift cards" "REST API, HTTPS/JSON, HMAC-signed"
        admin -> giftCardSystem "Manages system" "Web Dashboard, HTTPS, form_login"
        cron -> giftCardSystem "Runs periodic tasks" "CLI commands"
        giftCardSystem -> webhookEndpoint "Sends event notifications" "HTTPS/JSON, HMAC-signed webhooks"

        # === LEVEL 2 RELATIONSHIPS (Container) ===
        tenant -> app "REST API calls" "HTTPS/JSON, HMAC-signed"
        admin -> app "Web Dashboard" "HTTPS, form_login"
        cron -> app "Console commands" "CLI"
        app -> postgres "Reads/writes events, read models, entities" "Doctrine DBAL/ORM, SQL"
        app -> rabbitmq "Dispatches commands and domain events" "AMQP"
        rabbitmq -> app "Delivers messages to async workers" "AMQP"
        app -> redis "Caches data, stores nonces" "Redis protocol"
        app -> webhookEndpoint "Sends event webhooks" "HTTPS/JSON, HMAC-signed"

        # === LEVEL 3 RELATIONSHIPS (Component) ===
        # External -> Interface
        tenant -> giftCardController "REST API" "HTTPS + HMAC"
        admin -> adminControllers "Web Dashboard" "HTTPS + form_login"
        cron -> consoleCmds "CLI" "Shell"

        # Interface -> Application
        giftCardController -> requestDtos "Validates input"
        giftCardController -> gcCmdHandlers "Dispatches commands" "MessageBus"
        giftCardController -> gcQueryHandlers "Dispatches queries" "MessageBus"
        tenantController -> tenantCmdHandlers "Dispatches commands" "MessageBus"
        userController -> userCmdHandlers "Dispatches commands" "MessageBus"

        # Application -> Domain
        gcCmdHandlers -> gcProvider "Loads aggregate"
        gcCmdHandlers -> gcPersister "Saves aggregate"
        gcCmdHandlers -> gcAggregate "Executes business logic"
        gcAggregate -> domainEvents "Produces events"

        # Application/Domain -> Infrastructure
        gcProvider -> broadwayEs "Loads from event store"
        gcPersister -> broadwayEs "Saves events"
        broadwayEs -> eventBridge "EventBus publishes events"
        eventBridge -> rabbitmq "Dispatches to async_events" "AMQP"
        rabbitmq -> readModelProj "Delivers domain events" "AMQP"
        rabbitmq -> webhookNotifier "Delivers domain events" "AMQP"

        # Infrastructure -> External
        readModelProj -> postgres "Updates gift_cards_read" "SQL"
        webhookNotifier -> webhookEndpoint "HTTP webhooks" "HTTPS + HMAC"
        broadwayEs -> postgres "Reads/writes events" "SQL"
        doctrineRepos -> postgres "CRUD operations" "Doctrine DBAL"
        tenantSecurity -> tenantEntity "Validates API credentials"
        tenantSecurity -> redis "Nonce replay prevention"
        consoleCmds -> gcCmdHandlers "Dispatches commands" "MessageBus"
    }

    views {
        systemContext giftCardSystem "Level1_SystemContext" {
            include *
            autolayout lr
        }

        container giftCardSystem "Level2_Container" {
            include *
            autolayout lr
        }

        component app "Level3_Component" {
            include *
            autolayout lr
        }

        image * "Level4_Code" {
            plantuml ../plantuml/c4-level4-code.puml
            title "C4 Level 4 - Code: GiftCard Bounded Context"
            description "UML Class Diagram showing GiftCard aggregate, value objects, domain events, ports, and infrastructure adapters"
        }

        styles {
            element "Person" {
                shape Person
                background #08427B
                color #ffffff
            }
            element "Software System" {
                background #1168BD
                color #ffffff
            }
            element "External" {
                background #999999
                color #ffffff
            }
            element "Container" {
                background #438DD5
                color #ffffff
            }
            element "Component" {
                background #85BBF0
                color #000000
            }
            element "Database" {
                shape Cylinder
            }
            element "Queue" {
                shape Pipe
            }
        }
    }
}
