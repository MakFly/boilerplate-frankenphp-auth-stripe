# **Expert in Symfony, PHP, and Related Web Development Technologies**

## **Key Principles**
- Provide precise technical responses with accurate PHP examples.
- Adhere to **Symfony 6.3+** best practices and conventions.
- Apply object-oriented programming following **SOLID principles**.
- Prefer **iteration and modularization** over code duplication.
- Use **descriptive names** for variables and methods.
- Follow naming conventions:
  - `snake_case` for **services**.
  - `camelCase` for **methods and variables**.
- **Search exclusively within this project**.
- I using docker for this project don't this forget
- Respect a rules PSR-4 & PSR-12 with phpstan.neon

---

## **PHP/Symfony**
- Leverage **PHP 8.2+** features when appropriate (**typed properties, match expressions, readonly properties**).
- Follow **PSR-12** coding standards.
- Enable **strict typing** (`declare(strict_types=1);`).
- Prioritize Symfony's **built-in features and tools**.
- Adhere to Symfony's directory structure:
  - `src/Controller` for controllers.
  - `src/Entity` for entities.
  - `src/Service` for business logic services.
- Implement proper error handling and logging:
  - Use **Symfony events** and **custom exceptions**.
  - Configure **Monolog** for error logging.
  - Handle expected exceptions with **try-catch**.
- Validate forms and requests using **Symfony validators**.
- Implement **middleware** (`EventSubscriber`, `EventListener`) for request filtering and modification.
- Use **Doctrine ORM** for database interactions.
- Manage schema updates with **Doctrine migrations**.

---

## **Dependencies**
- **Symfony** (latest stable version)
- **Composer** for dependency management

---

## **Symfony Best Practices**
1. **MVC Architecture**: Follow Symfony's MVC architecture.
2. **Controllers and Services**:
   - Keep **controllers lightweight**, delegate business logic to **services (`src/Service`)**.
3. **Validation**:
   - Validate data using **Symfony constraints** (`@Assert`, `ValidatorInterface`).
4. **Routing**:
   - Use Symfony's **routing system** via **PHP attributes, YAML, or XML**.
5. **Twig**:
   - Use **Twig** for rendering views.
6. **Entities and Relationships**:
   - Define **Doctrine entities** with appropriate relationships (**OneToOne, OneToMany, ManyToMany**).
7. **Authentication and Authorization**:
   - Utilize Symfony's **built-in security system** (`Authenticators`, `Voters`, `Security`).
8. **Caching**:
   - Use the **Symfony Cache component** for performance optimization.
9. **Asynchronous Tasks**:
   - Implement long-running tasks using the **Messenger component** and a queue like **RabbitMQ**.
10. **Testing**:
    - Use **PestPHP** for **unit and functional tests**.
11. **Internationalization**:
    - Use Symfony’s **Translation component** for multi-language support.
12. **Security & CSRF Protection**:
    - Enable **CSRF protection** for forms.
    - Use **Symfony security mechanisms** (`Firewall`, `Access Control`).
13. **Query Optimization**:
    - Add **indexes** on frequently queried columns.
    - Avoid **N+1 queries** by using **fetch joins**.
14. **Event System**:
    - Decouple business logic using **Symfony’s event system** (`EventSubscriber`, `EventDispatcher`).
15. **Task Scheduling**:
    - Automate recurring tasks using the **Scheduler component** or **Messenger**.

---

## **Entity & Database Table Naming Conventions**
1. **Entities** (`src/Entity`):
   - Use **singular, PascalCase** names (`User`, `OrderDetail`).
   - Avoid unnecessary suffixes (`Entity`, `Tbl`, `Db`).
2. **Database Tables**:
   - Use **plural, snake_case** names (`users`, `order_details`).
3. **Columns**:
   - Use **snake_case**, descriptive names, and avoid type indicators (`created_at` instead of `created_datetime`).
4. **Foreign Keys**:
   - Follow the `{entity_name}_id` format (`user_id`, `order_id`).
5. **Join Tables (`ManyToMany`)**:
   - Use **alphabetically ordered, snake_case** names (`user_role`, `product_category`).

---

## **General Conventions**
- Follow **Symfony’s directory and file naming standards**.
- Use **PHP annotations, YAML, or XML** for configuration based on project policies.
- Structure **services and bundles** for optimal **modularity**.
- Utilize **DTOs** (Data Transfer Objects) for data transfer between services.
- Define **constants and configurations** in appropriate files (`.env`, `config/services.yaml`).
- Minimize **inter-component dependencies** using **interfaces and dependency injection**.

---

This document now includes all essential best practices for an efficient and maintainable Symfony development workflow! 🚀
