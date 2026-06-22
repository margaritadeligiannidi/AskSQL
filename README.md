# AskSQL

## What is AskSQL?

AskSQL is an open-source web application for relational databases with AI-powered natural language querying.

It is designed for:

- Database administrators
- Developers
- Students
- Users with limited SQL knowledge

The system allows users to connect to MySQL and PostgreSQL databases, explore database schemas, generate SQL queries from natural language, execute queries, and export results.

---

## Features

- Natural language to SQL conversion using AI models
- SQL query execution
- Database schema exploration
- Query history management
- User authentication and authorization
- Google OAuth login
- Voice-to-text functionality
- MySQL and PostgreSQL support
- TCP and SSH tunneling connections
- CSV export of query results
- Account management

---

## Database Setup

Create the application database:

```sql
CREATE DATABASE asksql;
```

Import the provided schema:

```text
database/database_schema.sql
```

---

## Environment Variables

Create a new `.env` file based on `.env.example`.

Configure the following settings:

- Database credentials
- AI model API keys
- SMTP configuration
- Google OAuth credentials

To use AI-powered query generation, you must create API keys for the supported LLM providers.

To enable email functionality, you must configure an SMTP account and generate an application password if required by your email provider.

---

## Installation

Clone the repository:

```bash
git clone https://github.com/YOUR_USERNAME/AskSQL.git
cd AskSQL
```

Install PHP dependencies:

```bash
composer install
```

---

## Server Requirements

The server should have:

- PHP 8.x
- MySQL or PostgreSQL
- PDO extensions for supported databases
- SSH utilities for SSH tunneling functionality
- `mysqldump` for MySQL schema extraction
- `pg_dump` for PostgreSQL schema extraction
- Composer

---

## Configuration

Create a `.env` file using `.env.example` as a template:

```env
DB_HOST=localhost
DB_NAME=asksql
DB_USER=your_db_user
DB_PASS=your_db_password
```

---

## Author

**Margarita Delegiannidi**

Department of Information and Electronic Engineering

International Hellenic University