# Ticket-System: A Clean Architecture, DDD, and CQRS Project

This project is a **Ticket Management System** designed and developed as a portfolio piece to demonstrate proficiency in modern software design principles, architectural patterns, and a variety of technologies. It's built with a strong emphasis on clean code, maintainability, and scalability.

## Project Overview

The Ticket-System allows users to create, manage, and track tickets. It's designed to be a robust and flexible system that can be adapted to various use cases.

## Core Concepts and Architectural Patterns

This project showcases the following core software design concepts and architectural patterns:

* **Clean Architecture:** The project is structured using Clean Architecture, ensuring a clear separation of concerns between the core business logic (Domain), application logic (Application), and external concerns (Infrastructure). This promotes testability, maintainability, and flexibility.
* **Domain-Driven Design (DDD):** DDD principles are applied to model the core business domain. This includes:
  * **Entities:** Representing core business objects (e.g., `Ticket`).
  * **Value Objects:** Representing immutable data structures (e.g., `Status`).
  * **Domain Events:** Representing significant events within the domain (e.g., `TicketCreated`).
* **Command Query Responsibility Segregation (CQRS):** The system separates read (Queries) and write (Commands) operations. This allows for optimized data access and improved scalability.
* **Hexagonal Architecture (Ports and Adapters):** The project implicitly follows Hexagonal Architecture principles by defining clear interfaces (Ports) for interacting with the core domain and providing concrete implementations (Adapters) in the Infrastructure layer.
* **Event Sourcing (ES):** Adopted as a fundamental pattern (primarily for educational exploration of its concepts). Instead of persisting the current state of aggregates, the system stores the full sequence of domain events that have occurred (the event stream) in an Event Store. The state of an aggregate is reconstructed by replaying its historical events. This provides a complete audit log and enables temporal queries.
* **Dependency Inversion:** The core of the application depends on abstractions (interfaces), not concrete implementations. This promotes loose coupling and testability.
* **SOLID Principles:** The code adheres to SOLID principles (Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion) to ensure maintainability and extensibility.
* **Data Transfer Objects (DTOs):** DTOs are used to transfer data between layers, decoupling them and making the code more testable.

## Technologies Used

This project leverages the following technologies:

* **PHP 8.3:** The core programming language.
* **Laravel 12 (without Eloquent):** Used as the foundation framework, but without leveraging Eloquent ORM to demonstrate core PHP and OOP skills.
* **MongoDB:** Used as the primary database for data persistence.
* **Kafka:** Used for asynchronous messaging and event handling.
* **Redis:** Used for background job queuing (processing listeners like projections and Kafka publishing).
* **Docker:** Used for containerization, ensuring consistent development and deployment environments.
* **SonarQube:** Used for static code analysis.

## Project Structure

The project is organized into the following main directories:

* **`app/Domain/`:** Contains the core business logic, including Entities, Value Objects, Events, Interfaces, and Exceptions.
* **`app/Application/`:** Contains the application logic, including Use Cases (Commands and Queries) and DTOs.
* **`app/Infrastructure/`:** Contains the concrete implementations for external concerns, including HTTP Controllers, MongoDB persistence, Kafka messaging, and Service Providers.

## Testing

A comprehensive testing strategy is planned to ensure code quality, reliability, and maintainability. The following types of tests will be implemented:

* **Unit Tests:** Focusing on isolated components within the Domain (Entities, Value Objects, Domain Events) and Application (Use Cases/Handlers) layers.
* **Integration Tests:** Verifying the interaction between different components, particularly between the Application layer and Infrastructure implementations (e.g., testing Use Case interaction with the Event Store or Kafka publishers).
* **Feature Tests:** Testing the application from the outside in, typically by making HTTP requests to the API endpoints and asserting the responses.

To run the test suite (once implemented), use the following Docker command:

```bash
docker compose exec app php artisan test
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
   
    *(If the containers are not running yet, you might need to run `docker compose run --rm app php artisan key:generate` first, copy the key, then run `docker compose up -d --build`)*

    **Review `.env` Settings:**
        The default values in `.env.example` are configured for the Docker development environment provided in `docker-compose.yml`. Hostnames like `mongo`, `redis`, and `kafka` refer to the service names within the Docker network.
        The `DB_DATABASE` is set to `tickets` by default, but you can change it if needed.
        No passwords are required for MongoDB or Redis in the default Docker setup.
        Review other settings like `APP_NAME` and `APP_URL` if necessary.

    **Important:** Never commit your actual `.env` file to version control. The `.env.example` file serves as a template.

5. **Build and Run Docker Containers:**

    ```bash
    docker compose up -d --build
    ```

    This command will:
    * Build the Docker image for the Ticket-System application (if needed).
    * Start all necessary containers (app, nginx, mongo, kafka, worker) in detached mode (`-d`).
    * **Note:** The default configuration (.env) uses QUEUE_CONNECTION=redis for background jobs. The dedicated worker service automatically starts the queue processor (php artisan queue:work), ensuring background jobs are handled without manual intervention.
    * **Note:** The `app` and `worker` container's entrypoint script automatically runs database migrations (`php artisan migrate --force`) upon starting, so manual migration is typically not needed after the initial setup.

6. **Accessing the Application:**

The application API endpoints are available under the base URL `http://localhost/api`.

You can interact with the API using tools like Postman, Insomnia, or cURL. For example, to create a ticket, you would send a POST request to `http://localhost/api/tickets`.

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

## Future Enhancements and Areas for Exploration

While the core architecture is in place, the following enhancements and areas are planned or could be explored further:

* **API Documentation (OpenAPI/Swagger):** Generate comprehensive API documentation.
* **Authentication and Authorization:** Implement robust security mechanisms.
* **Rate Limiting:** Protect the API from abuse.
* **Advanced Kafka Patterns:** Implement more sophisticated messaging patterns for resilience and complex workflows:
    Retry mechanisms for transient failures.
    Circuit Breaker pattern to prevent cascading failures.
    Dead Letter Queues (DLQ) for handling unprocessable messages.
    Saga pattern for managing distributed transactions across services (if the system evolves towards microservices).
* **Caching:** Introduce caching strategies for read models or frequent queries.
* **Monitoring and Logging:** Integrate advanced monitoring (e.g., Prometheus, Grafana) and structured logging (e.g., ELK stack).
* **Continuous Integration/Continuous Deployment (CI/CD):** Implement a CI/CD pipeline (e.g., using GitHub Actions) to automate testing, code analysis, and potentially deployment processes.
* **Read Model Optimization:** Explore different strategies for building and updating read models/projections (e.g., dedicated projectors, different storage).

## Author

[Adriano Shineyder](https://github.com/shineyder)

## License

MIT License
