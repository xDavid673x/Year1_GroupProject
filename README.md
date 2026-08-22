# Motiv8

Motiv8 is a PHP/MySQL fitness web app for tracking workouts, finding gyms, managing profiles, adding friends, watching fitness videos, and viewing leaderboards.

## Features

- User login and account registration
- Profile management with bio, fitness details, profile photo, and selected gym
- Workout logging with exercises, sets, reps, weight, duration, and saved workout history
- Weekly leaderboard based on tracked workouts
- Friends system for connecting with other users
- Gym locator with map-based gym selection and postcode search
- Fitness video library
- FAQ, admin dashboard, and Gemini-powered fitness chat

## Tech Stack

- PHP
- MySQL
- HTML, CSS, and JavaScript
- Leaflet and OpenStreetMap for the gym locator
- Gemini API integration for the AI fitness chat

## Project Structure

```text
DatabaseConfig/      Database table setup, exercise seed data, and demo workout data
Exercise_Tracker/    Workout logging and saved workout views
Friends/             Friend request and friendship pages
Gym_Locator/         Gym map, gym search, and gym selection
Login_FAQs/          Login, registration, FAQ, leaderboard, admin, and API endpoints
Profile/             User profile pages and profile update handlers
Videos/              Fitness video library
homepage/            Main landing/home page
img/                 Shared image assets
```

## Local Setup

1. Install a local PHP/MySQL environment such as XAMPP, MAMP, WAMP, or the PHP built-in server with a MySQL server.
2. Clone the repository.
3. Copy `DatabaseInit.local.example.php` to `DatabaseInit.local.php` and add your local database credentials.
4. Optionally copy `Login_FAQs/api/config.local.example.php` to
   `Login_FAQs/api/config.local.php` for local MongoDB or Gemini settings.
5. Set the local database credentials, then run `php DatabaseConfig/migrate.php`.
   This creates every application table and imports `DatabaseConfig/workouts.csv`.
6. Serve the project from the repository root and open `homepage/homepage.html`.

Example using PHP's built-in server:

```bash
php -S localhost:8000
```

Then visit:

```text
http://localhost:8000/homepage/homepage.html
```

## Configuration

The app uses MySQL for user accounts, profiles, workouts, friends, and leaderboard data.

The root database connection reads environment variables first and then optional values from:

```text
DatabaseInit.local.php
```

For the FAQ/chat APIs, `Login_FAQs/api/config.php` supports environment variables and an optional local override file:

```text
Login_FAQs/api/config.local.php
```

Local config files are ignored by Git and should be used for secrets such as database passwords, MongoDB connection strings, and Gemini API keys.

## Security Before Publishing

Do not commit real database credentials or API keys. Keep secrets in environment variables or ignored local config files instead.

## Vercel Deployment

The repository includes `vercel.json` and a guarded PHP entrypoint for Vercel's
recommended `vercel-php` community runtime. The site root opens the Motiv8
homepage, while the application's public PHP routes run through one function.

The TiDB Cloud Marketplace integration supplies these variables automatically:

```text
TIDB_HOST
TIDB_PORT
TIDB_DATABASE
TIDB_USER
TIDB_PASSWORD
```

For another MySQL host, set the equivalent variables below:

```text
MYSQL_HOST
MYSQL_PORT
MYSQL_DATABASE
MYSQL_USERNAME
MYSQL_PASSWORD
MYSQL_TIMEOUT_SECONDS
```

Hosted TiDB connections use verified TLS with the bundled ISRG Root X1 CA.
On Vercel, PHP sessions are stored in the `PhpSessions` database table so login
state survives function restarts.

The Gemini chat additionally needs `GEMINI_API_KEY`. MongoDB profile mirroring
is optional and uses `MONGODB_URI`, `MONGODB_DATABASE`, and
`MONGODB_USERS_COLLECTION`.

Use a hosted MySQL database that accepts connections from Vercel; `localhost`
refers to the serverless function itself and cannot reach a database running on
your computer.

## Notes

- Uploaded profile images are stored under `Profile/uploads/`.
- Vercel's function filesystem is temporary, so profile image uploads need an
  object-storage service before that feature is production-safe.
- The video library stores exercise videos under `Videos/videos/`.
- Some features require a working database session and will not function from static file browsing alone.
