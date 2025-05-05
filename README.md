# Ticket-System: A Clean Architecture, DDD, and CQRS Project

This project is a **Ticket Management System** designed and developed as a portfolio piece to demonstrate proficiency in modern software design principles, architectural patterns, and a variety of technologies. It's built with a strong emphasis on clean code, maintainability, and scalability.

## Project Overview

The Ticket-System allows users to create, manage, and track tickets. It's designed as a robust and flexible system showcasing advanced architectural patterns.

## Core Concepts and Architectural Patterns

This project showcases the following core software design concepts and architectural patterns:

* **Clean Architecture / Hexagonal Architecture (Ports and Adapters):** The project is structured with a clear separation of concerns into layers (Domain, Application, Infrastructure). Dependencies point inwards towards the Domain. Interfaces (Ports) defined in the Domain/Application layers are implemented by Adapters in the Infrastructure layer, promoting testability, maintainability, and flexibility.
* **Domain-Driven Design (DDD):** DDD principles are applied to model the core business domain. This includes:
  * **Entities:** Representing core business objects (e.g., `Ticket`).
  * **Value Objects:** Representing immutable data structures (e.g., `Status`).
  * **Domain Events:** Representing significant events within the domain (e.g., `TicketCreated`).
* **Command Query Responsibility Segregation (CQRS):** The system separates read (Queries) and write (Commands) operations. This allows for optimized data access and improved scalability.
* **Event Sourcing (ES):** Implemented as the **primary persistence strategy**. Instead of storing the current state of aggregates, the system persists the full sequence of domain events (e.g., `TicketCreated`, `TicketResolved`) that represent changes to the aggregate over time. The current state of an aggregate is reconstructed on demand by replaying its event history from the Event Store. This provides a complete audit log, enables temporal queries, and decouples state changes from state representation.
* **Dependency Inversion:** The core of the application depends on abstractions (interfaces), not concrete implementations. This promotes loose coupling and testability.
* **SOLID Principles:** The code adheres to SOLID principles (Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion) to ensure maintainability and extensibility.
* **Data Transfer Objects (DTOs):** DTOs are used to transfer data between layers, decoupling them and making the code more testable.

## Technologies Used

This project leverages the following technologies:

* **PHP 8.3:** The core programming language.
* **Laravel 12:** Used as the foundation framework (routing, dependency injection container, validation, queueing, application events, etc.), but **without leveraging Eloquent ORM for primary persistence**, focusing on custom Infrastructure implementations.
* **MongoDB:** Used as the primary database for:
  * **Event Store:** Storing the sequence of domain events (`ticket_events` collection).
  * **Read Models/Projections:** Storing optimized data for queries (`ticket_read_models` collection).
  * Laravel's default cache, session, and failed job storage (configurable).
* **Kafka:** Used as a message broker for asynchronously publishing domain events to external consumers (via the `PublishDomainEventsToKafka` listener).
* **Redis:** Used as an in-memory key-value store, primarily as the **backend for Laravel's queue system** (`QUEUE_CONNECTION=redis`), processing background jobs like projections and Kafka publishing.
* **Docker & Docker Compose:** Used for complete containerization of the development environment, including PHP-FPM, Nginx, MongoDB (with Replica Set for transactions), Kafka, Redis, and a dedicated Worker container.
* **Nginx:** Web server acting as a reverse proxy to serve the application via PHP-FPM.
* **SonarQube:** Used for static code analysis.

## Applied Principles

* **SOLID:** Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion.
* **KISS:** Keep It Simple, Stupid.
* **DRY:** Don't Repeat Yourself.
* Clear Layer Separation (Clean Architecture).
* Dependency Inversion.

## Project Structure

The project is organized into the following main directories:

* **`app/Domain/`:** Contains the core business logic, including Entities, Value Objects, Events, Interfaces, and Exceptions.
* **`app/Application/`:** Contains the application logic, including Use Cases (Commands and Queries) and DTOs.
* **`app/Infrastructure/`:** Contains the concrete implementations for external concerns, including HTTP Controllers, MongoDB persistence, Kafka messaging, and Service Providers.

## Testing

A comprehensive testing strategy is implemented to ensure code quality and reliability:

* **Unit Tests:** Focus on isolated components: Domain logic (Aggregates, Value Objects), Application logic (Use Case Handlers), and specific Infrastructure units (e.g., Cache Decorator logic).
* **Integration Tests:** Verify interactions between components: Application layer handlers interacting with Infrastructure implementations (e.g., Event Store, Kafka Publisher, Read Model Repository).
* **Feature Tests:** Test the application from the outside in, making HTTP requests to API endpoints and asserting responses and side effects (e.g., checking if read models are updated or events published to Kafka).
* **Mutation Testing:** Used via Infection PHP to assess test suite quality by introducing small code changes (mutants) and checking if tests fail.

To run the **complete test suite with coverage**, use the following Docker command:

```bash
docker compose exec app run-tests.sh --coverage-html public/reports/coverage
```

To run mutation tests, use the following Docker command:

```bash
docker-compose exec app sh -c " \
    echo 'Setting environment for Infection...' && \
    export APP_ENV=testing && \
    export QUEUE_CONNECTION=sync && \
    echo 'Clearing config cache...' && \
    php artisan config:clear && \
    echo 'Running Infection...' && \
    vendor/bin/infection --min-msi=80 --min-covered-msi=90 \
"
```

## Getting Started

This section provides instructions on how to set up and run the Ticket-System project locally using Docker.

### Prerequisites

* **Docker:** Ensure you have Docker installed and running on your system.
* **Docker Compose:** Docker Compose is required to manage the multi-container setup.
* **PHP:** You need to have PHP installed on your machine.
* **Composer:** You need to have Composer installed on your machine.

### Installation and Setup

1. **Clone the Repository:**

    ```bash
    git clone https://github.com/shineyder/ticket-system.git
    cd ticket-system
    ```

2. **Install PHP Dependencies:**

    ```bash
    composer install
    ```

3. **Environment Configuration**

    Before running the application, you need to set up your environment variables:

    **Copy the Example File:**
        Create your own environment file by copying the example:

    ```bash
    cp .env.example .env
    ```

    **Generate Application Key:**
        Laravel requires an application key for security. Generate one using Artisan:

    ```bash
    docker compose exec app php artisan key:generate
    ```

    *(If the containers are not running yet, you might need to run `docker compose run --rm app php artisan key:generate` first, copy the key, then proceed to the next step)*

    **Review `.env` Settings:**
        The default values in `.env.example` are configured for the Docker development environment provided in `docker-compose.yml`. Hostnames like `mongo`, `redis`, and `kafka` refer to the service names within the Docker network.
        The `DB_DATABASE` is set to `tickets` by default, but you can change it if needed.
        No passwords are required for MongoDB or Redis in the default Docker setup.
        Ensure `QUEUE_CONNECTION=redis` is set to use the Redis queue.

    **Important:** Never commit your actual `.env` file to version control. The `.env.example` file serves as a template.

4. **Build and Run Docker Containers:**

    ```bash
    docker compose up -d --build
    ```

    This command will:
    * Build the Docker image for the Ticket-System application (if needed).
    * Start all necessary containers (`app` (PHP-FPM), `nginx`, `mongo`, `kafka`, `redis`, `worker`) in detached mode (`-d`).
    * **Note:** The `worker` service automatically starts `php artisan queue:work` using the Redis connection defined in `.env`, processing background jobs asynchronously.
    * **Note:** The `app` and `worker` containers' entrypoint script automatically runs database migrations (`php artisan migrate --force`) upon starting, ensuring the necessary MongoDB collections for Laravel's internal use (jobs, failed_jobs, cache locks) are created.

5. **Accessing the Application:**

    The application API endpoints are available under the base URL `http://localhost/api/v1`.

    You can interact with the API using tools like Postman, Insomnia, or cURL. The API includes OpenAPI documentation generated from code annotations.

## Implemented Features

* **Event Sourcing:** Core persistence mechanism.
* **CQRS:** Clear separation of Commands and Queries.
* **Read Models/Projections:** Optimized data views for queries stored in MongoDB.
* **Asynchronous Processing:** Using Laravel Queues with Redis backend for listeners (Projections, Kafka Publishing).
* **Kafka Integration:** Publishing domain events for external consumers.
* **API Documentation:** Using OpenAPI (Swagger) annotations.
* **HATEOAS:** Links provided in API responses (`_links`).
* **Caching:** Read model caching (`findAll`) with tag-based invalidation using Redis.
* **Idempotency:** Listeners/Consumers check for already processed events using Redis cache.
* **Retries with Backoff:** Implemented for queued jobs (listeners).
* **Input Validation & Sanitization:** Rigorous validation in Request classes.
* **API Versioning:** Via URL (`/api/v1`).
* **Rate Limiting:** Basic protection against abuse (configurable via Laravel).
* **Mutation Testing:** Setup to improve test suite quality.
* **MongoDB Indexing:** Basic indexing applied for sorting/filtering.

### Running Commands

To execute commands within the application container, use the following format:

```bash
docker compose exec app <command>
```

For example, to run a Laravel Artisan command:

```bash
docker compose exec app php artisan cache:clear
```

### Stopping the Application

To stop the application and its containers, run:

```bash
docker compose down
```

## Future Enhancements / Next Steps

The primary planned next step is:

* **Continuous Integration/Continuous Deployment (CI/CD):** Implement a pipeline (e.g., using GitHub Actions) to automate testing, code analysis, building Docker images, and potentially deployment.

Other areas for future exploration include:

* **Authentication and Authorization:** Implement robust security mechanisms (e.g., JWT, Sanctum, or OAuth2).
* **Advanced Kafka Patterns:** Explore Dead Letter Queues (DLQ), Circuit Breaker patterns for increased resilience.
* **Monitoring and Logging:** Integrate advanced monitoring (e.g., Prometheus, Grafana) and structured logging (e.g., ELK stack).
* **Read Model Optimization:** Explore different strategies for building and updating read models/projections (e.g., dedicated projector processes, different storage options for specific query needs).

## Author

[Adriano Shineyder](https://github.com/shineyder)

## License

MIT License
