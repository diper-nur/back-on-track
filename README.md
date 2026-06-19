# Back on Track

Back on Track is a student-focused web application designed to help learners manage academic workload, organize class tasks, track deadlines, and recover from falling behind through structured AI-generated study support.

The project was created to address a common problem among students: assignments, materials, deadlines, and responsibilities often become scattered across different places, making it difficult to understand what to do first. Back on Track brings these elements into one system and helps students turn academic overload into a clear, manageable workflow.

## Features

* User authentication and account management
* Class creation, enrollment, and organization
* Task management with progress tracking and deadline monitoring
* Dashboard analytics and visual productivity insights
* Upcoming deadline reminders and overdue task detection
* Study material storage and management
* AI-assisted task generation from learning materials
* AI-powered academic recovery planning
* Response caching for efficient AI usage
* Multilingual user interface (English, Kazakh, and Russian)
* Reusable and modular application components
* MySQL database integration and data persistence
* Environment-based configuration management
* Composer-based dependency management

## AI Integration

Back on Track uses the OpenAI API to generate personalized academic recovery plans. The AI system analyzes a student's pending tasks, deadlines, notes, task types, and available study time, then returns a structured plan that helps the student decide what to focus on first.

The AI output is designed to support decision-making, not replace human judgment. Students remain responsible for reviewing the suggested plan and adjusting it based on teacher instructions, school priorities, personal circumstances, and available time.

The project also includes an AI caching system. This helps store generated responses and reduce unnecessary repeated API requests, making the system more efficient and cost-aware.

The API key is not included in this repository. To run the project locally, create a `.env` file based on `.env.example`.

## Responsible AI Approach

Back on Track uses AI as a supportive academic assistant rather than an authority. The system provides structured suggestions, but it does not make final academic decisions for students.

Main responsible AI considerations:

* AI-generated plans are presented as guidance, not mandatory instructions.
* Students can review and adjust the plan manually.
* The system uses task-related academic data only.
* API keys and private credentials are excluded from the public repository.
* AI caching reduces unnecessary API usage.
* The project is focused on organization and learning support, not grading or replacing teachers.

## Built With

* PHP
* MySQL
* HTML
* CSS
* JavaScript
* Chart.js
* Composer
* vlucas/phpdotenv
* smalot/pdfparser
* OpenAI API

## Project Structure

* `actions/` — task actions such as creating, completing, and deleting tasks
* `assets/` — frontend assets including CSS, JavaScript, and uploads structure
* `config/` — database and environment configuration
* `includes/` — shared components such as authentication, navbar, header, footer, language logic, and AI cache
* `lang/` — multilingual interface files
* `dashboard.php` — main student dashboard
* `task.php` — task management page
* `materials.php` — study materials page
* `material-detail.php` — detailed material view and AI-supported task generation
* `recovery-plan.php` — AI-powered academic recovery plan generation
* `composer.json` — PHP dependency configuration

## Environment Variables

This repository does not include real API keys or private credentials.

Create a `.env` file using `.env.example`:

```env
OPENAI_API_KEY=your_openai_api_key_here
DEEPSEEK_API_KEY=your_deepseek_api_key_here
GOOGLE_API_KEY=your_google_api_key_here

DB_HOST=localhost
DB_NAME=backontrack
DB_USER=root
DB_PASS=your_database_password_here

AWS_ACCESS_KEY_ID=your_aws_key_here
AWS_SECRET_ACCESS_KEY=your_aws_secret_here
AWS_REGION=your_region_here
S3_BUCKET=your_bucket_name_here

NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY=your_clerk_publishable_key_here
CLERK_SECRET_KEY=your_clerk_secret_key_here
```

## How to Run Locally

1. Clone the repository.
2. Install PHP dependencies:

```bash
composer install
```

3. Create a `.env` file based on `.env.example`.
4. Set up the MySQL database.
5. Import or create the required database tables.
6. Run the project on a local PHP server or local development environment such as XAMPP.

## Security Note

The real `.env` file is intentionally excluded from this repository. API keys, database passwords, secret tokens, and private credentials must never be committed to GitHub.
