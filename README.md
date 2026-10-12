# Lumoura 

Lumoura is a luxury perfume e-commerce website that offers an elegant
shopping experience and helps users **discover fragrances that match
their preferences** through a personalized recommendation quiz.

Built with PHP and MySQL as part of my BTS SIO (SLAM) studies.

##  Features
- Perfume catalogue with detailed product pages
- **Personalized recommendation quiz**: [X questions on scent family,
  intensity, occasion... → matching perfumes ranked by score]
- Shopping cart and customer accounts (registration, login)
- [Order management / admin panel, if it exists]
- Responsive interface built with Bootstrap

##  Technologies
PHP · MySQL · HTML5 · CSS3 · JavaScript · Bootstrap · Composer · Git

##  Security
- Passwords hashed with `password_hash()`
- Prepared statements (PDO) against SQL injection
- [Session handling, input validation...]

## Project structure
- `assets/`: stylesheets, scripts, images
- `includes/`: shared components and configuration
- `pages/`: website pages
- `database/`: SQL file to create the database

##  Getting started
1. Clone the repository
2. Run `composer install`
3. Create a database and import `database/lumoura.sql`
4. Copy `includes/config.example.php` to `includes/config.php`
   and enter your database credentials
5. Start XAMPP/WAMP and open `http://localhost/lumoura`

##  What I learned
- Designing a relational database for an e-commerce site
- Building a recommendation logic in PHP
- Securing authentication and database access
- [Something specific you are proud of]
