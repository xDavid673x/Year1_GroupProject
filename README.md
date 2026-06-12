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
4. Copy `Login_FAQs/api/config.local.example.php` to `Login_FAQs/api/config.local.php` and add your local API/database settings.
5. Create the database tables using the scripts in `DatabaseConfig/` and `Login_FAQs/api/schema.sql`.
6. Import the exercise seed data from `DatabaseConfig/workouts.csv` by running `DatabaseConfig/PopulateExercises.php`.
7. Serve the project from the repository root and open `homepage/homepage.html`.

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

## Notes

- Uploaded profile images are stored under `Profile/uploads/`.
- The video library stores exercise videos under `Videos/videos/`.
- Some features require a working database session and will not function from static file browsing alone.
