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
* **Dependency Inversion:** The core of the application depends on abstractions (interfaces), not concrete implementations. This promotes loose coupling and testability.
* **SOLID Principles:** The code adheres to SOLID principles (Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion) to ensure maintainability and extensibility.
* **Data Transfer Objects (DTOs):** DTOs are used to transfer data between layers, decoupling them and making the code more testable.

## Technologies Used

This project leverages the following technologies:

* **PHP 8.3:** The core programming language.
* **Laravel (without Eloquent):** Used as the foundation framework, but without leveraging Eloquent ORM to demonstrate core PHP and OOP skills.
* **MongoDB:** Used as the primary database for data persistence.
* **Kafka:** Used for asynchronous messaging and event handling.
* **Docker:** Used for containerization, ensuring consistent development and deployment environments.
* **SonarQube:** Used for static code analysis.

## Project Structure

The project is organized into the following main directories:

* **`app/Domain/`:** Contains the core business logic, including Entities, Value Objects, Events, Interfaces, and Exceptions.
* **`app/Application/`:** Contains the application logic, including Use Cases (Commands and Queries), DTOs, and Interfaces.
* **`app/Infrastructure/`:** Contains the concrete implementations for external concerns, including HTTP Controllers, MongoDB persistence, Kafka messaging, and Service Providers.

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

3. **Build and Run Docker Containers:**

    ```bash
    docker compose up -d
    ```

    This command will:

    * Build the Docker image for the Ticket-System.
    * Start the necessary containers in detached mode (`-d`).

4. **Accessing the Application:**

    The application will be available at `http://localhost:8000`.

### Running the Application

After completing the setup, you can access the Ticket-System API by navigating to `http://localhost:8000` in your web browser or using an API client.

### Running Commands

To execute commands within the application container, use the following format:

```bash
docker compose exec app <command>
```

For example, to run a Laravel Artisan command:

```bash
docker compose exec app php artisan migrate
```

### Stopping the Application

To stop the application and its containers, run:

```bash
docker compose down
```

## Future Enhancements

Once the core Ticket-System implementation is complete, the following enhancements are planned:

* **API Documentation (OpenAPI/Swagger):** Generate comprehensive API documentation using OpenAPI (formerly Swagger) to facilitate integration and understanding of the system's endpoints.
* **Rate Limiting:** Implement rate limiting to protect the API from abuse and ensure fair usage.
* **Authentication and Authorization:** Add robust authentication and authorization mechanisms to secure the API and control access to resources..
* **Caching:** Introduce caching strategies to improve performance and reduce database load.
* **Monitoring and Logging:** Integrate advanced monitoring and logging tools to track application health, performance, and errors.
* **Refactor to use a Message Broker:** Refactor the code to use a message broker to handle the communication between the application and the kafka.
* **Implement a retry mechanism:** Implement a retry mechanism to handle the communication with the kafka.
* **Implement a circuit breaker:** Implement a circuit breaker to handle the communication with the kafka.
* **Implement a dead letter queue:** Implement a dead letter queue to handle the messages that failed to be processed.
* **Implement a saga pattern:** Implement a saga pattern to handle the transactions that involve multiple services.
* **Implement a event sourcing:** Implement a event sourcing to handle the events that occur in the system.

## Author

[Adriano Shineyder](https://github.com/shineyder)

## License

MIT License
